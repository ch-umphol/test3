<?php
session_start();
require_once '../conn.php'; 

// 1. ตรวจสอบสิทธิ์การเข้าถึง
if (!isset($_SESSION['loggedin']) || !in_array($_SESSION['user_role'], ['manager', 'admin'])) {
    header("location: ../login.php");
    exit;
}

// ตรวจสอบ emp_id
if (!isset($_GET['emp_id']) || !is_numeric($_GET['emp_id'])) {
    header("location: manage_employees.php");
    exit;
}

$target_emp_id = (int)$_GET['emp_id'];
$emp_id_session = $_SESSION['emp_id'];
$user_name_session = $_SESSION['username'];
$user_role = $_SESSION['user_role'];

// ดึงชื่อจริง Manager (Header)
$sql_user = "SELECT first_name, last_name FROM employees WHERE emp_id = $emp_id_session";
$res_user = $conn->query($sql_user);
$user_display_name = ($row_u = $res_user->fetch_assoc()) ? $row_u['first_name'].' '.$row_u['last_name'] : $user_name_session;

$status_message = $_SESSION['status_message'] ?? '';
unset($_SESSION['status_message']);

// ------------------ HANDLING CRUD ACTIONS ------------------

// ADD Balance
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] === 'add') {
    $leave_type_id = (int)$_POST['leave_type_id'];
    $allowed_days = (float)$_POST['allowed_days']; 
    $year = date('Y');

    $stmt = $conn->prepare("SELECT balance_id FROM employee_leave_balances WHERE emp_id=? AND leave_type_id=? AND year=?");
    $stmt->bind_param("iii", $target_emp_id, $leave_type_id, $year);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $_SESSION['status_message'] = "⚠️ พนักงานท่านนี้มีสิทธิ์การลานี้ในปี $year อยู่แล้ว";
    } else {
        $stmt2 = $conn->prepare("INSERT INTO employee_leave_balances (emp_id, leave_type_id, year, allowed_days, used_days) VALUES (?, ?, ?, ?, 0)");
        $stmt2->bind_param("iiid", $target_emp_id, $leave_type_id, $year, $allowed_days);
        if ($stmt2->execute()) $_SESSION['status_message'] = "✅ เพิ่มสิทธิ์การลาสำเร็จ";
        else $_SESSION['status_message'] = "❌ เกิดข้อผิดพลาด: ".$stmt2->error;
    }
    header("location: manage_balance.php?emp_id=$target_emp_id");
    exit;
}

// UPDATE Balance (แก้ไขโควตาหลัก)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] === 'edit') {
    $balance_id = (int)$_POST['balance_id'];
    $new_allowed = (float)$_POST['new_allowed_days'];
    $stmt = $conn->prepare("UPDATE employee_leave_balances SET allowed_days=? WHERE balance_id=?");
    $stmt->bind_param("di", $new_allowed, $balance_id);
    if ($stmt->execute()) $_SESSION['status_message'] = "✅ ปรับปรุงสิทธิ์การลาสำเร็จ";
    header("location: manage_balance.php?emp_id=$target_emp_id");
    exit;
}

// REFUND Used Days (คืนยอดที่ใช้ไป)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] === 'refund') {
    $balance_id = (int)$_POST['balance_id'];
    $refund_amount = (float)$_POST['refund_amount'];

    // ตรวจสอบยอด used_days ปัจจุบัน
    $res = $conn->query("SELECT used_days FROM employee_leave_balances WHERE balance_id = $balance_id");
    $current_used = (float)$res->fetch_assoc()['used_days'];

    if ($refund_amount > $current_used) {
        $_SESSION['status_message'] = "⚠️ ไม่สามารถคืนวันลาเกินจำนวนที่ใช้ไปจริงได้";
    } else {
        $new_used = $current_used - $refund_amount;
        $stmt = $conn->prepare("UPDATE employee_leave_balances SET used_days=? WHERE balance_id=?");
        $stmt->bind_param("di", $new_used, $balance_id);
        if ($stmt->execute()) $_SESSION['status_message'] = "✅ คืนสิทธิ์วันลาสำเร็จจำนวน $refund_amount รายการ";
    }
    header("location: manage_balance.php?emp_id=$target_emp_id");
    exit;
}

// DELETE Balance
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['balance_id'])) {
    $bid = (int)$_GET['balance_id'];
    $conn->query("DELETE FROM employee_leave_balances WHERE balance_id = $bid");
    $_SESSION['status_message'] = "✅ ลบรายการสิทธิ์วันลาสำเร็จ";
    header("location: manage_balance.php?emp_id=$target_emp_id");
    exit;
}

// ------------------ FETCH DATA ------------------
$emp = $conn->query("SELECT first_name, last_name, emp_code FROM employees WHERE emp_id = $target_emp_id")->fetch_assoc();
$sql_balances = "SELECT B.*, LT.leave_type_display, LT.leave_unit FROM employee_leave_balances B JOIN leave_types LT ON B.leave_type_id = LT.leave_type_id WHERE B.emp_id = $target_emp_id ORDER BY B.year DESC";
$balance_result = $conn->query($sql_balances);
$types = $conn->query("SELECT * FROM leave_types ORDER BY leave_type_id ASC");
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>จัดการสิทธิ์วันลา | LALA MUKHA</title>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {--primary:#004030;--secondary:#66BB6A;--accent:#81C784;--bg:#f5f7fb;--text:#2e2e2e;}
        body{margin:0;font-family:'Prompt',sans-serif;display:flex;background:var(--bg);color:var(--text);}
        .sidebar{width:250px;background:linear-gradient(180deg,var(--primary),#2e7d32);color:#fff;position:fixed;height:100%;display:flex;flex-direction:column;box-shadow:3px 0 10px rgba(0,0,0,0.1);}
        .sidebar-header{padding:25px;text-align:center;font-weight:600;border-bottom:1px solid rgba(255,255,255,0.2);}
        .menu-item{display:flex;align-items:center;padding:15px 25px;color:rgba(255,255,255,0.85);text-decoration:none;transition:0.2s;}
        .menu-item:hover, .menu-item.active{background:rgba(255,255,255,0.15);color:#fff;font-weight:600;}
        .menu-item i{margin-right:12px; width:20px; text-align:center;}
        .main-content{flex-grow:1;margin-left:250px;padding:30px;}
        .header{display:flex;justify-content:space-between;align-items:center;background:#fff;padding:15px 25px;border-radius:12px;box-shadow:0 4px 10px rgba(0,0,0,0.05);margin-bottom:25px;}
        .card{background:#fff;border-radius:16px;padding:25px;box-shadow:0 6px 20px rgba(0,0,0,0.05);margin-bottom:25px;}
        .card h1{margin-top:0;color:var(--primary); font-size:1.5em; display:flex; align-items:center; gap:10px;}
        .data-table{width:100%;border-collapse:collapse;margin-top:15px;}
        .data-table th, .data-table td{padding:12px 15px; border-bottom:1px solid #eee; text-align:left;}
        .data-table th{background:#f8f9fa; color:var(--primary); font-weight:600;}
        .form-inline { display: flex; gap: 15px; flex-wrap: wrap; margin-top: 20px; background: #f9f9f9; padding: 20px; border-radius: 12px; border: 1px dashed #ccc; }
        .form-group { flex: 1; min-width: 150px; display: flex; flex-direction: column; gap: 5px; }
        .form-group label { font-size: 0.9em; color: #555; font-weight: 500; }
        .form-group input, .form-group select { padding: 12px; border: 1px solid #ddd; border-radius: 8px; font-family: 'Prompt'; font-size: 1em; }
        .btn-add { background: var(--primary); color: white; border: none; padding: 0 30px; border-radius: 8px; cursor: pointer; font-weight: 600; height: 48px; align-self: flex-end; transition: 0.3s; }
        .action-btn { width: 32px; height: 32px; border-radius: 6px; display: inline-flex; align-items: center; justify-content: center; text-decoration: none; font-size: 0.9em; border: none; cursor: pointer; }
        .btn-edit { background: #fff3e0; color: #ef6c00; margin-right: 5px; }
        .btn-refund { background: #e8f5e9; color: #2e7d32; margin-right: 5px; }
        .btn-delete { background: #ffebee; color: #c62828; }
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 500; background: #e8f5e9; color: #2e7d32; border-left: 5px solid #4caf50; }
        .unit-badge { padding: 3px 8px; border-radius: 6px; font-size: 0.75em; font-weight: 600; text-transform: uppercase; margin-left: 5px; }
        .unit-day { background: #e3f2fd; color: #1976d2; }
        .unit-hour { background: #fff3e0; color: #ef6c00; }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="sidebar-header">LALA MUKHA<br>ระบบจัดการพนักงาน</div>
    <div style="flex-grow:1; padding-top:10px;">
        <a href="manager_dashboard.php" class="menu-item"><i class="fas fa-tachometer-alt"></i> ภาพรวมระบบ</a>
        <a href="manage_leave.php" class="menu-item"><i class="fas fa-clipboard-list"></i> จัดการการลา</a>
        <a href="manage_employees.php" class="menu-item active"><i class="fas fa-users"></i> ข้อมูลพนักงาน</a>
        <a href="manage_leavetype.php" class="menu-item"><i class="fas fa-list"></i> ประเภทการลา</a>
        <a href="manage_department.php" class="menu-item"><i class="fas fa-building"></i> แผนก</a>
    </div>
    <a href="logout.php" style="padding:15px; background:#388e3c; color:#fff; text-decoration:none; text-align:center;"><i class="fas fa-sign-out-alt"></i> ออกจากระบบ</a>
</div>

<div class="main-content">
    <div class="header">
        <div style="display:flex; align-items:center; gap:15px;">
            <a href="manage_employees.php" style="color:var(--primary); text-decoration:none;"><i class="fas fa-arrow-left"></i> ย้อนกลับ</a>
            <span style="color:#ccc;">|</span>
            <span style="font-weight:600; color:var(--primary);">จัดการยอดสิทธิ์การลา</span>
        </div>
        <div style="display:flex; align-items:center; gap:10px;">
            <span><?= htmlspecialchars($user_display_name) ?></span>
            <i class="fas fa-user-circle" style="font-size:1.8em; color:var(--primary);"></i>
        </div>
    </div>

    <div class="card">
        <h1><i class="fas fa-calendar-plus"></i> เพิ่มสิทธิ์การลาประจำปี <?= date('Y') ?></h1>
        <p style="color:#666;">สำหรับพนักงาน: <strong><?= htmlspecialchars($emp['first_name'].' '.$emp['last_name']) ?></strong> (รหัส: <?= $emp['emp_code'] ?>)</p>
        <form method="POST" class="form-inline">
            <input type="hidden" name="action" value="add">
            <div class="form-group">
                <label>เลือกประเภทการลา</label>
                <select name="leave_type_id" id="lt_select" required onchange="updateMaxDays(this)">
                    <option value="">-- โปรดเลือก --</option>
                    <?php $types->data_seek(0); while($t = $types->fetch_assoc()): ?>
                        <option value="<?= $t['leave_type_id'] ?>" data-max="<?= $t['max_days'] ?>"><?= htmlspecialchars($t['leave_type_display']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group">
                <label>จำนวนวันที่ให้สิทธิ์</label>
                <input type="number" step="0.5" name="allowed_days" id="allowed_days_input" placeholder="0.0" required>
            </div>
            <button type="submit" class="btn-add">มอบสิทธิ์การลา</button>
        </form>
    </div>

    <div class="card">
        <h1><i class="fas fa-wallet"></i> รายการสิทธิ์วันคงเหลือ</h1>
        <?php if($status_message): ?><div class="alert"><?= $status_message ?></div><?php endif; ?>
        
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 50px;">ลำดับ</th>
                    <th>ปี</th>
                    <th>ประเภทการลา</th>
                    <th>ได้รับสิทธิ์</th>
                    <th>ใช้ไปแล้ว</th>
                    <th>คงเหลือ</th>
                    <th style="width: 120px;">จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $idx = 1;
                if ($balance_result->num_rows > 0):
                    while($row = $balance_result->fetch_assoc()): 
                        $remain = $row['allowed_days'] - $row['used_days'];
                        $u_class = ($row['leave_unit'] == 'day') ? 'unit-day' : 'unit-hour';
                        $u_text = ($row['leave_unit'] == 'day') ? 'วัน' : 'ชม.';
                ?>
                <tr>
                    <td><?= $idx++ ?></td>
                    <td><?= $row['year'] ?></td>
                    <td>
                        <strong><?= htmlspecialchars($row['leave_type_display']) ?></strong>
                        <span class="unit-badge <?= $u_class ?>"><?= $u_text ?></span>
                    </td>
                    <td><?= number_format($row['allowed_days'], 1) ?></td>
                    <td><?= number_format($row['used_days'], 1) ?></td>
                    <td><strong style="color:var(--primary);"><?= number_format($remain, 1) ?></strong></td>
                    <td>
                        <button type="button" class="action-btn btn-edit" onclick="toggleEdit(<?= $row['balance_id'] ?>)" title="แก้ไขโควตา">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button type="button" class="action-btn btn-refund" onclick="toggleRefund(<?= $row['balance_id'] ?>)" title="คืนวันลา">
                            <i class="fas fa-undo"></i>
                        </button>
                        <a href="?emp_id=<?= $target_emp_id ?>&action=delete&balance_id=<?= $row['balance_id'] ?>" 
                           class="action-btn btn-delete" onclick="return confirm('ต้องการลบสิทธิ์การลานี้หรือไม่?')">
                            <i class="fas fa-trash"></i>
                        </a>
                    </td>
                </tr>
                <tr id="edit-row-<?= $row['balance_id'] ?>" style="display:none; background:#fdfdfd;">
                    <td colspan="7">
                        <form method="POST" style="display:flex; align-items:center; gap:15px; padding:10px;">
                            <input type="hidden" name="action" value="edit">
                            <input type="hidden" name="balance_id" value="<?= $row['balance_id'] ?>">
                            <span>แก้ไขโควตาใหม่:</span>
                            <input type="number" step="0.5" name="new_allowed_days" value="<?= $row['allowed_days'] ?>" style="width:100px; padding:5px;">
                            <button type="submit" class="btn-add" style="height:35px; padding:0 15px; background:#fb8c00;">บันทึก</button>
                            <button type="button" class="btn-add" style="height:35px; padding:0 15px; background:#999;" onclick="toggleEdit(<?= $row['balance_id'] ?>)">ยกเลิก</button>
                        </form>
                    </td>
                </tr>
                <tr id="refund-row-<?= $row['balance_id'] ?>" style="display:none; background:#f1f8e9;">
                    <td colspan="7">
                        <form method="POST" style="display:flex; align-items:center; gap:15px; padding:10px;">
                            <input type="hidden" name="action" value="refund">
                            <input type="hidden" name="balance_id" value="<?= $row['balance_id'] ?>">
                            <span style="color:#2e7d32;">จำนวนที่ต้องการคืน (<?= $u_text ?>):</span>
                            <input type="number" step="0.1" name="refund_amount" value="<?= $row['used_days'] ?>" max="<?= $row['used_days'] ?>" style="width:100px; padding:5px;">
                            <button type="submit" class="btn-add" style="height:35px; padding:0 15px; background:#4caf50;">ยืนยันคืนวัน</button>
                            <button type="button" class="btn-add" style="height:35px; padding:0 15px; background:#999;" onclick="toggleRefund(<?= $row['balance_id'] ?>)">ยกเลิก</button>
                        </form>
                    </td>
                </tr>
                <?php endwhile; else: ?>
                    <tr><td colspan="7" style="text-align:center; color:#999;">ยังไม่ได้กำหนดสิทธิ์วันลาให้พนักงานท่านนี้</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function toggleEdit(id) {
    const row = document.getElementById('edit-row-' + id);
    const refundRow = document.getElementById('refund-row-' + id);
    if(refundRow) refundRow.style.display = 'none';
    row.style.display = (row.style.display === 'none') ? 'table-row' : 'none';
}

function toggleRefund(id) {
    const row = document.getElementById('refund-row-' + id);
    const editRow = document.getElementById('edit-row-' + id);
    if(editRow) editRow.style.display = 'none';
    row.style.display = (row.style.display === 'none') ? 'table-row' : 'none';
}

function updateMaxDays(select) {
    const selected = select.options[select.selectedIndex];
    const max = selected.getAttribute('data-max');
    const input = document.getElementById('allowed_days_input');
    input.value = max ? max : '';
}
</script>
</body>
</html>