<?php
session_start();
require_once '../conn.php'; 

// 1. ตรวจสอบสิทธิ์การเข้าถึง
if (!isset($_SESSION['loggedin']) || $_SESSION['user_role'] !== 'employee') {
    header("location: ../login.php");
    exit;
}

$emp_id = $_SESSION['emp_id']; 
$error_message = '';
$success_message = '';
$current_year = date('Y');
$today = date('Y-m-d');
$min_leave_date_normal = date('Y-m-d', strtotime('+2 days')); 

// 2. ดึงข้อมูลส่วนตัวและผู้บันทึก/อนุมัติ
$sql_user = "SELECT E.emp_code, E.first_name, E.last_name, E.supervisor_id, D.dept_name 
             FROM employees E 
             LEFT JOIN departments D ON E.dept_id = D.dept_id
             WHERE E.emp_id = ?";
$stmt_user = $conn->prepare($sql_user);
$stmt_user->bind_param("i", $emp_id);
$stmt_user->execute();
$result_user = $stmt_user->get_result();
$row_user = $result_user->fetch_assoc();

$emp_code = htmlspecialchars($row_user['emp_code'] ?? '');
$user_display_name = htmlspecialchars(($row_user['first_name'] ?? '') . ' ' . ($row_user['last_name'] ?? ''));
$dept_name = htmlspecialchars($row_user['dept_name'] ?? 'ไม่ระบุสังกัด');
$approver_id = $row_user['supervisor_id'] ?? null;

// 3. ดึงยอดวันลาคงเหลือ (ป้องกัน Error บรรทัด 65 โดยตรวจสอบผลลัพธ์)
$sql_balances = "
    SELECT LT.leave_type_id, LT.leave_type_display, LT.leave_type_name, LT.leave_unit,
           (ELB.allowed_days - ELB.used_days) as remaining
    FROM employee_leave_balances ELB
    JOIN leave_types LT ON ELB.leave_type_id = LT.leave_type_id
    WHERE ELB.emp_id = ? AND ELB.year = ?
    ORDER BY LT.leave_type_id ASC";
$stmt_lb = $conn->prepare($sql_balances);
$stmt_lb->bind_param("ii", $emp_id, $current_year);
$stmt_lb->execute();
$balances_result = $stmt_lb->get_result();

// 4. ดึงจำนวนแจ้งเตือน (Topbar)
$sql_notif = "SELECT status, COUNT(*) as cnt FROM leave_requests WHERE emp_id = ? AND status IN ('Pending', 'Rejected') GROUP BY status";
$stmt_notif = $conn->prepare($sql_notif);
$stmt_notif->bind_param("i", $emp_id);
$stmt_notif->execute();
$res_notif = $stmt_notif->get_result();
$count_pending = 0; $count_rejected = 0;
while($row_n = $res_notif->fetch_assoc()) {
    if($row_n['status'] == 'Pending') $count_pending = $row_n['cnt'];
    if($row_n['status'] == 'Rejected') $count_rejected = $row_n['cnt'];
}

// 5. ประมวลผลการส่งฟอร์ม
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $leave_type_id = filter_input(INPUT_POST, 'leave_type_id', FILTER_VALIDATE_INT);
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'] ?? $start_date;
    $leave_period = $_POST['leave_period'] ?? 'full'; 
    $reason = trim($_POST['reason']);

    $stmt_type = $conn->prepare("SELECT leave_type_name, leave_unit FROM leave_types WHERE leave_type_id = ?");
    $stmt_type->bind_param("i", $leave_type_id);
    $stmt_type->execute();
    $lt_res = $stmt_type->get_result()->fetch_assoc();
    $lt_key_name = $lt_res['leave_type_name'] ?? '';
    $lt_unit = $lt_res['leave_unit'] ?? 'day';

    if ($lt_key_name === 'Sick Leave') {
        if ($start_date > $today) { $error_message = "❌ ลาป่วยไม่สามารถเลือกวันล่วงหน้าได้"; }
    } else {
        if ($start_date < $min_leave_date_normal) { $error_message = "❌ การลาประเภทนี้ต้องยื่นล่วงหน้าอย่างน้อย 2 วัน"; }
    }

    if (empty($error_message)) {
        $evidence_file = null;
        if (!empty($_FILES['evidence_file']['name'])) {
            $ext = strtolower(pathinfo($_FILES["evidence_file"]["name"], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'pdf'])) {
                $target_dir = "../uploads/evidence/";
                if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
                $new_filename = "ev_" . time() . "_" . $emp_id . "." . $ext;
                move_uploaded_file($_FILES["evidence_file"]["tmp_name"], $target_dir . $new_filename);
                $evidence_file = $new_filename;
            }
        }

        $sql_insert = "INSERT INTO leave_requests (emp_id, leave_type, start_date, end_date, reason, evidence_file, status, approver_id) 
                       VALUES (?, ?, ?, ?, ?, ?, 'Pending', ?)";
        $stmt_ins = $conn->prepare($sql_insert);
        $stmt_ins->bind_param("isssssi", $emp_id, $lt_key_name, $start_date, $end_date, $reason, $evidence_file, $approver_id);
        if ($stmt_ins->execute()) {
            $success_message = "✅ ส่งคำร้องขอลาเรียบร้อยแล้ว";
            // Refresh balance after success
            $stmt_lb->execute();
            $balances_result = $stmt_lb->get_result();
        } else { $error_message = "❌ เกิดข้อผิดพลาดทางระบบ"; }
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ยื่นคำร้องการลา | LALA MUKHA</title>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root { --primary: #182848; --secondary: #4b6cb7; --bg: #f5f7fb; --text: #2e2e2e; --success: #4caf50; --warning: #ffa000; --danger: #f44336; --border: #e0e6ed; }
        body { margin: 0; font-family: 'Prompt', sans-serif; background: var(--bg); display: flex; color: var(--text); min-height: 100vh; }
        .sidebar { width: 250px; background: linear-gradient(135deg, #4b6cb7 0%, #182848 100%); color: #fff; position: fixed; height: 100vh; display: flex; flex-direction: column; z-index: 20; }
        .sidebar-header { text-align: center; padding: 25px 15px; font-weight: 600; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .menu-item { display: flex; align-items: center; padding: 15px 25px; color: rgba(255,255,255,0.85); text-decoration: none; transition: 0.25s; }
        .menu-item:hover, .menu-item.active { background: rgba(255,255,255,0.2); color: #fff; border-left: 4px solid #fff; }
        .menu-item i { margin-right: 12px; width: 20px; text-align: center; }
        .main-content { flex-grow: 1; margin-left: 250px; padding: 30px; width: calc(100% - 250px); }
        .topbar { background: #fff; border-radius: 12px; padding: 12px 25px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 4px 10px rgba(0,0,0,0.05); margin-bottom: 25px; }
        .notif-badge-item { display: inline-flex; align-items: center; margin-right: 15px; font-size: 0.85em; font-weight: 500; text-decoration: none; color: var(--text); background: #f8f9fa; padding: 6px 12px; border-radius: 8px; }
        .count-tag { background: var(--danger); color: #fff; padding: 1px 7px; border-radius: 10px; font-size: 0.8em; margin-left: 5px; }
        .count-tag.wait { background: var(--warning); }
        .card { background: #fff; border-radius: 16px; padding: 30px; box-shadow: 0 6px 20px rgba(0,0,0,0.05); }
        .emp-info-banner { background: #f0f4f8; padding: 15px 20px; border-radius: 12px; margin-bottom: 25px; display: grid; grid-template-columns: repeat(3, 1fr); border-left: 5px solid var(--secondary); }
        .notice-box { background: #e3f2fd; border-radius: 10px; padding: 12px; margin-bottom: 20px; border-left: 5px solid var(--secondary); font-size: 0.9em; color: #0d47a1; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
        .form-group { display: flex; flex-direction: column; }
        .form-group.full { grid-column: 1 / span 2; }
        .form-control { padding: 12px 15px; border: 1px solid var(--border); border-radius: 10px; font-family: 'Prompt'; background: #fafafa; }
        .btn-submit { background: linear-gradient(135deg, #4b6cb7, #182848); color: white; border: none; padding: 15px; border-radius: 10px; cursor: pointer; width: 100%; font-size: 1em; font-weight: 600; margin-top: 10px; }
        .alert { padding: 15px; border-radius: 10px; margin-bottom: 20px; }
        .alert-success { background: #e8f5e9; color: #2e7d32; border-left: 5px solid var(--success); }
        .alert-danger { background: #ffebee; color: #c62828; border-left: 5px solid var(--danger); }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="sidebar-header">LALA MUKHA<br><span style="font-size: 0.8em; opacity: 0.8;">ระบบบริหารจัดการการลา</span></div>
    <div style="padding-top: 20px;">
        <a href="employee_dashboard.php" class="menu-item"><i class="fas fa-home"></i><span>Dashboard</span></a>
        <a href="apply_leave.php" class="menu-item active"><i class="fas fa-calendar-plus"></i><span>ยื่นคำร้องขอลา</span></a>
        <a href="leave_history.php" class="menu-item"><i class="fas fa-history"></i><span>ประวัติการลา</span></a>
        <a href="public_holidays.php" class="menu-item"><i class="fas fa-calendar-alt"></i><span>ปฏิทินวันหยุด</span></a>
    </div>
    <a href="logout.php" class="menu-item" style="margin-top:auto;"><i class="fas fa-sign-out-alt"></i><span> ออกจากระบบ</span></a>
</div>

<div class="main-content">
    <div class="topbar">
        <div style="display: flex; align-items: center;">
            <?php if ($count_pending > 0): ?>
                <a href="leave_history.php" class="notif-badge-item">
                    <i class="fas fa-hourglass-half" style="color: var(--warning);"></i> รออนุมัติ <span class="count-tag wait"><?= $count_pending ?></span>
                </a>
            <?php endif; ?>
            <?php if ($count_rejected > 0): ?>
                <a href="leave_history.php" class="notif-badge-item">
                    <i class="fas fa-exclamation-circle" style="color: var(--danger);"></i> ไม่นุมัติ <span class="count-tag"><?= $count_rejected ?></span>
                </a>
            <?php endif; ?>
        </div>
        <div class="user-info">
            <span>สวัสดี, <strong><?= $user_display_name ?></strong> <small>(<?= $emp_code ?>)</small></span>
            <i class="fas fa-user-circle" style="font-size: 1.8em; color: var(--primary); margin-left:10px; vertical-align: middle;"></i>
        </div>
    </div>

    <div class="card">
        <div style="font-size: 1.25em; color: var(--primary); margin-bottom: 20px;"><i class="fas fa-pen-to-square"></i> แบบฟอร์มขออนุมัติลา</div>

        <div class="emp-info-banner">
            <div><span style="font-size:0.75em; color:#777;">รหัสพนักงาน</span><br><strong><?= $emp_code ?></strong></div>
            <div><span style="font-size:0.75em; color:#777;">ชื่อ-นามสกุล</span><br><strong><?= $user_display_name ?></strong></div>
            <div><span style="font-size:0.75em; color:#777;">แผนก</span><br><strong><?= $dept_name ?></strong></div>
        </div>

        <?php if ($success_message): ?><div class="alert alert-success"><?= $success_message ?></div><?php endif; ?>
        <?php if ($error_message): ?><div class="alert alert-danger"><?= $error_message ?></div><?php endif; ?>

        <div id="notice_box" class="notice-box">
             <i class="fas fa-info-circle"></i> <span id="notice_text">กรุณาเลือกประเภทการลาเพื่อตรวจสอบเงื่อนไข</span>
        </div>

        <form method="POST" enctype="multipart/form-data">
            <div class="form-row">
                <div class="form-group full">
                    <label>ประเภทการลา <span style="color:red">*</span></label>
                    <select name="leave_type_id" id="leave_type_id" class="form-control" required>
                        <option value="">-- กรุณาเลือกประเภทการลา --</option>
                        <?php 
                        if ($balances_result && $balances_result->num_rows > 0):
                            $balances_result->data_seek(0);
                            while($row = $balances_result->fetch_assoc()): ?>
                                <option value="<?= $row['leave_type_id'] ?>" 
                                        data-name="<?= $row['leave_type_name'] ?>" 
                                        data-unit="<?= $row['leave_unit'] ?>">
                                    <?= htmlspecialchars($row['leave_type_display']) ?> 
                                    (คงเหลือ: <?= number_format($row['remaining'], 1) ?> <?= ($row['leave_unit'] == 'day' ? 'วัน' : 'ชม.') ?>)
                                </option>
                            <?php endwhile; 
                        endif; ?>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label id="start_label">วันที่เริ่มลา <span style="color:red">*</span></label>
                    <input type="date" name="start_date" id="start_date" class="form-control" required disabled>
                </div>
                <div class="form-group" id="end_date_group">
                    <label>วันที่สิ้นสุด <span style="color:red">*</span></label>
                    <input type="date" name="end_date" id="end_date" class="form-control" required disabled>
                </div>
                <div class="form-group" id="period_group" style="display:none;">
                    <label>ช่วงเวลาที่ลา <span style="color:red">*</span></label>
                    <select name="leave_period" class="form-control">
                        <option value="full">เต็มวัน (8 ชม.)</option>
                        <option value="morning">ครึ่งวันเช้า (4 ชม.)</option>
                        <option value="afternoon">ครึ่งวันบ่าย (4 ชม.)</option>
                    </select>
                </div>
            </div>

            <div class="form-group full" style="margin-bottom:20px;">
                <label>เหตุผลการลา <span style="color:red">*</span></label>
                <textarea name="reason" class="form-control" rows="3" placeholder="ระบุรายละเอียด..." required></textarea>
            </div>

            <div class="form-group full" style="margin-bottom:25px;">
                <label>แนบหลักฐาน (ถ้ามี)</label>
                <input type="file" name="evidence_file" class="form-control" accept=".jpg,.jpeg,.png,.pdf">
            </div>

            <button type="submit" class="btn-submit"><i class="fas fa-paper-plane"></i> ยื่นคำร้องขอลา</button>
        </form>
    </div>
</div>

<script>
    const today = '<?php echo $today; ?>';
    const minNormalDate = '<?php echo $min_leave_date_normal; ?>';
    const leaveTypeSelect = document.getElementById('leave_type_id');
    const startDateInput = document.getElementById('start_date');
    const endDateInput = document.getElementById('end_date');
    const noticeText = document.getElementById('notice_text');
    const endGroup = document.getElementById('end_date_group');
    const periodGroup = document.getElementById('period_group');
    const startLabel = document.getElementById('start_label');

    leaveTypeSelect.addEventListener('change', function() {
        const opt = this.options[this.selectedIndex];
        if(!opt.value) {
            startDateInput.disabled = true;
            endDateInput.disabled = true;
            return;
        }

        const name = opt.getAttribute('data-name');
        const unit = opt.getAttribute('data-unit');
        
        startDateInput.disabled = false;
        endDateInput.disabled = (unit === 'hour'); // ถ้าลาเป็นชั่วโมง ปิดช่องวันที่สิ้นสุด
        startDateInput.value = ""; 
        endDateInput.value = "";

        if (name === 'Sick Leave') {
            // ลาป่วย: เลือกได้แค่ วันปัจจุบัน หรือ ย้อนหลัง (ห้ามล่วงหน้า)
            startDateInput.max = today; 
            startDateInput.min = ""; 
            endDateInput.max = today;
            noticeText.innerHTML = "<strong>ลาป่วย:</strong> สามารถเลือกวันปัจจุบันหรือย้อนหลังได้เท่านั้น (ห้ามเลือกวันล่วงหน้า)";
        } else {
            // ลาอื่นๆ: ต้องล่วงหน้า 2 วัน
            startDateInput.min = minNormalDate;
            startDateInput.max = "";
            endDateInput.min = minNormalDate;
            endDateInput.max = "";
            noticeText.innerHTML = "<strong>เงื่อนไข:</strong> ต้องยื่นลาล่วงหน้าอย่างน้อย 2 วัน เริ่มลาได้ตั้งแต่วันที่ <strong>" + minNormalDate.split('-').reverse().join('/') + "</strong>";
        }

        // จัดการ UI สำหรับ OT (รายชั่วโมง)
        if (unit === 'hour') {
            periodGroup.style.display = 'flex';
            endGroup.style.display = 'none';
            endDateInput.required = false;
            startLabel.innerText = "วันที่ต้องการลา OT";
        } else {
            periodGroup.style.display = 'none';
            endGroup.style.display = 'flex';
            endDateInput.required = true;
            startLabel.innerText = "วันที่เริ่มลา";
        }
    });

    startDateInput.addEventListener('change', function() {
        endDateInput.min = this.value;
        if (endDateInput.value && endDateInput.value < this.value) {
            endDateInput.value = this.value;
        }
    });
</script>
</body>
</html>
</body>
</html>