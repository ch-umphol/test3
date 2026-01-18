<?php
session_start();
require_once 'conn.php'; 

// ตรวจสอบสิทธิ์การเข้าถึง
if (!isset($_SESSION['loggedin']) || !in_array($_SESSION['user_role'], ['ผู้ดูแลระบบ', 'ผู้จัดการ', 'หัวหน้างาน'])) {
    header("location: login.php");
    exit;
}

$current_user_id = $_SESSION['employee_id'];
$user_role = $_SESSION['user_role'];
$user_name = $_SESSION['username'];
$action = $_GET['action'] ?? 'read';
$user_id = (int)($_GET['id'] ?? 0);
$status_message = $_SESSION['status_message'] ?? '';
unset($_SESSION['status_message']);


// ------------------ ADD / EDIT / DELETE LOGIC ------------------

// *** DELETE USER ***
if($action === 'delete' && $user_id > 0) {
    // ... (โค้ด DELETE เหมือนเดิม)
    $conn->begin_transaction();
    try {
        $username_to_delete = $conn->query("SELECT username FROM USER WHERE user_id=$user_id")->fetch_assoc()['username'];
        $conn->query("DELETE FROM USER WHERE user_id=$user_id");
        $conn->query("DELETE FROM EMPLOYEE WHERE username='$username_to_delete'");
        $conn->commit();
        $_SESSION['status_message'] = "✅ ลบผู้ใช้งานเรียบร้อยแล้ว";
    } catch(Exception $e) {
        $conn->rollback();
        $_SESSION['status_message'] = "❌ เกิดข้อผิดพลาด: ".$e->getMessage();
    }
    header("location: manage_users.php");
    exit;
}

// *** POST HANDLING for EDIT ***
if($action === 'edit' && $user_id > 0 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // โค้ดส่วนนี้จะทำงานหลังการ submit form แก้ไข (จาก Modal)
    $username = $conn->real_escape_string($_POST['username']);
    $password = $_POST['password'];
    $first_name = $conn->real_escape_string($_POST['first_name']);
    $last_name = $conn->real_escape_string($_POST['last_name']);
    $email = $conn->real_escape_string($_POST['email']);
    $position = $conn->real_escape_string($_POST['position']);
    $department_id = (int)$_POST['department_id'];
    $user_type_id = (int)$_POST['user_type_id'];

    $user_data_old = $conn->query("SELECT username FROM USER WHERE user_id=$user_id")->fetch_assoc();

    $conn->begin_transaction();
    try {
        $password_update_sql = "";
        if (!empty($password)) {
            $password_update_sql = ", password='$password'";
        }

        // อัปเดต EMPLOYEE
        $conn->query("UPDATE EMPLOYEE SET username='$username' $password_update_sql, first_name='$first_name', last_name='$last_name',
                      email='$email', position='$position', department_id=$department_id WHERE username='{$user_data_old['username']}'");

        // อัปเดต USER
        $role = $conn->query("SELECT user_type_name FROM User_Type WHERE user_type_id=$user_type_id")->fetch_assoc()['user_type_name'];
        $conn->query("UPDATE USER SET username='$username' $password_update_sql, user_role='$role', user_type_id=$user_type_id WHERE user_id=$user_id");
        
        $conn->commit();
        $_SESSION['status_message'] = "✅ แก้ไขผู้ใช้งานสำเร็จ!";
        header("location: manage_users.php");
        exit;
    } catch(Exception $e) {
        $conn->rollback();
        $_SESSION['status_message'] = "❌ เกิดข้อผิดพลาด: ".$e->getMessage();
    }
}

// *** POST HANDLING for ADD ***
if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // โค้ดส่วนนี้จะทำงานหลังการ submit form เพิ่มผู้ใช้งาน
    $username = $conn->real_escape_string($_POST['username']);
    $password = $_POST['password'];
    $first_name = $conn->real_escape_string($_POST['first_name']);
    $last_name = $conn->real_escape_string($_POST['last_name']);
    $email = $conn->real_escape_string($_POST['email']);
    $position = $conn->real_escape_string($_POST['position']);
    $department_id = (int)$_POST['department_id'];
    $user_type_id = (int)$_POST['user_type_id'];

    $check = $conn->query("SELECT username FROM EMPLOYEE WHERE username='$username'");
    if ($check->num_rows > 0) {
        $_SESSION['status_message'] = "❌ Username นี้ถูกใช้งานแล้ว";
    } else {
        $conn->begin_transaction();
        try {
            $conn->query("INSERT INTO EMPLOYEE(username,password,first_name,last_name,email,position,department_id) 
                VALUES('$username','$password','$first_name','$last_name','$email','$position',$department_id)");
            $role = $conn->query("SELECT user_type_name FROM User_Type WHERE user_type_id=$user_type_id")->fetch_assoc()['user_type_name'];
            $conn->query("INSERT INTO USER(username,password,user_role,user_type_id) VALUES('$username','$password','$role',$user_type_id)");
            $conn->commit();
            $_SESSION['status_message'] = "✅ เพิ่มผู้ใช้งาน $first_name $last_name สำเร็จ!";
            header("location: manage_users.php");
            exit;
        } catch(Exception $e) {
            $conn->rollback();
            $_SESSION['status_message'] = "❌ เกิดข้อผิดพลาด: " . $e->getMessage();
        }
    }
}


// ------------------ FETCH DATA FOR DISPLAY / DROPDOWN ------------------

// ดึงรายการผู้ใช้งานสำหรับตารางหลัก
$where_clause = ($user_role === 'หัวหน้างาน') ? "WHERE E.supervisor_id = $current_user_id" : "";
$sql = "
    SELECT 
        U.user_id, U.username, UT.user_type_name AS role_name, DT.department_name,
        E.first_name, E.last_name, E.email, E.position
    FROM USER U
    JOIN User_Type UT ON U.user_type_id = UT.user_type_id
    JOIN EMPLOYEE E ON U.username = E.username
    JOIN Department_Type DT ON E.department_id = DT.department_id
    $where_clause
    ORDER BY U.user_id ASC";
$users_result = $conn->query($sql);

// ดึงข้อมูลสำหรับ Dropdown
$user_types = $conn->query("SELECT user_type_id, user_type_name FROM User_Type");
$departments = $conn->query("SELECT department_id, department_name FROM Department_Type");


// ------------------ FORM DISPLAY LOGIC (FOR MODAL/ADD) ------------------

// *** Logic พิเศษสำหรับดึง Form ด้วย Fetch API (แทนการแสดงผลบนหน้าเดิม) ***
if ($action === 'fetch_form' && $user_id > 0) {
     // ดึงข้อมูลผู้ใช้สำหรับฟอร์มแก้ไข
    $user_data_form = $conn->query("
        SELECT U.user_id,U.username,U.user_type_id,E.first_name,E.last_name,E.email,E.position,E.department_id 
        FROM USER U 
        JOIN EMPLOYEE E ON U.username=E.username 
        WHERE U.user_id=$user_id
    ")->fetch_assoc();

    // รีเซ็ตตัวชี้สำหรับ Dropdown (สำคัญมากเมื่อใช้ซ้ำ)
    $user_types->data_seek(0);
    $departments->data_seek(0);

    // เริ่มสร้าง HTML Form สำหรับส่งกลับไปที่ Modal
    ?>
    <div class="modal-header">
        <h2><i class="fas fa-edit"></i> แก้ไขผู้ใช้งาน: <?= htmlspecialchars($user_data_form['first_name'].' '.$user_data_form['last_name']) ?></h2>
        <button onclick="closeModal()">&times;</button>
    </div>
    <form method="POST" action="manage_users.php?action=edit&id=<?= $user_id ?>">
        
        <div class="form-row">
            <div class="form-group form-col">
                <label>ชื่อผู้ใช้งาน</label>
                <input type="text" name="username" required value="<?= $user_data_form['username']??'' ?>">
            </div>
            <div class="form-group form-col">
                <label>รหัสผ่าน **(กรอกเพื่อเปลี่ยน)**</label>
                <input type="text" name="password" value="">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group form-col">
                <label>ชื่อ</label>
                <input type="text" name="first_name" required value="<?= $user_data_form['first_name']??'' ?>">
            </div>
            <div class="form-group form-col">
                <label>นามสกุล</label>
                <input type="text" name="last_name" required value="<?= $user_data_form['last_name']??'' ?>">
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group form-col">
                <label>Email</label>
                <input type="email" name="email" value="<?= $user_data_form['email']??'' ?>">
            </div>
            <div class="form-group form-col">
                <label>ตำแหน่ง</label>
                <input type="text" name="position" value="<?= $user_data_form['position']??'' ?>">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group form-col">
                <label>แผนก</label>
                <select name="department_id">
                    <?php 
                    $departments->data_seek(0);
                    while($dep = $departments->fetch_assoc()): ?>
                        <option value="<?= $dep['department_id'] ?>" <?= isset($user_data_form['department_id']) && $user_data_form['department_id']==$dep['department_id']?'selected':'' ?>><?= $dep['department_name'] ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group form-col">
                <label>บทบาท</label>
                <select name="user_type_id">
                    <?php 
                    $user_types->data_seek(0);
                    while($ut = $user_types->fetch_assoc()): ?>
                        <option value="<?= $ut['user_type_id'] ?>" <?= isset($user_data_form['user_type_id']) && $user_data_form['user_type_id']==$ut['user_type_id']?'selected':'' ?>><?= $ut['user_type_name'] ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
        </div>
        
        <div style="text-align: right; margin-top: 20px;">
            <button type="submit" class="btn-submit"><i class="fas fa-save"></i> บันทึกการแก้ไข</button>
        </div>
    </form>
    <?php
    exit; // สำคัญ: หยุดการทำงานของ PHP ที่นี่เพื่อส่ง Form HTML กลับไป
}
// *************************************************************************


// ปิดการเชื่อมต่อ DB ทันทีหลังดึงข้อมูลทั้งหมดเสร็จ
$conn->close();
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>จัดการผู้ใช้งาน | LALA MUKHA</title>
<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
/* Styles for Sidebar, Header, Alerts, and Action Buttons (Unchanged) */
:root {--primary:#004030;--secondary:#66BB6A;--accent:#81C784;--bg:#f5f7fb;--text:#2e2e2e;}
body{margin:0;font-family:'Prompt',sans-serif;display:flex;background:var(--bg);color:var(--text);}
.sidebar{width:250px;background:linear-gradient(180deg,var(--primary),#2e7d32);color:#fff;position:fixed;height:100%;display:flex;flex-direction:column;box-shadow:3px 0 10px rgba(0,0,0,0.1);}
.sidebar-header{padding:25px;text-align:center;font-weight:600;border-bottom:1px solid rgba(255,255,255,0.2);}
.menu-item{display:flex;align-items:center;padding:15px 25px;color:rgba(255,255,255,0.85);text-decoration:none;transition:0.2s;}
.menu-item:hover{background:rgba(255,255,255,0.15);color:#fff;}
.menu-item.active{background:rgba(255,255,255,0.25);color:#fff;font-weight:600;}
.menu-item i{margin-right:12px;}
.logout{margin-top:auto;text-align:center;padding:15px;background:#388e3c;color:#fff;text-decoration:none;}
.logout:hover{background:#2e7d32;}
.main-content{flex-grow:1;margin-left:250px;padding:30px;}
.header{display:flex;justify-content:space-between;align-items:center;background:#fff;padding:15px 25px;border-radius:12px;box-shadow:0 4px 10px rgba(0,0,0,0.05);margin-bottom:25px;position:sticky;top:0;z-index:10;}
.search-box{background:#f7f7f7;border-radius:25px;padding:6px 15px;display:flex;align-items:center;box-shadow:inset 0 2px 4px rgba(0,0,0,0.05);}
.search-box input{border:none;outline:none;padding-left:10px;font-size:1em;background:transparent;}
.user-profile{display:flex;align-items:center;gap:10px;}
.card{background:rgba(255,255,255,0.9);border-radius:16px;padding:25px;box-shadow:0 6px 20px rgba(0,0,0,0.05);}
.card h1{margin-top:0;color:var(--primary);}
.data-table{width:100%;border-collapse:collapse;font-size:0.95em;}
.data-table th,.data-table td{padding:12px 15px;border-bottom:1px solid #eee;text-align:left;}
.data-table th{background-color:#f4f4f4;color:#333;font-weight:600;}
.btn-add{background:var(--secondary);color:#fff;padding:10px 15px;border-radius:5px;text-decoration:none;display:inline-block;margin-bottom:15px;}
.btn-add:hover{background:#4caf50;}
.form-group{margin-bottom:15px;}
.form-group label{display:block;margin-bottom:5px;font-weight:500;color:#555;}
.form-group input,.form-group select{width:100%;padding:10px;border:1px solid #ccc;border-radius:4px;box-sizing:border-box;font-family:'Prompt',sans-serif;}
.btn-submit{background:#17A2B8;color:#fff;padding:10px 20px;border:none;border-radius:5px;cursor:pointer;font-weight:500;}
.btn-submit:hover{background:#117a8b;}
.alert-success{color:#2e7d32;background:#dff0d8;padding:10px 15px;border-radius:6px;margin-bottom:20px;border-left:4px solid #66bb6a;}
.alert-error{color:#c00;background:#fdd;padding:10px 15px;border-radius:6px;margin-bottom:20px;border-left:4px solid #f44336;}
.action-btn{margin-right:8px;text-decoration:none;font-weight:500;}
.action-edit{color:#17A2B8;}
.action-delete{color:#c00;}
.action-edit:hover{color:#0c5460;}
.action-delete:hover{color:#900;}
.action-btn {display:inline-flex;justify-content:center;align-items:center;width:36px;height:36px;border-radius:50%;text-decoration:none;font-size:16px;transition:0.2s;margin-right:5px;border:2px solid;color:inherit;background:transparent;}
.action-edit {border-color:#17A2B8;color:#17A2B8;}
.action-edit:hover {background:#17A2B8;color:#fff;}
.action-delete {border-color:#c00;color:#c00;}
.action-delete:hover {background:#c00;color:#fff;}
.form-row { display: flex; gap: 20px; margin-bottom: 5px; }
.form-col { flex: 1; }

/* *** MODAL STYLES *** */
.modal {
    display: none; /* ถูกซ่อนโดยค่าเริ่มต้น */
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow: auto;
    background-color: rgba(0,0,0,0.4); /* Backdrop */
}
.modal-content {
    background-color: #fff;
    margin: 5% auto; /* 5% จากด้านบน และอยู่ตรงกลาง */
    padding: 20px;
    border-radius: 12px;
    width: 80%;
    max-width: 600px;
    box-shadow: 0 4px 8px 0 rgba(0,0,0,0.2),0 6px 20px 0 rgba(0,0,0,0.19);
}
.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid #eee;
    padding-bottom: 10px;
    margin-bottom: 15px;
}
.modal-header h2 {
    margin: 0;
    color: var(--primary);
    font-size: 1.5em;
}
.modal-header button {
    background: none;
    border: none;
    font-size: 2em;
    cursor: pointer;
    color: #888;
}
/* ******************** */
</style>
</head>
<body>

<div class="sidebar">
    <div class="sidebar-header">LALA MUKHA<br>ระบบจัดการผู้ใช้งาน</div>
    <a href="admin_dashboard.php" class="menu-item"><i class="fas fa-tachometer-alt"></i> ภาพรวมระบบ</a>
    <a href="manage_leave.php" class="menu-item"><i class="fas fa-clipboard-list"></i> จัดการการลา</a>
    <a href="manage_users.php" class="menu-item active"><i class="fas fa-user"></i> ข้อมูลผู้ใช้</a>
    <a href="manage_employees.php" class="menu-item"><i class="fas fa-users"></i> ข้อมูลพนักงาน</a>
    <a href="manage_leavetype.php" class="menu-item"><i class="fas fa-list"></i> ประเภทการลา</a>
    <a href="manage_department.php" class="menu-item"><i class="fas fa-building"></i> แผนก</a>
    <a href="manage_holiday.php" class="menu-item"><i class="fas fa-calendar-alt"></i> วันหยุดพิเศษ</a>
    <a href="logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> ออกจากระบบ</a>
</div>

<div class="main-content">
    <div class="header">
        <div class="search-box"><i class="fas fa-search"></i><input type="text" placeholder="ค้นหา..."></div>
        <div class="user-profile"><span><?= htmlspecialchars($user_name) ?> (<?= $user_role ?>)</span><i class="fas fa-user-circle" style="font-size:1.8em;color:var(--primary)"></i></div>
    </div>

    <div class="card">
        <h1>รายชื่อผู้ใช้งาน</h1>

        <?php if($status_message): ?>
            <div class="<?= strpos($status_message,'✅')!==false?'alert-success':'alert-error' ?>">
                <?= $status_message ?>
            </div>
        <?php endif; ?>

        <a href="manage_users.php?action=add" class="btn-add"><i class="fas fa-plus"></i> เพิ่มผู้ใช้งาน</a>

        <?php if($users_result && $users_result->num_rows > 0): ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>ชื่อผู้ใช้งาน</th>
                    <th>ชื่อ-สกุล</th>
                    <th>ตำแหน่ง</th>
                    <th>แผนก</th>
                    <th>บทบาท</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php while($row = $users_result->fetch_assoc()): ?>
                <tr>
                    <td><?= $row['user_id'] ?></td>
                    <td><?= htmlspecialchars($row['username']) ?></td>
                    <td><?= htmlspecialchars($row['first_name'].' '.$row['last_name']) ?></td>
                    <td><?= htmlspecialchars($row['position']) ?></td>
                    <td><?= htmlspecialchars($row['department_name']) ?></td>
                    <td><?= htmlspecialchars($row['role_name']) ?></td>
                    <td>

        <a href="#" onclick="openEditModal(<?= $row['user_id'] ?>)" class="action-btn action-edit" title="แก้ไข">
            <i class="fas fa-edit"></i>
        </a>
        <a href="manage_users.php?action=delete&id=<?= $row['user_id'] ?>" class="action-btn action-delete" title="ลบ" onclick="return confirm('คุณต้องการลบผู้ใช้นี้หรือไม่?');">
            <i class="fas fa-trash-alt"></i>
        </a>

                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        <?php else: ?>
            <p>ไม่พบผู้ใช้งาน</p>
        <?php endif; ?>
    </div>

    <?php 
    if ($action === 'add'): 
        if (isset($departments)) $departments->data_seek(0);
        if (isset($user_types)) $user_types->data_seek(0);
    ?>
    <div class="card" style="margin-top:25px;">
        <h1><i class="fas fa-user-plus"></i> เพิ่มผู้ใช้งานใหม่</h1>
        <form method="POST" action="manage_users.php?action=add">
            
            <div class="form-row">
                <div class="form-group form-col">
                    <label>ชื่อผู้ใช้งาน</label>
                    <input type="text" name="username" required>
                </div>
                <div class="form-group form-col">
                    <label>รหัสผ่าน **(ต้องกรอก)**</label>
                    <input type="text" name="password" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group form-col">
                    <label>ชื่อ</label>
                    <input type="text" name="first_name" required>
                </div>
                <div class="form-group form-col">
                    <label>นามสกุล</label>
                    <input type="text" name="last_name" required>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group form-col">
                    <label>Email</label>
                    <input type="email" name="email">
                </div>
                <div class="form-group form-col">
                    <label>ตำแหน่ง</label>
                    <input type="text" name="position">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group form-col">
                    <label>แผนก</label>
                    <select name="department_id">
                        <?php 
                        if (isset($departments)) $departments->data_seek(0);
                        while($dep = $departments->fetch_assoc()): ?>
                            <option value="<?= $dep['department_id'] ?>"><?= $dep['department_name'] ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group form-col">
                    <label>บทบาท</label>
                    <select name="user_type_id">
                        <?php 
                        if (isset($user_types)) $user_types->data_seek(0);
                        while($ut = $user_types->fetch_assoc()): ?>
                            <option value="<?= $ut['user_type_id'] ?>"><?= $ut['user_type_name'] ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </div>
            
            <button type="submit" class="btn-submit"><i class="fas fa-save"></i> บันทึกผู้ใช้งาน</button>
            <a href="manage_users.php" class="btn-submit" style="background:#888; margin-left: 10px;"><i class="fas fa-times"></i> ยกเลิก</a>
        </form>
    </div>
    <?php endif; ?>

</div>

<div id="editModal" class="modal" onclick="if(event.target.id === 'editModal') closeModal()">
    <div class="modal-content">
        <div id="modalFormContainer" style="text-align: center;">
            <i class="fas fa-spinner fa-spin" style="font-size: 2em; color: var(--primary);"></i>
            <p>กำลังโหลดฟอร์ม...</p>
        </div>
    </div>
</div>
<script>
const editModal = document.getElementById('editModal');
const modalFormContainer = document.getElementById('modalFormContainer');

function openEditModal(userId) {
    editModal.style.display = 'block';
    
    // แสดง loading state
    modalFormContainer.innerHTML = '<div style="text-align: center; padding: 20px;"><i class="fas fa-spinner fa-spin" style="font-size: 2em; color: var(--primary);"></i><p>กำลังโหลดฟอร์ม...</p></div>';

    // ใช้ Fetch API เพื่อดึงเฉพาะ Form HTML
    fetch(`manage_users.php?action=fetch_form&id=${userId}`)
        .then(response => response.text())
        .then(html => {
            modalFormContainer.innerHTML = html;
        })
        .catch(error => {
            modalFormContainer.innerHTML = '<p style="color:red;">❌ โหลดฟอร์มไม่สำเร็จ</p>';
            console.error('Fetch error:', error);
        });
}

function closeModal() {
    editModal.style.display = 'none';
}

// ปิด Modal ด้วยปุ่ม Esc
document.addEventListener('keydown', function(event) {
    if (event.key === "Escape") {
        closeModal();
    }
});
</script>
</body>
</html>