<?php
session_start();
require_once '../conn.php';
require_once '../vendor/autoload.php';

// 1. ตรวจสอบสิทธิ์การเข้าถึง
if (!isset($_SESSION['loggedin']) || !in_array($_SESSION['user_role'], ['admin', 'manager'])) {
    die("Permission denied");
}

// 2. รับค่าปีจาก Filter (ถ้าไม่มีให้ใช้ปีปัจจุบัน)
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

// 3. ดึงข้อมูลวันหยุดประจำปี
$sql = "SELECT holiday_date, holiday_name 
        FROM public_holidays 
        WHERE YEAR(holiday_date) = ? 
        ORDER BY holiday_date ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $year);
$stmt->execute();
$result = $stmt->get_result();

// 4. ตั้งค่า mPDF และฟอนต์ TH Sarabun New
$defaultConfig = (new \Mpdf\Config\ConfigVariables())->getDefaults();
$fontDirs = $defaultConfig['fontDir'];
$defaultFontConfig = (new \Mpdf\Config\FontVariables())->getDefaults();
$fontData = $defaultFontConfig['fontdata'];

$mpdf = new \Mpdf\Mpdf([
    'fontDir' => array_merge($fontDirs, [__DIR__ . '/fonts']),
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
    'margin_left' => 20,
    'margin_right' => 20,
    'margin_top' => 20,
    'margin_bottom' => 20,
]);

// 5. เตรียมเนื้อหา HTML
$html = '
<style>
    body { font-family: "thsarabun"; font-size: 16pt; color: #333; }
    .header { text-align: center; font-weight: bold; font-size: 22pt; color: #004030; margin-bottom: 5px; }
    .subtitle { text-align: center; font-size: 18pt; margin-bottom: 25px; border-bottom: 2px solid #004030; padding-bottom: 10px; }
    table { width: 100%; border-collapse: collapse; }
    th { background-color: #004030; color: white; padding: 12px; border: 1px solid #000; font-weight: bold; text-align: center; }
    td { padding: 10px; border: 1px solid #ccc; text-align: center; }
    .text-left { text-align: left; padding-left: 20px; }
    .holiday-row:nth-child(even) { background-color: #f9f9f9; }
    .footer { text-align: right; font-size: 14pt; margin-top: 30px; font-style: italic; }
</style>

<div class="header">ตารางวันหยุดประจำปี (LALA MUKHA)</div>
<div class="subtitle">ประจำปีคริสต์ศักราช ' . $year . ' (พ.ศ. ' . ($year + 543) . ')</div>

<table>
    <thead>
        <tr>
            <th width="10%">ลำดับ</th>
            <th width="30%">วันที่ / เดือน</th>
            <th width="60%">ชื่อวันหยุด</th>
        </tr>
    </thead>
    <tbody>';

$months_th = ["", "มกราคม", "กุมภาพันธ์", "มีนาคม", "เมษายน", "พฤษภาคม", "มิถุนายน", "กรกฎาคม", "สิงหาคม", "กันยายน", "ตุลาคม", "พฤศจิกายน", "ธันวาคม"];

if ($result->num_rows > 0) {
    $i = 1;
    while ($row = $result->fetch_assoc()) {
        $date = strtotime($row['holiday_date']);
        $thai_date = date('j', $date) . ' ' . $months_th[date('n', $date)];
        
        $html .= '<tr class="holiday-row">
            <td>' . $i++ . '</td>
            <td>' . $thai_date . '</td>
            <td class="text-left">' . htmlspecialchars($row['holiday_name']) . '</td>
        </tr>';
    }
} else {
    $html .= '<tr><td colspan="3" style="padding: 30px;">ไม่พบข้อมูลวันหยุดสำหรับปีนี้</td></tr>';
}

$html .= '</tbody></table>';
$html .= '<div class="footer">ออกเอกสาร ณ วันที่: ' . date('d/m/Y H:i') . ' โดย ' . htmlspecialchars($_SESSION['username']) . '</div>';

// 6. สร้างไฟล์ PDF
$mpdf->WriteHTML($html);
$mpdf->Output('Public_Holidays_' . $year . '.pdf', 'I');
?>