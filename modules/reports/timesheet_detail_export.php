<?php
/**
 * Export Detail Timesheet ke Excel
 */
require_once __DIR__ . '/../../includes/auth.php';
requirePermission('report_timesheet');

$month = $_GET['month'] ?? date('n');
$year = $_GET['year'] ?? date('Y');
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$company_id = $_GET['company_id'] ?? '';
$project_id = $_GET['project_id'] ?? '';

$where = ["t.status = 'approved'"];
$params = [];

if (!empty($start_date) && !empty($end_date)) {
    $where[] = "t.work_date BETWEEN ? AND ?";
    $params[] = $start_date;
    $params[] = $end_date;
    
    $begin = new DateTime($start_date);
    $end = new DateTime($end_date);
    $end->modify('+1 day');
    $interval = new DateInterval('P1D');
    $dateRange = new DatePeriod($begin, $interval, $end);
    $dates = [];
    foreach ($dateRange as $date) {
        $dates[] = $date->format('Y-m-d');
    }
    $periodText = date('d M Y', strtotime($start_date)) . " - " . date('d M Y', strtotime($end_date));
} else {
    $where[] = "MONTH(t.work_date) = ?";
    $where[] = "YEAR(t.work_date) = ?";
    $params[] = $month;
    $params[] = $year;
    
    $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
    $dates = [];
    for ($d = 1; $d <= $daysInMonth; $d++) {
        $dates[] = sprintf('%04d-%02d-%02d', $year, $month, $d);
    }
    $monthsLabel = ['', 'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    $periodText = $monthsLabel[(int)$month] . " " . $year;
}

if (!empty($company_id)) {
    $where[] = "t.company_id = ?";
    $params[] = $company_id;
}
if (!empty($project_id)) {
    $where[] = "t.project_id = ?";
    $params[] = $project_id;
}

$user = getCurrentUser();
$isAdmin = !in_array('karyawan', array_map('strtolower', $user['roles'] ?? [$user['role']]));
if (!$isAdmin) {
    $stmt = $pdo->prepare("SELECT id FROM employees WHERE user_id = ?");
    $stmt->execute([$user['id']]);
    $emp_id = $stmt->fetchColumn();
    if ($emp_id) {
        $where[] = "t.employee_id = ?";
        $params[] = $emp_id;
    } else {
        die("Unauthorized.");
    }
}

$whereClause = implode(" AND ", $where);

// Fetch Data
$sql = "
    SELECT 
        c.name as company_name,
        cu.abbreviation as project_code,
        p.name as project_name,
        u.full_name as employee_name,
        e.id as emp_id,
        t.work_date,
        t.work_type,
        t.overtime_hours
    FROM timesheet_entries t
    JOIN employees e ON t.employee_id = e.id
    JOIN users u ON e.user_id = u.id
    JOIN companies c ON t.company_id = c.id
    LEFT JOIN projects p ON t.project_id = p.id
    LEFT JOIN customers cu ON p.customer_id = cu.id
    WHERE $whereClause
    ORDER BY c.name, u.full_name, t.work_date
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$data = $stmt->fetchAll();

$groupedData = [];
foreach ($data as $row) {
    $comp = $row['company_name'];
    $eid = $row['emp_id'];
    $dateKey = $row['work_date'];
    
    if (!isset($groupedData[$comp])) $groupedData[$comp] = [];
    if (!isset($groupedData[$comp][$eid])) $groupedData[$comp][$eid] = ['name' => $row['employee_name'], 'days' => []];
    if (!isset($groupedData[$comp][$eid]['days'][$dateKey])) $groupedData[$comp][$eid]['days'][$dateKey] = ['codes' => [], 'hours' => [], 'ots' => []];
    
    $code = $row['project_code'] ?: 'UNKNOWN';
    $hour = ($row['work_type'] == 'full') ? 1 : 0.5;
    $ot = floatval($row['overtime_hours']);
    
    $groupedData[$comp][$eid]['days'][$dateKey]['codes'][] = $code;
    $groupedData[$comp][$eid]['days'][$dateKey]['hours'][] = $hour;
    $groupedData[$comp][$eid]['days'][$dateKey]['ots'][] = $ot;
}

// Headers for Excel
$filename = "Timesheet_Detail_" . str_replace(' ', '_', $periodText) . ".xls";
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");

?>
<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
</head>
<body>
    <?php foreach ($groupedData as $companyName => $employees): ?>
    <table border="1">
        <tr>
            <th colspan="<?= (count($dates) * 2) + 3 ?>" style="background-color: #f2f2f2; font-size: 16px;">
                PERUSAHAAN: <?= strtoupper(sanitize($companyName)) ?>
            </th>
        </tr>
        <tr>
            <th colspan="<?= (count($dates) * 2) + 3 ?>" style="background-color: #f2f2f2;">
                PERIODE: <?= strtoupper($periodText) ?>
            </th>
        </tr>
        <tr>
            <th rowspan="3" style="background-color: #e2efda; vertical-align: middle;">NAMA KARYAWAN</th>
            <th colspan="<?= count($dates) * 2 ?>" style="background-color: #fff2cc;">TANGGAL</th>
            <th rowspan="3" style="background-color: #d9d9d9; vertical-align: middle;">TOTAL JAM</th>
            <th rowspan="3" style="background-color: #d9d9d9; vertical-align: middle;">TOTAL LEMBUR</th>
        </tr>
        <tr>
            <?php foreach ($dates as $date): ?>
                <th colspan="2" style="background-color: #fff2cc;"><?= date('d', strtotime($date)) ?></th>
            <?php endforeach; ?>
        </tr>
        <tr>
            <?php foreach ($dates as $date): 
                $isSunday = (date('w', strtotime($date)) == 0);
                if ($isSunday): ?>
                    <th colspan="2" style="background-color: #ffc7ce; color: #9c0006;">OFF</th>
                <?php else: ?>
                    <th style="background-color: #d9d9d9;">In</th>
                    <th style="background-color: #d9d9d9;">Jam</th>
                <?php endif; ?>
            <?php endforeach; ?>
        </tr>
        <?php foreach ($employees as $eid => $emp): 
            $rowTotalJam = 0;
            $rowTotalLembur = 0;
        ?>
        <tr>
            <td style="font-weight: bold;"><?= sanitize($emp['name']) ?></td>
            <?php foreach ($dates as $date): 
                $entry = $emp['days'][$date] ?? null;
                if ($entry):
                    $codes = implode(' / ', array_map('sanitize', $entry['codes']));
                    $jams = [];
                    foreach($entry['hours'] as $idx => $h) {
                        $ot = $entry['ots'][$idx];
                        $rowTotalJam += $h;
                        $rowTotalLembur += $ot;
                        $hStr = str_replace('.', ',', (string)$h);
                        $otStr = $ot > 0 ? str_replace('.', ',', (string)$ot) : '';
                        $jams[] = $hStr . ($ot > 0 ? " ($otStr)" : "");
                    }
                    $jamStr = implode(' / ', $jams);
            ?>
                <td style="text-align: center; color: #006100; font-weight: bold;"><?= $codes ?></td>
                <td style="text-align: center;"><?= $jamStr ?></td>
            <?php else: ?>
                <td></td>
                <td></td>
            <?php endif; ?>
            <?php endforeach; ?>
            <td style="text-align: center; font-weight: bold; background-color: #f2f2f2;"><?= str_replace('.', ',', (string)$rowTotalJam) ?></td>
            <td style="text-align: center; font-weight: bold; background-color: #f2f2f2; color: #0000ff;"><?= str_replace('.', ',', (string)$rowTotalLembur) ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <br><br>
    <?php endforeach; ?>
</body>
</html>
<?php exit; ?>
