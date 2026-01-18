<?php
session_start();
require_once '../conn.php'; 

// 1. ตรวจสอบสิทธิ์การเข้าถึง (เฉพาะ Supervisor)
if (!isset($_SESSION['loggedin']) || !in_array($_SESSION['user_role'], ['supervisor'])) {
    header("location: ../login.php");
    exit;
}

$emp_id = $_SESSION['emp_id']; 
$status_message = $_SESSION['status_message'] ?? '';
unset($_SESSION['status_message']);

$current_year = date('Y');
$today_db = date('Y-m-d');
$today_timestamp = strtotime('2026-01-09'); // วันที่จำลองตามโจทย์

// --- 1. ดึงข้อมูลหัวหน้างานและแผนก ---
$sql_user = "SELECT E.first_name, E.last_name, D.dept_name 
             FROM employees E 
             LEFT JOIN departments D ON E.dept_id = D.dept_id 
             WHERE E.emp_id = ?";
$stmt_user = $conn->prepare($sql_user);
$stmt_user->bind_param("i", $emp_id);
$stmt_user->execute();
$result_user = $stmt_user->get_result();

if ($row_user = $result_user->fetch_assoc()) {
    $user_display_name = htmlspecialchars($row_user['first_name'] . ' ' . $row_user['last_name']);
    $current_dept_name = htmlspecialchars($row_user['dept_name']);
}

// --- 2. ดึงรายชื่อวันหยุดที่ใช้สิทธิ์ไปแล้ว (กันลาซ้ำ) ---
$used_holidays = [];
$sql_check_used = "SELECT reason FROM leave_requests WHERE emp_id = ? AND status != 'Cancelled'";
$stmt_used = $conn->prepare($sql_check_used);
$stmt_used->bind_param("i", $emp_id);
$stmt_used->execute();
$res_used = $stmt_used->get_result();
while ($row_u = $res_used->fetch_assoc()) {
    // ตรวจสอบวันที่ในวงเล็บจากฟิลด์เหตุผล เช่น (2026-01-01)
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
    <title>ปฏิทินวันหยุด | LALA MUKHA</title>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root { --primary:#004030; --secondary:#66BB6A; --bg:#f5f7fb; --text:#2e2e2e; --warning:#ffa000; --success:#4caf50; --danger:#f44336; }
        body { margin:0; font-family:'Prompt',sans-serif; background:var(--bg); display:flex; color:var(--text); }
        
        .sidebar { width:250px; background:linear-gradient(180deg,var(--primary),#2e7d32); color:#fff; position:fixed; height:100%; display:flex; flex-direction:column; box-shadow:3px 0 10px rgba(0,0,0,0.15); }
        .sidebar-header { text-align:center; padding:25px 15px; font-weight:600; border-bottom:1px solid rgba(255,255,255,0.2); }
        .menu-item { display:flex; align-items:center; padding:15px 25px; color:rgba(255,255,255,0.85); text-decoration:none; transition:0.25s; }
        .menu-item:hover, .menu-item.active { background:rgba(255,255,255,0.2); color:#fff; font-weight:600; }
        .menu-item i { margin-right:12px; width:20px; text-align:center; }
        
        .main-content { flex-grow:1; margin-left:250px; padding:30px; }
        .header { background:#fff; border-radius:12px; padding:15px 25px; display:flex; justify-content:space-between; align-items:center; box-shadow:0 4px 10px rgba(0,0,0,0.05); margin-bottom:25px; }
        
        .calendar-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 20px; }
        .month-card { background:#fff; border-radius:16px; padding:20px; box-shadow:0 6px 20px rgba(0,0,0,0.05); border-top: 5px solid var(--primary); }
        .month-name { color: var(--primary); font-weight: 700; font-size: 1.1em; border-bottom: 2px solid #f0f0f0; padding-bottom: 10px; margin-bottom: 15px; }
        
        .holiday-row { display: flex; gap: 12px; padding: 12px 0; border-bottom: 1px dashed #eee; align-items: center; justify-content: space-between; }
        .holiday-row:last-child { border-bottom: none; }
        .holiday-row.is-used { opacity: 0.6; filter: grayscale(1); }
        .date-box { background: #f0f4ff; color: var(--primary); width: 42px; height: 42px; display: flex; align-items: center; justify-content: center; border-radius: 10px; font-weight: 700; flex-shrink: 0; }
        
        .btn-apply { background: var(--success); color: white; border: none; padding: 6px 12px; border-radius: 6px; font-size: 0.8em; cursor: pointer; transition: 0.3s; }
        .btn-apply:hover { background: #2e7d32; transform: scale(1.05); }
        .btn-locked { background: #e0e0e0; color: #999; border: none; padding: 6px 12px; border-radius: 6px; font-size: 0.8em; cursor: not-allowed; }
        .btn-used { background: #abb2b9; color: white; border: none; padding: 6px 12px; border-radius: 6px; font-size: 0.8em; cursor: not-allowed; }

        .status-text { font-size: 0.75em; display: block; margin-top: 2px; }
        .ready { color: var(--success); font-weight: 600; }
        .locked { color: #888; }
        .used { color: var(--primary); }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="sidebar-header">LALA MUKHA<br>ระบบจัดการพนักงาน</div>
    <a href="supervisor_dashboard.php" class="menu-item"><i class="fas fa-tachometer-alt"></i> ภาพรวมระบบ</a>
    <a href="manage_leave.php" class="menu-item"><i class="fas fa-clipboard-list"></i> จัดการการลา</a>
    <a href="apply_leave.php" class="menu-item"><i class="fas fa-calendar-plus"></i> ยื่นคำร้องการลา</a>
    <a href="manage_employees.php" class="menu-item"><i class="fas fa-users"></i> ข้อมูลพนักงาน</a>
    <a href="public_holidays.php" class="menu-item active"><i class="fas fa-calendar-alt"></i> ปฏิทินวันหยุด</a>
    <a href="leave_history.php" class="menu-item"><i class="fas fa-history"></i><span>ประวัติการลาส่วนตัว</span></a>
    <a href="logout.php" style="margin-top:auto; padding:15px; background:#388e3c; text-align:center; color:#fff; text-decoration:none;"><i class="fas fa-sign-out-alt"></i> ออกจากระบบ</a>
</div>

<div class="main-content">
    <div class="header">
        <div style="font-weight:600; color:var(--primary);">แผนก: <span style="color:var(--secondary);"><?= $current_dept_name ?></span></div>
        <div>
            <span><strong><?= $user_display_name ?></strong> (Supervisor)</span>
            <i class="fas fa-user-circle" style="font-size:1.8em; color:var(--primary); margin-left:10px;"></i>
        </div>
    </div>

    <div style="margin-bottom: 25px;">
        <h1 style="margin:0;"><i class="fas fa-calendar-check"></i> ใช้สิทธิ์ลาชดเชยวันหยุดนักขัตฤกษ์</h1>
        <p style="color:#666;">เลือกวันหยุดที่ผ่านมาเพื่อทำรายการลาชดเชยอัตโนมัติ</p>
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
                            $is_past = ($h_date_ts < $today_timestamp);
                            $can_use = ($is_past && !$is_already_used);
                        ?>
                            <div class="holiday-row <?php echo ($is_already_used) ? 'is-used' : ''; ?>">
                                <div style="display: flex; gap: 12px; align-items: center;">
                                    <div class="date-box"><?php echo date('d', $h_date_ts); ?></div>
                                    <div class="holiday-info">
                                        <div style="font-weight: 600; font-size: 0.9em;"><?php echo htmlspecialchars($h['holiday_name']); ?></div>
                                        <?php if ($is_already_used): ?>
                                            <span class="status-text used"><i class="fas fa-check-double"></i> ใช้สิทธิ์แล้ว</span>
                                        <?php elseif ($is_past): ?>
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
                                            ใช้ลาอัตโนมัติ
                                        </button>
                                    <?php else: ?>
                                        <button class="btn-locked" disabled>ล็อกสิทธิ์</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="color:#ccc; text-align:center; font-size:0.85em; padding:10px;">ไม่มีวันหยุดนักขัตฤกษ์</p>
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
        text: `คุณต้องการใช้สิทธิ์ลาชดเชยของวันที่ "${name}" ใช่หรือไม่? ระบบจะทำรายการลาล่วงหน้าให้ 1 วันอัตโนมัติ`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#004030',
        cancelButtonColor: '#f44336',
        confirmButtonText: 'ยืนยันการลา',
        cancelButtonText: 'ยกเลิก'
    }).then((result) => {
        if (result.isConfirmed) {
            // ส่งไปยังไฟล์ประมวลผลการลาอัตโนมัติ (ใช้ไฟล์เดียวกันกับพนักงาน)
            window.location.href = `process_holiday_leave.php?holiday_date=${date}&holiday_name=${encodeURIComponent(name)}`;
        }
    })
}

// แสดง Swal เมื่อมีผลลัพธ์จากเซิร์ฟเวอร์
<?php if(isset($_SESSION['swal_success'])): ?>
    Swal.fire({ icon: 'success', title: 'สำเร็จ!', text: '<?php echo $_SESSION['swal_success']; ?>', timer: 2500, showConfirmButton: false });
    <?php unset($_SESSION['swal_success']); ?>
<?php endif; ?>

<?php if(isset($_SESSION['swal_error'])): ?>
    Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: '<?php echo $_SESSION['swal_error']; ?>' });
    <?php unset($_SESSION['swal_error']); ?>
<?php endif; ?>
</script>

</body>
</html>