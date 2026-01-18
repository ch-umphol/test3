<?php
session_start();
require_once 'conn.php'; 

// ตรวจสอบสิทธิ์การเข้าถึง: Admin, Manager, Supervisor เท่านั้น
if (!isset($_SESSION['loggedin']) || !in_array($_SESSION['user_role'], ['ผู้ดูแลระบบ', 'ผู้จัดการ', 'หัวหน้างาน'])) {
    // Redirect หากไม่มีสิทธิ์
    header("location: login.php");
    exit;
}

$approver_id = $_SESSION['employee_id'];
$approver_name = $_SESSION['user_name']; // ชื่อผู้อนุมัติสำหรับบันทึกใน comment
$request_id = isset($_GET['request_id']) ? (int)$_GET['request_id'] : 0;
$status = isset($_GET['status']) ? $_GET['status'] : ''; // Approved หรือ Rejected

// **1. Input Validation: ตรวจสอบความสมบูรณ์ของข้อมูลที่ได้รับ**
if ($request_id === 0 || !in_array($status, ['Approved', 'Rejected'])) {
    $_SESSION['status_message'] = "❌ การดำเนินการไม่สมบูรณ์: ข้อมูลคำขอลาไม่ถูกต้อง";
    header("location: manage_leave.php");
    exit;
}

$conn->begin_transaction();

try {
    // 2. ดึงข้อมูลคำขอลาที่เกี่ยวข้อง (ต้องเป็น Pending เท่านั้น)
    $sql_request = "SELECT employee_id, leave_type_id, start_date, end_date, balance_id 
                    FROM LEAVE_REQUEST 
                    WHERE request_id = $request_id AND status = 'Pending'";
    $request_data = $conn->query($sql_request)->fetch_assoc();

    if (!$request_data) {
        throw new Exception("คำขอลาไม่อยู่ในสถานะ 'Pending' หรือไม่พบคำขอ");
    }

    $employee_id = $request_data['employee_id'];
    $balance_id = $request_data['balance_id'];
    
    // คำนวณระยะเวลาเป็นวัน
    $datetime1 = new DateTime($request_data['start_date']);
    $datetime2 = new DateTime($request_data['end_date']);
    $duration_days = $datetime1->diff($datetime2)->days + 1;

    // 3. อัปเดตสถานะใน LEAVE_REQUEST
    $approval_date = date('Y-m-d H:i:s');
    $sql_update_request = "UPDATE LEAVE_REQUEST 
                           SET status = '$status' 
                           WHERE request_id = $request_id";
    $conn->query($sql_update_request);

    // 4. บันทึกการอนุมัติ/ไม่อนุมัติใน LEAVE_APPROVAL
    $comment = ($status === 'Approved') ? "อนุมัติโดย {$approver_name}" : "ไม่อนุมัติโดย {$approver_name}";
    
    $sql_approval = "INSERT INTO LEAVE_APPROVAL (request_id, approver_id, approval_date, approval_status, employee_id, comment)
                     VALUES ($request_id, $approver_id, '$approval_date', '$status', $employee_id, '$comment')";
    $conn->query($sql_approval);

    if ($status === 'Approved') {
        // 5. หักยอดวันลาคงเหลือ (เฉพาะเมื่ออนุมัติ)
        $sql_update_balance = "UPDATE EMPLOYEE_LEAVE_BALANCE 
                               SET remaining_days = remaining_days - $duration_days 
                               WHERE balance_id = $balance_id";
        $conn->query($sql_update_balance);
        $_SESSION['status_message'] = "✅ อนุมัติคำขอลา ID $request_id สำเร็จ และหักวันลาจำนวน {$duration_days} วันเรียบร้อย";
    } else {
        // แจ้งสถานะ Rejected
        $_SESSION['status_message'] = "✅ ไม่อนุมัติคำขอลา ID $request_id เรียบร้อย";
    }

    // 6. ส่ง Notification กลับไปยังพนักงาน
    $notif_msg = "คำขอลา ID $request_id ของคุณได้รับการ" . ($status === 'Approved' ? 'อนุมัติ' : 'ปฏิเสธ') . "แล้ว";
    $sql_notif = "INSERT INTO NOTIFICATION (request_id, recipient_id, message, timestamp) 
                  VALUES ($request_id, $employee_id, '$notif_msg', '$approval_date')";
    $conn->query($sql_notif);

    $conn->commit();

} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['status_message'] = "❌ ดำเนินการล้มเหลว (DB Error): " . $e->getMessage();
}

$conn->close();
header("location: manage_leave.php");
exit;
?>