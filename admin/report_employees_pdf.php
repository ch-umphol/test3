<?php
session_start();
require_once '../conn.php';
require_once '../vendor/autoload.php';

// 1. ตรวจสอบสิทธิ์
if (!isset($_SESSION['loggedin']) || !in_array($_SESSION['user_role'], ['admin', 'manager'])) {
    die("Permission denied");
}

// 2. ดึงข้อมูลพนักงาน
$sql = "SELECT E.emp_code, E.first_name, E.last_name, D.dept_name, E.position, E.hired_date 
        FROM employees E 
        LEFT JOIN departments D ON E.dept_id = D.dept_id 
        ORDER BY D.dept_name ASC, E.emp_code ASC";
$result = $conn->query($sql);

// 3. ตั้งค่า mPDF (แก้ไขจุดที่ทำให้ Error)
$defaultConfig = (new \Mpdf\Config\ConfigVariables())->getDefaults();
$fontDirs = $defaultConfig['fontDir'];

$defaultFontConfig = (new \Mpdf\Config\FontVariables())->getDefaults();
$fontData = $defaultFontConfig['fontdata'];

$mpdf = new \Mpdf\Mpdf([
    'fontDir' => array_merge($fontDirs, [
        __DIR__ . '/fonts', // ใส่ / ข้างหน้า fonts เพื่อให้ Path ถูกต้อง
    ]),
    'fontdata' => $fontData + [
        'thsarabun' => [
            'R'  => 'THSarabunNew.ttf',
            'B'  => 'THSarabunNew Bold.ttf',      // ชื่อไฟล์ต้องมีช่องว่างตามภาพของคุณ
            'I'  => 'THSarabunNew Italic.ttf',    // ชื่อไฟล์ต้องมีช่องว่างตามภาพของคุณ
            'BI' => 'THSarabunNew BoldItalic.ttf' 
        ]
    ],
    'default_font' => 'thsarabun',
    'format' => 'A4',
    'margin_left' => 10,
    'margin_right' => 10,
    'margin_top' => 15,
    'margin_bottom' => 15,
]);

// 4. เตรียมเนื้อหา HTML (รูปแบบตารางรายงาน)
$html = '
<style>
    body { font-family: "thsarabun"; font-size: 16pt; line-height: 1.1; }
    .header { text-align: center; font-weight: bold; font-size: 20pt; margin-bottom: 0px; }
    .subtitle { text-align: center; font-size: 16pt; margin-bottom: 10px; }
    
    table { width: 100%; border-collapse: collapse; table-layout: fixed; }
    th { 
        background-color: #f2f2f2; 
        padding: 8px; 
        border: 1px solid #000; 
        font-weight: bold; 
        text-align: center;
    }
    td { 
        padding: 5px 8px; 
        border: 1px solid #000; 
        text-align: center; 
        vertical-align: middle;
    }
    .text-left { text-align: left; }
    .footer { text-align: right; font-size: 14pt; margin-top: 15px; }
</style>

<div class="header">รายงานรายชื่อพนักงานทั้งหมด</div>
<div class="header">LALA MUKHA TENTED RESORT KHAO YAI</div>
<div class="subtitle">ข้อมูล ณ วันที่: ' . date('d/m/Y') . ' | จำนวนพนักงาน: ' . $result->num_rows . ' คน</div>

<table>
    <thead>
        <tr>
            <th width="15%">รหัสพนักงาน</th>
            <th width="30%">ชื่อ-นามสกุล</th>
            <th width="20%">แผนก</th>
            <th width="18%">ตำแหน่ง</th>
            <th width="17%">วันที่เริ่มงาน</th>
        </tr>
    </thead>
    <tbody>';

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $html .= '<tr>
            <td>' . $row['emp_code'] . '</td>
            <td class="text-left">' . $row['first_name'] . ' ' . $row['last_name'] . '</td>
            <td>' . ($row['dept_name'] ?? '-') . '</td>
            <td>' . ($row['position'] ?? '-') . '</td>
            <td>' . date('d/m/Y', strtotime($row['hired_date'])) . '</td>
        </tr>';
    }
} else {
    $html .= '<tr><td colspan="5" style="padding:20px;">ไม่พบข้อมูลพนักงานในระบบ</td></tr>';
}

$html .= '</tbody></table>';
$html .= '<div class="footer">พิมพ์โดย: ' . ($_SESSION['username'] ?? 'System Admin') . ' | วันที่พิมพ์: ' . date('d/m/Y H:i') . '</div>';

// 5. สร้างและแสดงผล
$mpdf->WriteHTML($html);
$mpdf->Output('Employee_Report_' . date('Ymd_Hi') . '.pdf', 'I');
?>