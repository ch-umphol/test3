<?php
session_start();
require_once '../conn.php'; 

// 1. ตรวจสอบสิทธิ์การเข้าถึง (Manager / Admin)
if (!isset($_SESSION['loggedin']) || !in_array($_SESSION['user_role'], ['manager', 'admin'])) {
    header("location: ../login.php");
    exit;
}

$emp_id = $_SESSION['emp_id']; 
$user_role = $_SESSION['user_role'];
$user_name_session = $_SESSION['username'];
$action = $_GET['action'] ?? 'read';
$edit_emp_id = (int)($_GET['id'] ?? 0); 
$status_message = $_SESSION['status_message'] ?? '';
unset($_SESSION['status_message']);

// --- ดึงข้อมูลผู้ใช้งานปัจจุบัน (Header) ---
$sql_user = "SELECT first_name, last_name, dept_id FROM employees WHERE emp_id = ?";
if ($stmt_user = $conn->prepare($sql_user)) {
    $stmt_user->bind_param("i", $emp_id);
    $stmt_user->execute();
    $result_user = $stmt_user->get_result();
    if ($row_user = $result_user->fetch_assoc()) {
        $user_display_name = htmlspecialchars($row_user['first_name'] . ' ' . $row_user['last_name']);
    }
}

// ------------------ HANDLING RESIGNATION ------------------
if ($action === 'resign' && $edit_emp_id > 0) {
    $current_date = date('Y-m-d');
    $sql_resign = "UPDATE employees SET resign_date = '$current_date' WHERE emp_id = $edit_emp_id";
    if ($conn->query($sql_resign)) {
        $_SESSION['status_message'] = "✅ บันทึกสถานะลาออกสำเร็จ";
    }
    header("location: manage_employees.php");
    exit;
}

// ------------------ HANDLING UPDATE ROLE/DEPT ------------------
if($action === 'update_supervisor' && $edit_emp_id > 0 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_role_id = (int)$_POST['role_id'];
    $new_dept_id = (int)$_POST['dept_id'];
    $new_supervisor_id = (int)$_POST['supervisor_id']; 
    $supervisor_val = ($new_supervisor_id > 0) ? $new_supervisor_id : "NULL";
    $sql_update = "UPDATE employees SET role_id = $new_role_id, dept_id = $new_dept_id, supervisor_id = $supervisor_val WHERE emp_id = $edit_emp_id";
    if ($conn->query($sql_update)) $_SESSION['status_message'] = "✅ อัปเดตข้อมูลสำเร็จ!";
    header("location: manage_employees.php"); exit;
}

// ------------------ FETCH FORM FOR MODAL ------------------
if ($action === 'fetch_supervisor_form' && $edit_emp_id > 0) {
    $data = $conn->query("SELECT * FROM employees WHERE emp_id = $edit_emp_id")->fetch_assoc();
    $supervisors_result = $conn->query("SELECT emp_id, first_name, last_name FROM employees WHERE role_id IN (2, 3) ORDER BY first_name ASC");
    $departments_result = $conn->query("SELECT dept_id, dept_name FROM departments ORDER BY dept_name ASC");
    $roles_result = $conn->query("SELECT role_id, role_display FROM roles ORDER BY role_id ASC");
    ?>
    <div style="text-align:left;">
        <h2 style="color:var(--primary);"><i class="fas fa-user-edit"></i> แก้ไขบทบาท: <?= htmlspecialchars($data['first_name']) ?></h2>
        <form method="POST" action="manage_employees.php?action=update_supervisor&id=<?= $edit_emp_id ?>">
            <label>บทบาท:</label><br>
            <select name="role_id" style="width:100%; padding:10px; margin-bottom:10px; border-radius:8px;">
                <?php while($role = $roles_result->fetch_assoc()): ?>
                    <option value="<?= $role['role_id'] ?>" <?= $role['role_id'] == $data['role_id'] ? 'selected' : '' ?>><?= $role['role_display'] ?></option>
                <?php endwhile; ?>
            </select><br>
            <label>แผนก:</label><br>
            <select name="dept_id" style="width:100%; padding:10px; margin-bottom:10px; border-radius:8px;">
                <?php while($dep = $departments_result->fetch_assoc()): ?>
                    <option value="<?= $dep['dept_id'] ?>" <?= $dep['dept_id'] == $data['dept_id'] ? 'selected' : '' ?>><?= $dep['dept_name'] ?></option>
                <?php endwhile; ?>
            </select><br>
            <label>หัวหน้างาน:</label><br>
            <select name="supervisor_id" style="width:100%; padding:10px; margin-bottom:20px; border-radius:8px;">
                <option value="0">--- ไม่มีหัวหน้างาน ---</option>
                <?php while($boss = $supervisors_result->fetch_assoc()): if($boss['emp_id'] != $edit_emp_id): ?>
                    <option value="<?= $boss['emp_id'] ?>" <?= $boss['emp_id'] == $data['supervisor_id'] ? 'selected' : '' ?>><?= $boss['first_name'].' '.$boss['last_name'] ?></option>
                <?php endif; endwhile; ?>
            </select>
            <button type="submit" class="btn-submit" style="background:var(--primary); color:#fff; padding:10px; border:none; border-radius:8px; cursor:pointer; width:100%;">บันทึกแก้ไข</button>
        </form>
    </div>
    <?php exit;
}

// --- PAGINATION & DATA FETCH ---
$records_per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $records_per_page;
$search_term = $_GET['search'] ?? '';
$where_sql = $search_term ? "WHERE (E.first_name LIKE '%$search_term%' OR E.last_name LIKE '%$search_term%' OR E.emp_code LIKE '%$search_term%')" : "";

$sql_employees = "
    SELECT E.*, D.dept_name, R.role_display, R.role_name, S.first_name AS boss_f, S.last_name AS boss_l
    FROM employees E
    LEFT JOIN departments D ON E.dept_id = D.dept_id 
    LEFT JOIN roles R ON E.role_id = R.role_id 
    LEFT JOIN employees S ON E.supervisor_id = S.emp_id
    $where_sql ORDER BY E.emp_id ASC LIMIT $records_per_page OFFSET $offset";
$employees_result = $conn->query($sql_employees);
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<title>จัดการพนักงาน | LALA MUKHA</title>
<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root {--primary:#004030;--secondary:#66BB6A;--bg:#f5f7fb;--text:#2e2e2e;--danger:#e53935;}
body{margin:0;font-family:'Prompt',sans-serif;display:flex;background:var(--bg);color:var(--text);}
.sidebar{width:250px;background:linear-gradient(180deg,var(--primary),#2e7d32);color:#fff;position:fixed;height:100%;display:flex;flex-direction:column;box-shadow:3px 0 10px rgba(0,0,0,0.1);}
.sidebar-header{padding:25px;text-align:center;font-weight:600;border-bottom:1px solid rgba(255,255,255,0.2);}
.menu-item{display:flex;align-items:center;padding:15px 25px;color:rgba(255,255,255,0.85);text-decoration:none;transition:0.2s;}
.menu-item:hover, .menu-item.active{background:rgba(255,255,255,0.15);color:#fff;font-weight:600;}
.menu-item i{margin-right:12px; width: 20px; text-align: center;}
.logout{margin-top:auto;text-align:center;padding:15px;background:#388e3c;color:#fff;text-decoration:none;}
.main-content{flex-grow:1;margin-left:250px;padding:30px;}
.header{display:flex;justify-content:space-between;align-items:center;background:#fff;padding:15px 25px;border-radius:12px;box-shadow:0 4px 10px rgba(0,0,0,0.05);margin-bottom:25px;}
.search-box{background:#f7f7f7;border-radius:25px;padding:6px 15px;display:flex;align-items:center;}
.search-box input{border:none;outline:none;background:transparent;width:200px;padding-left:10px;font-family:'Prompt';}
.card{background:#fff;border-radius:16px;padding:25px;box-shadow:0 6px 20px rgba(0,0,0,0.05);}
.data-table{width:100%;border-collapse:collapse;font-size:0.85em;}
.data-table th, .data-table td{padding:12px;border-bottom:1px solid #eee;text-align:left;}
.role-badge { padding: 3px 8px; border-radius: 12px; font-size: 0.85em; color: #fff; }
.role-admin { background: #d32f2f; } .role-manager { background: #2e7d32; } .role-supervisor { background: #1976d2; } .role-employee { background: #757575; }
.action-btn{display:inline-flex;justify-content:center;align-items:center;width:30px;height:30px;border-radius:50%;border:1px solid #ddd;color:#666;text-decoration:none;margin-right:5px;transition:0.2s;}
.action-btn:hover{background:#f0f0f0;}
.btn-stats{color:var(--secondary); border-color:var(--secondary); cursor:pointer;}
.modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); }
.modal-content { background: #fff; margin: 5% auto; padding: 25px; border-radius: 16px; width: 90%; max-width: 700px; position: relative; }
.stat-badge { background:#f1f8f1; padding:10px; border-radius:10px; text-align:center; border:1px solid #e0eee0; flex:1; margin:5px;}
.btn-cancel-leave { background: #fff5f5; color: var(--danger); border: 1px solid #feb2b2; padding: 4px 8px; border-radius: 6px; cursor: pointer; font-size: 0.8em; }
</style>
</head>
<body>

<div class="sidebar">
    <div class="sidebar-header">LALA MUKHA<br>ระบบจัดการพนักงาน</div>
    <a href="manager_dashboard.php" class="menu-item"><i class="fas fa-tachometer-alt"></i> ภาพรวมระบบ</a>
    <a href="manage_leave.php" class="menu-item"><i class="fas fa-clipboard-list"></i> จัดการการลา</a>
    <a href="manage_employees.php" class="menu-item active"><i class="fas fa-users"></i> ข้อมูลพนักงาน</a>
    <a href="manage_leavetype.php" class="menu-item"><i class="fas fa-list"></i> ประเภทการลา</a>
    <a href="manage_department.php" class="menu-item"><i class="fas fa-building"></i> แผนก</a>
    <a href="manage_holidays.php" class="menu-item"><i class="fas fa-calendar-alt"></i> จัดการวันหยุดพิเศษ</a>
    <a href="report.php" class="menu-item"><i class="fas fa-file-pdf"></i> ออกรายงาน</a>
    <a href="logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> ออกจากระบบ</a>
</div>

<div class="main-content">
    <div class="header">
        <div class="search-box">
            <form method="GET"><i class="fas fa-search"></i><input type="text" name="search" placeholder="ค้นหาพนักงาน..." value="<?= htmlspecialchars($search_term) ?>"></form>
        </div>
        <div><span><?= $user_display_name ?> (<?= strtoupper($user_role) ?>)</span><i class="fas fa-user-circle" style="font-size:1.8em; color:var(--primary); margin-left:10px;"></i></div>
    </div>

    <div class="card">
        <h2>รายชื่อพนักงาน</h2>
        <?php if($status_message): ?><div style="padding:10px; background:#e8f5e9; color:#2e7d32; border-radius:8px; margin-bottom:15px;"><?= $status_message ?></div><?php endif; ?>

        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th><th>ชื่อ-นามสกุล</th><th>บทบาท</th><th>แผนก</th><th>หัวหน้า</th><th>จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php while($row=$employees_result->fetch_assoc()): ?>
                <tr>
                    <td><?= $row['emp_id'] ?></td>
                    <td><?= htmlspecialchars($row['first_name'].' '.$row['last_name']) ?></td>
                    <td><span class="role-badge role-<?= $row['role_name'] ?>"><?= $row['role_display'] ?></span></td>
                    <td><?= htmlspecialchars($row['dept_name'] ?: '-') ?></td>
                    <td><?= $row['boss_f'] ? htmlspecialchars($row['boss_f'].' '.$row['boss_l']) : '-' ?></td>
                    <td>
                        <a href="#" onclick="openEditModal(<?= $row['emp_id'] ?>)" class="action-btn" title="แก้ไขบทบาท/แผนก"><i class="fas fa-user-edit"></i></a>
                        <a href="#" onclick="viewLeaveStats(<?= $row['emp_id'] ?>, '<?= htmlspecialchars($row['first_name']) ?>')" class="action-btn btn-stats" title="ดูสถิติ/คืนสิทธิ์"><i class="fas fa-chart-line"></i></a>
                        <a href="manage_balance.php?emp_id=<?= $row['emp_id'] ?>" class="action-btn" style="color:#fb8c00; border-color:#fb8c00;" title="จัดการสิทธิ์วันลา"><i class="fas fa-calendar-check"></i></a>
                        <?php if(!$row['resign_date']): ?>
                        <a href="?action=resign&id=<?= $row['emp_id'] ?>" class="action-btn" style="color:var(--danger); border-color:var(--danger);" onclick="return confirm('ยืนยันบันทึกการลาออก?')" title="ลาออก"><i class="fas fa-door-open"></i></a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="editModal" class="modal" onclick="if(event.target.id === 'editModal') closeModal()">
    <div class="modal-content"><div id="modalFormContainer" style="text-align:center;">กำลังโหลด...</div></div>
</div>

<div id="statsModal" class="modal" onclick="if(event.target.id === 'statsModal') closeStatsModal()">
    <div class="modal-content">
        <span onclick="closeStatsModal()" style="float:right; cursor:pointer; font-size:24px;">&times;</span>
        <h2 id="statsTitle" style="color:var(--primary);"></h2>
        <div id="balanceSection" style="display:flex; flex-wrap:wrap; gap:10px; margin-bottom:20px;"></div>
        <div style="max-height:400px; overflow-y:auto;">
            <table class="data-table">
                <thead><tr><th>ประเภท</th><th>วันที่ลา</th><th>สถานะ</th><th>จัดการ</th></tr></thead>
                <tbody id="historyTableBody"></tbody>
            </table>
        </div>
        <button onclick="closeStatsModal()" style="margin-top:20px; padding:10px 20px; cursor:pointer; border-radius:8px; border:none; background:#eee;">ปิดหน้าต่าง</button>
    </div>
</div>

<script>
function openEditModal(empId) {
    document.getElementById('editModal').style.display = 'block';
    fetch(`manage_employees.php?action=fetch_supervisor_form&id=${empId}`)
        .then(r => r.text()).then(html => { document.getElementById('modalFormContainer').innerHTML = html; });
}
function closeModal() { document.getElementById('editModal').style.display = 'none'; }

function viewLeaveStats(empId, empName) {
    document.getElementById('statsTitle').innerText = "ข้อมูลการลาของ: " + empName;
    document.getElementById('statsModal').style.display = 'block';
    const balanceDiv = document.getElementById('balanceSection');
    const tableBody = document.getElementById('historyTableBody');
    
    balanceDiv.innerHTML = 'กำลังโหลด...';
    tableBody.innerHTML = '<tr><td colspan="4" style="text-align:center;">กำลังโหลด...</td></tr>';
    
    fetch(`get_employee_leave_stats.php?emp_id=${empId}`)
        .then(res => res.json())
        .then(data => {
            // แสดงยอดคงเหลือ
            balanceDiv.innerHTML = data.balances.map(b => `
                <div class="stat-badge">
                    <small>${b.leave_type_display}</small><br>
                    <b>${parseFloat(b.remaining)} ${b.leave_unit == 'hour' ? 'ชม.' : 'วัน'}</b>
                </div>
            `).join('') || 'ไม่พบข้อมูลสิทธิ์';

            // แสดงประวัติและเช็คสิทธิ์คืนสิทธิ์ (เฉพาะหัวหน้างาน Role ID 3)
            tableBody.innerHTML = data.history.map(h => {
                // เงื่อนไข: สถานะ Approved และ ต้องเป็นหัวหน้างาน (role_id 3) เท่านั้นที่ Manager จะเห็นปุ่มคืนสิทธิ์
                let canCancel = (h.status === 'Approved' && data.employee_role_id == 3); 
                let actionBtn = canCancel 
                    ? `<button class="btn-cancel-leave" onclick="confirmCancel(${h.request_id})"><i class="fas fa-undo"></i> คืนสิทธิ์</button>` 
                    : '-';

                return `
                    <tr>
                        <td>${h.leave_display}</td>
                        <td>${h.start_date}</td>
                        <td><b style="color:${h.status=='Approved'?'#2e7d32':(h.status=='Rejected'?'#d32f2f':'#ef6c00')}">${h.status}</b></td>
                        <td>${actionBtn}</td>
                    </tr>`;
            }).join('') || '<tr><td colspan="4" style="text-align:center;">ไม่พบประวัติการลา</td></tr>';
        });
}

function confirmCancel(id) {
    if (confirm("ยืนยันการยกเลิกรายการลาและคืนสิทธิ์ให้พนักงานท่านนี้?")) {
        window.location.href = `cancel_leave_action.php?request_id=${id}`;
    }
}
function closeStatsModal() { document.getElementById('statsModal').style.display = 'none'; }
</script>
</body>
</html>