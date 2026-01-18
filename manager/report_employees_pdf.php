<?php
session_start();
require_once '../conn.php';
require_once '../vendor/autoload.php';

// 1. ตรวจสอบสิทธิ์การเข้าถึง (เฉพาะ manager หรือ admin)
if (!isset($_SESSION['loggedin']) || !in_array($_SESSION['user_role'], ['admin', 'manager'])) {
    die("Permission denied");
}

// 2. รับค่าการกรอง (Filter) จากหน้า report.php
$dept_filter = isset($_GET['dept_id']) ? $_GET['dept_id'] : 'all';

// 3. สร้าง Query ตามเงื่อนไขการกรอง
$sql = "SELECT E.emp_code, E.first_name, E.last_name, D.dept_name, E.position, E.hired_date 
        FROM employees E 
        LEFT JOIN departments D ON E.dept_id = D.dept_id";

if ($dept_filter !== 'all') {
    $sql .= " WHERE E.dept_id = " . intval($dept_filter);
}

$sql .= " ORDER BY D.dept_name ASC, E.emp_code ASC";
$result = $conn->query($sql);

// 4. ตั้งค่า mPDF และฟอนต์ TH Sarabun New
$defaultConfig = (new \Mpdf\Config\ConfigVariables())->getDefaults();
$fontDirs = $defaultConfig['fontDir'];

$defaultFontConfig = (new \Mpdf\Config\FontVariables())->getDefaults();
$fontData = $defaultFontConfig['fontdata'];

$mpdf = new \Mpdf\Mpdf([
    'fontDir' => array_merge($fontDirs, [
        __DIR__ . '/fonts', // ชี้ไปที่โฟลเดอร์ admin/fonts
    ]),
    'fontdata' => $fontData + [
        'thsarabun' => [
            'R'  => 'THSarabunNew.ttf',
            'B'  => 'THSarabunNew Bold.ttf',
            'I'  => 'THSarabunNew Italic.ttf',
            'BI' => 'THSarabunNew BoldItalic.ttf'
        ]
    ],
    'default_font' => 'thsarabun',
    'format' => 'A4',
    'margin_left' => 15,
    'margin_right' => 15,
    'margin_top' => 15,
    'margin_bottom' => 15,
]);

// 5. เตรียมเนื้อหา HTML สำหรับรายงาน
$html = '
<style>
    body { font-family: "thsarabun"; font-size: 16pt; }
    table { width: 100%; border-collapse: collapse; margin-top: 10px; table-layout: fixed; }
    th { background-color: #f2f2f2; padding: 10px; border: 1px solid #000; font-weight: bold; text-align: center; }
    td { padding: 8px; border: 1px solid #000; text-align: center; vertical-align: middle; }
    .header { text-align: center; font-weight: bold; font-size: 20pt; line-height: 1; }
    .subtitle { text-align: center; font-size: 16pt; margin-bottom: 20px; }
    .text-left { text-align: left; padding-left: 8px; }
    .footer { text-align: right; font-size: 14pt; margin-top: 20px; }
</style>

<div class="header">รายงานรายชื่อพนักงาน (LALA MUKHA TENTED RESORT)</div>
<div class="subtitle">
    ข้อมูล ณ วันที่: ' . date('d/m/Y') . ' 
    | แผนก: ' . ($dept_filter === 'all' ? 'ทุกแผนก' : 'เฉพาะแผนกที่เลือก') . '
    | จำนวน: ' . $result->num_rows . ' คน
</div>

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
            <td>' . htmlspecialchars($row['emp_code']) . '</td>
            <td class="text-left">' . htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) . '</td>
            <td>' . htmlspecialchars($row['dept_name'] ?? '-') . '</td>
            <td>' . htmlspecialchars($row['position'] ?? '-') . '</td>
            <td>' . date('d/m/Y', strtotime($row['hired_date'])) . '</td>
        </tr>';
    }
} else {
    $html .= '<tr><td colspan="5">ไม่พบข้อมูลพนักงานตามเงื่อนไขที่เลือก</td></tr>';
}

$html .= '</tbody></table>';
$html .= '<div class="footer">พิมพ์โดย: ' . htmlspecialchars($_SESSION['username']) . ' | วันที่พิมพ์: ' . date('d/m/Y H:i') . '</div>';

// 6. สร้างและแสดงผล PDF
$mpdf->WriteHTML($html);
$mpdf->Output('Employee_Report_' . date('Ymd') . '.pdf', 'I');
?>