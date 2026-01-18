<?php
session_start();
require_once 'conn.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['user_role'] !== 'supervisor') {
    header("location: ../login.php");
    exit;
}

$request_id = isset($_GET['request_id']) ? intval($_GET['request_id']) : 0;

if ($request_id > 0) {
    // ดึงข้อมูลรายการลาเพื่อคำนวณจำนวนที่ต้องคืน
    $sql_info = "SELECT LR.*, LT.leave_type_id, LT.leave_unit 
                 FROM leave_requests LR
                 JOIN leave_types LT ON LR.leave_type = LT.leave_type_name
                 WHERE LR.request_id = ? AND LR.status = 'Approved'";
    
    $stmt = $conn->prepare($sql_info);
    $stmt->bind_param("i", $request_id);
    $stmt->execute();
    $leave = $stmt->get_result()->fetch_assoc();

    if ($leave) {
        $emp_id = $leave['emp_id'];
        $lt_id = $leave['leave_type_id'];
        $year = date('Y', strtotime($leave['start_date']));
        
        // คำนวณจำนวนที่ต้องคืน (วัน หรือ ชม.)
        if ($leave['leave_unit'] === 'hour') {
            $amount_to_return = (strpos($leave['reason'], '4 ชม.') !== false) ? 4 : 8;
        } else {
            $amount_to_return = (strtotime($leave['end_date']) - strtotime($leave['start_date'])) / (60 * 60 * 24) + 1;
        }

        $conn->begin_transaction();
        try {
            // 1. เปลี่ยนสถานะเป็น Cancelled
            $update_status = "UPDATE leave_requests SET status = 'Cancelled' WHERE request_id = ?";
            $st1 = $conn->prepare($update_status);
            $st1->bind_param("i", $request_id);
            $st1->execute();

            // 2. คืนยอด used_days กลับไป
            $update_bal = "UPDATE employee_leave_balances 
                           SET used_days = used_days - ? 
                           WHERE emp_id = ? AND leave_type_id = ? AND year = ?";
            $st2 = $conn->prepare($update_bal);
            $st2->bind_param("diii", $amount_to_return, $emp_id, $lt_id, $year);
            $st2->execute();

            $conn->commit();
            $_SESSION['status_message'] = "ยกเลิกรายการและคืนสิทธิ์เรียบร้อยแล้ว";
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['status_message'] = "เกิดข้อผิดพลาด: " . $e->getMessage();
        }
    }
}

header("location: manage_employees.php");
exit;