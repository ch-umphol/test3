<?php
session_start();
require_once 'conn.php'; 

header('Content-Type: application/json; charset=utf-8');

// ปิดการแสดง Error เพื่อป้องกัน JSON พัง
ini_set('display_errors', 0);
error_reporting(E_ALL);

$response = ['balances' => [], 'history' => [], 'error' => null];

if (!isset($_SESSION['loggedin'])) {
    echo json_encode(['error' => 'เซสชันหมดอายุ']);
    exit;
}

$emp_id = isset($_GET['emp_id']) ? intval($_GET['emp_id']) : 0;
$current_year = date('Y');

try {
    // 1. ดึงยอดคงเหลือ - แก้ไขจาก max_days เป็น allowed_days ให้ตรงกับ DB
    $sql_bal = "SELECT LT.leave_type_display, LT.leave_unit, (ELB.allowed_days - ELB.used_days) AS remaining 
                FROM employee_leave_balances ELB 
                JOIN leave_types LT ON ELB.leave_type_id = LT.leave_type_id 
                WHERE ELB.emp_id = ? AND ELB.year = ?";
    $stmt1 = $conn->prepare($sql_bal);
    $stmt1->bind_param("ii", $emp_id, $current_year);
    $stmt1->execute();
    $response['balances'] = $stmt1->get_result()->fetch_all(MYSQLI_ASSOC);

    // 2. ดึงประวัติการลา - เปลี่ยนจาก ORDER BY created_at เป็น request_id
    $sql_his = "SELECT LR.request_id, COALESCE(LT.leave_type_display, LR.leave_type) AS leave_display, 
                LR.start_date, LR.status, LT.leave_unit, LR.reason,
                CASE WHEN LT.leave_unit = 'hour' THEN 
                    (CASE WHEN LR.reason LIKE '%4 ชม.%' THEN 4 ELSE 8 END)
                ELSE (DATEDIFF(LR.end_date, LR.start_date) + 1) END AS duration
                FROM leave_requests LR 
                LEFT JOIN leave_types LT ON LT.leave_type_name = LR.leave_type 
                WHERE LR.emp_id = ? 
                ORDER BY LR.request_id DESC LIMIT 10"; 
    $stmt2 = $conn->prepare($sql_his);
    $stmt2->bind_param("i", $emp_id);
    $stmt2->execute();
    $response['history'] = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);

} catch (Exception $e) {
    $response['error'] = "Database Error: " . $e->getMessage();
}

echo json_encode($response);
$conn->close();