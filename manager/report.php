<?php
session_start();
require_once '../conn.php';

// 1. ตรวจสอบสิทธิ์การเข้าถึง (เฉพาะ manager)
if (!isset($_SESSION['loggedin']) || $_SESSION['user_role'] !== 'manager') {
    header("location: ../login.php");
    exit;
}

$emp_id = $_SESSION['emp_id'];
$user_display_name = $_SESSION['username'];

// 2. ดึงข้อมูลแผนกทั้งหมดเพื่อใช้ในตัวกรอง (Dropdown)
$depts = [];
$sql_dept = "SELECT dept_id, dept_name FROM departments ORDER BY dept_name ASC";
$res_dept = $conn->query($sql_dept);
while ($row = $res_dept->fetch_assoc()) {
    $depts[] = $row;
}

// 3. ดึงปีที่มีการทำรายการลาจริงเพื่อใช้ในตัวกรองปี
$years = [];
$sql_years = "SELECT DISTINCT YEAR(start_date) as yr FROM leave_requests ORDER BY yr DESC";
$res_years = $conn->query($sql_years);
if ($res_years->num_rows > 0) {
    while ($row = $res_years->fetch_assoc()) { $years[] = $row['yr']; }
} else {
    $years[] = date('Y'); // ถ้ายังไม่มีข้อมูลให้ใช้ปีปัจจุบัน
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ศูนย์ออกรายงาน | LALA MUKHA</title>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #004030;
            --secondary: #66BB6A;
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

        /* Sidebar Style อิงจาก Dashboard */
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
        .sidebar-header { padding: 25px; text-align: center; font-weight: 600; border-bottom: 1px solid rgba(255, 255, 255, 0.2); }
        .menu-list { flex-grow: 1; padding-top: 10px; }
        .menu-item { display: flex; align-items: center; padding: 15px 25px; color: rgba(255, 255, 255, 0.85); text-decoration: none; transition: 0.2s; }
        .menu-item:hover, .menu-item.active { background: rgba(255, 255, 255, 0.15); color: #fff; }
        .menu-item i { margin-right: 12px; width: 20px; text-align: center; }
        .logout-btn { margin-top: auto; text-align: center; padding: 15px; background: #388e3c; color: #fff; text-decoration: none; }

        /* Main Content */
        .main-content { flex-grow: 1; margin-left: 250px; padding: 30px; }
        .header-top {
            display: flex; justify-content: space-between; align-items: center;
            background: #fff; padding: 15px 25px; border-radius: 12px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05); margin-bottom: 25px;
        }
        .card { background: #fff; border-radius: 16px; padding: 30px; box-shadow: 0 6px 20px rgba(0, 0, 0, 0.05); }

        /* Report Grid */
        .report-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-top: 25px; }
        .report-option {
            border: 1px solid #eee; border-radius: 15px; padding: 25px;
            transition: transform 0.3s, border-color 0.3s; background: #fff;
        }
        .report-option:hover { transform: translateY(-5px); border-color: var(--secondary); background: #fafffa; }
        .report-option h3 { margin: 0 0 20px 0; color: var(--primary); font-size: 1.2em; display: flex; align-items: center; }
        .report-option h3 i { margin-right: 12px; color: var(--secondary); }

        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 8px; font-size: 0.9em; color: #555; font-weight: 500; }
        select {
            width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px;
            font-family: 'Prompt'; font-size: 14px; outline: none; transition: border-color 0.3s;
        }
        select:focus { border-color: var(--secondary); }

        .btn-print {
            display: block; width: 100%; padding: 12px; background: var(--primary);
            color: white; border: none; border-radius: 8px; cursor: pointer;
            font-family: 'Prompt'; font-weight: 600; text-align: center;
            text-decoration: none; margin-top: 15px; transition: 0.3s;
        }
        .btn-print:hover { background: #002d22; box-shadow: 0 4px 12px rgba(0,64,48,0.2); }
    </style>
</head>
<body>

    <div class="sidebar">
        <div class="sidebar-header">
            LALA MUKHA<br><span style="font-size: 0.8em; opacity: 0.8;">Manager Control Panel</span>
        </div>
        <div class="menu-list">
            <a href="manager_dashboard.php" class="menu-item"><i class="fas fa-tachometer-alt"></i> ภาพรวมระบบ</a>
            <a href="manage_leave.php" class="menu-item"><i class="fas fa-clipboard-list"></i> จัดการการลา</a>
            <a href="manage_employees.php" class="menu-item"><i class="fas fa-users"></i> ข้อมูลพนักงาน</a>
            <a href="manage_leavetype.php" class="menu-item"><i class="fas fa-list-ul"></i> ประเภทการลา</a>
            <a href="manage_department.php" class="menu-item"><i class="fas fa-building"></i> แผนก</a>
            <a href="manage_holidays.php" class="menu-item"><i class="fas fa-calendar-alt"></i> จัดการวันหยุดพิเศษ</a>
            <a href="report.php" class="menu-item active"><i class="fas fa-file-pdf"></i> ออกรายงาน</a>
        </div>
        <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> ออกจากระบบ</a>
    </div>

    <div class="main-content">
        <div class="header-top">
            <div style="color: var(--primary); font-weight: 600;">ศูนย์จัดการรายงานระบบ (PDF Export)</div>
            <div class="user-profile">
                <span><?php echo $user_display_name; ?> (Manager)</span>
                <i class="fas fa-user-circle" style="font-size: 1.8em; color: var(--primary); margin-left:10px;"></i>
            </div>
        </div>

        <div class="card">
            <h1><i class="fas fa-print" style="margin-right:15px; color: var(--secondary);"></i>ออกรายงานระบบ</h1>
            <p style="color: #666;">กรุณาเลือกรูปแบบรายงานและเงื่อนไขที่ต้องการกรองข้อมูลเพื่อความถูกต้องของเอกสาร</p>
            <hr style="border: 0; border-top: 1px solid #eee; margin: 25px 0;">

            <div class="report-grid">
                
                <div class="report-option">
                    <h3><i class="fas fa-user-tie"></i>รายชื่อพนักงาน</h3>
                    <form action="report_employees_pdf.php" method="GET" target="_blank">
                        <div class="form-group">
                            <label>เลือกแผนกที่ต้องการดู</label>
                            <select name="dept_id">
                                <option value="all">--- พนักงานทุกแผนก ---</option>
                                <?php foreach ($depts as $d): ?>
                                    <option value="<?php echo $d['dept_id']; ?>"><?php echo $d['dept_name']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn-print">สร้างรายงานพนักงาน</button>
                    </form>
                </div>

                <div class="report-option">
                    <h3><i class="fas fa-calendar-check"></i>สรุปสถิติการลา</h3>
                    <form action="report_leave_summary_pdf.php" method="GET" target="_blank">
                        <div class="form-group">
                            <label>ประจำปี</label>
                            <select name="year">
                                <?php foreach ($years as $y): ?>
                                    <option value="<?php echo $y; ?>" <?php echo ($y == date('Y')) ? 'selected' : ''; ?>><?php echo $y; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>ประจำเดือน</label>
                            <select name="month">
                                <option value="all">ดูข้อมูลทั้งปี</option>
                                <?php
                                $m_names = ["มกราคม", "กุมภาพันธ์", "มีนาคม", "เมษายน", "พฤษภาคม", "มิถุนายน", "กรกฎาคม", "สิงหาคม", "กันยายน", "ตุลาคม", "พฤศจิกายน", "ธันวาคม"];
                                foreach ($m_names as $idx => $name) {
                                    $m_val = str_pad($idx + 1, 2, "0", STR_PAD_LEFT);
                                    echo "<option value='$m_val'>$name</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <button type="submit" class="btn-print" style="background: #2196F3;">สร้างรายงานการลา</button>
                    </form>
                </div>

                <div class="report-option">
                    <h3><i class="fas fa-umbrella-beach"></i>ตารางวันหยุดบริษัท</h3>
                    <form action="report_holidays_pdf.php" method="GET" target="_blank">
                        <div class="form-group">
                            <label>เลือกปีปฏิทิน</label>
                            <select name="year">
                                <option value="2026" selected>2026</option>
                                <option value="2025">2025</option>
                            </select>
                        </div>
                        <button type="submit" class="btn-print" style="background: #FF9800;">สร้างตารางวันหยุด</button>
                    </form>
                </div>

            </div>
        </div>
    </div>
</body>
</html>