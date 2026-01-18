<?php
session_start();
require_once '../conn.php'; 

// 1. ตรวจสอบสิทธิ์การเข้าถึง
if (!isset($_SESSION['loggedin']) || !in_array($_SESSION['user_role'], ['manager', 'admin'])) {
    header("location: ../login.php");
    exit;
}

$emp_id = $_SESSION['emp_id'];
$user_name = $_SESSION['username'];
$user_role = $_SESSION['user_role'];

// ดึงชื่อจริง Manager
$sql_user = "SELECT first_name, last_name FROM employees WHERE emp_id = $emp_id";
$res_user = $conn->query($sql_user);
$user_display_name = ($row_u = $res_user->fetch_assoc()) ? $row_u['first_name'].' '.$row_u['last_name'] : $user_name;

$status_message = $_SESSION['status_message'] ?? '';
unset($_SESSION['status_message']);

// ------------------ HANDLING ACTIONS ------------------

// ลบประเภทการลา
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    if ($conn->query("DELETE FROM leave_types WHERE leave_type_id = $id")) {
        $_SESSION['status_message'] = "✅ ลบประเภทการลาสำเร็จ";
    } else {
        $_SESSION['status_message'] = "❌ ไม่สามารถลบได้เนื่องจากมีการใช้งานอยู่ในระบบ";
    }
    header("location: manage_leavetype.php");
    exit;
}

// เพิ่มหรือแก้ไขประเภทการลา
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_leave_type'])) {
    $name_en = $conn->real_escape_string($_POST['leave_type_name']);
    $name_th = $conn->real_escape_string($_POST['leave_type_display']);
    $max_days = (int)$_POST['max_days'];
    $leave_unit = $conn->real_escape_string($_POST['leave_unit']);
    $edit_id = isset($_POST['edit_id']) ? (int)$_POST['edit_id'] : 0;

    if ($edit_id > 0) {
        $sql = "UPDATE leave_types SET leave_type_name='$name_en', leave_type_display='$name_th', max_days=$max_days, leave_unit='$leave_unit' WHERE leave_type_id=$edit_id";
        $msg = "✅ อัปเดตประเภทการลาสำเร็จ";
    } else {
        $sql = "INSERT INTO leave_types (leave_type_name, leave_type_display, max_days, leave_unit) VALUES ('$name_en', '$name_th', $max_days, '$leave_unit')";
        $msg = "✅ เพิ่มประเภทการลาสำเร็จ";
    }

    if ($conn->query($sql)) {
        $_SESSION['status_message'] = $msg;
    } else {
        $_SESSION['status_message'] = "❌ เกิดข้อผิดพลาด: " . $conn->error;
    }
    header("location: manage_leavetype.php");
    exit;
}

// ------------------ FETCH DATA ------------------
$sql_types = "SELECT * FROM leave_types ORDER BY leave_type_id ASC";
$result_types = $conn->query($sql_types);
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>จัดการประเภทการลา | LALA MUKHA</title>
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
        .btn-add { background: var(--primary); color: white; border: none; padding: 0 30px; border-radius: 8px; cursor: pointer; font-weight: 600; height: 48px; align-self: flex-end; transition: 0.3s; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; }
        .action-btn { width: 32px; height: 32px; border-radius: 6px; display: inline-flex; align-items: center; justify-content: center; text-decoration: none; font-size: 0.9em; transition: 0.2s; border: none; cursor: pointer; }
        .btn-edit { background: #fff3e0; color: #ef6c00; margin-right: 5px; }
        .btn-delete { background: #ffebee; color: #c62828; }
        .unit-badge { padding: 4px 10px; border-radius: 6px; font-size: 0.8em; font-weight: 600; }
        .unit-day { background: #e3f2fd; color: #1976d2; }
        .unit-hour { background: #fff3e0; color: #ef6c00; }
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 500; background: #e8f5e9; color: #2e7d32; border-left: 5px solid #4caf50; }
        
        /* สไตล์เพิ่มเติมสำหรับส่วนเชื่อมโยงวันหยุด */
        .holiday-link-card { background: linear-gradient(90deg, #e8f5e9 0%, #ffffff 100%); border-left: 5px solid #2e7d32; display: flex; justify-content: space-between; align-items: center; padding: 20px 25px; border-radius: 16px; margin-bottom: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        .holiday-info h3 { margin: 0; color: #1b5e20; font-size: 1.1em; }
        .holiday-info p { margin: 5px 0 0 0; color: #666; font-size: 0.9em; }
        .badge-link { background: #fff3e0; color: #e65100; font-size: 0.75em; padding: 2px 8px; border-radius: 4px; font-weight: 500; margin-left: 5px; border: 1px solid #ffe0b2; }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="sidebar-header">LALA MUKHA<br>ระบบจัดการพนักงาน</div>
    <div style="flex-grow:1; padding-top:10px;">
        <a href="manager_dashboard.php" class="menu-item"><i class="fas fa-tachometer-alt"></i> ภาพรวมระบบ</a>
        <a href="manage_leave.php" class="menu-item"><i class="fas fa-clipboard-list"></i> จัดการการลา</a>
        <a href="manage_employees.php" class="menu-item"><i class="fas fa-users"></i> ข้อมูลพนักงาน</a>
        <a href="manage_leavetype.php" class="menu-item active"><i class="fas fa-list"></i> ประเภทการลา</a>
        <a href="manage_department.php" class="menu-item"><i class="fas fa-building"></i> แผนก</a>
        <a href="manage_holidays.php" class="menu-item">
                <i class="fas fa-calendar-alt"></i> จัดการวันหยุดพิเศษ
            </a>
            <a href="report.php" class="menu-item">
            <i class="fas fa-file-pdf"></i> ออกรายงาน
        </a>
    </div>
    <a href="logout.php" style="padding:15px; background:#388e3c; color:#fff; text-decoration:none; text-align:center;"><i class="fas fa-sign-out-alt"></i> ออกจากระบบ</a>
</div>

<div class="main-content">
    <div class="header">
        <div style="font-weight:600; color:var(--primary);">จัดการประเภทการลา</div>
        <div style="display:flex; align-items:center; gap:10px;">
            <span><?= htmlspecialchars($user_display_name) ?></span>
            <i class="fas fa-user-circle" style="font-size:1.8em; color:var(--primary);"></i>
        </div>
    </div>

    <div class="holiday-link-card">
        <div class="holiday-info">
            <h3><i class="fas fa-calendar-star"></i> ตั้งค่าวันหยุดนักขัตฤกษ์</h3>
            <p>เชื่อมโยงประเภทการลากับปฏิทินวันหยุด เพื่อให้พนักงานใช้สิทธิ์หยุดพิเศษได้</p>
        </div>
        <a href="manage_holidays.php" class="btn-add" style="background: #2e7d32;">
            <i class="fas fa-calendar-alt" style="margin-right: 8px;"></i> จัดการวันหยุด
        </a>
    </div>

    <div class="card">
        <h1 id="form-title"><i class="fas fa-plus-circle"></i> เพิ่มประเภทการลาใหม่</h1>
        <form method="POST" class="form-inline" id="leave-form">
            <input type="hidden" name="edit_id" id="edit_id" value="0">
            <div class="form-group">
                <label>ชื่อ (EN)</label>
                <input type="text" name="leave_type_name" id="leave_name_en" placeholder="เช่น Sick Leave" required>
            </div>
            <div class="form-group">
                <label>ชื่อ (TH)</label>
                <input type="text" name="leave_type_display" id="leave_name_th" placeholder="เช่น ลาป่วย" required>
            </div>
            <div class="form-group">
                <label>หน่วยการลา</label>
                <select name="leave_unit" id="leave_unit" required>
                    <option value="day">เป็นวัน (8 ชม. = 1 วัน)</option>
                    <option value="hour">เป็นชั่วโมง</option>
                </select>
            </div>
            <div class="form-group">
                <label>สิทธิ์สูงสุด</label>
                <input type="number" name="max_days" id="max_days" placeholder="ระบุจำนวน" required min="1">
            </div>
            <button type="submit" name="save_leave_type" class="btn-add">บันทึกข้อมูล</button>
            <button type="button" onclick="resetForm()" id="cancel-btn" style="display:none; background:#eee; color:#333;" class="btn-add">ยกเลิก</button>
        </form>
    </div>

    <div class="card">
        <h1><i class="fas fa-table"></i> รายการประเภทการลา</h1>
        <?php if($status_message): ?><div class="alert"><?= $status_message ?></div><?php endif; ?>
        
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 80px;">ลำดับ</th>
                    <th>ประเภทการลา</th>
                    <th>หน่วยการเก็บ</th>
                    <th>สิทธิ์สูงสุด</th>
                    <th style="width: 120px;">จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $count = 1;
                if ($result_types && $result_types->num_rows > 0):
                    while($row = $result_types->fetch_assoc()): 
                        $unit_class = ($row['leave_unit'] == 'day') ? 'unit-day' : 'unit-hour';
                        $unit_text = ($row['leave_unit'] == 'day') ? 'วัน' : 'ชั่วโมง';
                        
                        // ตรวจสอบว่าเป็นประเภทวันหยุดหรือไม่
                        $is_holiday = (stripos($row['leave_type_name'], 'holiday') !== false || stripos($row['leave_type_display'], 'วันหยุด') !== false);
                ?>
                <tr>
                    <td><strong><?= $count++ ?></strong></td>
                    <td>
                        <strong><?= htmlspecialchars($row['leave_type_display']) ?></strong> 
                        <?php if($is_holiday): ?>
                            <span class="badge-link"><i class="fas fa-link"></i> เชื่อมปฏิทิน</span>
                        <?php endif; ?>
                        <br><small style="color: #888;"><?= htmlspecialchars($row['leave_type_name']) ?></small>
                    </td>
                    <td><span class="unit-badge <?= $unit_class ?>"><?= $unit_text ?></span></td>
                    <td><?= $row['max_days'] ?> <?= $unit_text ?></td>
                    <td>
                        <button type="button" class="action-btn btn-edit" onclick='editType(<?= json_encode($row) ?>)' title="แก้ไข">
                            <i class="fas fa-edit"></i>
                        </button>
                        <a href="?action=delete&id=<?= $row['leave_type_id'] ?>" class="action-btn btn-delete" onclick="return confirm('ยืนยันการลบ?')" title="ลบ">
                            <i class="fas fa-trash"></i>
                        </a>
                    </td>
                </tr>
                <?php endwhile; else: ?>
                    <tr><td colspan="5" style="text-align:center; color:#999;">ไม่พบข้อมูลประเภทการลา</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function editType(data) {
    document.getElementById('form-title').innerHTML = '<i class="fas fa-edit"></i> แก้ไขประเภทการลา';
    document.getElementById('edit_id').value = data.leave_type_id;
    document.getElementById('leave_name_en').value = data.leave_type_name;
    document.getElementById('leave_name_th').value = data.leave_type_display;
    document.getElementById('leave_unit').value = data.leave_unit;
    document.getElementById('max_days').value = data.max_days;
    document.getElementById('cancel-btn').style.display = 'inline-block';
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function resetForm() {
    document.getElementById('form-title').innerHTML = '<i class="fas fa-plus-circle"></i> เพิ่มประเภทการลาใหม่';
    document.getElementById('leave-form').reset();
    document.getElementById('edit_id').value = 0;
    document.getElementById('cancel-btn').style.display = 'none';
}
</script>
</body>
</html>