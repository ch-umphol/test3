<?php
session_start();
require_once 'conn.php'; 

if (!isset($_SESSION['loggedin']) || !in_array($_SESSION['user_role'], ['ผู้ดูแลระบบ', 'ผู้จัดการ', 'หัวหน้างาน'])) {
    header("location: login.php");
    exit;
}

$current_user_id = $_SESSION['employee_id'];
$user_role = $_SESSION['user_role'];
$user_name = $_SESSION['username'];
$status_message = $_SESSION['status_message'] ?? '';
unset($_SESSION['status_message']);

$where_clause = ($user_role === 'หัวหน้างาน') ? "AND E.supervisor_id = $current_user_id" : "";

$sql = "
    SELECT 
        LR.request_id,
        E.first_name,
        E.last_name,
        LT.leave_type_name,
        LR.status,
        DATEDIFF(LR.end_date, LR.start_date) + 1 AS duration_days
    FROM LEAVE_REQUEST LR
    JOIN EMPLOYEE E ON LR.employee_id = E.employee_id
    JOIN LEAVE_TYPE LT ON LR.leave_type_id = LT.leave_type_id
    WHERE LR.status = 'Pending' $where_clause
    ORDER BY LR.submission_date DESC";

$result = $conn->query($sql);
$conn->close();
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>จัดการการลา | LALA MUKHA</title>
<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root {
    --primary: #004030;
    --secondary: #66BB6A;
    --accent: #81C784;
    --bg: #f5f7fb;
    --text: #2e2e2e;
    --glass: rgba(255, 255, 255, 0.3);
}
body {
    margin: 0;
    font-family: 'Prompt', sans-serif;
    background: var(--bg);
    display: flex;
    color: var(--text);
}
/* ... โค้ด CSS ส่วน Sidebar, Main content, Header, Card/Table, Modal, .close-btn, .detail-row, .detail-label, .detail-value เหมือนเดิม ... */

.sidebar { width: 250px; background: linear-gradient(180deg, var(--primary), #2e7d32); color: #fff; position: fixed; height: 100%; display: flex; flex-direction: column; box-shadow: 3px 0 10px rgba(0,0,0,0.15); }
.sidebar-header { text-align: center; padding: 25px 15px; font-weight: 600; border-bottom: 1px solid rgba(255,255,255,0.2); }
.menu-item { display: flex; align-items: center; padding: 15px 25px; color: rgba(255,255,255,0.85); text-decoration: none; transition: 0.25s; }
.menu-item:hover, .menu-item.active { background: rgba(255,255,255,0.2); color: #fff; font-weight: 600; }
.menu-item i { margin-right: 12px; }
.logout { margin-top: auto; text-align: center; padding: 15px; background: rgba(255,255,255,0.15); color: #fff; }
.logout:hover { background: rgba(255,255,255,0.3); }
.main-content { flex-grow: 1; margin-left: 250px; padding: 30px; }
.header { background: #fff; border-radius: 12px; padding: 15px 25px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 4px 10px rgba(0,0,0,0.05); margin-bottom: 25px; }
.search-box { background: #f7f7f7; border-radius: 25px; padding: 6px 15px; display: flex; align-items: center; }
.search-box input { border: none; outline: none; background: transparent; padding-left: 10px; }
.user-profile { display: flex; align-items: center; gap: 10px; }
.card { background: rgba(255,255,255,0.95); border-radius: 16px; padding: 25px; box-shadow: 0 6px 20px rgba(0,0,0,0.05); }
.card h1 { margin-top: 0; color: var(--primary); }
.data-table { width: 100%; border-collapse: collapse; }
.data-table th, .data-table td { padding: 12px 15px; border-bottom: 1px solid #eee; text-align: left; }
.data-table th { background: #f4f4f4; font-weight: 600; }
.status-badge { padding: 6px 12px; border-radius: 15px; font-size: 0.85em; background: #e6f7d9; color: #4CAF50; }
.action-btn { border: 1px solid #ccc; border-radius: 50%; width: 32px; height: 32px; cursor: pointer; margin: 0 3px; transition: 0.2s; }
.action-btn.approve { color: #4CAF50; }
.action-btn.reject { color: #F44336; }
.action-btn.approve:hover { background: #4CAF50; color: #fff; }
.action-btn.reject:hover { background: #F44336; color: #fff; }
.modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.4); }
.modal-content { background: #fff; margin: 5% auto; padding: 30px; border-radius: 12px; width: 80%; max-width: 650px; box-shadow: 0 4px 12px rgba(0,0,0,0.2); }
.close-btn { float: right; font-size: 26px; color: #999; cursor: pointer; }
.close-btn:hover { color: #000; }
.detail-row { padding: 8px 0; border-bottom: 1px dashed #eee; display: flex; }
.detail-label { font-weight: 600; color: var(--primary); width: 150px; }
.detail-value { flex: 1; }
.evidence-box { text-align: center; margin-top: 15px; }
.evidence-box img { max-width: 100%; max-height: 350px; width: auto; border-radius: 10px; box-shadow: 0 3px 10px rgba(0,0,0,0.1); }

/* *** CSS สำหรับการจัดวางซ้าย-ขวา *** */
.evidence-link-box {
    text-align: left; /* ปุ่มดูหลักฐานชิดซ้าย */
    margin-top: 0;
}
.evidence-link {
    display: inline-flex; align-items: center; padding: 10px 20px;
    background-color: var(--secondary); color: white; border-radius: 25px;
    text-decoration: none; font-weight: 500; transition: background-color 0.3s;
}
.evidence-link:hover { background-color: var(--primary); }
.evidence-link i { margin-right: 8px; }

.modal-footer-actions {
    display: flex;
    justify-content: space-between; /* จัดองค์ประกอบแรกชิดซ้าย องค์ประกอบสุดท้ายชิดขวา */
    align-items: center;
    margin-top: 25px;
    padding-top: 15px;
    border-top: 1px solid #eee; 
}
.action-buttons-group {
    /* ปุ่มอนุมัติ/ไม่อนุมัติจะรวมอยู่ใน div นี้และชิดขวาตาม Flexbox */
}
.btn-submit {
    padding: 10px 20px;
    border-radius: 25px;
    font-weight: 500;
    cursor: pointer;
    border: none;
    color: white;
    transition: 0.3s;
}

</style>
</head>
<body>

<div class="sidebar">
    <div class="sidebar-header">LALA MUKHA<br>ระบบจัดการการลา</div>
    <a href="admin_dashboard.php" class="menu-item"><i class="fas fa-tachometer-alt"></i> ภาพรวมระบบ</a>
    <a href="manage_leave.php" class="menu-item active"><i class="fas fa-clipboard-list"></i> จัดการการลา</a>
    <a href="manage_users.php" class="menu-item"><i class="fas fa-user"></i> ข้อมูลผู้ใช้</a>
    <a href="manage_employees.php" class="menu-item"><i class="fas fa-users"></i> ข้อมูลพนักงาน</a>
    <a href="manage_leavetype.php" class="menu-item"><i class="fas fa-list"></i> ประเภทการลา</a>
    <a href="manage_department.php" class="menu-item"><i class="fas fa-building"></i> แผนก</a>
    <a href="manage_holiday.php" class="menu-item"><i class="fas fa-calendar-alt"></i> วันหยุดพิเศษ</a>
    <a href="logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> ออกจากระบบ</a>
</div>

<div class="main-content">
    <div class="header">
        <div class="search-box">
            <i class="fas fa-search"></i><input type="text" placeholder="ค้นหาที่นี่...">
        </div>
        <div class="user-profile">
            <span><?php echo htmlspecialchars($user_name); ?> (<?php echo $user_role; ?>)</span>
            <i class="fas fa-user-circle" style="font-size:1.8em; color:var(--secondary);"></i>
        </div>
    </div>

    <div class="card">
        <h1>การจัดการการลา</h1>
        <?php if ($status_message): ?><div class="alert-success"><?php echo $status_message; ?></div><?php endif; ?>

        <table class="data-table">
            <thead>
                <tr>
                    <th>ลำดับ</th>
                    <th>ชื่อ - นามสกุล</th>
                    <th>ประเภทการลา</th>
                    <th>สถานะ</th>
                    <th>จำนวนวัน</th>
                    <th>จัดการ</th>
                    <th>รายละเอียด</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($result && $result->num_rows > 0): $i=1; while($row=$result->fetch_assoc()): ?>
                <tr>
                    <td><?= $i++ ?></td>
                    <td><?= htmlspecialchars($row['first_name'].' '.$row['last_name']) ?></td>
                    <td><?= htmlspecialchars($row['leave_type_name']) ?></td>
                    <td><span class="status-badge"><?= htmlspecialchars($row['status']) ?></span></td>
                    <td><?= $row['duration_days'] ?> วัน</td>
                    <td>
                        <button class="action-btn approve" onclick="handleApproval(<?= $row['request_id'] ?>,'Approved')"><i class="fas fa-check"></i></button>
                        <button class="action-btn reject" onclick="handleApproval(<?= $row['request_id'] ?>,'Rejected')"><i class="fas fa-times"></i></button>
                    </td>
                    <td><button class="action-btn" style="border:1px solid #17A2B8;color:#17A2B8;" onclick="showDetails(<?= $row['request_id'] ?>)"><i class="fas fa-info"></i></button></td>
                </tr>
            <?php endwhile; else: ?>
                <tr><td colspan="7" style="text-align:center;">ไม่มีคำขอลาในขณะนี้</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="leaveModal" class="modal">
    <div class="modal-content">
        <span class="close-btn">&times;</span>
        <h2><i class="fas fa-info-circle"></i> รายละเอียดคำร้องขอลา</h2>
        <div id="modal-body"><p>กำลังโหลด...</p></div>
    </div>
</div>

<script>
const modal=document.getElementById("leaveModal");
const closeBtn=document.querySelector(".close-btn");
const modalBody=document.getElementById("modal-body");
closeBtn.onclick=()=>modal.style.display="none";
window.onclick=e=>{if(e.target===modal)modal.style.display="none";}

function handleApproval(id,action){
    if(confirm(`คุณต้องการ${action==='Approved'?'อนุมัติ':'ไม่อนุมัติ'}คำขอนี้หรือไม่?`))
        window.location=`approve_leave.php?request_id=${id}&status=${action}`;
}

function showDetails(id){
    modal.style.display="block";
    modalBody.innerHTML='<p style="text-align:center;"><i class="fas fa-spinner fa-spin"></i> กำลังโหลดข้อมูล...</p>';
    fetch(`get_leave_details.php?request_id=${id}`)
    .then(r=>r.json())
    .then(data=>{
        let evidenceHTML = '';
        if(data.evidence_file){
            const ext = data.evidence_file.split('.').pop().toLowerCase();
            const evidencePath = `../uploads/evidence/${data.evidence_file}`;
            
            let iconClass = 'fas fa-file-alt';
            let linkText = 'ดาวน์โหลดหลักฐาน';
            
            if(['jpg','jpeg','png','gif','webp'].includes(ext)){
                iconClass = 'fas fa-image';
                linkText = 'ดูรูปภาพหลักฐาน';
            } else if (ext === 'pdf') {
                iconClass = 'fas fa-file-pdf';
            }

            // ปุ่มดูหลักฐาน ชิดซ้าย
            evidenceHTML = `
                <div class='evidence-link-box'>
                    <a href='${evidencePath}' target='_blank' class='evidence-link'>
                        <i class='${iconClass}'></i> ${linkText}
                    </a>
                </div>`;
        } else {
            // ใช้ evidence-link-box เพื่อจัดตำแหน่งเดียวกับปุ่มอื่น ๆ แม้จะไม่มีไฟล์
            evidenceHTML = `<div class='evidence-link-box'><p style='color:#888; margin: 10px 0;'>ไม่มีหลักฐานแนบมา</p></div>`;
        }
        
        // ปุ่มอนุมัติ/ไม่อนุมัติ ชิดขวา
        let actionButtons = '';
        if (data.status === 'Pending') {
            actionButtons = `
                <div class="action-buttons-group">
                    <button class="btn-submit" style="background: var(--secondary); margin-right: 15px;" 
                            onclick="handleApproval(${id}, 'Approved'); modal.style.display='none';">
                        <i class="fas fa-check"></i> อนุมัติ
                    </button>
                    <button class="btn-submit" style="background: #F44336;" 
                            onclick="handleApproval(${id}, 'Rejected'); modal.style.display='none';">
                        <i class="fas fa-times"></i> ไม่อนุมัติ
                    </button>
                </div>
            `;
        } else {
            // ถ้าไม่ Pending, ให้แสดงสถานะปัจจุบันอยู่ทางขวา
            actionButtons = `<div class="action-buttons-group"><p style='font-weight: 600; color: var(--primary);'>สถานะ: ${data.status}</p></div>`;
        }
        

        modalBody.innerHTML=`
            <div class="detail-row"><span class="detail-label">ชื่อพนักงาน:</span><span class="detail-value">${data.first_name} ${data.last_name}</span></div>
            <div class="detail-row"><span class="detail-label">ประเภทการลา:</span><span class="detail-value">${data.leave_type_name}</span></div>
            <div class="detail-row"><span class="detail-label">ช่วงวันลา:</span><span class="detail-value">${data.start_date} - ${data.end_date} (${data.duration_days} วัน)</span></div>
            <div class="detail-row"><span class="detail-label">เหตุผล:</span><span class="detail-value">${data.reason.replace(/\n/g,'<br>')}</span></div>
            
            <h3 style='margin-top:15px;color:var(--secondary);'><i class='fas fa-paperclip'></i> หลักฐานการลา</h3>
            
            <div class="modal-footer-actions">
                ${evidenceHTML} 
                ${actionButtons}
            </div>
        `;
    })
    .catch((error)=>{
        console.error("Error loading details:", error);
        modalBody.innerHTML='<p style="color:red;text-align:center;">โหลดข้อมูลไม่สำเร็จ (ตรวจสอบ Console)</p>';
    });
}
</script>
</body>
</html>