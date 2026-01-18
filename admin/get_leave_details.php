<?php
require_once 'conn.php';

if (isset($_GET['request_id'])) {
    $request_id = intval($_GET['request_id']);

    $sql = "
        SELECT LR.*, E.first_name, E.last_name, LT.leave_type_name,
        DATEDIFF(LR.end_date, LR.start_date) + 1 AS duration_days
        FROM LEAVE_REQUEST LR
        JOIN EMPLOYEE E ON LR.employee_id = E.employee_id
        JOIN LEAVE_TYPE LT ON LR.leave_type_id = LT.leave_type_id
        WHERE LR.request_id = ?";
        
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $request_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $data = $result->fetch_assoc();
        echo json_encode($data);
    } else {
        echo json_encode(['error' => 'ไม่พบข้อมูล']);
    }

    $stmt->close();
    $conn->close();
}
?>
