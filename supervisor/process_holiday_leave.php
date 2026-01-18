<?php
session_start();
require_once '../conn.php'; 

// 1. ตรวจสอบสิทธิ์เบื้องต้น
if (!isset($_SESSION['loggedin']) || !isset($_GET['holiday_date'])) {
    header("location: ../login.php");
    exit;
}

$emp_id = $_SESSION['emp_id'];
$holiday_date = $_GET['holiday_date'];
$holiday_name = $_GET['holiday_name'] ?? 'วันหยุดนักขัตฤกษ์';
$current_year = date('Y');

// เริ่มกระบวนการ Transaction เพื่อป้องกันข้อมูลผิดพลาดหากเกิด Error กลางคัน
$conn->begin_transaction();

try {
    // 2. ตรวจสอบว่าพนักงานเคยใช้สิทธิ์วันหยุดนี้ไปหรือยัง (ซ้ำซ้อน)
    $sql_check = "SELECT usage_id FROM holiday_usage_records WHERE emp_id = ? AND holiday_id = 
                 (SELECT holiday_id FROM public_holidays WHERE holiday_date = ? LIMIT 1)";
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->bind_param("is", $emp_id, $holiday_date);
    $stmt_check->execute();
    if ($stmt_check->get_result()->num_rows > 0) {
        throw new Exception("คุณได้ใช้สิทธิ์วันหยุดนี้ไปแล้ว");
    }

    // 3. ตรวจสอบโควตาวันลาคงเหลือ (ลากิจ/Business Leave - ID: 2)
    $sql_balance = "SELECT allowed_days, used_days FROM employee_leave_balances 
                    WHERE emp_id = ? AND leave_type_id = 2 AND year = ?";
    $stmt_bal = $conn->prepare($sql_balance);
    $stmt_bal->bind_param("ii", $emp_id, $current_year);
    $stmt_bal->execute();
    $res_bal = $stmt_bal->get_result();
    
    if ($row_bal = $res_bal->fetch_assoc()) {
        if (($row_bal['allowed_days'] - $row_bal['used_days']) < 1) {
            throw new Exception("โควตาวันลาของคุณไม่เพียงพอ");
        }
    } else {
        throw new Exception("ไม่พบข้อมูลโควตาวันลาของคุณ");
    }

    // 4. บันทึกประวัติการใช้สิทธิ์ในตาราง holiday_usage_records
    $sql_get_hid = "SELECT holiday_id FROM public_holidays WHERE holiday_date = ? LIMIT 1";
    $stmt_hid = $conn->prepare($sql_get_hid);
    $stmt_hid->bind_param("s", $holiday_date);
    $stmt_hid->execute();
    $holiday_id = $stmt_hid->get_result()->fetch_assoc()['holiday_id'];

    $sql_ins_usage = "INSERT INTO holiday_usage_records (emp_id, holiday_id, taken_date) VALUES (?, ?, ?)";
    $stmt_ins_u = $conn->prepare($sql_ins_usage);
    $today = date('Y-m-d');
    $stmt_ins_u->bind_param("iis", $emp_id, $holiday_id, $today);
    $stmt_ins_u->execute();

    // 5. หักยอดวันลาคงเหลือใน employee_leave_balances
    $sql_update_bal = "UPDATE employee_leave_balances SET used_days = used_days + 1 
                       WHERE emp_id = ? AND leave_type_id = 2 AND year = ?";
    $stmt_upd = $conn->prepare($sql_update_bal);
    $stmt_upd->bind_param("ii", $emp_id, $current_year);
    $stmt_upd->execute();

    // 6. เพิ่มรายการลาอัตโนมัติลงในตาราง leave_requests
    // กำหนดสถานะเป็น 'Approved' ทันที เพราะเป็นการใช้สิทธิ์ที่เกิดขึ้นจริงตามกฎบริษัท
    $reason = "ใช้สิทธิ์ลาชดเชยวันหยุด: $holiday_name ($holiday_date)";
    $sql_ins_leave = "INSERT INTO leave_requests (emp_id, leave_type, start_date, end_date, status, reason) 
                      VALUES (?, 'Business L', ?, ?, 'Approved', ?)";
    $stmt_ins_l = $conn->prepare($sql_ins_leave);
    // วันที่ลาจะเป็นวันที่ทำรายการ (Today) หรือวันที่กำหนดเองตามนโยบาย
    $stmt_ins_l->bind_param("isss", $emp_id, $today, $today, $reason);
    $stmt_ins_l->execute();

    // หากทุกขั้นตอนสำเร็จ ทำการยืนยัน Transaction
    $conn->commit();
    $_SESSION['swal_success'] = "บันทึกการใช้สิทธิ์ลาชดเชยเรียบร้อยแล้ว";

} catch (Exception $e) {
    // หากเกิด Error ใดๆ ให้ยกเลิกการทำงานทั้งหมดกลับไปที่สถานะเดิม
    $conn->rollback();
    $_SESSION['swal_error'] = $e->getMessage();
}

// 7. Redirect กลับไปหน้าเดิมตามบทบาทของผู้ใช้
$redirect_path = ($_SESSION['user_role'] === 'supervisor') ? 'public_holidays.php' : 'public_holidays.php';
header("location: $redirect_path");
exit;