<?php
session_start();
require_once '../conn.php'; 

// ตรวจสอบสิทธิ์การเข้าถึง
if (!isset($_SESSION['loggedin']) || $_SESSION['user_role'] !== 'manager') {
    header("location: ../login.php");
    exit;
}

$emp_id = $_SESSION['emp_id']; 
$user_role = $_SESSION['user_role'];

// ดึงข้อมูล Manager และแผนก
$sql_user = "SELECT first_name, last_name, dept_id FROM employees WHERE emp_id = ?";
$current_dept_id = 0;
$user_display_name = $_SESSION['username'];

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

// นับสถิติสำหรับ Dashboard
$stats = ['total_employees' => 0, 'pending_requests' => 0, 'total_leave_types' => 0, 'total_holidays' => 0];

// 1. นับพนักงานในแผนก
$sql_emp_count = "SELECT COUNT(emp_id) AS total FROM employees WHERE dept_id = ? AND resign_date IS NULL";
if ($stmt_emp = $conn->prepare($sql_emp_count)) {
    $stmt_emp->bind_param("i", $current_dept_id);
    $stmt_emp->execute();
    $stats['total_employees'] = $stmt_emp->get_result()->fetch_assoc()['total'];
    $stmt_emp->close();
}

// 2. นับคำขอลาที่รออนุมัติเฉพาะในแผนก
$sql_pend_count = "SELECT COUNT(lr.request_id) AS total 
                   FROM leave_requests lr 
                   JOIN employees e ON lr.emp_id = e.emp_id 
                   WHERE lr.status = 'Pending' AND e.dept_id = ?";
if ($stmt_pend = $conn->prepare($sql_pend_count)) {
    $stmt_pend->bind_param("i", $current_dept_id);
    $stmt_pend->execute();
    $stats['pending_requests'] = $stmt_pend->get_result()->fetch_assoc()['total'];
    $stmt_pend->close();
}

// 3. นับประเภทการลาทั้งหมด
$sql_lt_count = "SELECT COUNT(*) AS total FROM leave_types";
$res_lt = $conn->query($sql_lt_count);
$stats['total_leave_types'] = $res_lt->fetch_assoc()['total'];

// 4. นับวันหยุดพิเศษทั้งหมดที่มีในระบบ
$sql_h_count = "SELECT COUNT(*) AS total FROM public_holidays";
$res_h = $conn->query($sql_h_count);
$stats['total_holidays'] = $res_h->fetch_assoc()['total'];

// 5. ข้อมูลสำหรับกราฟแท่ง: สถิติการลาที่อนุมัติแล้วในแผนก
$graph_labels = [];
$graph_data = [];
$sql_graph = "SELECT lr.leave_type, COUNT(lr.request_id) as cnt 
              FROM leave_requests lr
              JOIN employees e ON lr.emp_id = e.emp_id
              WHERE e.dept_id = ? AND lr.status = 'Approved'
              GROUP BY lr.leave_type";
if ($stmt_graph = $conn->prepare($sql_graph)) {
    $stmt_graph->bind_param("i", $current_dept_id);
    $stmt_graph->execute();
    $res_graph = $stmt_graph->get_result();
    while($row_g = $res_graph->fetch_assoc()){
        $graph_labels[] = $row_g['leave_type'];
        $graph_data[] = $row_g['cnt'];
    }
    $stmt_graph->close();
}

// 6. ข้อมูลสำหรับกราฟวงกลม: สัดส่วนพนักงานทุกแผนก
$pie_labels = [];
$pie_data = [];
$sql_pie = "SELECT d.dept_name, COUNT(e.emp_id) as cnt 
            FROM departments d 
            LEFT JOIN employees e ON d.dept_id = e.dept_id AND e.resign_date IS NULL 
            GROUP BY d.dept_id";
$res_pie = $conn->query($sql_pie);
while($row_p = $res_pie->fetch_assoc()) {
    $pie_labels[] = $row_p['dept_name'];
    $pie_data[] = $row_p['cnt'];
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manager Dashboard | LALA MUKHA</title>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
    :root {
        --primary: #004030;
        --secondary: #66BB6A;
        --accent: #81C784;
        --bg: #f5f7fb;
        --text: #2e2e2e;
    }

    body {
        margin: 0;
        font-family: 'Prompt', sans-serif;
        background: var(--bg);
        display: flex;
        min-height: 100vh;
        color: var(--text);
    }

    .sidebar {
        width: 250px;
        background: linear-gradient(180deg, var(--primary), #2e7d32);
        color: #fff;
        position: fixed;
        height: 100%;
        display: flex;
        flex-direction: column;
        box-shadow: 3px 0 10px rgba(0, 0, 0, 0.1);
        z-index: 100;
    }

    .sidebar-header {
        padding: 25px;
        text-align: center;
        font-weight: 600;
        border-bottom: 1px solid rgba(255, 255, 255, 0.2);
    }

    .menu-list {
        flex-grow: 1;
        padding-top: 10px;
    }

    .menu-item {
        display: flex;
        align-items: center;
        padding: 15px 25px;
        color: rgba(255, 255, 255, 0.85);
        text-decoration: none;
        transition: 0.2s;
    }

    .menu-item:hover {
        background: rgba(255, 255, 255, 0.15);
        color: #fff;
    }

    .menu-item.active {
        background: rgba(255, 255, 255, 0.25);
        color: #fff;
        font-weight: 600;
    }

    .menu-item i {
        margin-right: 12px;
        width: 20px;
        text-align: center;
    }

    .logout-btn {
        margin-top: auto;
        text-align: center;
        padding: 15px;
        background: #388e3c;
        color: #fff;
        text-decoration: none;
        transition: 0.3s;
    }

    .main-content {
        flex-grow: 1;
        margin-left: 250px;
        padding: 30px;
    }

    .header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: #fff;
        padding: 15px 25px;
        border-radius: 12px;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
        margin-bottom: 25px;
    }

    .card {
        background: rgba(255, 255, 255, 0.9);
        border-radius: 16px;
        padding: 25px;
        box-shadow: 0 6px 20px rgba(0, 0, 0, 0.05);
        margin-bottom: 25px;
    }

    .stat-cards {
        display: flex;
        gap: 20px;
        flex-wrap: wrap;
    }

    .stat-card {
        flex: 1;
        min-width: 200px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: #fff;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
        transition: 0.3s;
    }

    .stat-icon {
        font-size: 2em;
        color: var(--secondary);
    }

    .stat-details h3 {
        margin: 0;
        font-size: 1.8em;
        color: var(--primary);
    }

    .stat-details p {
        margin: 0;
        font-size: 0.9em;
        color: #666;
    }

    .chart-container-row {
        display: flex;
        gap: 20px;
        flex-wrap: wrap;
    }

    .chart-box {
        flex: 1;
        min-width: 300px;
        background: #fff;
        padding: 20px;
        border-radius: 12px;
        box-shadow: 0 4px 10px rgba(0,0,0,0.05);
    }

    .btn-action {
        display: inline-block;
        background: var(--primary);
        color: #fff;
        padding: 12px 25px;
        border-radius: 8px;
        text-decoration: none;
        transition: 0.3s;
        font-weight: 500;
    }
    </style>
</head>

<body>

    <div class="sidebar">
        <div class="sidebar-header">
            LALA MUKHA<br><span style="font-size: 0.8em; opacity: 0.8;">Manager Control Panel</span>
        </div>
        <div class="menu-list">
            <a href="manager_dashboard.php" class="menu-item active">
                <i class="fas fa-tachometer-alt"></i> ภาพรวมระบบ
            </a>
            <a href="manage_leave.php" class="menu-item">
                <i class="fas fa-clipboard-list"></i> จัดการการลา
            </a>
            <a href="manage_employees.php" class="menu-item">
                <i class="fas fa-users"></i> ข้อมูลพนักงาน
            </a>
            <a href="manage_leavetype.php" class="menu-item">
                <i class="fas fa-list-ul"></i> ประเภทการลา
            </a>
            <a href="manage_department.php" class="menu-item">
                <i class="fas fa-building"></i> แผนก
            </a>
            <a href="manage_holidays.php" class="menu-item">
                <i class="fas fa-calendar-alt"></i> จัดการวันหยุดพิเศษ
            </a>
            <a href="report.php" class="menu-item">
            <i class="fas fa-file-pdf"></i> ออกรายงาน
        </a>
        </div>

        <a href="logout.php" class="logout-btn">
            <i class="fas fa-sign-out-alt"></i> ออกจากระบบ
        </a>
    </div>

    <div class="main-content">
        <div class="header">
            <div style="color: var(--primary); font-weight: 600;">ยินดีต้อนรับสู่ระบบจัดการแผนก</div>
            <div class="user-profile">
                <span><?php echo $user_display_name; ?> (Manager)</span>
                <i class="fas fa-user-circle" style="font-size: 1.8em; color: var(--primary);"></i>
            </div>
        </div>

        <div class="card">
            <h1>สรุปข้อมูลภาพรวม</h1>
            <div class="stat-cards">
                <div class="stat-card">
                    <div class="stat-details">
                        <h3><?php echo number_format($stats['total_employees']); ?></h3>
                        <p>พนักงานในแผนก</p>
                    </div>
                    <i class="fas fa-users stat-icon"></i>
                </div>
                <div class="stat-card">
                    <div class="stat-details">
                        <h3><?php echo number_format($stats['pending_requests']); ?></h3>
                        <p>คำขอรออนุมัติ</p>
                    </div>
                    <i class="fas fa-clock stat-icon" style="color:#FFC107;"></i>
                </div>
                <div class="stat-card">
                    <div class="stat-details">
                        <h3><?php echo number_format($stats['total_leave_types']); ?></h3>
                        <p>ประเภทการลา</p>
                    </div>
                    <i class="fas fa-list-ul stat-icon" style="color:#42A5F5;"></i>
                </div>
                <div class="stat-card">
                    <div class="stat-details">
                        <h3><?php echo number_format($stats['total_holidays']); ?></h3>
                        <p>วันหยุดพิเศษทั้งหมด</p>
                    </div>
                    <i class="fas fa-calendar-star stat-icon" style="color:#FF7043;"></i>
                </div>
                
            </div>
        </div>

        <div class="chart-container-row">
            <div class="chart-box">
                <h3 style="font-size: 1.1em; margin-bottom: 20px;">สัดส่วนพนักงานทุกแผนก</h3>
                <div style="height: 300px; position: relative;">
                    <canvas id="allDepartmentPieChart"></canvas>
                </div>
            </div>
            <div class="chart-box">
                <h3 style="font-size: 1.1em; margin-bottom: 20px;">สถิติการลาที่อนุมัติ (แยกประเภท)</h3>
                <div style="height: 300px; position: relative;">
                    <canvas id="departmentLeaveChart"></canvas>
                </div>
            </div>
        </div>

        <div class="card" style="margin-top: 25px;">
            <h2>เมนูจัดการด่วน</h2>
            <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                <a href="manage_leave.php" class="btn-action">
                    อนุมัติการลา <i class="fas fa-clipboard-check" style="margin-left: 8px;"></i>
                </a>
                <a href="manage_employees.php" class="btn-action" style="background: var(--secondary);">
                    ดูข้อมูลพนักงาน <i class="fas fa-users" style="margin-left: 8px;"></i>
                </a>
            </div>
        </div>
    </div>

    <script>
    // 1. กราฟวงกลม: สัดส่วนพนักงานทุกแผนก
    new Chart(document.getElementById('allDepartmentPieChart'), {
        type: 'pie',
        data: {
            labels: <?php echo json_encode($pie_labels); ?>,
            datasets: [{
                data: <?php echo json_encode($pie_data); ?>,
                backgroundColor: ['#004030', '#66BB6A', '#81C784', '#FFC107', '#42A5F5', '#FF7043', '#AB47BC', '#FFD54F', '#4DB6AC'],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', labels: { font: { family: 'Prompt', size: 10 } } }
            }
        }
    });

    // 2. กราฟแท่ง: สถิติการลาแต่ละประเภท
    new Chart(document.getElementById('departmentLeaveChart'), {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($graph_labels); ?>,
            datasets: [{
                label: 'จำนวนครั้งที่ลา',
                data: <?php echo json_encode($graph_data); ?>,
                backgroundColor: '#66BB6A',
                borderRadius: 5
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: { beginAtZero: true, ticks: { stepSize: 1 } }
            },
            plugins: {
                legend: { display: false }
            }
        }
    });
    </script>
</body>
</html>