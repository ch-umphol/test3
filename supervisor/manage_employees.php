<?php
session_start();
require_once '../conn.php'; 

if (!isset($_SESSION['loggedin']) || !in_array($_SESSION['user_role'], ['supervisor'])) {
    header("location: ../login.php");
    exit;
}

$emp_id = $_SESSION['emp_id']; 
$status_message = $_SESSION['status_message'] ?? '';
unset($_SESSION['status_message']);

// 1. ดึงข้อมูลแผนกของ Supervisor
$sql_user = "SELECT E.dept_id, D.dept_name, E.first_name, E.last_name 
             FROM employees E 
             LEFT JOIN departments D ON E.dept_id = D.dept_id 
             WHERE E.emp_id = ?";
$current_dept_id = 0;
$current_dept_name = "ไม่ระบุแผนก";

if ($stmt_user = $conn->prepare($sql_user)) {
    $stmt_user->bind_param("i", $emp_id);
    $stmt_user->execute();
    $result_user = $stmt_user->get_result();
    if ($row_user = $result_user->fetch_assoc()) {
        $user_display_name = htmlspecialchars($row_user['first_name'] . ' ' . $row_user['last_name']);
        $current_dept_id = $row_user['dept_id'];
        $current_dept_name = htmlspecialchars($row_user['dept_name']);
    }
}

// 2. ดึงรายชื่อพนักงานในแผนก (role_id = 4 คือพนักงานทั่วไป)
$sql_employees = "
    SELECT E.emp_id, E.emp_code, E.first_name, E.last_name, R.role_display
    FROM employees E
    JOIN roles R ON E.role_id = R.role_id
    WHERE E.dept_id = ? AND E.role_id = 4
    ORDER BY E.emp_code ASC";

$stmt_list = $conn->prepare($sql_employees);
$stmt_list->bind_param("i", $current_dept_id);
$stmt_list->execute();
$result = $stmt_list->get_result();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>จัดการพนักงาน | LALA MUKHA</title>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary:#004030; --secondary:#66BB6A; --bg:#f5f7fb; --text:#2e2e2e; }
        body { margin:0; font-family:'Prompt',sans-serif; background:var(--bg); display:flex; color:var(--text); }
        .sidebar { width:250px; background:linear-gradient(180deg,var(--primary),#2e7d32); color:#fff; position:fixed; height:100%; display:flex; flex-direction:column; box-shadow:3px 0 10px rgba(0,0,0,0.15); }
        .sidebar-header { text-align:center; padding:25px 15px; font-weight:600; border-bottom:1px solid rgba(255,255,255,0.2); }
        .menu-item { display:flex; align-items:center; padding:15px 25px; color:rgba(255,255,255,0.85); text-decoration:none; transition:0.25s; }
        .menu-item:hover, .menu-item.active { background:rgba(255,255,255,0.2); color:#fff; font-weight:600; }
        .menu-item i { margin-right:12px; width:20px; text-align:center; }
        .main-content { flex-grow:1; margin-left:250px; padding:30px; }
        .header { background:#fff; border-radius:12px; padding:15px 25px; display:flex; justify-content:space-between; align-items:center; box-shadow:0 4px 10px rgba(0,0,0,0.05); margin-bottom:25px; }
        .card { background:#fff; border-radius:16px; padding:25px; box-shadow:0 6px 20px rgba(0,0,0,0.05); }
        .data-table { width:100%; border-collapse:collapse; }
        .data-table th, .data-table td { padding:15px; border-bottom:1px solid #eee; text-align:left; }
        .data-table th { background:#f8f9fa; font-weight:600; color:var(--primary); }
        .btn-stats { background:var(--secondary); color:#fff; border:none; padding:8px 15px; border-radius:20px; cursor:pointer; font-size:0.9em; transition: 0.3s; }
        .btn-stats:hover { background: #45a049; }
        
        /* Modal Styles */
        .modal { display:none; position:fixed; z-index:1000; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.5); backdrop-filter: blur(4px); }
        .modal-content { background:#fff; margin:5% auto; padding:30px; border-radius:16px; width:90%; max-width:750px; position: relative; animation: slideDown 0.3s ease-out; }
        @keyframes slideDown { from { transform: translateY(-20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        
        .stat-badge { background:#f1f8f1; padding:12px; border-radius:10px; flex:1; min-width:140px; text-align:center; border:1px solid #e0eee0; }
        .status-badge { padding:4px 8px; border-radius:12px; font-size:0.8em; font-weight:600; }
        
        .btn-cancel-leave { 
            background: #fff5f5; color: #e53e3e; border: 1px solid #feb2b2; 
            padding: 4px 10px; border-radius: 6px; cursor: pointer; font-size: 0.8em;
            transition: 0.2s;
        }
        .btn-cancel-leave:hover { background: #feb2b2; color: #fff; }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="sidebar-header">LALA MUKHA<br>ระบบจัดการพนักงาน</div>
    <a href="supervisor_dashboard.php" class="menu-item"><i class="fas fa-tachometer-alt"></i> ภาพรวมระบบ</a>
    <a href="manage_leave.php" class="menu-item"><i class="fas fa-clipboard-list"></i> จัดการการลา</a>
    <a href="apply_leave.php" class="menu-item"><i class="fas fa-calendar-plus"></i> ยื่นคำร้องการลา</a>
    <a href="manage_employees.php" class="menu-item active"><i class="fas fa-users"></i> ข้อมูลพนักงาน</a>
    <a href="public_holidays.php" class="menu-item"><i class="fas fa-calendar-alt"></i><span>ปฏิทินวันหยุด</span></a>
    <a href="leave_history.php" class="menu-item"><i class="fas fa-history"></i><span>ประวัติการลาส่วนตัว</span></a>
    <a href="logout.php" style="margin-top:auto; padding:15px; background:#388e3c; text-align:center; color:#fff; text-decoration:none;"><i class="fas fa-sign-out-alt"></i> ออกจากระบบ</a>
</div>

<div class="main-content">
    <div class="header">
        <div style="font-weight:600; color:var(--primary);">แผนก: <span style="color:var(--secondary);"><?= $current_dept_name ?></span></div>
        <div>
            <span><strong><?= $user_display_name ?></strong> (Supervisor)</span>
            <i class="fas fa-user-circle" style="font-size:1.8em; color:var(--primary); margin-left:10px;"></i>
        </div>
    </div>

    <?php if($status_message): ?>
        <div style="background:#e8f5e9; color:#2e7d32; padding:15px; border-radius:12px; margin-bottom:20px; border-left:5px solid #4caf50;">
            <i class="fas fa-check-circle"></i> <?= $status_message ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <h1><i class="fas fa-users-cog"></i> รายชื่อพนักงานในแผนก</h1>
        <table class="data-table">
            <thead>
                <tr>
                    <th>รหัสพนักงาน</th>
                    <th>ชื่อ-นามสกุล</th>
                    <th style="text-align:center;">จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($row['emp_code']) ?></strong></td>
                    <td><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?></td>
                    <td style="text-align:center;">
                        <button class="btn-stats" onclick="viewLeaveStats(<?= $row['emp_id'] ?>, '<?= htmlspecialchars($row['first_name'].' '.$row['last_name']) ?>')">
                            <i class="fas fa-chart-line"></i> ดูสถิติและประวัติการลา
                        </button>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="statsModal" class="modal">
    <div class="modal-content">
        <span onclick="closeModal()" style="float:right; cursor:pointer; font-size:28px; color:#aaa;">&times;</span>
        <h2 id="modalTitle" style="color:var(--primary); margin-top:0;"></h2>
        
        <h4 style="border-left:4px solid var(--secondary); padding-left:10px;"><i class="fas fa-wallet"></i> สิทธิ์คงเหลือปี <?= date('Y') ?></h4>
        <div id="balanceSection" style="display:flex; gap:10px; flex-wrap:wrap; margin-bottom:25px;"></div>

        <h4 style="border-left:4px solid var(--primary); padding-left:10px;"><i class="fas fa-history"></i> ประวัติการลาล่าสุด (แสดงปุ่มยกเลิกเมื่ออนุมัติแล้ว)</h4>
        <div style="overflow-x:auto;">
            <table class="data-table" style="font-size:0.9em;">
                <thead>
                    <tr>
                        <th>ประเภท</th>
                        <th>วันที่ลา</th>
                        <th>จำนวน</th>
                        <th>สถานะ</th>
                        <th>จัดการ</th>
                    </tr>
                </thead>
                <tbody id="historyTableBody"></tbody>
            </table>
        </div>
    </div>
</div>

<script>
function viewLeaveStats(empId, empName) {
    document.getElementById('modalTitle').innerText = "ข้อมูลพนักงาน: " + empName;
    const balanceDiv = document.getElementById('balanceSection');
    const tableBody = document.getElementById('historyTableBody');
    
    balanceDiv.innerHTML = '<i class="fas fa-spinner fa-spin"></i> กำลังโหลดสิทธิ์...';
    tableBody.innerHTML = '<tr><td colspan="5" style="text-align:center;">กำลังโหลดประวัติ...</td></tr>';
    document.getElementById('statsModal').style.display = 'block';

    fetch(`get_employee_leave_stats.php?emp_id=${empId}`)
        .then(res => res.json())
        .then(data => {
            // 1. แสดงยอดคงเหลือ
            balanceDiv.innerHTML = data.balances.map(b => `
                <div class="stat-badge">
                    <div style="font-size:0.8em; color:#666;">${b.leave_type_display}</div>
                    <div style="font-size:1.1em; font-weight:700; color:var(--primary);">${parseFloat(b.remaining)} ${b.leave_unit == 'hour' ? 'ชม.' : 'วัน'}</div>
                </div>
            `).join('') || 'ไม่พบข้อมูลสิทธิ์วันลา';

            // 2. แสดงประวัติการลา
            tableBody.innerHTML = data.history.map(h => {
                let color = h.status == 'Approved' ? '#2e7d32' : (h.status == 'Pending' ? '#ffa000' : '#c62828');
                let unitLabel = h.leave_unit == 'hour' ? 'ชม.' : 'วัน';
                
                // ปุ่มยกเลิก: แสดงเฉพาะรายการที่ได้รับการอนุมัติ (Approved)
                let actionBtn = '-';
                if (h.status === 'Approved') {
                    actionBtn = `<button class="btn-cancel-leave" onclick="confirmCancel(${h.request_id})">
                                    <i class="fas fa-undo"></i> ยกเลิก/คืนสิทธิ์
                                 </button>`;
                } else if (h.status === 'Cancelled') {
                    color = '#718096';
                }

                return `
                    <tr>
                        <td><strong>${h.leave_display}</strong></td>
                        <td>${h.start_date}</td>
                        <td>${h.duration} ${unitLabel}</td>
                        <td><span class="status-badge" style="background:${color}15; color:${color}; border:1px solid ${color}40;">${h.status}</span></td>
                        <td>${actionBtn}</td>
                    </tr>
                `;
            }).join('') || '<tr><td colspan="5" style="text-align:center;">ไม่พบประวัติการลา</td></tr>';
        });
}

function confirmCancel(id) {
    if (confirm("คุณต้องการยกเลิกรายการลานี้ใช่หรือไม่?\n* ระบบจะทำการคืนชั่วโมงหรือวันลาที่ถูกหักไป กลับเข้าสู่ยอดคงเหลือของพนักงานทันที")) {
        window.location.href = `cancel_leave_action.php?request_id=${id}`;
    }
}

function closeModal() { document.getElementById('statsModal').style.display = 'none'; }
window.onclick = function(event) { if (event.target == document.getElementById('statsModal')) closeModal(); }
</script>
</body>
</html>