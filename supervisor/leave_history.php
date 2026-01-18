<?php
session_start();
require_once '../conn.php'; 

// ตรวจสอบสิทธิ์การเข้าถึง (เฉพาะ Supervisor)
if (!isset($_SESSION['loggedin']) || $_SESSION['user_role'] !== 'supervisor') {
    header("location: ../login.php");
    exit;
}

$emp_id = $_SESSION['emp_id'];
$user_role = $_SESSION['user_role'];
$success_message = '';
$error_message = '';

// --- 1. ประมวลผลการยกเลิกการลา (Cancel Request) ---
if (isset($_GET['cancel_id'])) {
    $cancel_id = filter_input(INPUT_GET, 'cancel_id', FILTER_VALIDATE_INT);
    
    // ตรวจสอบก่อนว่าคำร้องนี้เป็นของผู้ใช้คนนี้จริง และสถานะยังเป็น Pending
    $sql_cancel = "UPDATE leave_requests SET status = 'Cancelled' 
                   WHERE request_id = ? AND emp_id = ? AND status = 'Pending'";
    
    if ($stmt_cancel = $conn->prepare($sql_cancel)) {
        $stmt_cancel->bind_param("ii", $cancel_id, $emp_id);
        if ($stmt_cancel->execute()) {
            if ($stmt_cancel->affected_rows > 0) {
                $success_message = "✅ ยกเลิกคำร้องการลาเรียบร้อยแล้ว";
            } else {
                $error_message = "❌ ไม่สามารถยกเลิกได้ (คำร้องอาจถูกอนุมัติไปแล้ว หรือข้อมูลไม่ถูกต้อง)";
            }
        }
        $stmt_cancel->close();
    }
}

// --- 2. ดึงข้อมูลผู้ใช้เพื่อแสดงใน Header ---
$sql_user = "SELECT first_name, last_name FROM employees WHERE emp_id = ?";
$user_display_name = "";
if ($stmt_user = $conn->prepare($sql_user)) {
    $stmt_user->bind_param("i", $emp_id);
    $stmt_user->execute();
    $result_user = $stmt_user->get_result();
    if ($row_user = $result_user->fetch_assoc()) {
        $user_display_name = htmlspecialchars($row_user['first_name'] . ' ' . $row_user['last_name']);
    }
    $stmt_user->close();
}

// --- 3. ดึงประวัติการลาทั้งหมดของผู้ใช้ปัจจุบัน ---
$leave_history = [];
$sql_history = "
    SELECT LR.request_id, LR.status, LT.leave_type_display, LR.start_date, LR.end_date, 
           LR.reason, LR.evidence_file,
           DATEDIFF(LR.end_date, LR.start_date) + 1 AS duration
    FROM leave_requests LR
    JOIN leave_types LT ON LR.leave_type = LT.leave_type_name
    WHERE LR.emp_id = ?
    ORDER BY LR.request_id DESC"; // เรียงตามรายการล่าสุด

if ($stmt_history = $conn->prepare($sql_history)) {
    $stmt_history->bind_param("i", $emp_id);
    $stmt_history->execute();
    $result_history = $stmt_history->get_result();
    while ($row = $result_history->fetch_assoc()) {
        $leave_history[] = $row;
    }
    $stmt_history->close();
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ประวัติการลาส่วนตัว | LALA MUKHA</title>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {--primary:#004030; --secondary:#66BB6A; --accent:#81C784; --bg:#f5f7fb; --text:#2e2e2e; --danger:#dc3545;}
        body {margin:0; font-family:'Prompt',sans-serif; background:var(--bg); display:flex;}
        
        /* Sidebar */
        .sidebar {width:250px; background:linear-gradient(180deg, var(--primary), #2e7d32); color:#fff; position:fixed; height:100%; display:flex; flex-direction:column;}
        .sidebar-header {padding:25px; text-align:center; font-weight:600; border-bottom:1px solid rgba(255,255,255,0.2);}
        .menu-item {display:flex; align-items:center; padding:15px 25px; color:rgba(255,255,255,0.85); text-decoration:none; transition:0.2s;}
        .menu-item:hover, .menu-item.active {background:rgba(255,255,255,0.15); color:#fff;}
        .menu-item i {margin-right:12px; width:20px; text-align:center;}
        
        /* Main Content */
        .main-content {flex-grow:1; margin-left:250px; padding:30px;}
        .header {display:flex; justify-content:space-between; align-items:center; background:#fff; padding:15px 25px; border-radius:12px; box-shadow:0 4px 10px rgba(0,0,0,0.05); margin-bottom:25px;}
        
        .card {background:#fff; border-radius:16px; padding:25px; box-shadow:0 6px 20px rgba(0,0,0,0.05); margin-bottom:25px;}
        
        /* Table Style */
        table {width:100%; border-collapse:collapse; margin-top:15px;}
        th {background:#f8f9fa; color:var(--primary); text-align:left; padding:15px; border-bottom:2px solid #eee;}
        td {padding:15px; border-bottom:1px solid #eee; font-size:0.95em;}
        tr:hover {background:#f1f8e9;}

        /* Status Badges */
        .badge {padding:6px 12px; border-radius:20px; font-size:0.85em; font-weight:600;}
        .badge-pending {background:#fff9c4; color:#fbc02d;}
        .badge-approved {background:#c8e6c9; color:#2e7d32;}
        .badge-rejected {background:#ffcdd2; color:#c62828;}
        .badge-cancelled {background:#e0e0e0; color:#616161;}

        .btn-cancel {color: var(--danger); text-decoration: none; font-weight: 500;}
        .btn-cancel:hover {text-decoration: underline;}
        
        .btn-back {display:inline-block; color:var(--primary); text-decoration:none; margin-bottom:15px; font-weight:500;}
        .no-data {text-align:center; padding:40px; color:#999;}

        .alert {padding:15px; border-radius:8px; margin-bottom:20px;}
        .alert-success {background:#e8f5e9; color:#2e7d32;}
        .alert-danger {background:#ffebee; color:#c62828;}
    </style>
</head>
<body>

    <div class="sidebar">
        <div class="sidebar-header">LALA MUKHA<br>ระบบจัดการพนักงาน</div>
        <a href="supervisor_dashboard.php" class="menu-item"><i class="fas fa-tachometer-alt"></i> ภาพรวมระบบ</a>
        <a href="manage_leave.php" class="menu-item"><i class="fas fa-clipboard-list"></i> จัดการการลา</a>
        <a href="apply_leave.php" class="menu-item"><i class="fas fa-calendar-plus"></i> ยื่นคำร้องการลา</a>
        <a href="manage_employees.php" class="menu-item"><i class="fas fa-users"></i> ข้อมูลพนักงาน</a>
        <a href="public_holidays.php" class="menu-item"><i class="fas fa-calendar-alt"></i><span>ปฏิทินวันหยุด</span></a>
        <a href="leave_history.php" class="menu-item active"><i class="fas fa-history"></i><span>ประวัติการลาส่วนตัว</span></a>
        <a href="logout.php" style="margin-top:auto; background:#388e3c; text-align:center; padding:15px; color:#fff; text-decoration:none;"><i class="fas fa-sign-out-alt"></i> ออกจากระบบ</a>
    </div>

    <div class="main-content">
        <div class="header">
            <div><strong>ประวัติการลาส่วนตัว</strong></div>
            <div class="user-profile">
                <span><strong><?= $user_display_name ?></strong> (<?= strtoupper($user_role) ?>)</span>
                <i class="fas fa-user-circle" style="font-size:1.8em; color:var(--primary); margin-left:10px;"></i>
            </div>
        </div>

        <a href="supervisor_dashboard.php" class="btn-back"><i class="fas fa-arrow-left"></i> กลับสู่หน้าหลัก</a>

        <?php if($success_message): ?>
            <div class="alert alert-success"><?= $success_message ?></div>
        <?php endif; ?>
        <?php if($error_message): ?>
            <div class="alert alert-danger"><?= $error_message ?></div>
        <?php endif; ?>

        <div class="card">
            <h2><i class="fas fa-history"></i> รายการลาที่ผ่านมา</h2>
            <div style="overflow-x:auto;">
                <table>
                    <thead>
                        <tr>
                            <th>ประเภทการลา</th>
                            <th>วันที่เริ่ม - สิ้นสุด</th>
                            <th>จำนวนวัน</th>
                            <th>เหตุผล</th>
                            <th>สถานะ</th>
                            <th>หลักฐาน</th>
                            <th>จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($leave_history)): foreach ($leave_history as $leave): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($leave['leave_type_display']) ?></strong></td>
                                <td>
                                    <?= date('d/m/Y', strtotime($leave['start_date'])) ?> - 
                                    <?= date('d/m/Y', strtotime($leave['end_date'])) ?>
                                </td>
                                <td><?= number_format($leave['duration'], 1) ?> วัน</td>
                                <td><?= htmlspecialchars($leave['reason'] ?: '-') ?></td>
                                <td>
                                    <?php 
                                        $status_key = strtolower($leave['status']);
                                        $status_class = 'badge-'.$status_key;
                                        $status_text = $leave['status'];
                                        if($status_text == 'Pending') $status_text = 'รออนุมัติ';
                                        else if($status_text == 'Approved') $status_text = 'อนุมัติแล้ว';
                                        else if($status_text == 'Rejected') $status_text = 'ปฏิเสธ';
                                        else if($status_text == 'Cancelled') $status_text = 'ยกเลิก';
                                    ?>
                                    <span class="badge <?= $status_class ?>"><?= $status_text ?></span>
                                </td>
                                <td>
                                    <?php if ($leave['evidence_file']): ?>
                                        <a href="../uploads/evidence/<?= $leave['evidence_file'] ?>" target="_blank" style="color:var(--secondary);"><i class="fas fa-file-image"></i> ดูไฟล์</a>
                                    <?php else: ?> - <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($leave['status'] == 'Pending'): ?>
                                        <a href="?cancel_id=<?= $leave['request_id'] ?>" 
                                           class="btn-cancel"
                                           onclick="return confirm('คุณแน่ใจหรือไม่ว่าต้องการยกเลิกคำร้องการลานี้?')">
                                           <i class="fas fa-times-circle"></i> ยกเลิก
                                        </a>
                                    <?php else: ?>
                                        <span style="color:#ccc;">ไม่มี</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; else: ?>
                            <tr>
                                <td colspan="7" class="no-data">ไม่พบประวัติการลาของคุณ</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>