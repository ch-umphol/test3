<?php
session_start();
require_once '../conn.php';

// ตรวจสอบสิทธิ์ (สำหรับพนักงานที่กำลังกดใช้สิทธิ์)
if (!isset($_SESSION['loggedin']) || $_SESSION['user_role'] !== 'employee') {
    exit('Access Denied');
}

$emp_id = $_SESSION['emp_id'];
$holiday_date = $_GET['holiday_date'] ?? '';
$holiday_name = $_GET['holiday_name'] ?? '';
$current_year = date('Y');

// เริ่ม Transaction เพื่อความปลอดภัยของข้อมูล
$conn->begin_transaction();

try {
    // 1. ตรวจสอบว่าพนักงานเคยใช้สิทธิ์วันหยุดนี้ไปแล้วหรือยัง (ใช้ความสัมพันธ์ของวันที่ในวงเล็บ)
    $check_sql = "SELECT request_id FROM leave_requests WHERE emp_id = ? AND reason LIKE ?";
    $stmt_check = $conn->prepare($check_sql);
    $search_reason = "%(" . $holiday_date . ")%"; // ตรวจสอบวันที่ YYYY-MM-DD
    $stmt_check->bind_param("is", $emp_id, $search_reason);
    $stmt_check->execute();
    if ($stmt_check->get_result()->num_rows > 0) {
        throw new Exception("คุณได้ใช้สิทธิ์วันหยุดนี้ไปแล้ว");
    }

    // 2. บันทึกข้อมูลการลาลงตาราง leave_requests
    // *** จุดสำคัญ: กำหนด status เป็น 'Approved' ทันที ***
    $reason = "ใช้สิทธิ์ลาชดเชยวันหยุด: " . $holiday_name . " (" . $holiday_date . ")";
    $insert_sql = "INSERT INTO leave_requests (emp_id, leave_type, start_date, end_date, status, reason) 
                   VALUES (?, 'Business Leave', CURDATE(), CURDATE(), 'Approved', ?)";
    $stmt_ins = $conn->prepare($insert_sql);
    $stmt_ins->bind_param("is", $emp_id, $reason);
    $stmt_ins->execute();

    // 3. ลดจำนวนวันลาคงเหลือในตาราง employee_leave_balances (อิง ลากิจ ID = 2)
    // การคำนวณ: used_days = used_days + 1
    $update_bal = "UPDATE employee_leave_balances 
                   SET used_days = used_days + 1 
                   WHERE emp_id = ? AND leave_type_id = 2 AND year = ?";
    $stmt_upd = $conn->prepare($update_bal);
    $stmt_upd->bind_param("ii", $emp_id, $current_year);
    $stmt_upd->execute();

    if ($stmt_upd->affected_rows === 0) {
        throw new Exception("ไม่พบโควตาวันลาของคุณในปีนี้ หรือโควตาเต็มแล้ว");
    }

    // 4. บันทึกประวัติการใช้สิทธิ์ลงในตาราง holiday_usage_records (เพื่อให้ manage_holidays.php นับจำนวนคนได้)
    $sql_usage = "INSERT INTO holiday_usage_records (emp_id, holiday_id, taken_date) 
                  SELECT ?, holiday_id, CURDATE() FROM public_holidays WHERE holiday_date = ?";
    $stmt_usage = $conn->prepare($sql_usage);
    $stmt_usage->bind_param("is", $emp_id, $holiday_date);
    $stmt_usage->execute();

    $conn->commit();
    $_SESSION['swal_success'] = "ใช้สิทธิ์ลาชดเชยสำเร็จ! ระบบอนุมัติและหักวันลาให้อัตโนมัติแล้ว";
    header("Location: public_holidays.php");

} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['swal_error'] = "เกิดข้อผิดพลาด: " . $e->getMessage();
    header("Location: public_holidays.php");
}

$conn->close();
?>