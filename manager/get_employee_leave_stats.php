<?php
session_start();
require_once '../conn.php'; 

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
error_reporting(E_ALL);

$response = ['balances' => [], 'history' => [], 'employee_role_id' => null, 'error' => null];

if (!isset($_SESSION['loggedin'])) {
    echo json_encode(['error' => 'เซสชันหมดอายุ']);
    exit;
}

$emp_id = isset($_GET['emp_id']) ? intval($_GET['emp_id']) : 0;
$current_year = date('Y');

try {
    // ดึง role_id ของพนักงานที่ถูกเรียกดูข้อมูล เพื่อตรวจสอบสิทธิ์ในหน้า Manager
    $sql_role = "SELECT role_id FROM employees WHERE emp_id = ?";
    $stmt_role = $conn->prepare($sql_role);
    $stmt_role->bind_param("i", $emp_id);
    $stmt_role->execute();
    $res_role = $stmt_role->get_result()->fetch_assoc();
    $response['employee_role_id'] = $res_role['role_id'] ?? null;

    // 1. ดึงยอดคงเหลือ (ใช้ allowed_days ตามฐานข้อมูล)
    $sql_bal = "SELECT LT.leave_type_display, LT.leave_unit, (ELB.allowed_days - ELB.used_days) AS remaining 
                FROM employee_leave_balances ELB 
                JOIN leave_types LT ON ELB.leave_type_id = LT.leave_type_id 
                WHERE ELB.emp_id = ? AND ELB.year = ?";
    $stmt1 = $conn->prepare($sql_bal);
    $stmt1->bind_param("ii", $emp_id, $current_year);
    $stmt1->execute();
    $response['balances'] = $stmt1->get_result()->fetch_all(MYSQLI_ASSOC);

    // 2. ดึงประวัติการลา (เรียงตาม request_id เนื่องจากไม่มี created_at)
    $sql_his = "SELECT LR.request_id, COALESCE(LT.leave_type_display, LR.leave_type) AS leave_display, 
                LR.start_date, LR.status, LT.leave_unit, LR.reason,
                CASE WHEN LT.leave_unit = 'hour' THEN 8 ELSE (DATEDIFF(LR.end_date, LR.start_date) + 1) END AS duration
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