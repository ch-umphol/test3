<?php
session_start();
require_once 'conn.php'; 

$message = "";
$message_type = ""; // error หรือ success

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $conn->real_escape_string($_POST['username']);
    $emp_code = $conn->real_escape_string($_POST['emp_code']);
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // 1. ตรวจสอบว่ามีผู้ใช้งานนี้จริงหรือไม่ (เช็คคู่ username และ emp_code)
    $check_sql = "SELECT emp_id FROM employees WHERE username = '$username' AND emp_code = '$emp_code'";
    $result = $conn->query($check_sql);

    if ($result->num_rows == 1) {
        if ($new_password === $confirm_password) {
            // 2. อัปเดตรหัสผ่านใหม่ (ในที่นี้ใช้ Plain Text ตามโครงสร้างข้อมูลเดิมของคุณ)
            // หมายเหตุ: ในระบบจริงควรใช้ password_hash()
            $update_sql = "UPDATE employees SET password = '$new_password' WHERE username = '$username'";
            
            if ($conn->query($update_sql) === TRUE) {
                $message = "เปลี่ยนรหัสผ่านสำเร็จ! กรุณาเข้าสู่ระบบด้วยรหัสผ่านใหม่";
                $message_type = "success";
            } else {
                $message = "เกิดข้อผิดพลาดในการอัปเดตข้อมูล: " . $conn->error;
                $message_type = "error";
            }
        } else {
            $message = "รหัสผ่านใหม่และยืนยันรหัสผ่านไม่ตรงกัน";
            $message_type = "error";
        }
    } else {
        $message = "ข้อมูลไม่ถูกต้อง ไม่พบชื่อผู้ใช้นี้หรือรหัสพนักงานไม่ตรงกับระบบ";
        $message_type = "error";
    }
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ลืมรหัสผ่าน | LALA MUKHA</title>
<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root { --primary: #004030; --secondary: #66BB6A; --bg: #f5f7fb; --text: #333; }
body { margin: 0; height: 100vh; display: flex; font-family: 'Prompt', sans-serif; background: linear-gradient(120deg, rgba(0,64,48,0.95), rgba(102,187,106,0.9)), url('https://images.unsplash.com/photo-1603481589399-1f7c0a11c73f?auto=format&fit=crop&w=1400&q=80') no-repeat center/cover; align-items: center; justify-content: center; }
.login-card { background: rgba(255,255,255,0.95); border-radius: 20px; padding: 40px 50px; max-width: 420px; width: 90%; box-shadow: 0 10px 25px rgba(0,0,0,0.15); text-align: center; }
h2 { margin: 10px 0 25px; color: var(--primary); font-weight: 600; }
.form-group { margin-bottom: 15px; text-align: left; }
input { width: 100%; padding: 12px; border: 1px solid #ccc; border-radius: 10px; font-size: 1em; font-family: 'Prompt', sans-serif; box-sizing: border-box; }
input:focus { border-color: var(--secondary); outline: none; }
button { width: 100%; background: var(--secondary); color: white; border: none; padding: 14px; font-size: 1.1em; font-weight: 600; border-radius: 10px; cursor: pointer; transition: all 0.3s; margin-top: 10px; }
button:hover { background: #43A047; }
.message { padding: 10px; border-radius: 6px; margin-bottom: 20px; text-align: left; font-size: 0.9em; }
.error { background: #fdd; color: #b71c1c; border-left: 4px solid #f44336; }
.success { background: #e8f5e9; color: #2e7d32; border-left: 4px solid #4caf50; }
.back-to-login { margin-top: 20px; }
.back-to-login a { text-decoration: none; color: var(--primary); font-weight: 500; }
</style>
</head>
<body>
    <div class="login-card">
      <h2>ตั้งรหัสผ่านใหม่</h2>

      <?php if (!empty($message)): ?>
        <div class="message <?= $message_type ?>"><?= htmlspecialchars($message) ?></div>
      <?php endif; ?>

      <form method="POST">
        <div class="form-group">
          <input type="text" name="username" placeholder="ชื่อผู้ใช้ (Username)" required>
        </div>
        <div class="form-group">
          <input type="text" name="emp_code" placeholder="รหัสพนักงาน (Emp Code)" required>
        </div>
        <hr style="border: 0.5px solid #eee; margin: 20px 0;">
        <div class="form-group">
          <input type="password" name="new_password" placeholder="รหัสผ่านใหม่" required>
        </div>
        <div class="form-group">
          <input type="password" name="confirm_password" placeholder="ยืนยันรหัสผ่านใหม่" required>
        </div>
        <button type="submit">บันทึกรหัสผ่านใหม่</button>
      </form>

      <div class="back-to-login">
        <a href="index.php"><i class="fas fa-arrow-left"></i> กลับหน้าเข้าสู่ระบบ</a>
      </div>
    </div>
</body>
</html>