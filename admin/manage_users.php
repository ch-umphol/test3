<?php
session_start();
require_once '../conn.php'; 

// ตรวจสอบสิทธิ์การเข้าถึง (admin เท่านั้นที่ควรจัดการผู้ใช้ได้)
if (!isset($_SESSION['loggedin']) || $_SESSION['user_role'] !== 'admin') {
    header("location: ../login.php");
    exit;
}

$current_user_id = $_SESSION['emp_id'];
$user_role_session = $_SESSION['user_role'];

$action = $_GET['action'] ?? 'read';
$id = (int)($_GET['id'] ?? 0);
$status_message = $_SESSION['status_message'] ?? '';
unset($_SESSION['status_message']);

// ------------------ ดึงชื่อจริงสำหรับ Header ------------------
$user_display_name = $_SESSION['username'];
$sql_user = "SELECT first_name, last_name FROM employees WHERE emp_id = ?";
if ($stmt_user = $conn->prepare($sql_user)) {
    $stmt_user->bind_param("i", $current_user_id);
    $stmt_user->execute();
    $result_user = $stmt_user->get_result();
    if ($row_user = $result_user->fetch_assoc()) {
        $user_display_name = htmlspecialchars($row_user['first_name'] . ' ' . $row_user['last_name']);
    }
}

// ------------------ ADD / EDIT / DELETE LOGIC ------------------

// *** DELETE ***
if($action === 'delete' && $id > 0) {
    if ($conn->query("DELETE FROM employees WHERE emp_id = $id")) {
        $_SESSION['status_message'] = "✅ ลบพนักงานเรียบร้อยแล้ว";
    } else {
        $_SESSION['status_message'] = "❌ ไม่สามารถลบได้: " . $conn->error;
    }
    header("location: manage_users.php");
    exit;
}

// *** POST EDIT ***
if($action === 'edit' && $id > 0 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $emp_code = $conn->real_escape_string($_POST['emp_code']);
    $username = $conn->real_escape_string($_POST['username']);
    $first_name = $conn->real_escape_string($_POST['first_name']);
    $last_name = $conn->real_escape_string($_POST['last_name']);
    $role_id = (int)$_POST['role_id'];
    $hired_date = $conn->real_escape_string($_POST['hired_date']); // รับค่าวันเริ่มงาน
    $password = $_POST['password'];

    $pw_sql = !empty($password) ? ", password='$password'" : "";
    
    $sql_update = "UPDATE employees SET 
                    emp_code='$emp_code', username='$username', 
                    first_name='$first_name', last_name='$last_name', 
                    role_id=$role_id, hired_date='$hired_date' $pw_sql 
                   WHERE emp_id=$id"; // อัปเดต hired_date เข้าฐานข้อมูล

    if ($conn->query($sql_update)) {
        $_SESSION['status_message'] = "✅ แก้ไขข้อมูลสำเร็จ!";
    } else {
        $_SESSION['status_message'] = "❌ เกิดข้อผิดพลาด: " . $conn->error;
    }
    header("location: manage_users.php");
    exit;
}

// *** POST ADD ***
if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $emp_code = $conn->real_escape_string($_POST['emp_code']);
    $username = $conn->real_escape_string($_POST['username']);
    $password = $conn->real_escape_string($_POST['password']);
    $first_name = $conn->real_escape_string($_POST['first_name']);
    $last_name = $conn->real_escape_string($_POST['last_name']);
    $role_id = (int)$_POST['role_id'];
    $hired_date = $conn->real_escape_string($_POST['hired_date']); // รับค่าวันเริ่มงาน

    $sql_add = "INSERT INTO employees (emp_code, username, password, first_name, last_name, role_id, hired_date) 
                VALUES ('$emp_code', '$username', '$password', '$first_name', '$last_name', $role_id, '$hired_date')"; // เพิ่ม hired_date ในคำสั่ง INSERT

    if ($conn->query($sql_add)) {
        $_SESSION['status_message'] = "✅ เพิ่มพนักงานใหม่สำเร็จ!";
        header("location: manage_users.php");
        exit;
    } else {
        $_SESSION['status_message'] = "❌ Username หรือ Code นี้อาจมีอยู่แล้ว";
    }
}

// ------------------ FETCH DATA ------------------
$users_result = $conn->query("
    SELECT E.*, R.role_display 
    FROM employees E 
    JOIN roles R ON E.role_id = R.role_id 
    ORDER BY E.emp_id ASC
");

$roles_result = $conn->query("SELECT * FROM roles");

// ------------------ FETCH FORM FOR MODAL (แก้ไขพนักงาน) ------------------
if ($action === 'fetch_form' && $id > 0) {
    $data = $conn->query("SELECT * FROM employees WHERE emp_id=$id")->fetch_assoc();
    ?>
    <div class="modal-header">
        <h2><i class="fas fa-edit"></i> แก้ไข: <?= htmlspecialchars($data['first_name']) ?></h2>
        <button onclick="closeModal()">&times;</button>
    </div>
    <form method="POST" action="manage_users.php?action=edit&id=<?= $id ?>">
        <div class="form-row">
            <div class="form-group form-col">
                <label>รหัสพนักงาน</label>
                <input type="text" name="emp_code" required value="<?= $data['emp_code'] ?>">
            </div>
            <div class="form-group form-col">
                <label>Username</label>
                <input type="text" name="username" required value="<?= $data['username'] ?>">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group form-col">
                <label>ชื่อ</label>
                <input type="text" name="first_name" required value="<?= $data['first_name'] ?>">
            </div>
            <div class="form-group form-col">
                <label>นามสกุล</label>
                <input type="text" name="last_name" required value="<?= $data['last_name'] ?>">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group form-col">
                <label>รหัสผ่าน (เว้นว่างไว้หากไม่เปลี่ยน)</label>
                <input type="password" name="password">
            </div>
            <div class="form-group form-col">
                <label>วันเริ่มงาน</label>
                <input type="date" name="hired_date" value="<?= $data['hired_date'] ?>"> </div>
        </div>
        <div class="form-row">
            <div class="form-group form-col">
                <label>บทบาท</label>
                <select name="role_id">
                    <?php 
                    $roles_result->data_seek(0);
                    while($r = $roles_result->fetch_assoc()): ?>
                        <option value="<?= $r['role_id'] ?>" <?= $data['role_id']==$r['role_id']?'selected':'' ?>><?= $r['role_display'] ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
        </div>
        <div style="text-align: right; margin-top: 20px;">
            <button type="submit" class="btn-submit"><i class="fas fa-save"></i> บันทึกข้อมูล</button>
        </div>
    </form>
    <?php
    exit;
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>จัดการพนักงาน | LALA MUKHA</title>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {--primary:#004030;--secondary:#66BB6A;--accent:#81C784;--bg:#f5f7fb;--text:#2e2e2e;}
        body{margin:0;font-family:'Prompt',sans-serif;display:flex;background:var(--bg);color:var(--text);}
        .sidebar{width:250px;background:linear-gradient(180deg,var(--primary),#2e7d32);color:#fff;position:fixed;height:100%;display:flex;flex-direction:column;box-shadow:3px 0 10px rgba(0,0,0,0.1);}
        .sidebar-header{padding:25px;text-align:center;font-weight:600;border-bottom:1px solid rgba(255,255,255,0.2);}
        .menu-item{display:flex;align-items:center;padding:15px 25px;color:rgba(255,255,255,0.85);text-decoration:none;transition:0.2s;}
        .menu-item:hover{background:rgba(255,255,255,0.15);color:#fff;}
        .menu-item.active{background:rgba(255,255,255,0.25);color:#fff;font-weight:600;}
        .menu-item i{margin-right:12px;}
        .logout{margin-top:auto;text-align:center;padding:15px;background:#388e3c;color:#fff;text-decoration:none;}
        .main-content{flex-grow:1;margin-left:250px;padding:30px;}
        .header{display:flex;justify-content:space-between;align-items:center;background:#fff;padding:15px 25px;border-radius:12px;box-shadow:0 4px 10px rgba(0,0,0,0.05);margin-bottom:25px;position:sticky;top:0;z-index:10;}
        .card{background:rgba(255,255,255,0.9);border-radius:16px;padding:25px;box-shadow:0 6px 20px rgba(0,0,0,0.05);}
        .data-table{width:100%;border-collapse:collapse;}
        .data-table th, .data-table td{padding:12px; border-bottom:1px solid #eee; text-align:left;}
        .data-table th{background:#f4f4f4;}
        .btn-add{background:var(--secondary);color:#fff;padding:10px 15px;border-radius:5px;text-decoration:none;display:inline-block;margin-bottom:15px;}
        .btn-submit{background:#17A2B8;color:#fff;padding:10px 20px;border:none;border-radius:5px;cursor:pointer;}
        .alert-success{color:#2e7d32;background:#dff0d8;padding:10px;border-radius:6px;margin-bottom:20px;border-left:4px solid #66bb6a;}
        .alert-error{color:#c00;background:#fdd;padding:10px;border-radius:6px;margin-bottom:20px;border-left:4px solid #f44336;}
        .action-btn {display:inline-flex;justify-content:center;align-items:center;width:32px;height:32px;border-radius:50%;text-decoration:none;margin-right:5px;border:1px solid;}
        .action-edit {border-color:#17A2B8;color:#17A2B8;}
        .action-delete {border-color:#c00;color:#c00;}
        .form-row { display: flex; gap: 20px; margin-bottom: 10px; }
        .form-col { flex: 1; }
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); }
        .modal-content { background: #fff; margin: 5% auto; padding: 20px; border-radius: 12px; width: 90%; max-width: 600px; }
        .modal-header { display: flex; justify-content: space-between; border-bottom: 1px solid #eee; padding-bottom: 10px; margin-bottom: 15px; }
        .form-group label { display:block; margin-bottom:5px; font-weight:500; }
        .form-group input, .form-group select { width:100%; padding:8px; border:1px solid #ccc; border-radius:4px; }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="sidebar-header">LALA MUKHA<br>Admin Panel</div>
    <a href="admin_dashboard.php" class="menu-item"><i class="fas fa-tachometer-alt"></i> ภาพรวมระบบ</a>
    <a href="manage_users.php" class="menu-item active"><i class="fas fa-user-cog"></i> จัดการพนักงาน</a>
    <a href="logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> ออกจากระบบ</a>
</div>

<div class="main-content">
    <div class="header">
        <div style="font-weight:600; color:var(--primary);">จัดการข้อมูลพนักงาน</div>
        <div class="user-profile"><span><?= $user_display_name ?></span><i class="fas fa-user-circle" style="font-size:1.8em;color:var(--primary); margin-left:10px;"></i></div>
    </div>

    <div class="card">
        <?php if($status_message): ?>
            <div class="<?= strpos($status_message,'✅')!==false?'alert-success':'alert-error' ?>"><?= $status_message ?></div>
        <?php endif; ?>

        <a href="manage_users.php?action=add" class="btn-add"><i class="fas fa-plus"></i> เพิ่มพนักงานใหม่</a>

        <table class="data-table">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>ชื่อ-สกุล</th>
                    <th>Username</th>
                    <th>บทบาท</th>
                    <th>วันเริ่มงาน</th> <th>จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php while($row = $users_result->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($row['emp_code']) ?></td>
                    <td><?= htmlspecialchars($row['first_name'].' '.$row['last_name']) ?></td>
                    <td><?= htmlspecialchars($row['username']) ?></td>
                    <td><?= htmlspecialchars($row['role_display']) ?></td>
                    <td><?= $row['hired_date'] ? date('d/m/Y', strtotime($row['hired_date'])) : '-' ?></td> <td>
                        <a href="#" onclick="openEditModal(<?= $row['emp_id'] ?>)" class="action-btn action-edit"><i class="fas fa-edit"></i></a>
                        <a href="manage_users.php?action=delete&id=<?= $row['emp_id'] ?>" class="action-btn action-delete" onclick="return confirm('ลบพนักงานคนนี้หรือไม่?');"><i class="fas fa-trash"></i></a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <?php if ($action === 'add'): ?>
    <div class="card" style="margin-top:20px;">
        <h2><i class="fas fa-user-plus"></i> เพิ่มพนักงาน</h2>
        <form method="POST">
            <div class="form-row">
                <div class="form-group form-col"><label>รหัสพนักงาน</label><input type="text" name="emp_code" required></div>
                <div class="form-group form-col"><label>Username</label><input type="text" name="username" required></div>
            </div>
            <div class="form-row">
                <div class="form-group form-col"><label>ชื่อ</label><input type="text" name="first_name" required></div>
                <div class="form-group form-col"><label>นามสกุล</label><input type="text" name="last_name" required></div>
            </div>
            <div class="form-row">
                <div class="form-group form-col"><label>รหัสผ่าน</label><input type="password" name="password" required></div>
                <div class="form-group form-col">
                    <label>วันเริ่มงาน</label>
                    <input type="date" name="hired_date" required> </div>
            </div>
            <div class="form-row">
                <div class="form-group form-col">
                    <label>บทบาท</label>
                    <select name="role_id">
                        <?php 
                        $roles_result->data_seek(0);
                        while($r = $roles_result->fetch_assoc()): ?>
                            <option value="<?= $r['role_id'] ?>"><?= $r['role_display'] ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-col"></div> </div>
            <button type="submit" class="btn-submit">บันทึกพนักงาน</button>
            <a href="manage_users.php" class="btn-submit" style="background:#888; text-decoration:none;">ยกเลิก</a>
        </form>
    </div>
    <?php endif; ?>
</div>

<div id="editModal" class="modal" onclick="if(event.target.id === 'editModal') closeModal()">
    <div class="modal-content"><div id="modalFormContainer">โหลดข้อมูล...</div></div>
</div>

<script>
function openEditModal(id) {
    document.getElementById('editModal').style.display = 'block';
    fetch(`manage_users.php?action=fetch_form&id=${id}`)
        .then(r => r.text())
        .then(html => document.getElementById('modalFormContainer').innerHTML = html);
}
function closeModal() { document.getElementById('editModal').style.display = 'none'; }
</script>
</body>
</html>