<?php
session_start();
require_once '../conn.php';
require_once '../vendor/autoload.php';

if (!isset($_SESSION['loggedin']) || !in_array($_SESSION['user_role'], ['admin', 'manager'])) {
    die("Permission denied");
}

$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
$month = isset($_GET['month']) ? $_GET['month'] : 'all';

// 1. ดึงประเภทการลาและกำหนดสัญลักษณ์ (ใช้ตารางสีขาวแบบเน้นเส้นขอบ)
$leave_types = [];
$sql_lt = "SELECT leave_type_name, leave_type_display FROM leave_types ORDER BY leave_type_id ASC";
$res_lt = $conn->query($sql_lt);

while ($lt = $res_lt->fetch_assoc()) {
    $name = $lt['leave_type_name'];
    $char = strtoupper(substr($name, 0, 1));
    $leave_types[$name] = [
        'char' => $char,
        'display' => $lt['leave_type_display']
    ];
}

// 2. ตั้งค่า mPDF
$mpdf = new \Mpdf\Mpdf([
    'fontDir' => array_merge((new \Mpdf\Config\ConfigVariables())->getDefaults()['fontDir'], [__DIR__ . '/fonts']),
    'fontdata' => (new \Mpdf\Config\FontVariables())->getDefaults()['fontdata'] + [
        'thsarabun' => [
            'R' => 'THSarabunNew.ttf', 'B' => 'THSarabunNew Bold.ttf',
        ]
    ],
    'default_font' => 'thsarabun',
    'format' => 'A4-L',
    'margin_left' => 10, 'margin_right' => 10, 'margin_top' => 15, 'margin_bottom' => 15,
]);

$month_names = ["01" => "มกราคม", "02" => "กุมภาพันธ์", "03" => "มีนาคม", "04" => "เมษายน", "05" => "พฤษภาคม", "06" => "มิถุนายน", "07" => "กรกฎาคม", "08" => "สิงหาคม", "09" => "กันยายน", "10" => "ตุลาคม", "11" => "พฤศจิกายน", "12" => "ธันวาคม"];

// 3. ดึงข้อมูลพนักงาน
$sql_emp = "SELECT E.emp_id, E.emp_code, E.first_name, E.last_name FROM employees E ORDER BY E.emp_code ASC";
$res_emp = $conn->query($sql_emp);

$html = '<style>
    body { font-family: "thsarabun"; font-size: 12pt; color: #000; }
    .header { text-align: center; font-weight: bold; font-size: 18pt; margin-bottom: 5px; }
    table { width: 100%; border-collapse: collapse; table-layout: fixed; background-color: #fff; }
    th, td { border: 0.5pt solid #000; text-align: center; padding: 4px; font-size: 10pt; }
    th { background-color: #fff; font-weight: bold; }
    .name-col { text-align: left; padding-left: 5px; white-space: nowrap; overflow: hidden; }
    .total-col { font-weight: bold; background-color: #fff; }
    .note-box { margin-top: 15px; border: 0.5pt solid #000; padding: 8px; font-size: 11pt; }
</style>';

if ($month !== 'all') {
    // ================== รายงานรายวัน (ตารางสีขาว) ==================
    $days = cal_days_in_month(CAL_GREGORIAN, intval($month), $year);
    $html .= '<div class="header">รายงานสถิติการลาประจำเดือน (รายวัน)</div>
              <div style="text-align: center; margin-bottom: 15px;">ประจำเดือน ' . $month_names[$month] . ' พ.ศ. ' . ($year + 543) . '</div>
              <table><thead>
                <tr><th width="120px" rowspan="2">ชื่อ-นามสกุล</th><th colspan="'.$days.'">วันที่</th><th colspan="'.(count($leave_types)+1).'">สรุป (วัน)</th></tr>
                <tr>';
                for ($d = 1; $d <= $days; $d++) { $html .= '<th width="20px">'.$d.'</th>'; }
                foreach ($leave_types as $lt) { $html .= '<th width="25px">'.$lt['char'].'</th>'; }
    $html .= '<th width="35px">รวม</th></tr></thead><tbody>';

    while ($emp = $res_emp->fetch_assoc()) {
        $attendance = []; $counts = array_fill_keys(array_keys($leave_types), 0);
        $q = "SELECT start_date, end_date, leave_type FROM leave_requests WHERE emp_id={$emp['emp_id']} AND status='Approved' AND (YEAR(start_date)=$year OR YEAR(end_date)=$year)";
        $res_l = $conn->query($q);
        while ($l = $res_l->fetch_assoc()) {
            $period = new DatePeriod(new DateTime($l['start_date']), new DateInterval('P1D'), (new DateTime($l['end_date']))->modify('+1 day'));
            foreach($period as $dt) {
                if($dt->format("m") == $month && $dt->format("Y") == $year) {
                    $attendance[intval($dt->format("d"))] = $leave_types[$l['leave_type']]['char'];
                    $counts[$l['leave_type']]++;
                }
            }
        }
        $html .= '<tr><td class="name-col">' . $emp['first_name'] . ' ' . $emp['last_name'] . '</td>';
        for ($d=1; $d<=$days; $d++) { $html .= '<td>' . ($attendance[$d] ?? '') . '</td>'; }
        foreach ($counts as $c) { $html .= '<td>' . ($c > 0 ? $c : '') . '</td>'; }
        $html .= '<td class="total-col">' . array_sum($counts) . '</td></tr>';
    }
} else {
    // ================== รายงานรายปี (ตารางสีขาว) ==================
    $html .= '<div class="header">รายงานสรุปสถิติการลาประจำปี</div>
              <div style="text-align: center; margin-bottom: 15px;">ประจำปี พ.ศ. ' . ($year + 543) . '</div>
              <table><thead>
                <tr><th width="130px" rowspan="2">ชื่อ-นามสกุล</th><th colspan="12">เดือน (จำนวนวันลา)</th><th colspan="'.(count($leave_types)+1).'">สรุปทั้งปี (วัน)</th></tr>
                <tr>';
                foreach($month_names as $m) { $html .= '<th>' . mb_substr($m,0,3,'UTF-8') . '</th>'; }
                foreach ($leave_types as $lt) { $html .= '<th>' . $lt['char'] . '</th>'; }
    $html .= '<th>รวม</th></tr></thead><tbody>';

    while ($emp = $res_emp->fetch_assoc()) {
        $m_sum = array_fill(1, 12, 0); $t_sum = array_fill_keys(array_keys($leave_types), 0);
        $q = "SELECT start_date, end_date, leave_type FROM leave_requests WHERE emp_id={$emp['emp_id']} AND status='Approved' AND (YEAR(start_date)=$year OR YEAR(end_date)=$year)";
        $res_l = $conn->query($q);
        while ($l = $res_l->fetch_assoc()) {
            $period = new DatePeriod(new DateTime($l['start_date']), new DateInterval('P1D'), (new DateTime($l['end_date']))->modify('+1 day'));
            foreach($period as $dt) {
                if($dt->format("Y") == $year) {
                    $m_sum[intval($dt->format("m"))]++;
                    $t_sum[$l['leave_type']]++;
                }
            }
        }
        $html .= '<tr><td class="name-col">' . $emp['first_name'] . ' ' . $emp['last_name'] . '</td>';
        for($m=1; $m<=12; $m++) { $html .= '<td>' . ($m_sum[$m] > 0 ? $m_sum[$m] : '-') . '</td>'; }
        foreach ($t_sum as $val) { $html .= '<td>' . ($val > 0 ? $val : '') . '</td>'; }
        $html .= '<td class="total-col">' . array_sum($t_sum) . '</td></tr>';
    }
}

// 4. ส่วนหมายเหตุสีขาวสะอาด
$html .= '</tbody></table>
<div class="note-box">
    <strong>คำอธิบายประเภทการลา:</strong><br>';
foreach ($leave_types as $lt) {
    $html .= '<span style="margin-right:25px;"><strong>' . $lt['char'] . '</strong> = ' . $lt['display'] . '</span>';
}
$html .= '</div>
<div style="text-align: right; margin-top: 10px; font-size: 10pt;">
    พิมพ์โดย: ' . htmlspecialchars($_SESSION['username']) . ' | วันที่: ' . date('d/m/Y H:i') . '
</div>';

$mpdf->WriteHTML($html);
$mpdf->Output('Leave_Summary_White.pdf', 'I');
?>