<?php
session_start();
require_once 'conn.php'; 

// 1. ตรวจสอบสิทธิ์การเข้าถึง
if (!isset($_SESSION['loggedin']) || !in_array($_SESSION['user_role'], ['admin', 'manager', 'supervisor'])) {
    header("location: login.php");
    exit;
}

$request_id = isset($_GET['request_id']) ? intval($_GET['request_id']) : 0;
$status = isset($_GET['status']) ? $_GET['status'] : '';
$current_user_id = $_SESSION['emp_id']; 

if ($request_id > 0 && in_array($status, ['Approved', 'Rejected'])) {
    
    $conn->begin_transaction();

    try {
        // 1. อัปเดตสถานะในตาราง leave_requests
        $sql_update = "UPDATE leave_requests SET status = ?, approver_id = ? WHERE request_id = ?";
        $stmt = $conn->prepare($sql_update);
        $stmt->bind_param("sii", $status, $current_user_id, $request_id);
        
        if (!$stmt->execute()) {
            throw new Exception("ไม่สามารถอัปเดตสถานะการลาได้");
        }

        // 2. ถ้าอนุมัติ (Approved) ให้หักยอดสะสม
        if ($status === 'Approved') {
            // ดึงข้อมูลคำขอลา และ Join กับ leave_types เพื่อดูหน่วย (Unit)
            $sql_info = "SELECT LR.emp_id, LR.leave_type, LR.reason,
                                LT.leave_unit,
                                DATEDIFF(LR.end_date, LR.start_date) + 1 AS days_count
                         FROM leave_requests LR
                         LEFT JOIN leave_types LT ON LT.leave_type_name = LR.leave_type
                         WHERE LR.request_id = ?";
            
            $stmt_info = $conn->prepare($sql_info);
            $stmt_info->bind_param("i", $request_id);
            $stmt_info->execute();
            $req_data = $stmt_info->get_result()->fetch_assoc();

            if ($req_data) {
                $target_emp_id = $req_data['emp_id'];
                $leave_type_name = $req_data['leave_type'];
                $leave_unit = $req_data['leave_unit'];
                $current_year = date('Y');

                // --- Logic การคำนวณจำนวนที่จะหัก ---
                if ($leave_unit === 'hour') {
                    // กรณีลา OT หรือลาเป็นชั่วโมง
                    // ดึงตัวเลขจากเหตุผล (เช่น "[ครึ่งวันเช้า (4 ชม.)]") หรือกำหนดค่า Default 
                    // ถ้าคุณมีฟิลด์เก็บจำนวนชั่วโมงโดยเฉพาะใน leave_requests ให้เปลี่ยนมาใช้ฟิลด์นั้น
                    if (strpos($req_data['reason'], '4 ชม.') !== false) {
                        $amount_to_deduct = 4;
                    } elseif (strpos($req_data['reason'], '8 ชม.') !== false) {
                        $amount_to_deduct = 8;
                    } else {
                        $amount_to_deduct = 8; // Default 1 วันทำงาน
                    }
                } else {
                    // กรณีลาปกติ (หน่วยเป็นวัน)
                    $amount_to_deduct = $req_data['days_count'];
                }

                // อัปเดต used_days ในตาราง employee_leave_balances
                // (ใช้คอลัมน์ used_days เก็บค่าตามหน่วยของประเภทนั้นๆ)
                $sql_deduct = "UPDATE employee_leave_balances ELB
                               JOIN leave_types LT ON ELB.leave_type_id = LT.leave_type_id
                               SET ELB.used_days = ELB.used_days + ?
                               WHERE ELB.emp_id = ? 
                               AND ELB.year = ? 
                               AND LT.leave_type_name = ?";
                
                $stmt_deduct = $conn->prepare($sql_deduct);
                $stmt_deduct->bind_param("diis", $amount_to_deduct, $target_emp_id, $current_year, $leave_type_name);
                
                if (!$stmt_deduct->execute()) {
                    throw new Exception("ไม่สามารถหักยอดวันลาคงเหลือได้");
                }
            }
        }

        $conn->commit();
        $_SESSION['status_message'] = "✅ ดำเนินการเรียบร้อยแล้ว: รายการที่ #$request_id ถูก $status";
        
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['status_message'] = "❌ เกิดข้อผิดพลาด: " . $e->getMessage();
    }
} else {
    $_SESSION['status_message'] = "⚠️ ข้อมูลไม่ถูกต้อง";
}

header("location: manage_leave.php");
$conn->close();
exit;