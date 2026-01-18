<?php
session_start();
require_once '../conn.php'; 

// 1. ตรวจสอบสิทธิ์การเข้าถึง (สำหรับพนักงาน)
if (!isset($_SESSION['loggedin']) || $_SESSION['user_role'] !== 'employee') {
    header("location: ../login.php");
    exit;
}

$emp_id = $_SESSION['emp_id']; 
$status_message = $_SESSION['status_message'] ?? '';
unset($_SESSION['status_message']);

$current_year = date('Y');
$current_month = date('m'); 
$today_db = date('Y-m-d'); // วันที่ปัจจุบันสำหรับตรวจสอบสิทธิ์

// --- 1. ดึงข้อมูลพนักงาน ---
$sql_user = "SELECT emp_code, first_name, last_name FROM employees WHERE emp_id = ?";
$stmt_user = $conn->prepare($sql_user);
$stmt_user->bind_param("i", $emp_id);
$stmt_user->execute();
$result_user = $stmt_user->get_result();

if ($row_user = $result_user->fetch_assoc()) {
    $user_display_name = htmlspecialchars($row_user['first_name'] . ' ' . $row_user['last_name']);
    $emp_code = $row_user['emp_code'];
} else {
    $user_display_name = $_SESSION['username'];
    $emp_code = "-";
}

// --- 2. ดึงยอดวันลาคงเหลือจากฐานข้อมูล ---
$balances = [];
$sql_balance = "
    SELECT LT.leave_type_display, 
           (ELB.allowed_days - ELB.used_days) AS remaining_days,
           LT.leave_unit
    FROM employee_leave_balances ELB
    JOIN leave_types LT ON ELB.leave_type_id = LT.leave_type_id
    WHERE ELB.emp_id = ? AND ELB.year = ?
    ORDER BY LT.leave_type_id ASC";
$stmt_bal = $conn->prepare($sql_balance);
$stmt_bal->bind_param("ii", $emp_id, $current_year);
$stmt_bal->execute();
$result_balance = $stmt_bal->get_result();
while($row = $result_balance->fetch_assoc()) {
    $balances[] = $row;
}

// --- 3. ดึงจำนวนแจ้งเตือนสถานะการลา ---
$sql_notif = "SELECT status, COUNT(*) as cnt FROM leave_requests WHERE emp_id = ? AND status IN ('Pending', 'Rejected') GROUP BY status";
$stmt_notif = $conn->prepare($sql_notif);
$stmt_notif->bind_param("i", $emp_id);
$stmt_notif->execute();
$res_notif = $stmt_notif->get_result();

$count_pending = 0;
$count_rejected = 0;
while($row_n = $res_notif->fetch_assoc()) {
    if($row_n['status'] == 'Pending') $count_pending = $row_n['cnt'];
    if($row_n['status'] == 'Rejected') $count_rejected = $row_n['cnt'];
}
$total_urgent = $count_pending + $count_rejected;

// --- 4. ข้อมูลวันหยุดนักขัตฤกษ์และการคำนวณสิทธิ์คงเหลือ ---

// ก. รายการวันหยุดในเดือนนี้ (สำหรับแสดงใน Card รายชื่อ)
$holidays_month = [];
$sql_hol_month = "SELECT holiday_date, holiday_name FROM public_holidays WHERE YEAR(holiday_date) = ? AND MONTH(holiday_date) = ? ORDER BY holiday_date ASC";
$stmt_hm = $conn->prepare($sql_hol_month);
$stmt_hm->bind_param("ii", $current_year, $current_month);
$stmt_hm->execute();
$result_hol_month = $stmt_hm->get_result();
while($row = $result_hol_month->fetch_assoc()) { $holidays_month[] = $row; }

// ข. นับจำนวนวันหยุดที่ "ผ่านมาแล้ว" ทั้งหมดในปีนี้
$sql_passed_hol = "SELECT COUNT(*) as passed FROM public_holidays WHERE YEAR(holiday_date) = ? AND holiday_date <= ?";
$stmt_passed = $conn->prepare($sql_passed_hol);
$stmt_passed->bind_param("is", $current_year, $today_db);
$stmt_passed->execute();
$total_passed = $stmt_passed->get_result()->fetch_assoc()['passed'];

// ค. นับจำนวนที่พนักงาน "ใช้สิทธิ์ไปแล้ว" (จากตารางบันทึกการใช้สิทธิ์)
$sql_used_hol = "SELECT COUNT(*) as used FROM holiday_usage_records WHERE emp_id = ? AND YEAR(taken_date) = ?";
$stmt_used_h = $conn->prepare($sql_used_hol);
$stmt_used_h->bind_param("ii", $emp_id, $current_year);
$stmt_used_h->execute();
$total_used_hol = $stmt_used_h->get_result()->fetch_assoc()['used'];

// ง. คำนวณสิทธิ์คงเหลือสะสม (วันหยุดที่ผ่านมาแล้ว - วันที่ใช้ไปแล้ว)
$passed_holidays = $total_passed - $total_used_hol;
if($passed_holidays < 0) $passed_holidays = 0; // ป้องกันค่าติดลบกรณีข้อมูลผิดพลาด

// จ. ดึงจำนวนวันหยุดนักขัตฤกษ์ทั้งหมดในปีนี้ (รวมทุกเดือน)
$sql_total_holidays = "SELECT COUNT(*) as total FROM public_holidays WHERE YEAR(holiday_date) = ?";
$stmt_total_hol = $conn->prepare($sql_total_holidays);
$stmt_total_hol->bind_param("i", $current_year);
$stmt_total_hol->execute();
$total_year_holidays = $stmt_total_hol->get_result()->fetch_assoc()['total'];

$thai_months = ["", "มกราคม", "กุมภาพันธ์", "มีนาคม", "เมษายน", "พฤษภาคม", "มิถุนายน", "กรกฎาคม", "สิงหาคม", "กันยายน", "ตุลาคม", "พฤศจิกายน", "ธันวาคม"];
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard พนักงาน | LALA MUKHA</title>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root { --primary: #182848; --secondary: #4b6cb7; --bg: #f5f7fb; --text: #2e2e2e; --success: #4caf50; --warning: #ffa000; --danger: #f44336; --accent: #11998e; }
        body { margin: 0; font-family: 'Prompt', sans-serif; background: var(--bg); display: flex; color: var(--text); }
        
        /* Sidebar */
        .sidebar { width: 250px; background: linear-gradient(135deg, #4b6cb7 0%, #182848 100%); color: #fff; position: fixed; height: 100vh; display: flex; flex-direction: column; z-index: 20; }
        .sidebar-header { text-align: center; padding: 25px 15px; font-weight: 600; border-bottom: 1px solid rgba(255,255,255,0.1); font-size: 1.1em; }
        .menu-item { display: flex; align-items: center; padding: 15px 25px; color: rgba(255,255,255,0.85); text-decoration: none; transition: 0.25s; }
        .menu-item:hover, .menu-item.active { background: rgba(255,255,255,0.2); color: #fff; font-weight: 600; border-left: 4px solid #fff; }
        .menu-item i { margin-right: 12px; width: 20px; text-align: center; }
        
        .main-content { flex-grow: 1; margin-left: 250px; padding: 30px; width: calc(100% - 250px); }
        
        .topbar { background: #fff; border-radius: 12px; padding: 12px 25px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 4px 10px rgba(0,0,0,0.05); margin-bottom: 25px; }
        .notif-badge-item { display: inline-flex; align-items: center; margin-right: 15px; font-size: 0.85em; font-weight: 500; text-decoration: none; color: var(--text); background: #f8f9fa; padding: 6px 12px; border-radius: 8px; }
        .count-tag { background: var(--danger); color: #fff; padding: 1px 7px; border-radius: 10px; font-size: 0.8em; margin-left: 5px; }
        .count-tag.wait { background: var(--warning); }

        .card { background: #fff; border-radius: 16px; padding: 25px; margin-bottom: 25px; box-shadow: 0 6px 20px rgba(0,0,0,0.05); }
        .stat-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-top: 15px; }
        .stat-card { background: linear-gradient(135deg, #4b6cb7, #182848); color: white; border-radius: 12px; padding: 20px; position: relative; overflow: hidden; transition: 0.3s; }
        .stat-card:hover { transform: translateY(-5px); }
        .stat-card h3 { margin: 0; font-weight: 400; font-size: 0.85em; opacity: 0.8; }
        .stat-card p { font-size: 1.8em; margin: 5px 0 0 0; font-weight: 700; }
        .stat-card i { position: absolute; right: -5px; bottom: -5px; font-size: 2.5em; opacity: 0.15; }

        .grid-layout { display: grid; grid-template-columns: 1.5fr 1fr; gap: 25px; }
        .holiday-item { display: flex; align-items: center; gap: 15px; padding: 12px 0; border-bottom: 1px solid #eee; }
        .holiday-date { background: #eef2ff; color: var(--secondary); width: 45px; height: 45px; display: flex; align-items: center; justify-content: center; border-radius: 10px; font-weight: 700; flex-shrink: 0; }

        @media (max-width: 992px) { .grid-layout { grid-template-columns: 1fr; } }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="sidebar-header">LALA MUKHA<br><span style="font-size: 0.8em; opacity: 0.8;">ระบบบริหารจัดการการลา</span></div>
    <div style="padding-top: 20px;">
        <a href="employee_dashboard.php" class="menu-item active"><i class="fas fa-home"></i><span>Dashboard</span></a>
        <a href="apply_leave.php" class="menu-item"><i class="fas fa-calendar-plus"></i><span>ยื่นคำร้องขอลา</span></a>
        <a href="leave_history.php" class="menu-item"><i class="fas fa-history"></i><span>ประวัติการลา</span></a>
        <a href="public_holidays.php" class="menu-item"><i class="fas fa-calendar-alt"></i><span>ปฏิทินวันหยุด</span></a>
    </div>
    <a href="logout.php" class="menu-item" style="margin-top:auto; border-top: 1px solid rgba(255,255,255,0.1);"><i class="fas fa-sign-out-alt"></i><span> ออกจากระบบ</span></a>
</div>

<div class="main-content">
    <div class="topbar">
        <div style="display: flex; align-items: center;">
            <?php if ($count_pending > 0): ?>
                <a href="leave_history.php" class="notif-badge-item">
                    <i class="fas fa-hourglass-half" style="color: var(--warning);"></i> รออนุมัติ <span class="count-tag wait"><?php echo $count_pending; ?></span>
                </a>
            <?php endif; ?>
            <?php if ($count_rejected > 0): ?>
                <a href="leave_history.php" class="notif-badge-item">
                    <i class="fas fa-exclamation-circle" style="color: var(--danger);"></i> ไม่นุมัติ <span class="count-tag"><?php echo $count_rejected; ?></span>
                </a>
            <?php endif; ?>
            <?php if ($total_urgent == 0): ?>
                <span style="font-size: 0.85em; color: #999;"><i class="fas fa-check-circle" style="color: var(--success);"></i> ข้อมูลอัปเดตเป็นปัจจุบัน</span>
            <?php endif; ?>
        </div>

        <div class="user-info">
            <span style="font-size: 0.9em;">สวัสดี, <strong><?php echo $user_display_name; ?></strong> <small>(<?php echo $emp_code; ?>)</small></span>
            <i class="fas fa-user-circle" style="font-size: 1.8em; color: var(--primary); margin-left:10px; vertical-align: middle;"></i>
        </div>
    </div>

    <div class="card">
        <h2 style="margin:0; color:var(--primary); font-size:1.2em; display:flex; align-items:center; gap:10px;">
            <i class="fas fa-chart-pie"></i> สรุปสิทธิ์วันลาและวันหยุดประจำปี <?php echo $current_year + 543; ?>
        </h2>
        <div class="stat-cards">
            <?php foreach ($balances as $balance): ?>
                <div class="stat-card">
                    <h3><?php echo htmlspecialchars($balance['leave_type_display']); ?></h3>
                    <p><?php echo number_format($balance['remaining_days'], 1); ?> 
                        <span style="font-size: 0.5em; font-weight: 400;"><?php echo ($balance['leave_unit'] == 'day' ? 'วัน' : 'ชม.'); ?></span>
                    </p>
                    <i class="fas fa-calendar-check"></i>
                </div>
            <?php endforeach; ?>

            <div class="stat-card" style="background: linear-gradient(135deg, #11998e, #38ef7d);">
                <h3>สิทธิ์ลาชดเชยสะสม (คงเหลือ)</h3>
                <p><?php echo $passed_holidays; ?> <span style="font-size: 0.5em; font-weight: 400;">สิทธิ์</span></p>
                <i class="fas fa-gift"></i>
            </div>

            <div class="stat-card" style="background: linear-gradient(135deg, #FF512F, #DD2476);">
                <h3>วันหยุดนักขัตฤกษ์ (ทั้งปี)</h3>
                <p><?php echo $total_year_holidays; ?> <span style="font-size: 0.5em; font-weight: 400;">วัน</span></p>
                <i class="fas fa-calendar-star"></i>
            </div>
        </div>
    </div>

    <div class="grid-layout">
        <div class="card">
            <h2 style="margin-top:0; font-size:1.1em;"><i class="fas fa-clock"></i> กิจกรรมล่าสุด</h2>
            <div style="text-align:center; padding:40px 20px;">
                <img src="https://cdn-icons-png.flaticon.com/512/2645/2645850.png" style="width: 80px; opacity: 0.2; margin-bottom: 15px;">
                <p style="color:#888; font-size: 0.9em;">คุณสามารถตรวจสอบสถานะการลาอย่างละเอียดได้ที่หน้าประวัติ</p>
                <a href="leave_history.php" style="color:var(--secondary); text-decoration:none; font-weight:600; font-size:0.9em;">ดูประวัติการลาทั้งหมด →</a>
            </div>
        </div>

        <div class="card">
            <h2 style="margin-top:0; font-size:1.1em; color: var(--danger);">
                <i class="fas fa-calendar-day"></i> วันหยุดพิเศษเดือน <?php echo $thai_months[(int)$current_month]; ?>
            </h2>
            <div style="margin-top: 15px;">
                <?php if (!empty($holidays_month)): ?>
                    <?php foreach ($holidays_month as $h): ?>
                        <div class="holiday-item">
                            <div class="holiday-date"><?php echo date('d', strtotime($h['holiday_date'])); ?></div>
                            <div>
                                <div style="font-weight: 600; font-size: 0.95em;"><?php echo htmlspecialchars($h['holiday_name']); ?></div>
                                <div style="font-size: 0.8em; color: #777;">หยุดนักขัตฤกษ์</div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="color: #999; font-size: 0.9em; text-align: center; padding: 20px;">ไม่มีวันหยุดพิเศษในเดือนนี้</p>
                <?php endif; ?>
            </div>
            <a href="public_holidays.php" style="display:block; text-align:center; margin-top:15px; font-size:0.85em; color:var(--secondary); text-decoration:none;">ดูปฏิทินทั้งปี</a>
        </div>
    </div>
</div>

</body>
</html>