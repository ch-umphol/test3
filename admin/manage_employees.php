<?php
session_start();
require_once 'conn.php'; 

// ตรวจสอบสิทธิ์การเข้าถึง
if (!isset($_SESSION['loggedin']) || !in_array($_SESSION['user_role'], ['ผู้ดูแลระบบ', 'ผู้จัดการ', 'หัวหน้างาน'])) {
    header("location: admin_dashboard.php");
    exit;
}

$current_user_id = $_SESSION['employee_id'];
$user_role = $_SESSION['user_role'];
$user_name = $_SESSION['username'];
$action = $_GET['action'] ?? 'read';
$edit_employee_id = (int)($_GET['id'] ?? 0); // ID พนักงานที่ต้องการแก้ไข
$status_message = $_SESSION['status_message'] ?? '';
unset($_SESSION['status_message']);

// -----------------------------------------------------------------------------
// FETCH ALL EMPLOYEES (สำหรับ Dropdown หัวหน้างาน)
// -----------------------------------------------------------------------------
// NEW: เงื่อนไขการดึงพนักงานสำหรับ Dropdown
$employee_dropdown_where_clause = "";
if ($user_role === 'หัวหน้างาน') {
    // ถ้าเป็นหัวหน้างาน: ดึงพนักงานทุกคน (รวมตัวเอง) ที่หัวหน้างานคนนี้ดูแล
    $employee_dropdown_where_clause = "WHERE E.supervisor_id = $current_user_id OR E.employee_id = $current_user_id";
} else {
    // ถ้าเป็นผู้ดูแลระบบ/ผู้จัดการ: ดึงพนักงานทุกคนในระบบ
    $employee_dropdown_where_clause = "";
}

$all_employees_result = $conn->query("
    SELECT employee_id, first_name, last_name 
    FROM EMPLOYEE E
    $employee_dropdown_where_clause
    ORDER BY first_name
");
// *****************************************************************************


// -----------------------------------------------------------------------------
// POST HANDLING: UPDATE SUPERVISOR
// -----------------------------------------------------------------------------
if($action === 'update_supervisor' && $edit_employee_id > 0 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_supervisor_id = (int)$_POST['supervisor_id'];
    
    // ตรวจสอบไม่ให้พนักงานตั้งตัวเองเป็นหัวหน้างาน
    if ($new_supervisor_id === $edit_employee_id) {
        $_SESSION['status_message'] = "❌ ไม่สามารถตั้งตัวเองเป็นหัวหน้างานได้";
        header("location: manage_employees.php");
        exit;
    }

    // กำหนด supervisor_id เป็น NULL ถ้าเลือก "ไม่มีหัวหน้างาน" (ID=0)
    $supervisor_value = ($new_supervisor_id > 0) ? $new_supervisor_id : 'NULL';

    try {
        $sql = "UPDATE EMPLOYEE SET supervisor_id = $supervisor_value WHERE employee_id = $edit_employee_id";
        if ($conn->query($sql)) {
            $_SESSION['status_message'] = "✅ อัปเดตหัวหน้างานสำเร็จ!";
        } else {
            throw new Exception("Error updating: " . $conn->error);
        }
    } catch (Exception $e) {
        $_SESSION['status_message'] = "❌ อัปเดตล้มเหลว: " . $e->getMessage();
    }
    header("location: manage_employees.php");
    exit;
}


// -----------------------------------------------------------------------------
// FETCH SUPERVISOR FORM (สำหรับ Modal)
// -----------------------------------------------------------------------------
$employee_data_for_edit = null;
if ($action === 'fetch_supervisor_form' && $edit_employee_id > 0) {
    // ดึงข้อมูลพนักงานที่ต้องการแก้ไข
    $employee_data_for_edit = $conn->query("SELECT employee_id, first_name, last_name, supervisor_id FROM EMPLOYEE WHERE employee_id = $edit_employee_id")->fetch_assoc();

    // รีเซ็ตตัวชี้สำหรับ Dropdown
    $all_employees_result->data_seek(0);

    // เริ่มสร้าง HTML Form สำหรับส่งกลับไปที่ Modal
    ?>
    <div class="modal-header">
        <h2><i class="fas fa-user-tie"></i> แก้ไขหัวหน้างาน</h2>
        <button onclick="closeModal()">&times;</button>
    </div>
    <form method="POST" action="manage_employees.php?action=update_supervisor&id=<?= $edit_employee_id ?>">
        
        <div class="form-group">
            <label>พนักงาน:</label>
            <input type="text" value="<?= htmlspecialchars($employee_data_for_edit['first_name'].' '.$employee_data_for_edit['last_name']) ?>" disabled>
        </div>

        <div class="form-group">
            <label>หัวหน้างานคนใหม่:</label>
            <select name="supervisor_id" required>
                <option value="0">--- ไม่มีหัวหน้างาน (Clear) ---</option>
                <?php while($emp = $all_employees_result->fetch_assoc()): ?>
                    <?php if ($emp['employee_id'] != $edit_employee_id): // ห้ามตั้งตัวเองเป็นหัวหน้างาน ?>
                    <option value="<?= $emp['employee_id'] ?>" 
                            <?= $emp['employee_id'] == $employee_data_for_edit['supervisor_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?>
                    </option>
                    <?php endif; ?>
                <?php endwhile; ?>
            </select>
        </div>
        
        <div style="text-align: right; margin-top: 20px;">
            <button type="submit" class="btn-submit"><i class="fas fa-save"></i> บันทึกการแก้ไข</button>
        </div>
    </form>
    <?php
    exit; // สำคัญ: หยุดการทำงานของ PHP ที่นี่เพื่อส่ง Form HTML กลับไป
}

// -----------------------------------------------------------------------------
// READ EMPLOYEES LIST
// -----------------------------------------------------------------------------

// SQL เงื่อนไข
$search_term = $_GET['search'] ?? '';
$is_searching = !empty($search_term);

$where_clauses = [];
if ($user_role === 'หัวหน้างาน') {
    // หัวหน้างาน: ดูได้เฉพาะตัวเองและพนักงานที่ตัวเองดูแล
    $where_clauses[] = "(E.employee_id = $current_user_id OR E.supervisor_id = $current_user_id)";
}
if ($is_searching) {
    $search = $conn->real_escape_string($search_term);
    $where_clauses[] = "(E.first_name LIKE '%$search%' OR E.last_name LIKE '%$search%' OR E.position LIKE '%$search%' OR DT.department_name LIKE '%$search%')";
}
$where_clause = $where_clauses ? "WHERE ".implode(' AND ', $where_clauses) : "";

// SQL ดึงข้อมูลพนักงาน
$sql = "
    SELECT 
        E.employee_id,
        E.first_name,
        E.last_name,
        E.position,
        DT.department_name,
        S.first_name AS supervisor_name,
        S.last_name AS supervisor_last_name
    FROM EMPLOYEE E
    JOIN Department_Type DT ON E.department_id = DT.department_id
    LEFT JOIN EMPLOYEE S ON E.supervisor_id = S.employee_id
    $where_clause
    ORDER BY E.employee_id ASC";
$employees_result = $conn->query($sql);
$conn->close();
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>จัดการข้อมูลพนักงาน | LALA MUKHA</title>
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
.action-btn{display:inline-flex;justify-content:center;align-items:center;width:36px;height:36px;border-radius:50%;font-size:16px;transition:0.2s;margin-right:5px;border:2px solid;color:inherit;background:transparent;}
.action-edit{border-color:#17A2B8;color:#17A2B8;}
.action-edit:hover{background:#17A2B8;color:#fff;}
.action-delete{border-color:#c00;color:#c00;}
.action-delete:hover{background:#c00;color:#fff;}
.alert-success{color:#2e7d32;background:#dff0d8;padding:10px 15px;border-radius:6px;margin-bottom:20px;border-left:4px solid #66bb6a;}
.alert-error{color:#c00;background:#fdd;padding:10px 15px;border-radius:6px;margin-bottom:20px;border-left:4px solid #f44336;}

/* NEW/MODIFIED STYLES FOR MODAL */
.modal {
    display: none; 
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow: auto;
    background-color: rgba(0,0,0,0.4); 
}
.modal-content {
    background-color: #fff;
    margin: 5% auto; 
    padding: 20px;
    border-radius: 12px;
    width: 80%;
    max-width: 500px;
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
    font-size: 1.3em;
}
.modal-header button {
    background: none;
    border: none;
    font-size: 2em;
    cursor: pointer;
    color: #888;
}
.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 500;
    color: #555;
}
.form-group input, .form-group select {
    width: 100%;
    padding: 10px;
    border: 1px solid #ccc;
    border-radius: 4px;
    box-sizing: border-box;
    font-family: 'Prompt', sans-serif;
}
.btn-submit {
    background:#17A2B8;color:#fff;padding:10px 20px;border:none;border-radius:5px;cursor:pointer;font-weight:500;
}
/* ********************************* */
</style>
</head>
<body>

<div class="sidebar">
    <div class="sidebar-header">LALA MUKHA<br>ระบบจัดการพนักงาน</div>
    <a href="admin_dashboard.php" class="menu-item"><i class="fas fa-tachometer-alt"></i> ภาพรวมระบบ</a>
    <a href="manage_leave.php" class="menu-item"><i class="fas fa-clipboard-list"></i> จัดการการลา</a>
    <a href="manage_users.php" class="menu-item"><i class="fas fa-user"></i> ข้อมูลผู้ใช้</a>
    <a href="manage_employees.php" class="menu-item active"><i class="fas fa-users"></i> ข้อมูลพนักงาน</a>
    <a href="manage_leavetype.php" class="menu-item"><i class="fas fa-list"></i> ประเภทการลา</a>
    <a href="manage_department.php" class="menu-item"><i class="fas fa-building"></i> แผนก</a>
    <a href="manage_holiday.php" class="menu-item"><i class="fas fa-calendar-alt"></i> วันหยุดพิเศษ</a>
    <a href="logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> ออกจากระบบ</a>
</div>

<div class="main-content">
    <div class="header">
        <div class="search-box">
            <form method="GET" action="manage_employees.php" style="display: flex; align-items: center;">
                <i class="fas fa-search" style="color:#aaa;"></i>
                <input type="text" name="search" placeholder="ค้นหาพนักงาน..." value="<?= htmlspecialchars($search_term) ?>">
            </form>
        </div>
        <div class="user-profile">
            <span><?= htmlspecialchars($user_name) ?> (<?= $user_role ?>)</span>
            <i class="fas fa-user-circle" style="font-size:1.8em;#004030"></i>
        </div>
    </div>

    <div class="card">
        <h1>รายชื่อพนักงาน</h1>

        <?php if($status_message): ?>
            <div class="<?= strpos($status_message,'✅')!==false?'alert-success':'alert-error' ?>">
                <?= $status_message ?>
            </div>
        <?php endif; ?>

        <a href="manage_users.php?action=add" class="btn-add"><i class="fas fa-plus"></i> เพิ่มพนักงาน</a>

        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>ชื่อ-นามสกุล</th>
                    <th>ตำแหน่ง</th>
                    <th>แผนก</th>
                    <th>หัวหน้างาน</th>
                    <th>จัดการ</th>
                </tr>
            </thead>
            <tbody>
            <?php if($employees_result && $employees_result->num_rows>0): ?>
                <?php while($row=$employees_result->fetch_assoc()):
                    $supervisor = $row['supervisor_name'] ? $row['supervisor_name'].' '.$row['supervisor_last_name'] : '-';
                ?>
                <tr>
                    <td><?= htmlspecialchars($row['employee_id']) ?></td>
                    <td><?= htmlspecialchars($row['first_name'].' '.$row['last_name']) ?></td>
                    <td><?= htmlspecialchars($row['position']) ?></td>
                    <td><?= htmlspecialchars($row['department_name']) ?></td>
                    <td><?= htmlspecialchars($supervisor) ?></td>
                    <td>
                        <a href="#" onclick="openEditModal(<?= $row['employee_id'] ?>)" class="action-btn action-edit" title="แก้ไขหัวหน้างาน">
                            <i class="fas fa-user-tie"></i>
                        </a>
                        <a href="manage_balance.php?employee_id=<?= $row['employee_id'] ?>" class="action-btn" style="border-color:#66BB6A; color:#66BB6A;" title="จัดการยอดวันลา">
                            <i class="fas fa-clock-rotate-left"></i>
                        </a>
                    </td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="6" style="text-align:center;">ไม่พบข้อมูลพนักงาน<?= $is_searching ? " ที่ตรงกับการค้นหา" : "" ?></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="editSupervisorModal" class="modal" onclick="if(event.target.id === 'editSupervisorModal') closeModal()">
    <div class="modal-content">
        <div id="modalFormContainer" style="text-align: center;">
            <i class="fas fa-spinner fa-spin" style="font-size: 2em; color: var(--primary);"></i>
            <p>กำลังโหลดฟอร์ม...</p>
        </div>
    </div>
</div>
<script>
const editSupervisorModal = document.getElementById('editSupervisorModal');
const modalFormContainer = document.getElementById('modalFormContainer');

function openEditModal(employeeId) {
    editSupervisorModal.style.display = 'block';
    
    // แสดง loading state
    modalFormContainer.innerHTML = '<div style="text-align: center; padding: 20px;"><i class="fas fa-spinner fa-spin" style="font-size: 2em; color: var(--primary);"></i><p>กำลังโหลดฟอร์ม...</p></div>';

    // ใช้ Fetch API เพื่อดึงเฉพาะ Form HTML
    fetch(`manage_employees.php?action=fetch_supervisor_form&id=${employeeId}`)
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
    editSupervisorModal.style.display = 'none';
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