<?php
session_start();
require_once '../conn.php'; 

// 1. ตรวจสอบสิทธิ์การเข้าถึง
if (!isset($_SESSION['loggedin']) || $_SESSION['user_role'] !== 'employee') {
    header("location: ../login.php");
    exit;
}

$emp_id = $_SESSION['emp_id'];
$current_year = date('Y');
$current_month = date('m'); 
$today_timestamp = strtotime('2026-01-09'); // วันที่จำลองตามโจทย์

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

// --- 2. ดึงรายชื่อวันหยุดที่ใช้สิทธิ์ไปแล้ว ---
$used_holidays = [];
$sql_check_used = "SELECT reason FROM leave_requests WHERE emp_id = ? AND status != 'Cancelled'";
$stmt_used = $conn->prepare($sql_check_used);
$stmt_used->bind_param("i", $emp_id);
$stmt_used->execute();
$res_used = $stmt_used->get_result();
while ($row_u = $res_used->fetch_assoc()) {
    if (preg_match('/\((\d{4}-\d{2}-\d{2})\)/', $row_u['reason'], $matches)) {
        $used_holidays[] = $matches[1];
    }
}

// --- 3. ดึงข้อมูลวันหยุดนักขัตฤกษ์ทั้งหมดในปีนี้ ---
$sql_holidays = "SELECT * FROM public_holidays WHERE YEAR(holiday_date) = ? ORDER BY holiday_date ASC";
$stmt_hol = $conn->prepare($sql_holidays);
$stmt_hol->bind_param("i", $current_year);
$stmt_hol->execute();
$result = $stmt_hol->get_result();

$holidays_by_month = [];
while ($row = $result->fetch_assoc()) {
    $month = (int)date('m', strtotime($row['holiday_date']));
    $holidays_by_month[$month][] = $row;
}

$thai_months = ["", "มกราคม", "กุมภาพันธ์", "มีนาคม", "เมษายน", "พฤษภาคม", "มิถุนายน", "กรกฎาคม", "สิงหาคม", "กันยายน", "ตุลาคม", "พฤศจิกายน", "ธันวาคม"];
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ปฏิทินวันหยุด | LALA MUKHA</title>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root { --primary: #182848; --secondary: #4b6cb7; --bg: #f5f7fb; --text: #2e2e2e; --success: #4caf50; --warning: #ffa000; --danger: #f44336; --gray: #abb2b9; }
        body { margin: 0; font-family: 'Prompt', sans-serif; background: var(--bg); display: flex; color: var(--text); }
        
        /* Sidebar */
        .sidebar { width: 250px; background: linear-gradient(135deg, #4b6cb7 0%, #182848 100%); color: #fff; position: fixed; height: 100vh; display: flex; flex-direction: column; z-index: 20; }
        .sidebar-header { text-align: center; padding: 25px 15px; font-weight: 600; border-bottom: 1px solid rgba(255,255,255,0.1); font-size: 1.1em; }
        .menu-item { display: flex; align-items: center; padding: 15px 25px; color: rgba(255,255,255,0.85); text-decoration: none; transition: 0.25s; }
        .menu-item:hover, .menu-item.active { background: rgba(255,255,255,0.2); color: #fff; font-weight: 600; border-left: 4px solid #fff; }
        .menu-item i { margin-right: 12px; width: 20px; text-align: center; }
        
        /* Main Content */
        .main-content { flex-grow: 1; margin-left: 250px; padding: 30px; width: calc(100% - 250px); }
        
        /* Topbar */
        .topbar { background: #fff; border-radius: 12px; padding: 12px 25px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 4px 10px rgba(0,0,0,0.05); margin-bottom: 25px; }

        /* Calendar Grid */
        .calendar-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 20px; }
        .month-card { background: #fff; border-radius: 16px; padding: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); border-top: 5px solid var(--secondary); }
        .month-name { color: var(--primary); font-weight: 700; font-size: 1.1em; border-bottom: 2px solid #f0f0f0; padding-bottom: 10px; margin-bottom: 15px; }
        
        .holiday-row { display: flex; gap: 12px; padding: 12px 0; border-bottom: 1px dashed #eee; align-items: center; justify-content: space-between; }
        .holiday-row:last-child { border-bottom: none; }
        .date-box { background: #f0f4ff; color: var(--secondary); width: 42px; height: 42px; display: flex; align-items: center; justify-content: center; border-radius: 10px; font-weight: 700; flex-shrink: 0; }
        
        /* Status & Buttons */
        .is-used { opacity: 0.6; filter: grayscale(1); }
        .btn-apply { background: var(--success); color: white; border: none; padding: 6px 12px; border-radius: 6px; font-size: 0.8em; cursor: pointer; transition: 0.3s; }
        .btn-apply:hover { background: #2e7d32; transform: scale(1.05); }
        .btn-used { background: var(--gray); color: white; border: none; padding: 6px 12px; border-radius: 6px; font-size: 0.8em; cursor: not-allowed; }
        .btn-locked { background: #e0e0e0; color: #999; border: none; padding: 6px 12px; border-radius: 6px; font-size: 0.8em; cursor: not-allowed; }
        
        .status-text { font-size: 0.75em; display: block; margin-top: 2px; }
        .ready { color: var(--success); font-weight: 600; }
        .locked { color: var(--gray); }
        .used { color: #666; }

        @media (max-width: 768px) { .main-content { margin-left: 0; width: 100%; } .sidebar { display: none; } }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="sidebar-header">LALA MUKHA<br><span style="font-size: 0.8em; opacity: 0.8;">ระบบบริหารจัดการการลา</span></div>
    <div style="padding-top: 20px;">
        <a href="employee_dashboard.php" class="menu-item"><i class="fas fa-home"></i><span>Dashboard</span></a>
        <a href="apply_leave.php" class="menu-item"><i class="fas fa-calendar-plus"></i><span>ยื่นคำร้องขอลา</span></a>
        <a href="leave_history.php" class="menu-item"><i class="fas fa-history"></i><span>ประวัติการลา</span></a>
        <a href="public_holidays.php" class="menu-item active"><i class="fas fa-calendar-alt"></i><span>ปฏิทินวันหยุด</span></a>
    </div>
    <a href="logout.php" class="menu-item" style="margin-top:auto; border-top: 1px solid rgba(255,255,255,0.1);"><i class="fas fa-sign-out-alt"></i><span> ออกจากระบบ</span></a>
</div>

<div class="main-content">
    <div class="topbar">
        <div style="font-weight:600; color:var(--primary);"><i class="fas fa-calendar-check"></i> ตรวจสอบสิทธิ์และใช้ลาวันหยุดนักขัตฤกษ์</div>
        <div class="user-info">
            <span style="font-size: 0.9em;">สวัสดี, <strong><?php echo $user_display_name; ?></strong></span>
            <i class="fas fa-user-circle" style="font-size: 1.8em; color: var(--primary); margin-left:10px; vertical-align: middle;"></i>
        </div>
    </div>

    <div class="calendar-grid">
        <?php for ($m = 1; $m <= 12; $m++): ?>
            <div class="month-card">
                <div class="month-name"><?php echo $thai_months[$m]; ?></div>
                <div class="holiday-list">
                    <?php if (isset($holidays_by_month[$m])): ?>
                        <?php foreach ($holidays_by_month[$m] as $h): 
                            $h_date_ts = strtotime($h['holiday_date']);
                            $is_already_used = in_array($h['holiday_date'], $used_holidays);
                            $can_use = ($h_date_ts < $today_timestamp && !$is_already_used); 
                        ?>
                            <div class="holiday-row <?php echo ($is_already_used) ? 'is-used' : ''; ?>">
                                <div style="display: flex; gap: 12px; align-items: center;">
                                    <div class="date-box"><?php echo date('d', $h_date_ts); ?></div>
                                    <div class="holiday-info">
                                        <div style="font-weight: 600; font-size: 0.9em;"><?php echo htmlspecialchars($h['holiday_name']); ?></div>
                                        <?php if ($is_already_used): ?>
                                            <span class="status-text used"><i class="fas fa-check-double"></i> ใช้สิทธิ์แล้ว</span>
                                        <?php elseif ($h_date_ts < $today_timestamp): ?>
                                            <span class="status-text ready"><i class="fas fa-check-circle"></i> พร้อมใช้สิทธิ์</span>
                                        <?php else: ?>
                                            <span class="status-text locked"><i class="fas fa-lock"></i> ยังไม่ถึงกำหนด</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div style="text-align: right;">
                                    <?php if ($is_already_used): ?>
                                        <button class="btn-used" disabled>ใช้แล้ว</button>
                                    <?php elseif ($can_use): ?>
                                        <button class="btn-apply" onclick="confirmHolidayLeave('<?php echo $h['holiday_date']; ?>', '<?php echo htmlspecialchars($h['holiday_name']); ?>')">
                                            ใช้ลา (<?php echo date('d/m/y', $h_date_ts); ?>)
                                        </button>
                                    <?php else: ?>
                                        <button class="btn-locked" disabled>ล็อกสิทธิ์</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="color:#ccc; text-align:center; font-size:0.9em; padding:10px;">ไม่มีวันหยุดพิเศษ</p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endfor; ?>
    </div>
</div>

<script>
function confirmHolidayLeave(date, name) {
    Swal.fire({
        title: 'ยืนยันการใช้สิทธิ์ลา?',
        text: `คุณต้องการใช้สิทธิ์ลาของวันที่ ${name} ใช่หรือไม่? ระบบจะหักโควตาวันลาของคุณ 1 วันทันที`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#4caf50',
        cancelButtonColor: '#f44336',
        confirmButtonText: 'ยืนยัน',
        cancelButtonText: 'ยกเลิก'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = `process_holiday_leave.php?holiday_date=${date}&holiday_name=${encodeURIComponent(name)}`;
        }
    })
}

<?php if(isset($_SESSION['swal_success'])): ?>
    Swal.fire({ icon: 'success', title: 'สำเร็จ!', text: '<?php echo $_SESSION['swal_success']; ?>', timer: 2500, showConfirmButton: false });
    <?php unset($_SESSION['swal_success']); ?>
<?php endif; ?>
</script>
</body>
</html>