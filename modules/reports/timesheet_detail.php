<?php
/**
 * Laporan Detail Timesheet (Calendar View)
 */
require_once __DIR__ . '/../../includes/auth.php';
requirePermission('report_timesheet');

$user = getCurrentUser();
$isAdmin = in_array('super_admin', $user['roles'] ?? [$user['role']]) || in_array('finance', $user['roles'] ?? [$user['role']]) || in_array('project_manager', $user['roles'] ?? [$user['role']]);

// If employee, get their employee_id
$employee_id = null;
if (!$isAdmin) {
    $stmt = $pdo->prepare("SELECT id FROM employees WHERE user_id = ?");
    $stmt->execute([$user['id']]);
    $employee_id = $stmt->fetchColumn();
}

$pageTitle = 'Detail Timesheet';
$breadcrumbs = [
    ['label' => 'Laporan', 'url' => '#'],
    ['label' => 'Timesheet', 'url' => APP_URL . '/modules/reports/timesheet.php'],
    ['label' => 'Detail']
];

// Filters
$month = $_GET['month'] ?? date('n');
$year = $_GET['year'] ?? date('Y');
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$company_id = $_GET['company_id'] ?? '';
$project_id = $_GET['project_id'] ?? '';

if (!empty($start_date) && !empty($end_date)) {
    $begin = new DateTime($start_date);
    $end = new DateTime($end_date);
    $end->modify('+1 day');
    $interval = new DateInterval('P1D');
    $dateRange = new DatePeriod($begin, $interval, $end);
    $dates = [];
    foreach ($dateRange as $date) {
        $dates[] = $date->format('Y-m-d');
    }
    $daysInMonth = count($dates); 
} else {
    $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
    $dates = [];
    for ($d = 1; $d <= $daysInMonth; $d++) {
        $dates[] = sprintf('%04d-%02d-%02d', $year, $month, $d);
    }
}

// Build Query
$where = ["t.status = 'approved'"];
$params = [];

if (!empty($start_date) && !empty($end_date)) {
    $where[] = "t.work_date BETWEEN ? AND ?";
    $params[] = $start_date;
    $params[] = $end_date;
} else {
    $where[] = "MONTH(t.work_date) = ?";
    $where[] = "YEAR(t.work_date) = ?";
    $params[] = $month;
    $params[] = $year;
}

if (!empty($company_id)) {
    $where[] = "t.company_id = ?";
    $params[] = $company_id;
}
if (!empty($project_id)) {
    $where[] = "t.project_id = ?";
    $params[] = $project_id;
}
if ($employee_id) {
    $where[] = "t.employee_id = ?";
    $params[] = $employee_id;
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

// Group Data for Display
$groupedData = [];
foreach ($data as $row) {
    $comp = $row['company_name'];
    $eid = $row['emp_id'];
    $dateKey = $row['work_date'];
    
    if (!isset($groupedData[$comp])) {
        $groupedData[$comp] = [];
    }
    if (!isset($groupedData[$comp][$eid])) {
        $groupedData[$comp][$eid] = [
            'name' => $row['employee_name'],
            'days' => []
        ];
    }
    
    if (!isset($groupedData[$comp][$eid]['days'][$dateKey])) {
        $groupedData[$comp][$eid]['days'][$dateKey] = ['codes' => [], 'hours' => [], 'ots' => []];
    }
    
    $code = $row['project_code'] ?: 'UNKNOWN';
    $hour = ($row['work_type'] == 'full') ? 1 : 0.5;
    $ot = floatval($row['overtime_hours']);
    
    $groupedData[$comp][$eid]['days'][$dateKey]['codes'][] = $code;
    $groupedData[$comp][$eid]['days'][$dateKey]['hours'][] = $hour;
    $groupedData[$comp][$eid]['days'][$dateKey]['ots'][] = $ot;
}

require_once __DIR__ . '/../../includes/header.php';
$monthsLabel = ['', 'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
if (!empty($start_date) && !empty($end_date)) {
    $periodLabel = date('d/m/Y', strtotime($start_date)) . " - " . date('d/m/Y', strtotime($end_date));
} else {
    $periodLabel = strtoupper($monthsLabel[(int)$month]) . " " . $year;
}
?>

<div class="card mb-4 print-hide">
    <div class="card-body">
        <form action="" method="GET" class="row align-items-end">
            <div class="col-md-2 mb-3 mb-md-0">
                <label>Bulan</label>
                <select name="month" class="form-control">
                    <?php
                    for ($m=1; $m<=12; $m++) {
                        $sel = ($m == $month) ? 'selected' : '';
                        echo "<option value=\"$m\" $sel>{$monthsLabel[$m]}</option>";
                    }
                    ?>
                </select>
            </div>
            <div class="col-md-2 mb-3 mb-md-0">
                <label>Tahun</label>
                <select name="year" class="form-control">
                    <?php
                    $currentYear = date('Y');
                    for ($y = $currentYear - 2; $y <= $currentYear + 1; $y++) {
                        $sel = ($y == $year) ? 'selected' : '';
                        echo "<option value=\"$y\" $sel>$y</option>";
                    }
                    ?>
                </select>
            </div>

            <div class="col-md-2 mb-3 mb-md-0">
                <label>Tgl Mulai</label>
                <input type="date" name="start_date" class="form-control" value="<?= $start_date ?>">
            </div>
            <div class="col-md-2 mb-3 mb-md-0">
                <label>Tgl Selesai</label>
                <input type="date" name="end_date" class="form-control" value="<?= $end_date ?>">
            </div>
            
            <?php if ($isAdmin): ?>
            <div class="col-md-2 mb-3 mb-md-0">
                <label>Perusahaan</label>
                <select name="company_id" class="form-control select2">
                    <option value="">-- Semua Perusahaan --</option>
                    <?php
                    $comps = $pdo->query("SELECT id, name FROM companies ORDER BY name")->fetchAll();
                    foreach ($comps as $c) {
                        $sel = ($c['id'] == $company_id) ? 'selected' : '';
                        echo "<option value=\"{$c['id']}\" $sel>{$c['name']}</option>";
                    }
                    ?>
                </select>
            </div>
            <div class="col-md-2 mb-3 mb-md-0">
                <label>Proyek</label>
                <select name="project_id" class="form-control select2">
                    <option value="">-- Semua Proyek --</option>
                    <?php
                    $projs = $pdo->query("SELECT id, name FROM projects ORDER BY name")->fetchAll();
                    foreach ($projs as $p) {
                        $sel = ($p['id'] == $project_id) ? 'selected' : '';
                        echo "<option value=\"{$p['id']}\" $sel>{$p['name']}</option>";
                    }
                    ?>
                </select>
            </div>
            <?php endif; ?>
            
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search mr-1"></i> Filter</button>
            </div>
        </form>
    </div>
</div>

<div class="card" id="printArea">
    <div class="card-header d-flex justify-content-between align-items-center print-hide">
        <h3 class="card-title"><i class="fas fa-calendar-alt text-info mr-2"></i> Detail Timesheet</h3>
        <div class="ml-auto">
            <a href="<?= APP_URL ?>/modules/reports/timesheet.php" class="btn btn-secondary btn-sm mr-1"><i class="fas fa-arrow-left mr-1"></i> Kembali ke Rekap</a>
            <button onclick="window.print()" class="btn btn-secondary btn-sm"><i class="fas fa-print mr-1"></i> Print</button>
            <a href="<?= APP_URL ?>/modules/reports/timesheet_detail_export.php?month=<?= $month ?>&year=<?= $year ?>&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>&company_id=<?= $company_id ?>&project_id=<?= $project_id ?>" class="btn btn-success btn-sm ml-1"><i class="fas fa-download mr-1"></i> Export Excel</a>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="p-4 print-show" style="display:none;">
            <h4 class="text-center mb-0">LAPORAN DETAIL TIMESHEET</h4>
            <p class="text-center">Periode: <?= $periodLabel ?></p>
            <hr>
        </div>
        
        <div class="table-responsive p-3" style="overflow-x: auto;">
            <?php if (empty($groupedData)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="fas fa-calendar-times fa-3x mb-3 opacity-50"></i><br>
                    Data tidak ditemukan untuk filter ini.
                </div>
            <?php else: ?>
                <?php foreach ($groupedData as $companyName => $employees): ?>
                    <h5 class="bg-light p-2 mb-0 border"><i class="far fa-building mr-2"></i> <?= sanitize($companyName) ?></h5>
                    
                    <table class="table table-bordered table-sm table-striped mb-4" style="font-size:11px; white-space: nowrap; min-width: max-content;">
                        <thead class="thead-light">
                            <tr>
                                <th rowspan="3" class="align-middle text-center p-2" style="min-width: 150px;">NAMA</th>
                                <th colspan="<?= count($dates) * 2 ?>" class="text-center p-2 text-success" style="font-size: 14px; letter-spacing: 1px;">
                                    PERIODE: <?= $periodLabel ?>
                                </th>
                                <th rowspan="3" class="align-middle text-center p-2 bg-light">TOTAL JAM</th>
                                <th rowspan="3" class="align-middle text-center p-2 bg-light">TOTAL LEMBUR</th>
                            </tr>
                            <tr>
                                <?php foreach ($dates as $date): ?>
                                    <th colspan="2" class="text-center p-1 border-bottom-0"><?= date('d', strtotime($date)) ?></th>
                                <?php endforeach; ?>
                            </tr>
                            <tr>
                                <?php foreach ($dates as $date): 
                                    $isSunday = (date('w', strtotime($date)) == 0);
                                    if ($isSunday): ?>
                                        <th colspan="2" class="text-center p-1 bg-danger text-white">OFF</th>
                                    <?php else: ?>
                                        <th class="text-center p-1 text-muted" style="font-weight: normal; font-size: 10px;">In</th>
                                        <th class="text-center p-1 text-muted" style="font-weight: normal; font-size: 10px;">Jam</th>
                                    <?php endif; 
                                endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($employees as $eid => $emp): 
                                $rowTotalJam = 0;
                                $rowTotalLembur = 0;
                            ?>
                            <tr>
                                <td class="p-2 font-weight-bold"><?= sanitize($emp['name']) ?></td>
                                <?php foreach ($dates as $date): 
                                    $entry = $emp['days'][$date] ?? null;
                                    if ($entry):
                                        $codes = implode('<br>', array_map('sanitize', $entry['codes']));
                                        $jams = [];
                                        foreach($entry['hours'] as $idx => $h) {
                                            $ot = $entry['ots'][$idx];
                                            
                                            $rowTotalJam += $h;
                                            $rowTotalLembur += $ot;

                                            // Format: 1 or 0,5. If decimal, replace . with ,
                                            $hStr = str_replace('.', ',', (string)$h);
                                            $otStr = $ot > 0 ? str_replace('.', ',', (string)$ot) : '';
                                            $jams[] = $hStr . ($ot > 0 ? " ($otStr)" : "");
                                        }
                                        $jamStr = implode('<br>', $jams);
                                ?>
                                    <td class="text-center p-1 text-success font-weight-bold border-right-0"><?= $codes ?></td>
                                    <td class="text-center p-1 border-left-0"><?= $jamStr ?></td>
                                <?php else: ?>
                                    <td class="p-1 border-right-0"></td>
                                    <td class="p-1 border-left-0"></td>
                                <?php endif; endforeach; ?>
                                <td class="text-center p-2 font-weight-bold bg-light"><?= str_replace('.', ',', (string)$rowTotalJam) ?></td>
                                <td class="text-center p-2 font-weight-bold bg-light text-primary"><?= str_replace('.', ',', (string)$rowTotalLembur) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
@media print {
    @page { size: landscape; margin: 5mm; }
    body * { visibility: hidden; }
    #printArea, #printArea * { visibility: visible; }
    #printArea { position: absolute; left: 0; top: 0; width: 100%; }
    .print-hide { display: none !important; }
    .print-show { display: block !important; }
    .card { border: none !important; box-shadow: none !important; }
    .table-responsive { overflow: visible !important; }
    td, th { padding: 2px !important; font-size: 9px !important; }
}
/* Style to make borders between days more distinct */
th:nth-child(even), td:nth-child(even) { border-right: 2px solid #dee2e6; }
</style>

<?php
$extraJS = <<<'JS'
<script>
$(document).ready(function() {
    $('.select2').select2({ theme: 'bootstrap4' });
});
</script>
JS;
require_once __DIR__ . '/../../includes/footer.php';
?>
