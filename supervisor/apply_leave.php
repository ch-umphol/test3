<?php
session_start();
require_once '../conn.php'; 

// 1. ตรวจสอบสิทธิ์การเข้าถึง (Supervisor - role_id 3)
if (!isset($_SESSION['loggedin']) || $_SESSION['user_role'] !== 'supervisor') {
    header("location: ../login.php");
    exit;
}

$emp_id = $_SESSION['emp_id']; 
$user_role = $_SESSION['user_role'];
$error_message = '';
$success_message = '';
$current_year = date('Y');
$today = date('Y-m-d');
$min_leave_date_normal = date('Y-m-d', strtotime('+2 days')); 

// 2. ดึงข้อมูลผู้ใช้งานที่ Login และข้อมูลแผนก
$sql_user = "SELECT E.emp_code, E.first_name, E.last_name, E.dept_id, D.manager_id, D.dept_name 
             FROM employees E 
             LEFT JOIN departments D ON E.dept_id = D.dept_id 
             WHERE E.emp_id = ?";
$stmt_user = $conn->prepare($sql_user);
$stmt_user->bind_param("i", $emp_id);
$stmt_user->execute();
$row_user = $stmt_user->get_result()->fetch_assoc();

$emp_code = htmlspecialchars($row_user['emp_code'] ?? '');
$user_display_name = htmlspecialchars(($row_user['first_name'] ?? '') . ' ' . ($row_user['last_name'] ?? ''));
$dept_name = htmlspecialchars($row_user['dept_name'] ?? 'ไม่ระบุสังกัด');
$approver_id = $row_user['manager_id']; // Supervisor ส่งให้ Manager อนุมัติ

// 3. ดึงรายการประเภทการลาตามยอดคงเหลือ (ปีปัจจุบัน)
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

// 4. ประมวลผลการยื่นคำร้อง (POST)
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

    // ตรวจสอบเงื่อนไขวันที่
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

        // หากเป็นลา OT (รายชั่วโมง)
        if ($lt_unit === 'hour') {
            $end_date = $start_date;
            $period_label = ($leave_period == 'morning') ? 'ครึ่งวันเช้า (4 ชม.)' : (($leave_period == 'afternoon') ? 'ครึ่งวันบ่าย (4 ชม.)' : 'เต็มวัน (8 ชม.)');
            $reason = "[$period_label] " . $reason;
        }

        $sql_insert = "INSERT INTO leave_requests (emp_id, leave_type, start_date, end_date, reason, evidence_file, status, approver_id) 
                       VALUES (?, ?, ?, ?, ?, ?, 'Pending', ?)";
        $stmt_ins = $conn->prepare($sql_insert);
        $stmt_ins->bind_param("isssssi", $emp_id, $lt_key_name, $start_date, $end_date, $reason, $evidence_file, $approver_id);
        
        if ($stmt_ins->execute()) {
            $success_message = "✅ ส่งคำร้องขอลาเรียบร้อยแล้ว";
            $stmt_lb->execute();
            $balances_result = $stmt_lb->get_result();
        } else {
            $error_message = "❌ เกิดข้อผิดพลาดทางระบบ";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ยื่นคำร้องการลา | LALA MUKHA</title>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary: #004030; --secondary: #66BB6A; --bg: #f5f7fb; --text: #2e2e2e; --danger: #dc3545; }
        body { margin: 0; font-family: 'Prompt', sans-serif; background: var(--bg); display: flex; color: var(--text); }
        .sidebar { width: 250px; background: linear-gradient(180deg, var(--primary), #2e7d32); color: #fff; position: fixed; height: 100%; display: flex; flex-direction: column; box-shadow: 3px 0 10px rgba(0,0,0,0.15); }
        .sidebar-header { text-align: center; padding: 25px 15px; font-weight: 600; border-bottom: 1px solid rgba(255,255,255,0.2); }
        .menu-item { display: flex; align-items: center; padding: 15px 25px; color: rgba(255,255,255,0.85); text-decoration: none; transition: 0.25s; }
        .menu-item:hover, .menu-item.active { background: rgba(255,255,255,0.2); color: #fff; font-weight: 600; }
        .menu-item i { margin-right: 12px; }
        .logout { margin-top: auto; text-align: center; padding: 15px; background: rgba(255,255,255,0.15); color: #fff; text-decoration: none; }
        .main-content { flex-grow: 1; margin-left: 250px; padding: 30px; }
        .header { background: #fff; border-radius: 12px; padding: 15px 25px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 4px 10px rgba(0,0,0,0.05); margin-bottom: 25px; }
        .card { background: #fff; border-radius: 16px; padding: 30px; box-shadow: 0 6px 20px rgba(0,0,0,0.05); }
        .info-banner { background: #f0f4f8; padding: 15px 20px; border-radius: 12px; margin-bottom: 25px; display: grid; grid-template-columns: repeat(3, 1fr); border-left: 5px solid var(--secondary); }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
        .form-group { display: flex; flex-direction: column; }
        .form-group label { margin-bottom: 8px; font-weight: 600; color: var(--primary); }
        .form-control { padding: 12px; border: 1px solid #ddd; border-radius: 10px; font-family: 'Prompt'; background: #fafafa; }
        .btn-submit { background: linear-gradient(135deg, var(--primary), #2e7d32); color: #fff; border: none; padding: 15px; border-radius: 10px; cursor: pointer; width: 100%; font-size: 16px; font-weight: 600; transition: 0.3s; margin-top: 10px; }
        .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        .alert { padding: 15px; border-radius: 10px; margin-bottom: 20px; border-left: 5px solid; }
        .alert-success { background: #e8f5e9; color: #2e7d32; border-color: #66bb6a; }
        .alert-danger { background: #ffebee; color: #c62828; border-color: #ef5350; }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="sidebar-header">LALA MUKHA<br>ระบบจัดการผู้ใช้งาน</div>
    <a href="supervisor_dashboard.php" class="menu-item"><i class="fas fa-tachometer-alt"></i> ภาพรวมระบบ</a>
    <a href="manage_leave.php" class="menu-item"><i class="fas fa-clipboard-list"></i> จัดการการลา</a>
    <a href="apply_leave.php" class="menu-item active"><i class="fas fa-calendar-plus"></i> ยื่นคำร้องการลา</a>
    <a href="manage_employees.php" class="menu-item"><i class="fas fa-users"></i> ข้อมูลพนักงาน</a>
    <a href="public_holidays.php" class="menu-item"><i class="fas fa-calendar-alt"></i><span>ปฏิทินวันหยุด</span></a>
    <a href="leave_history.php" class="menu-item"><i class="fas fa-history"></i><span>ประวัติการลาส่วนตัว</span></a>
    <a href="logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> ออกจากระบบ</a>
</div>

<div class="main-content">
    <div class="header">
        <h3 style="margin:0;"><i class="fas fa-pen-nib"></i> ยื่นคำร้องขอลา</h3>
        <div class="user-profile">
            <span>สวัสดี, <strong><?php echo $user_display_name; ?></strong></span>
            <i class="fas fa-user-circle" style="font-size: 1.8em; color: var(--primary); margin-left:10px; vertical-align: middle;"></i>
        </div>
    </div>

    <div class="card">
        <div class="info-banner">
            <div><span style="font-size:0.8em; color:#777;">รหัสพนักงาน:</span><br><strong><?php echo $emp_code; ?></strong></div>
            <div><span style="font-size:0.8em; color:#777;">ชื่อ-นามสกุล:</span><br><strong><?php echo $user_display_name; ?></strong></div>
            <div><span style="font-size:0.8em; color:#777;">แผนก:</span><br><strong><?php echo $dept_name; ?></strong></div>
        </div>

        <?php if ($success_message): ?><div class="alert alert-success"><?php echo $success_message; ?></div><?php endif; ?>
        <?php if ($error_message): ?><div class="alert alert-danger"><?php echo $error_message; ?></div><?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <div class="form-group" style="margin-bottom:20px;">
                <label>ประเภทการลา <span style="color:red">*</span></label>
                <select name="leave_type_id" id="leave_type_id" class="form-control" required>
                    <option value="">-- กรุณาเลือกประเภทการลา --</option>
                    <?php while($row = $balances_result->fetch_assoc()): ?>
                        <option value="<?php echo $row['leave_type_id']; ?>" 
                                data-name="<?php echo $row['leave_type_name']; ?>" 
                                data-unit="<?php echo $row['leave_unit']; ?>">
                            <?php echo htmlspecialchars($row['leave_type_display']); ?> 
                            (คงเหลือ: <?php echo number_format($row['remaining'], 1); ?> <?php echo ($row['leave_unit'] == 'day' ? 'วัน' : 'ชม.'); ?>)
                        </option>
                    <?php endwhile; ?>
                </select>
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

            <div class="form-group" style="margin-bottom:20px;">
                <label>เหตุผลการลา <span style="color:red">*</span></label>
                <textarea name="reason" class="form-control" rows="3" placeholder="ระบุรายละเอียด..." required></textarea>
            </div>

            <div class="form-group" style="margin-bottom:25px;">
                <label>แนบหลักฐาน (ถ้ามี)</label>
                <input type="file" name="evidence_file" class="form-control" accept=".jpg,.jpeg,.png,.pdf">
            </div>

            <button type="submit" class="btn-submit"><i class="fas fa-paper-plane"></i> ส่งคำร้องขอลา</button>
        </form>
    </div>
</div>

<script>
    const today = '<?php echo $today; ?>';
    const minNormalDate = '<?php echo $min_leave_date_normal; ?>';
    const leaveTypeSelect = document.getElementById('leave_type_id');
    const startDateInput = document.getElementById('start_date');
    const endDateInput = document.getElementById('end_date');
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
        endDateInput.disabled = (unit === 'hour'); 
        startDateInput.value = ""; 
        endDateInput.value = "";
        if (name === 'Sick Leave') {
            startDateInput.max = today; 
            startDateInput.min = ""; 
            endDateInput.max = today;
        } else {
            startDateInput.min = minNormalDate;
            startDateInput.max = "";
            endDateInput.min = minNormalDate;
            endDateInput.max = "";
        }
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