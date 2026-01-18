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
$status_message = $_SESSION['status_message'] ?? '';
unset($_SESSION['status_message']);

// ดึงชื่อจริงผู้ใช้งาน (เมนูเดิม)
$sql_user = "SELECT first_name, last_name FROM employees WHERE emp_id = $emp_id";
$res_user = $conn->query($sql_user);
$user_display_name = ($row_u = $res_user->fetch_assoc()) ? $row_u['first_name'].' '.$row_u['last_name'] : $user_name;

// ------------------ PAGINATION LOGIC ------------------
$limit = 10; 
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

$total_res = $conn->query("SELECT COUNT(*) as total FROM public_holidays");
$total_rows = $total_res->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $limit);

// ------------------ HANDLING ACTIONS ------------------

// 1. คืนสิทธิ์ (ลบประวัติ + คืนโควตาวันลา + ลบรายการคำขอ)
if (isset($_GET['action']) && $_GET['action'] === 'revoke_usage' && isset($_GET['usage_id'])) {
    $usage_id = (int)$_GET['usage_id'];
    $conn->begin_transaction();

    try {
        // ดึงข้อมูลพนักงานและวันที่วันหยุด
        $sql_info = "SELECT r.emp_id, h.holiday_date FROM holiday_usage_records r 
                     JOIN public_holidays h ON r.holiday_id = h.holiday_id WHERE r.usage_id = ?";
        $stmt_info = $conn->prepare($sql_info);
        $stmt_info->bind_param("i", $usage_id);
        $stmt_info->execute();
        $res_info = $stmt_info->get_result();
        
        if ($info = $res_info->fetch_assoc()) {
            $target_emp = $info['emp_id'];
            $h_date = $info['holiday_date'];
            $target_year = date('Y', strtotime($h_date));

            // คืนจำนวนวันลา (หักคืนจาก used_days ในโควตาลากิจ ID: 2)
            $sql_refund = "UPDATE employee_leave_balances SET used_days = used_days - 1 
                           WHERE emp_id = ? AND leave_type_id = 2 AND year = ?";
            $stmt_refund = $conn->prepare($sql_refund);
            $stmt_refund->bind_param("ii", $target_emp, $target_year);
            $stmt_refund->execute();

            // ลบคำขอลาในระบบที่ระบุวันที่นี้
            $search_reason = "%($h_date)%";
            $sql_del_req = "DELETE FROM leave_requests WHERE emp_id = ? AND reason LIKE ?";
            $stmt_del_req = $conn->prepare($sql_del_req);
            $stmt_del_req->bind_param("is", $target_emp, $search_reason);
            $stmt_del_req->execute();

            // ลบประวัติการใช้สิทธิ์
            $conn->query("DELETE FROM holiday_usage_records WHERE usage_id = $usage_id");

            $conn->commit();
            $_SESSION['status_message'] = "✅ คืนสิทธิ์และคืนวันลาสำเร็จ";
        }
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['status_message'] = "❌ ผิดพลาด: " . $e->getMessage();
    }
    header("location: manage_holidays.php?page=$page");
    exit;
}

// 2. ลบวันหยุด
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    if ($conn->query("DELETE FROM public_holidays WHERE holiday_id = $id")) {
        $_SESSION['status_message'] = "✅ ลบวันหยุดสำเร็จ";
    } else {
        $_SESSION['status_message'] = "❌ ไม่สามารถลบได้ (กรุณาคืนสิทธิ์พนักงานก่อน)";
    }
    header("location: manage_holidays.php?page=$page");
    exit;
}

// 3. เพิ่ม/แก้ไขวันหยุด
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_holiday'])) {
    $h_name = $conn->real_escape_string($_POST['holiday_name']);
    $h_date = $conn->real_escape_string($_POST['holiday_date']);
    $edit_id = isset($_POST['edit_id']) ? (int)$_POST['edit_id'] : 0;

    $sql = ($edit_id > 0) 
        ? "UPDATE public_holidays SET holiday_name='$h_name', holiday_date='$h_date' WHERE holiday_id=$edit_id"
        : "INSERT INTO public_holidays (holiday_name, holiday_date) VALUES ('$h_name', '$h_date')";

    if ($conn->query($sql)) {
        $_SESSION['status_message'] = "✅ บันทึกข้อมูลเรียบร้อย";
    }
    header("location: manage_holidays.php?page=$page");
    exit;
}

// ------------------ FETCH DATA ------------------
$sql_holidays = "SELECT h.*, (SELECT COUNT(*) FROM holiday_usage_records WHERE holiday_id = h.holiday_id) as usage_count 
                FROM public_holidays h ORDER BY h.holiday_date ASC LIMIT $limit OFFSET $offset";
$result_holidays = $conn->query($sql_holidays);

$usage_details = [];
$res_usage = $conn->query("SELECT r.usage_id, r.holiday_id, e.first_name, e.last_name FROM holiday_usage_records r
                            JOIN employees e ON r.emp_id = e.emp_id");
while($u = $res_usage->fetch_assoc()) { $usage_details[$u['holiday_id']][] = $u; }
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>จัดการวันหยุด | LALA MUKHA</title>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {--primary:#004030;--secondary:#66BB6A;--bg:#f5f7fb;--text:#2e2e2e;}
        body{margin:0;font-family:'Prompt',sans-serif;display:flex;background:var(--bg);color:var(--text);}
        .sidebar{width:250px;background:linear-gradient(180deg,var(--primary),#2e7d32);color:#fff;position:fixed;height:100%;display:flex;flex-direction:column;}
        .sidebar-header{padding:25px;text-align:center;font-weight:600;border-bottom:1px solid rgba(255,255,255,0.2);}
        .menu-item{display:flex;align-items:center;padding:15px 25px;color:rgba(255,255,255,0.85);text-decoration:none;}
        .menu-item:hover, .menu-item.active{background:rgba(255,255,255,0.15);color:#fff;font-weight:600;}
        .menu-item i{margin-right:12px; width:20px; text-align:center;}
        .main-content{flex-grow:1;margin-left:250px;padding:30px;}
        .header{display:flex;justify-content:space-between;align-items:center;background:#fff;padding:15px 25px;border-radius:12px;box-shadow:0 4px 10px rgba(0,0,0,0.05);margin-bottom:25px;}
        .card{background:#fff;border-radius:16px;padding:25px;box-shadow:0 6px 20px rgba(0,0,0,0.05);margin-bottom:25px;}
        .card h1{margin-top:0;color:var(--primary); font-size:1.4em; display:flex; align-items:center; gap:10px;}
        .data-table{width:100%;border-collapse:collapse;margin-top:15px;}
        .data-table th, .data-table td{padding:12px 15px; border-bottom:1px solid #eee; text-align:left;}
        .data-table th{background:#f8f9fa; color:var(--primary);}
        .form-inline { display: flex; gap: 15px; flex-wrap: wrap; background: #f9f9f9; padding: 20px; border-radius: 12px; border: 1px dashed #ccc; }
        .form-group { flex: 1; min-width: 200px; display: flex; flex-direction: column; gap: 5px; }
        .form-group input { padding: 12px; border: 1px solid #ddd; border-radius: 8px; font-family: 'Prompt'; }
        .btn-save { background: var(--primary); color: white; border: none; padding: 0 30px; border-radius: 8px; cursor: pointer; font-weight: 600; height: 48px; align-self: flex-end; }
        .btn-cancel { background: #eee; color: #333; border: none; padding: 0 20px; border-radius: 8px; cursor: pointer; height: 48px; align-self: flex-end; display:none; }
        .action-btn { width: 32px; height: 32px; border-radius: 6px; display: inline-flex; align-items: center; justify-content: center; text-decoration: none; border: none; cursor: pointer; }
        .btn-edit { background: #fff3e0; color: #ef6c00; margin-right: 5px; }
        .btn-delete { background: #ffebee; color: #c62828; }
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; background: #e8f5e9; color: #2e7d32; border-left: 5px solid #4caf50; }
        .usage-badge { background: #e3f2fd; color: #1976d2; padding: 4px 12px; border-radius: 20px; font-size: 0.85em; font-weight: 600; }
        .usage-list { margin-top: 10px; font-size: 0.9em; border: 1px solid #f0f0f0; border-radius: 8px; overflow: hidden; }
        .usage-item { display: flex; justify-content: space-between; align-items: center; padding: 8px 12px; background: #fff; border-bottom: 1px solid #f0f0f0; }
        .btn-revoke { color: #d32f2f; text-decoration: none; font-size: 0.8em; border: 1px solid #ffcdd2; padding: 2px 8px; border-radius: 4px; }
        .pagination { display: flex; justify-content: center; gap: 8px; margin-top: 25px; }
        .page-link { padding: 8px 16px; background: #fff; border: 1px solid #ddd; border-radius: 8px; text-decoration: none; color: var(--primary); }
        .page-link.active { background: var(--primary); color: #fff; }
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
        <a href="manage_department.php" class="menu-item"><i class="fas fa-building"></i> แผนก</a>
        <a href="manage_holidays.php" class="menu-item active"><i class="fas fa-calendar-alt"></i> จัดการวันหยุดพิเศษ</a>
        <a href="report.php" class="menu-item"><i class="fas fa-file-pdf"></i> ออกรายงาน</a>
    </div>
    <a href="logout.php" style="padding:15px; background:#388e3c; color:#fff; text-decoration:none; text-align:center;"><i class="fas fa-sign-out-alt"></i> ออกจากระบบ</a>
</div>

<div class="main-content">
    <div class="header">
        <div style="font-weight:600; color:var(--primary);">จัดการวันหยุด (หน้า <?= $page ?>/<?= $total_pages ?>)</div>
        <div style="display:flex; align-items:center; gap:10px;">
            <span><?= htmlspecialchars($user_display_name) ?></span>
            <i class="fas fa-user-circle" style="font-size:1.8em; color:var(--primary);"></i>
        </div>
    </div>

    <div class="card">
        <h1 id="form-title"><i class="fas fa-calendar-plus"></i> จัดการข้อมูลวันหยุด</h1>
        <form method="POST" class="form-inline" id="holiday-form">
            <input type="hidden" name="edit_id" id="edit_id" value="0">
            <div class="form-group">
                <label>ชื่อวันหยุด</label>
                <input type="text" name="holiday_name" id="holiday_name" required>
            </div>
            <div class="form-group">
                <label>วันที่</label>
                <input type="date" name="holiday_date" id="holiday_date" required>
            </div>
            <button type="submit" name="save_holiday" class="btn-save">บันทึก</button>
            <button type="button" onclick="resetForm()" id="cancel-btn" class="btn-cancel">ยกเลิก</button>
        </form>
    </div>

    <div class="card">
        <h1><i class="fas fa-calendar-alt"></i> รายการวันหยุดและการใช้สิทธิ์</h1>
        <?php if($status_message): ?><div class="alert"><?= $status_message ?></div><?php endif; ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>วันที่</th>
                    <th>ชื่อวันหยุด</th>
                    <th>พนักงานที่ใช้สิทธิ์</th>
                    <th style="text-align: center;">จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php while($row = $result_holidays->fetch_assoc()): 
                    $h_id = $row['holiday_id']; 
                ?>
                <tr>
                    <td style="vertical-align: top;"><strong><?= date('d/m/Y', strtotime($row['holiday_date'])) ?></strong></td>
                    <td style="vertical-align: top;"><?= htmlspecialchars($row['holiday_name']) ?></td>
                    <td>
                        <span class="usage-badge">ใช้สิทธิ์ <?= $row['usage_count'] ?> คน</span>
                        <?php if(isset($usage_details[$h_id])): ?>
                            <div class="usage-list">
                                <?php foreach($usage_details[$h_id] as $user): ?>
                                    <div class="usage-item">
                                        <span><?= htmlspecialchars($user['first_name'].' '.$user['last_name']) ?></span>
                                        <a href="?action=revoke_usage&usage_id=<?= $user['usage_id'] ?>&page=<?= $page ?>" class="btn-revoke" onclick="return confirm('คืนสิทธิ์และคืนวันลาให้พนักงานท่านนี้?')">คืนสิทธิ์</a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td style="vertical-align: top; text-align: center;">
                        <button class="action-btn btn-edit" onclick='editHoliday(<?= json_encode($row) ?>)'><i class="fas fa-edit"></i></button>
                        <a href="?action=delete&id=<?= $h_id ?>&page=<?= $page ?>" class="action-btn btn-delete" onclick="return confirm('ลบวันหยุดนี้?')"><i class="fas fa-trash"></i></a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        <div class="pagination">
            <?php for ($i=1; $i<=$total_pages; $i++): ?>
                <a href="?page=<?= $i ?>" class="page-link <?= ($page == $i) ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
        </div>
    </div>
</div>

<script>
function editHoliday(data) {
    document.getElementById('form-title').innerText = 'แก้ไขข้อมูลวันหยุด';
    document.getElementById('edit_id').value = data.holiday_id;
    document.getElementById('holiday_name').value = data.holiday_name;
    document.getElementById('holiday_date').value = data.holiday_date;
    document.getElementById('cancel-btn').style.display = 'inline-block';
    window.scrollTo({ top: 0, behavior: 'smooth' });
}
function resetForm() {
    document.getElementById('form-title').innerText = 'เพิ่มวันหยุดใหม่';
    document.getElementById('holiday-form').reset();
    document.getElementById('edit_id').value = 0;
    document.getElementById('cancel-btn').style.display = 'none';
}
</script>
</body>
</html>