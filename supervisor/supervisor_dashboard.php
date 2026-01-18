<?php
session_start();
require_once '../conn.php'; 

// ตรวจสอบสิทธิ์การเข้าถึง (เฉพาะ Supervisor)
if (!isset($_SESSION['loggedin']) || $_SESSION['user_role'] !== 'supervisor') {
    header("location: ../login.php");
    exit;
}

$emp_id = $_SESSION['emp_id'];
$user_role = $_SESSION['user_role'];
$today = date('Y-m-d');
$current_year = date('Y');
$current_month = date('m'); 

// 1. ดึงชื่อและแผนกของผู้ใช้
$sql_user = "SELECT first_name, last_name, dept_id FROM employees WHERE emp_id = ?";
$current_dept_id = 0;
$user_display_name = "";
if ($stmt_user = $conn->prepare($sql_user)) {
    $stmt_user->bind_param("i", $emp_id);
    $stmt_user->execute();
    $result_user = $stmt_user->get_result();
    if ($row_user = $result_user->fetch_assoc()) {
        $user_display_name = htmlspecialchars($row_user['first_name'] . ' ' . $row_user['last_name']);
        $current_dept_id = $row_user['dept_id'];
    }
    $stmt_user->close();
}

// 2. นับจำนวนพนักงานในแผนก
$sql_employees = "SELECT COUNT(emp_id) AS total FROM employees WHERE dept_id = ? AND resign_date IS NULL";
$stmt_emp = $conn->prepare($sql_employees);
$stmt_emp->bind_param("i", $current_dept_id);
$stmt_emp->execute();
$total_employees = $stmt_emp->get_result()->fetch_assoc()['total'];

// 3. นับคำขอรออนุมัติ (เฉพาะลูกน้องที่รายงานตรงต่อ Supervisor นี้)
$sql_pending = "SELECT COUNT(LR.request_id) AS total FROM leave_requests LR 
                JOIN employees E ON LR.emp_id = E.emp_id
                WHERE LR.status = 'Pending' AND E.supervisor_id = ?";
$stmt_pen = $conn->prepare($sql_pending);
$stmt_pen->bind_param("i", $emp_id);
$stmt_pen->execute();
$pending_requests = $stmt_pen->get_result()->fetch_assoc()['total'];

// 4. ดึงยอดวันลาคงเหลือของผู้ใช้ปัจจุบัน
$user_balances = [];
$sql_balance = "SELECT LT.leave_type_display, (ELB.allowed_days - ELB.used_days) as remaining_days
                FROM employee_leave_balances ELB
                JOIN leave_types LT ON ELB.leave_type_id = LT.leave_type_id
                WHERE ELB.emp_id = ? AND ELB.year = ?
                ORDER BY LT.leave_type_id ASC";
$stmt_bal = $conn->prepare($sql_balance);
$stmt_bal->bind_param("ii", $emp_id, $current_year);
$stmt_bal->execute();
$result_bal = $stmt_bal->get_result();
while($row = $result_bal->fetch_assoc()) { $user_balances[] = $row; }

// --- เพิ่มส่วนข้อมูลวันหยุดนักขัตฤกษ์สำหรับใส่ใน Card ---
// นับวันหยุดที่ผ่านมาแล้วในปีนี้ (สิทธิ์ที่ใช้ได้)
$sql_passed_hol = "SELECT COUNT(*) as passed FROM public_holidays WHERE YEAR(holiday_date) = ? AND holiday_date < ?";
$stmt_passed = $conn->prepare($sql_passed_hol);
$stmt_passed->bind_param("is", $current_year, $today);
$stmt_passed->execute();
$passed_holidays = $stmt_passed->get_result()->fetch_assoc()['passed'];

// นับวันหยุดทั้งหมดในเดือนปัจจุบัน
$sql_hol_month = "SELECT COUNT(*) as month_total FROM public_holidays WHERE MONTH(holiday_date) = ? AND YEAR(holiday_date) = ?";
$stmt_hm = $conn->prepare($sql_hol_month);
$stmt_hm->bind_param("ii", $current_month, $current_year);
$stmt_hm->execute();
$hol_month_count = $stmt_hm->get_result()->fetch_assoc()['month_total'];

// 5. ดึงข้อมูลจริงสำหรับกราฟ
$monthly_data = array_fill(0, 12, 0); 
$sql_graph = "SELECT MONTH(LR.start_date) as month_num, SUM(DATEDIFF(LR.end_date, LR.start_date) + 1) as total_days
              FROM leave_requests LR
              JOIN employees E ON LR.emp_id = E.emp_id
              WHERE E.dept_id = ? AND LR.status = 'Approved' AND YEAR(LR.start_date) = ?
              GROUP BY MONTH(LR.start_date)";
if ($stmt_graph = $conn->prepare($sql_graph)) {
    $stmt_graph->bind_param("ii", $current_dept_id, $current_year);
    $stmt_graph->execute();
    $result_graph = $stmt_graph->get_result();
    while ($row_g = $result_graph->fetch_assoc()) {
        $monthly_data[$row_g['month_num'] - 1] = (float)$row_g['total_days'];
    }
}

$thai_months = ["", "มกราคม", "กุมภาพันธ์", "มีนาคม", "เมษายน", "พฤษภาคม", "มิถุนายน", "กรกฎาคม", "สิงหาคม", "กันยายน", "ตุลาคม", "พฤศจิกายน", "ธันวาคม"];
$conn->close();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ภาพรวมระบบ | LALA MUKHA</title>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {--primary:#004030; --secondary:#66BB6A; --accent:#81C784; --bg:#f5f7fb; --text:#2e2e2e;}
        body {margin:0; font-family:'Prompt',sans-serif; background:var(--bg); display:flex;}
        .sidebar {width:250px; background:linear-gradient(180deg, var(--primary), #2e7d32); color:#fff; position:fixed; height:100%; display:flex; flex-direction:column;}
        .sidebar-header {padding:25px; text-align:center; font-weight:600; border-bottom:1px solid rgba(255,255,255,0.2);}
        .menu-item {display:flex; align-items:center; padding:15px 25px; color:rgba(255,255,255,0.85); text-decoration:none; transition:0.2s;}
        .menu-item:hover, .menu-item.active {background:rgba(255,255,255,0.15); color:#fff;}
        .menu-item i {margin-right:12px; width:20px; text-align:center;}
        .main-content {flex-grow:1; margin-left:250px; padding:30px;}
        .header {display:flex; justify-content:space-between; align-items:center; background:#fff; padding:15px 25px; border-radius:12px; box-shadow:0 4px 10px rgba(0,0,0,0.05); margin-bottom:25px;}
        .card {background:#fff; border-radius:16px; padding:25px; box-shadow:0 6px 20px rgba(0,0,0,0.05); margin-bottom:25px;}
        .stat-cards {display:grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap:20px;}
        .stat-card {background:#fff; border-radius:12px; padding:25px; display:flex; align-items:center; gap:20px; box-shadow:0 4px 10px rgba(0,0,0,0.05); transition:0.3s; text-decoration:none; color:inherit;}
        .stat-card:hover {transform:translateY(-5px); background:#f1f8e9;}
        .stat-icon {font-size:2.5em; color:var(--secondary);}
        .stat-details h3 {margin:0; font-size:2em; color:var(--primary);}
        .leave-balance-cards {display:flex; gap:15px; flex-wrap:wrap; margin-top:15px;}
        .leave-balance-card {flex:1; min-width:180px; background:linear-gradient(135deg, #2e7d32, var(--primary)); color:#fff; border-radius:12px; padding:20px; box-shadow:0 4px 12px rgba(0,0,0,0.1);}
        .btn {display:inline-block; background:var(--primary); color:#fff; padding:10px 20px; border-radius:8px; text-decoration:none; font-weight:500; transition:0.3s;}
        .btn:hover {background:var(--secondary);}
        .btn-green {background:#388e3c;}
    </style>
</head>
<body>

    <div class="sidebar">
        <div class="sidebar-header">LALA MUKHA<br>ระบบจัดการพนักงาน</div>
        <a href="supervisor_dashboard.php" class="menu-item active"><i class="fas fa-tachometer-alt"></i> ภาพรวมระบบ</a>
        <a href="manage_leave.php" class="menu-item"><i class="fas fa-clipboard-list"></i> จัดการการลา</a>
        <a href="apply_leave.php" class="menu-item"><i class="fas fa-calendar-plus"></i> ยื่นคำร้องการลา</a>
        <a href="manage_employees.php" class="menu-item"><i class="fas fa-users"></i> ข้อมูลพนักงาน</a>
        <a href="public_holidays.php" class="menu-item"><i class="fas fa-calendar-alt"></i><span>ปฏิทินวันหยุด</span></a>
        <a href="leave_history.php" class="menu-item"><i class="fas fa-history"></i><span>ประวัติการลาส่วนตัว</span></a>
        <a href="logout.php" style="margin-top:auto; background:#388e3c; text-align:center; padding:15px; color:#fff; text-decoration:none;"><i class="fas fa-sign-out-alt"></i> ออกจากระบบ</a>
    </div>

    <div class="main-content">
        <div class="header">
            <div class="topbar-notification">
                <?php if ($pending_requests > 0): ?>
                    <a href="manage_leave.php" style="text-decoration:none; color:var(--text); font-size:0.9em;">
                        <i class="fas fa-bell" style="color:#fbc02d;"></i> คุณมีรายการรออนุมัติ: <strong><?= $pending_requests ?></strong>
                    </a>
                <?php else: ?>
                    <span style="color:#666; font-size:0.9em;"><i class="fas fa-check-circle" style="color:var(--secondary);"></i> ไม่มีรายการค้างคา</span>
                <?php endif; ?>
            </div>
            <div class="user-profile">
                <span><strong><?= $user_display_name ?></strong> (SUPERVISOR)</span>
                <i class="fas fa-user-circle" style="font-size:1.8em; color:var(--primary); margin-left:10px;"></i>
            </div>
        </div>

        <div class="card">
            <h1>แผงควบคุมหลัก</h1>
            <div class="stat-cards">
                <div class="stat-card">
                    <i class="fas fa-users stat-icon"></i>
                    <div class="stat-details">
                        <h3><?= number_format($total_employees) ?></h3>
                        <p>พนักงานในแผนก</p>
                    </div>
                </div>
                <div class="stat-card">
                    <i class="fas fa-clock stat-icon" style="color:#FFC107;"></i>
                    <div class="stat-details">
                        <h3><?= number_format($pending_requests) ?></h3>
                        <p>คำขอรออนุมัติ (ลูกน้อง)</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <h2 style="margin:0; color:var(--primary); font-size:1.2em; display:flex; align-items:center; gap:10px;">
                <i class="fas fa-umbrella-beach"></i> ยอดวันลาคงเหลือและสิทธิ์วันหยุด (ปี <?= $current_year + 543 ?>)
            </h2>
            <div class="leave-balance-cards">
                <?php foreach ($user_balances as $balance): ?>
                    <div class="leave-balance-card">
                        <h3 style="margin:0; font-size:0.9em; font-weight:400; opacity:0.9;"><?= htmlspecialchars($balance['leave_type_display']) ?></h3>
                        <p style="margin:0; font-size:1.5em; font-weight:700;"><?= number_format($balance['remaining_days'], 1) ?> วัน</p>
                    </div>
                <?php endforeach; ?>

                <div class="leave-balance-card" style="background: linear-gradient(135deg, #11998e, #38ef7d);">
                    <h3 style="margin:0; font-size:0.9em; font-weight:400; opacity:0.9;">วันหยุดนักขัตฤกษ์ (ที่ใช้ได้)</h3>
                    <p style="margin:0; font-size:1.5em; font-weight:700;"><?= $passed_holidays ?> สิทธิ์</p>
                    <small style="font-size: 0.75em; opacity: 0.8;">* จากวันหยุดที่ผ่านมาแล้ว</small>
                </div>
            </div>
        </div>

        <div class="card">
            <h2>รายงานสถิติการลาในแผนก (วัน)</h2>
            <div style="height:300px;">
                <canvas id="leaveChart"></canvas>
            </div>
        </div>

        <div class="card">
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <h2>จัดการและยื่นใบลา</h2>
                <a href="apply_leave.php" class="btn"><i class="fas fa-plus-circle"></i> ยื่นใบลาส่วนตัว</a>
            </div>
            <p>จัดการคำร้องขอลาของลูกน้องในความดูแล หรือดูประวัติการลาของคุณเอง</p>
            <div style="display:flex; gap:15px; margin-top:10px;">
                <a href="manage_leave.php" class="btn">จัดการการลาลูกน้อง <i class="fas fa-tasks"></i></a>
                <a href="leave_history.php" class="btn btn-green">ประวัติการลาส่วนตัว <i class="fas fa-history"></i></a>
            </div>
        </div>
    </div>

    <script>
    const ctx = document.getElementById('leaveChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'],
            datasets: [{
                label: 'จำนวนวันลาที่อนุมัติแล้วรวมในแผนก (วัน)',
                data: <?= json_encode($monthly_data) ?>,
                backgroundColor: 'rgba(102, 187, 106, 0.6)',
                borderColor: '#004030',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
        }
    });
    </script>
</body>
</html>