<?php
session_start();
require_once '../conn.php'; 

// 1. ตรวจสอบสิทธิ์การเข้าถึง (Manager หรือ Admin เท่านั้น)
if (!isset($_SESSION['loggedin']) || !in_array($_SESSION['user_role'], ['manager', 'admin'])) {
    header("location: ../login.php");
    exit;
}

$emp_id = $_SESSION['emp_id'];
$user_name = $_SESSION['username'];
$user_role = $_SESSION['user_role'];

// ดึงชื่อจริง Manager ผู้ใช้งานปัจจุบัน
$sql_user = "SELECT first_name, last_name FROM employees WHERE emp_id = $emp_id";
$res_user = $conn->query($sql_user);
$user_display_name = ($row_u = $res_user->fetch_assoc()) ? $row_u['first_name'].' '.$row_u['last_name'] : $user_name;

$status_message = $_SESSION['status_message'] ?? '';
unset($_SESSION['status_message']);

// ------------------ HANDLING ACTIONS ------------------

// ลบแผนก
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    // ตรวจสอบก่อนว่ามีพนักงานสังกัดแผนกนี้อยู่หรือไม่
    $check_emp = $conn->query("SELECT emp_id FROM employees WHERE dept_id = $id LIMIT 1");
    if ($check_emp->num_rows > 0) {
        $_SESSION['status_message'] = "❌ ไม่สามารถลบได้ เนื่องจากมีพนักงานสังกัดอยู่ในแผนกนี้";
    } else {
        if ($conn->query("DELETE FROM departments WHERE dept_id = $id")) {
            $_SESSION['status_message'] = "✅ ลบแผนกสำเร็จ";
        } else {
            $_SESSION['status_message'] = "❌ เกิดข้อผิดพลาดในการลบ";
        }
    }
    header("location: manage_department.php");
    exit;
}

// เพิ่มหรือแก้ไขแผนก
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_department'])) {
    $dept_name = $conn->real_escape_string($_POST['dept_name']);
    $edit_id = isset($_POST['edit_id']) ? (int)$_POST['edit_id'] : 0;
    
    if ($edit_id > 0) {
        // แก้ไขเฉพาะชื่อแผนก
        $sql = "UPDATE departments SET dept_name='$dept_name' WHERE dept_id=$edit_id";
        $msg = "✅ อัปเดตชื่อแผนกสำเร็จ";
    } else {
        // เพิ่มเฉพาะชื่อแผนก
        $sql = "INSERT INTO departments (dept_name) VALUES ('$dept_name')";
        $msg = "✅ เพิ่มแผนกใหม่สำเร็จ";
    }

    if ($conn->query($sql)) {
        $_SESSION['status_message'] = $msg;
    } else {
        $_SESSION['status_message'] = "❌ เกิดข้อผิดพลาด: " . $conn->error;
    }
    header("location: manage_department.php");
    exit;
}

// ------------------ FETCH DATA ------------------
// ดึงข้อมูลแผนกทั้งหมด
$sql_depts = "SELECT * FROM departments ORDER BY dept_id ASC";
$result_depts = $conn->query($sql_depts);
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>จัดการแผนก | LALA MUKHA</title>
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
        .form-group { flex: 1; min-width: 300px; display: flex; flex-direction: column; gap: 5px; }
        .form-group label { font-size: 0.9em; color: #555; font-weight: 500; }
        .form-group input { padding: 12px; border: 1px solid #ddd; border-radius: 8px; font-family: 'Prompt'; font-size: 1em; }
        .btn-add { background: var(--primary); color: white; border: none; padding: 0 30px; border-radius: 8px; cursor: pointer; font-weight: 600; height: 48px; align-self: flex-end; transition: 0.3s; }
        .action-btn { width: 32px; height: 32px; border-radius: 6px; display: inline-flex; align-items: center; justify-content: center; text-decoration: none; font-size: 0.9em; transition: 0.2s; border: none; cursor: pointer; }
        .btn-edit { background: #fff3e0; color: #ef6c00; margin-right: 5px; }
        .btn-delete { background: #ffebee; color: #c62828; }
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 500; background: #e8f5e9; color: #2e7d32; border-left: 5px solid #4caf50; }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="sidebar-header">LALA MUKHA<br>ระบบจัดการพนักงาน</div>
    <div style="flex-grow:1; padding-top:10px;">
        <a href="manager_dashboard.php" class="menu-item"><i class="fas fa-tachometer-alt"></i> ภาพรวมระบบ</a>
        <a href="manage_leave.php" class="menu-item"><i class="fas fa-clipboard-list"></i> จัดการการลา</a>
        <a href="manage_employees.php" class="menu-item"><i class="fas fa-users"></i> ข้อมูลพนักงาน</a>
        <a href="manage_leavetype.php" class="menu-item"><i class="fas fa-list"></i> ประเภทการลา</a>
        <a href="manage_department.php" class="menu-item active"><i class="fas fa-building"></i> แผนก</a>
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
        <div style="font-weight:600; color:var(--primary);">จัดการโครงสร้างแผนก</div>
        <div style="display:flex; align-items:center; gap:10px;">
            <span><?= htmlspecialchars($user_display_name) ?></span>
            <i class="fas fa-user-circle" style="font-size:1.8em; color:var(--primary);"></i>
        </div>
    </div>

    <div class="card">
        <h1 id="form-title"><i class="fas fa-plus-circle"></i> เพิ่มแผนกใหม่</h1>
        <form method="POST" class="form-inline" id="dept-form">
            <input type="hidden" name="edit_id" id="edit_id" value="0">
            <div class="form-group">
                <label>ชื่อแผนก</label>
                <input type="text" name="dept_name" id="dept_name" placeholder="ระบุชื่อแผนก" required>
            </div>
            <button type="submit" name="save_department" class="btn-add">บันทึกข้อมูล</button>
            <button type="button" onclick="resetForm()" id="cancel-btn" style="display:none; background:#eee; color:#333;" class="btn-add">ยกเลิก</button>
        </form>
    </div>

    <div class="card">
        <h1><i class="fas fa-table"></i> รายการแผนกทั้งหมด</h1>
        <?php if($status_message): ?><div class="alert"><?= $status_message ?></div><?php endif; ?>
        
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 80px;">ลำดับ</th>
                    <th>ชื่อแผนก</th>
                    <th style="width: 120px; text-align:center;">จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $count = 1;
                if ($result_depts && $result_depts->num_rows > 0):
                    while($row = $result_depts->fetch_assoc()): 
                ?>
                <tr>
                    <td><strong><?= $count++ ?></strong></td>
                    <td><strong><?= htmlspecialchars($row['dept_name']) ?></strong></td>
                    <td style="text-align:center;">
                        <button type="button" class="action-btn btn-edit" onclick='editDept(<?= json_encode($row) ?>)' title="แก้ไข">
                            <i class="fas fa-edit"></i>
                        </button>
                        <a href="?action=delete&id=<?= $row['dept_id'] ?>" class="action-btn btn-delete" onclick="return confirm('ยืนยันการลบแผนกนี้?')" title="ลบ">
                            <i class="fas fa-trash"></i>
                        </a>
                    </td>
                </tr>
                <?php endwhile; else: ?>
                    <tr><td colspan="3" style="text-align:center; color:#999;">ไม่พบข้อมูลแผนก</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function editDept(data) {
    document.getElementById('form-title').innerHTML = '<i class="fas fa-edit"></i> แก้ไขชื่อแผนก';
    document.getElementById('edit_id').value = data.dept_id;
    document.getElementById('dept_name').value = data.dept_name;
    document.getElementById('cancel-btn').style.display = 'inline-block';
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function resetForm() {
    document.getElementById('form-title').innerHTML = '<i class="fas fa-plus-circle"></i> เพิ่มแผนกใหม่';
    document.getElementById('dept-form').reset();
    document.getElementById('edit_id').value = 0;
    document.getElementById('cancel-btn').style.display = 'none';
}
</script>
</body>
</html>