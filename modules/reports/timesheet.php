<?php
/**
 * Laporan Timesheet
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

function formatUpah($val) {
    $formatted = number_format($val, 2, ',', '.');
    if (substr($formatted, -3) === ',00') {
        return substr($formatted, 0, -3);
    }
    if (substr($formatted, -1) === '0' && strpos($formatted, ',') !== false) {
        return substr($formatted, 0, -1);
    }
    return $formatted;
}

$pageTitle = 'Laporan Timesheet';
$breadcrumbs = [
    ['label' => 'Laporan', 'url' => '#'],
    ['label' => 'Timesheet']
];

// Filters
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('n');
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$company_id = (isset($_GET['company_id']) && $_GET['company_id'] !== '') ? (int)$_GET['company_id'] : '';
$project_id = (isset($_GET['project_id']) && $_GET['project_id'] !== '') ? (int)$_GET['project_id'] : '';

$start_date = '';
if (!empty($_GET['start_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['start_date'])) {
    $start_date = $_GET['start_date'];
}

$end_date = '';
if (!empty($_GET['end_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['end_date'])) {
    $end_date = $_GET['end_date'];
}

// Build Query
$where = ["t.status = 'approved'"];
$params = [];

if (!empty($start_date) && !empty($end_date)) {
    $where[] = "t.work_date BETWEEN ? AND ?";
    $params[] = $start_date;
    $params[] = $end_date;
    
    $months = ['', 'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    $periodText = date('d M Y', strtotime($start_date)) . " s/d " . date('d M Y', strtotime($end_date));
} else {
    $where[] = "MONTH(t.work_date) = ?";
    $where[] = "YEAR(t.work_date) = ?";
    $params[] = $month;
    $params[] = $year;
    
    $months = ['', 'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    $periodText = $months[$month] . " " . $year;
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

// Grouping query
$sql = "
    SELECT 
        c.name as company_name,
        p.name as project_name,
        u.full_name as employee_name,
        w.jabatan_name,
        MAX(t.daily_wage_at_time) as daily_wage,
        SUM(CASE WHEN t.work_type = 'full' THEN 1 ELSE 0 END) as full_days,
        SUM(CASE WHEN t.work_type = 'half' THEN 1 ELSE 0 END) as half_days,
        SUM(t.overtime_hours) as total_overtime,
        SUM(
            (CASE WHEN t.work_type = 'full' THEN 1 ELSE 0.5 END) * t.daily_wage_at_time
        ) as total_upah,
        SUM(
            (t.daily_wage_at_time / 8) * t.overtime_hours
        ) as total_upah_lembur
    FROM timesheet_entries t
    JOIN employees e ON t.employee_id = e.id
    JOIN users u ON e.user_id = u.id
    JOIN master_wages w ON e.wage_id = w.id
    JOIN projects p ON t.project_id = p.id
    JOIN companies c ON t.company_id = c.id
    WHERE $whereClause
    GROUP BY t.company_id, t.project_id, t.employee_id
    ORDER BY c.name, p.name, u.full_name
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$data = $stmt->fetchAll();

// Group Data for Display
$groupedData = [];
foreach ($data as $row) {
    $groupedData[$row['company_name']][$row['project_name']][] = $row;
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="card d-print-none mb-3">
    <div class="card-body p-3">
        <form method="GET" action="" class="row">
            <div class="col-md-2 col-sm-6 mb-2">
                <label style="font-size:12px;">Tgl Mulai</label>
                <input type="date" name="start_date" class="form-control form-control-sm" value="<?= $start_date ?>">
            </div>
            <div class="col-md-2 col-sm-6 mb-2">
                <label style="font-size:12px;">Tgl Selesai</label>
                <input type="date" name="end_date" class="form-control form-control-sm" value="<?= $end_date ?>">
            </div>
            <?php if ($isAdmin): ?>
            <div class="col-md-3 col-sm-6 mb-2">
                <label style="font-size:12px;">Perusahaan</label>
                <select name="company_id" class="form-control form-control-sm select2">
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
            <div class="col-md-3 col-sm-6 mb-2">
                <label style="font-size:12px;">Proyek</label>
                <select name="project_id" class="form-control form-control-sm select2">
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
            <div class="<?= $isAdmin ? 'col-md-2' : 'col-md-8' ?> col-sm-12 d-flex align-items-end mb-2">
                <button type="submit" class="btn btn-primary btn-sm btn-block"><i class="fas fa-search mr-1"></i>Filter</button>
                <a href="timesheet.php" class="btn btn-default btn-sm ml-2" title="Reset Filters"><i class="fas fa-sync-alt"></i></a>
            </div>
        </form>
    </div>
</div>

<div class="card" id="printArea">
    <div class="card-header d-flex justify-content-between align-items-center print-hide">
        <h3 class="card-title"><i class="fas fa-table text-primary mr-2"></i> Rekapitulasi Timesheet</h3>
        <div class="ml-auto">
            <a href="<?= APP_URL ?>/modules/reports/timesheet_detail.php?month=<?= $month ?>&year=<?= $year ?>&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>&company_id=<?= $company_id ?>&project_id=<?= $project_id ?>" class="btn btn-info btn-sm mr-1"><i class="fas fa-calendar-alt mr-1"></i> Detail Timesheet</a>
            <button onclick="window.print()" class="btn btn-secondary btn-sm"><i class="fas fa-print mr-1"></i> Print</button>
            <button type="button" class="btn btn-success btn-sm ml-1" data-toggle="modal" data-target="#importModal">
                <i class="fas fa-file-excel mr-1"></i> Import CSV
            </button>
            <a href="<?= APP_URL ?>/modules/timesheet/export_csv.php?month=<?= $month ?>&year=<?= $year ?>&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>&company_id=<?= $company_id ?>&project_id=<?= $project_id ?>" class="btn btn-success btn-sm ml-1"><i class="fas fa-download mr-1"></i> Export CSV</a>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="p-4 print-show" style="display:none;">
            <h4 class="text-center mb-0">LAPORAN TIMESHEET KARYAWAN</h4>
            <p class="text-center">Periode: <?= $periodText ?></p>
            <hr>
        </div>
        
        <div class="table-responsive p-3">
            <?php if (empty($groupedData)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="fas fa-folder-open fa-3x mb-3 opacity-50"></i><br>
                    Data tidak ditemukan untuk filter ini.
                </div>
            <?php else: ?>
                <?php 
                $grandTotalAll = 0;
                foreach ($groupedData as $companyName => $projects): 
                ?>
                    <h5 class="bg-light p-2 mb-0 border"><?= sanitize($companyName) ?></h5>
                    
                    <?php 
                    $companyTotal = 0;
                    foreach ($projects as $projectName => $employees): 
                    ?>
                        <div class="bg-secondary text-white p-2 border" style="font-size:14px;">Proyek: <?= sanitize($projectName) ?></div>
                        <table class="table table-bordered table-sm table-striped mb-4" style="width:100%;">
                            <thead class="thead-light">
                                <tr>
                                    <th rowspan="2" style="width:3%" class="text-center align-middle p-1">No</th>
                                    <th rowspan="2" style="width:14%" class="align-middle p-1">Nama Karyawan</th>
                                    <th rowspan="2" style="width:9%" class="align-middle p-1">Jabatan</th>
                                    <th colspan="2" class="text-center p-1">Hari Kerja</th>
                                    <th rowspan="2" style="width:9%" class="text-right align-middle p-1">Upah/Hari</th>
                                    <th rowspan="2" style="width:9%" class="text-right align-middle p-1">Total Upah</th>
                                    <th rowspan="2" style="width:8%" class="text-center align-middle p-1">Jam Kerja</th>
                                    <th rowspan="2" style="width:8%" class="text-center align-middle p-1">Jam Lembur</th>
                                    <th rowspan="2" style="width:9%" class="text-right align-middle p-1">Upah/Jam<br>Lembur</th>
                                    <th rowspan="2" style="width:9%" class="text-right align-middle p-1">Total Lembur</th>
                                    <th rowspan="2" style="width:10%" class="text-right align-middle p-1">Total Biaya</th>
                                </tr>
                                <tr>
                                    <th style="width:5%" class="text-center p-1">Full</th>
                                    <th style="width:5%" class="text-center p-1">Half</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $projectTotal = 0;
                                foreach ($employees as $i => $emp): 
                                    $jamKerjaNormal = ($emp['full_days'] * 8) + ($emp['half_days'] * 4);
                                    $upahLemburPerJam = $emp['daily_wage'] / 8;
                                    $totalBiaya = $emp['total_upah'] + $emp['total_upah_lembur'];
                                    
                                    $projectTotal += $totalBiaya;
                                ?>
                                <tr>
                                    <td class="text-center p-1"><?= $i + 1 ?></td>
                                    <td class="p-1 text-truncate"><?= sanitize($emp['employee_name']) ?></td>
                                    <td class="p-1 text-truncate"><?= sanitize($emp['jabatan_name']) ?></td>
                                    <td class="text-center p-1"><?= $emp['full_days'] ?></td>
                                    <td class="text-center p-1"><?= $emp['half_days'] ?></td>
                                    <td class="text-right p-1" style="white-space:nowrap"><?= formatUpah($emp['daily_wage']) ?></td>
                                    <td class="text-right p-1" style="white-space:nowrap"><?= formatUpah($emp['total_upah']) ?></td>
                                    <td class="text-center p-1"><?= $jamKerjaNormal ?></td>
                                    <td class="text-center p-1"><?= $emp['total_overtime'] ?></td>
                                    <td class="text-right p-1" style="white-space:nowrap"><?= number_format($upahLemburPerJam, 0, ',', '.') ?></td>
                                    <td class="text-right p-1" style="white-space:nowrap"><?= number_format($emp['total_upah_lembur'], 0, ',', '.') ?></td>
                                    <td class="text-right p-1 font-weight-bold" style="white-space:nowrap"><?= number_format($totalBiaya, 0, ',', '.') ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot class="bg-light">
                                <tr>
                                    <th colspan="11" class="text-right p-1" style="font-size: 13.5px;">Total Upah</th>
                                    <th class="text-right p-1 text-primary" style="white-space:nowrap; font-size: 13.5px;"><?= formatUpah($projectTotal) ?></th>
                                </tr>
                            </tfoot>
                        </table>
                    <?php 
                        $companyTotal += $projectTotal;
                    endforeach; 
                    ?>
                    
                    <div class="text-right mb-4">
                        <h6 class="font-weight-bold d-inline-block border p-2">TOTAL PERUSAHAAN: Rp <?= number_format($companyTotal, 0, ',', '.') ?></h6>
                    </div>
                    
                <?php 
                    $grandTotalAll += $companyTotal;
                endforeach; 
                ?>
                
                <?php if ($isAdmin): ?>
                <div class="alert alert-success text-right mb-0 border">
                    <h5 class="font-weight-bold mb-0">GRAND TOTAL: Rp <?= number_format($grandTotalAll, 0, ',', '.') ?></h5>
                </div>
                <?php endif; ?>
                
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Import CSV Modal -->
<div class="modal fade" id="importModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <form action="<?= APP_URL ?>/modules/timesheet/import_csv.php" method="POST" enctype="multipart/form-data">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Import Timesheet (CSV)</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info" style="font-size: 13px;">
                        <i class="fas fa-info-circle mr-1"></i> Format CSV wajib menggunakan kolom:
                        <br><strong>Username_Karyawan, ID_Company, ID_Proyek, Tanggal (YYYY-MM-DD), Tipe_Kerja (full/half), Jam_Lembur, Catatan</strong>
                    </div>
                    <div class="form-group mb-4">
                        <a href="<?= APP_URL ?>/modules/timesheet/template_csv.php" class="btn btn-outline-success btn-sm">
                            <i class="fas fa-download mr-1"></i> Download Template CSV
                        </a>
                    </div>
                    <div class="form-group">
                        <label>Upload File CSV <span class="text-danger">*</span></label>
                        <input type="file" name="file" class="form-control-file" accept=".csv" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-upload mr-1"></i> Proses Import</button>
                </div>
            </div>
        </form>
    </div>
</div>

<style>
@media print {
    body * { visibility: hidden; }
    #printArea, #printArea * { visibility: visible; }
    #printArea { position: absolute; left: 0; top: 0; width: 100%; }
    .print-hide { display: none !important; }
    .print-show { display: block !important; }
    .card { border: none !important; box-shadow: none !important; }
}
#printArea table th {
    vertical-align: middle;
    text-align: center;
    line-height: 1.2;
    word-wrap: break-word;
    white-space: normal !important;
}
</style>

<?php
$extraJS = <<<'JS'
<script>
$(document).ready(function() {
    initSelect2('.select2');
});
</script>
JS;
require_once __DIR__ . '/../../includes/footer.php';
?>
