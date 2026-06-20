<?php
/**
 * Report - Claim Nota (3 views: Detail, Per Karyawan, Per Proyek)
 */
require_once __DIR__ . '/../../includes/auth.php';
requirePermission('report_claim_nota');

$pageTitle = 'Laporan Claim Nota';
$breadcrumbs = [
    ['label' => 'Laporan', 'url' => '#'],
    ['label' => 'Claim Nota']
];

// Filters
$filterProject = $_GET['project_id'] ?? '';
$filterStatus = $_GET['status'] ?? '';
$filterDateFrom = $_GET['date_from'] ?? '';
$filterDateTo = $_GET['date_to'] ?? '';
$activeTab = $_GET['tab'] ?? 'detail';

// Fetch projects for filter
$projects = $pdo->query("SELECT id, name, abbreviation FROM projects ORDER BY name")->fetchAll();

// ─── TAB 1: Detail semua claim ───
$whereDetail = ["cn.status != 'draft'"];
$paramsDetail = [];

if ($filterProject) {
    $whereDetail[] = "cn.project_id = ?";
    $paramsDetail[] = $filterProject;
}
if ($filterStatus) {
    if ($filterStatus === 'reimbursed') {
        $whereDetail[] = "cn.is_reimbursed = 1";
    } elseif ($filterStatus === 'not_reimbursed') {
        $whereDetail[] = "cn.is_reimbursed = 0 AND cn.status = 'approved'";
    } else {
        $whereDetail[] = "cn.status = ?";
        $paramsDetail[] = $filterStatus;
    }
}
if ($filterDateFrom) {
    $whereDetail[] = "cn.claim_date >= ?";
    $paramsDetail[] = $filterDateFrom;
}
if ($filterDateTo) {
    $whereDetail[] = "cn.claim_date <= ?";
    $paramsDetail[] = $filterDateTo;
}

$whereClause = implode(' AND ', $whereDetail);

$sqlDetail = "
    SELECT cn.*, 
           p.name as project_name, p.abbreviation as project_abbr,
           c.name as company_name,
           u.full_name as claimer_name
    FROM claim_notas cn
    JOIN projects p ON cn.project_id = p.id
    JOIN companies c ON cn.company_id = c.id
    LEFT JOIN users u ON cn.claimed_by = u.id
    WHERE {$whereClause}
    ORDER BY cn.claim_date DESC, cn.id DESC
";
$stmtDetail = $pdo->prepare($sqlDetail);
$stmtDetail->execute($paramsDetail);
$detailRows = $stmtDetail->fetchAll();

// ─── TAB 2: Per Karyawan ───
$sqlEmployee = "
    SELECT cn.employee_name,
           COUNT(*) as total_claims,
           SUM(cn.subtotal) as total_amount,
           SUM(CASE WHEN cn.is_reimbursed = 1 THEN cn.subtotal ELSE 0 END) as reimbursed_amount,
           SUM(CASE WHEN cn.is_reimbursed = 0 AND cn.status = 'approved' THEN cn.subtotal ELSE 0 END) as pending_reimburse
    FROM claim_notas cn
    WHERE cn.status IN ('approved') OR cn.is_reimbursed = 1
    GROUP BY cn.employee_name
    ORDER BY total_amount DESC
";
$employeeRows = $pdo->query($sqlEmployee)->fetchAll();

// ─── TAB 3: Per Proyek ───
$sqlProject = "
    SELECT p.name as project_name, p.abbreviation as project_abbr,
           COUNT(*) as total_claims,
           SUM(cn.subtotal) as total_amount,
           SUM(CASE WHEN cn.is_reimbursed = 1 THEN cn.subtotal ELSE 0 END) as reimbursed_amount
    FROM claim_notas cn
    JOIN projects p ON cn.project_id = p.id
    WHERE cn.status IN ('approved') OR cn.is_reimbursed = 1
    GROUP BY cn.project_id
    ORDER BY total_amount DESC
";
$projectRows = $pdo->query($sqlProject)->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/report_print.php';
?>

<?php renderReportPrintHeader('Laporan Claim Nota'); ?>

<!-- Filter Card -->
<div class="card d-print-none mb-3">
    <div class="card-body p-3">
        <form method="GET" class="row">
            <input type="hidden" name="tab" value="<?= sanitize($activeTab) ?>">
            <div class="col-md-3 col-sm-6 mb-2">
                <label style="font-size:12px;">Proyek</label>
                <select name="project_id" class="form-control form-control-sm select2">
                    <option value="">-- Semua Proyek --</option>
                    <?php foreach ($projects as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= $filterProject == $p['id'] ? 'selected' : '' ?>>
                            [<?= sanitize($p['abbreviation']) ?>] <?= sanitize($p['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 col-sm-6 mb-2">
                <label style="font-size:12px;">Status</label>
                <select name="status" class="form-control form-control-sm select2">
                    <option value="">-- Semua Status --</option>
                    <option value="pending" <?= $filterStatus === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="approved" <?= $filterStatus === 'approved' ? 'selected' : '' ?>>Approved</option>
                    <option value="rejected" <?= $filterStatus === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                    <option value="reimbursed" <?= $filterStatus === 'reimbursed' ? 'selected' : '' ?>>Reimbursed</option>
                    <option value="not_reimbursed" <?= $filterStatus === 'not_reimbursed' ? 'selected' : '' ?>>Belum Reimburse</option>
                </select>
            </div>
            <div class="col-md-2 col-sm-6 mb-2">
                <label style="font-size:12px;">Dari Tanggal</label>
                <input type="date" name="date_from" class="form-control form-control-sm" value="<?= $filterDateFrom ?>">
            </div>
            <div class="col-md-2 col-sm-6 mb-2">
                <label style="font-size:12px;">Sampai Tanggal</label>
                <input type="date" name="date_to" class="form-control form-control-sm" value="<?= $filterDateTo ?>">
            </div>
            <div class="col-md-3 col-sm-12 d-flex align-items-end justify-content-end mb-2">
                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search mr-1"></i> Filter</button>
                <a href="claim_nota.php" class="btn btn-default btn-sm ml-1" title="Reset Filters"><i class="fas fa-sync-alt"></i></a>
                <a href="export_excel.php?type=claim_nota&<?= http_build_query($_GET) ?>" class="btn btn-success btn-sm ml-1" title="Export Excel"><i class="fas fa-file-excel"></i></a>
                <a href="export_csv.php?type=claim_nota&<?= http_build_query($_GET) ?>" class="btn btn-info btn-sm ml-1" title="Export CSV"><i class="fas fa-file-csv"></i></a>
            </div>
        </form>
    </div>
</div>

<!-- Tabs -->
<ul class="nav nav-tabs" role="tablist">
    <li class="nav-item">
        <a class="nav-link <?= $activeTab === 'detail' ? 'active' : '' ?>" href="?tab=detail&<?= http_build_query(array_diff_key($_GET, ['tab'=>''])) ?>">
            <i class="fas fa-list mr-1"></i> Detail Semua Claim
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $activeTab === 'employee' ? 'active' : '' ?>" href="?tab=employee">
            <i class="fas fa-users mr-1"></i> Per Karyawan
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $activeTab === 'project' ? 'active' : '' ?>" href="?tab=project">
            <i class="fas fa-project-diagram mr-1"></i> Per Proyek
        </a>
    </li>
</ul>

<!-- Tab Content -->
<div class="tab-content mt-0">
    <?php if ($activeTab === 'detail'): ?>
    <!-- Detail Tab -->
    <div class="card card-outline card-primary" style="border-top-left-radius:0;">
        <div class="card-body">
            <table id="reportTable" class="table table-bordered table-striped table-hover table-sm w-100" >
                <thead class="bg-light">
                    <tr>
                        <th width="12%">No. Claim</th>
                        <th width="9%">Tanggal</th>
                        <th width="14%">Karyawan</th>
                        <th width="15%">Proyek</th>
                        <th width="12%">Perusahaan</th>
                        <th width="10%">Toko</th>
                        <th width="12%" class="text-right">Total (Rp)</th>
                        <th width="8%" class="text-center">Status</th>
                        <th width="8%" class="text-center">Reimburse</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $grandTotal = 0;
                    foreach ($detailRows as $r): 
                        $grandTotal += $r['subtotal'];
                    ?>
                    <tr>
                        <td>
                            <a href="<?= APP_URL ?>/modules/finance/claim_nota/view.php?id=<?= $r['id'] ?>" class="font-weight-bold text-info" target="_blank">
                                <?= sanitize($r['claim_number']) ?>
                            </a>
                        </td>
                        <td><?= date('d-m-Y', strtotime($r['claim_date'])) ?></td>
                        <td><?= sanitize($r['employee_name']) ?></td>
                        <td><small class="badge badge-light"><?= sanitize($r['project_abbr']) ?></small> <?= sanitize($r['project_name']) ?></td>
                        <td><?= sanitize($r['company_name']) ?></td>
                        <td><?= sanitize($r['store_name']) ?: '-' ?></td>
                        <td class="text-right font-weight-bold"><?= formatRupiah($r['subtotal']) ?></td>
                        <td class="text-center"><?= getStatusBadge($r['status']) ?></td>
                        <td class="text-center">
                            <?php if ($r['is_reimbursed']): ?>
                                <span class="badge badge-info"><i class="fas fa-check"></i> Ya</span>
                            <?php else: ?>
                                <span class="badge badge-secondary">Belum</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="font-weight-bold bg-light">
                        <td colspan="6" class="text-right">TOTAL:</td>
                        <td class="text-right"><?= formatRupiah($grandTotal) ?></td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
    
    <?php elseif ($activeTab === 'employee'): ?>
    <!-- Per Karyawan Tab -->
    <div class="card card-outline card-primary" style="border-top-left-radius:0;">
        <div class="card-body">
            <table id="reportTable" class="table table-bordered table-striped table-hover table-sm w-100" >
                <thead class="bg-light">
                    <tr>
                        <th width="5%">No</th>
                        <th width="25%">Nama Karyawan</th>
                        <th width="10%" class="text-center">Jumlah Claim</th>
                        <th width="18%" class="text-right">Total Claim (Rp)</th>
                        <th width="18%" class="text-right">Sudah Reimburse (Rp)</th>
                        <th width="18%" class="text-right">Belum Reimburse (Rp)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $no = 1;
                    $gTotal = 0; $gReimb = 0; $gPending = 0;
                    foreach ($employeeRows as $r): 
                        $gTotal += $r['total_amount'];
                        $gReimb += $r['reimbursed_amount'];
                        $gPending += $r['pending_reimburse'];
                    ?>
                    <tr>
                        <td class="text-center"><?= $no++ ?></td>
                        <td><strong><?= sanitize($r['employee_name']) ?></strong></td>
                        <td class="text-center"><?= $r['total_claims'] ?></td>
                        <td class="text-right font-weight-bold"><?= formatRupiah($r['total_amount']) ?></td>
                        <td class="text-right text-success"><?= formatRupiah($r['reimbursed_amount']) ?></td>
                        <td class="text-right text-danger"><?= formatRupiah($r['pending_reimburse']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="font-weight-bold bg-light">
                        <td colspan="2" class="text-right">TOTAL:</td>
                        <td class="text-center"><?= array_sum(array_column($employeeRows, 'total_claims')) ?></td>
                        <td class="text-right"><?= formatRupiah($gTotal) ?></td>
                        <td class="text-right text-success"><?= formatRupiah($gReimb) ?></td>
                        <td class="text-right text-danger"><?= formatRupiah($gPending) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <?php elseif ($activeTab === 'project'): ?>
    <!-- Per Proyek Tab -->
    <div class="card card-outline card-primary" style="border-top-left-radius:0;">
        <div class="card-body">
            <table id="reportTable" class="table table-bordered table-striped table-hover table-sm w-100" >
                <thead class="bg-light">
                    <tr>
                        <th width="5%">No</th>
                        <th width="30%">Nama Proyek</th>
                        <th width="12%" class="text-center">Jumlah Claim</th>
                        <th width="20%" class="text-right">Total Claim (Rp)</th>
                        <th width="20%" class="text-right">Sudah Reimburse (Rp)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $no = 1;
                    $gTotal = 0; $gReimb = 0;
                    foreach ($projectRows as $r): 
                        $gTotal += $r['total_amount'];
                        $gReimb += $r['reimbursed_amount'];
                    ?>
                    <tr>
                        <td class="text-center"><?= $no++ ?></td>
                        <td><small class="badge badge-light"><?= sanitize($r['project_abbr']) ?></small> <strong><?= sanitize($r['project_name']) ?></strong></td>
                        <td class="text-center"><?= $r['total_claims'] ?></td>
                        <td class="text-right font-weight-bold"><?= formatRupiah($r['total_amount']) ?></td>
                        <td class="text-right text-success"><?= formatRupiah($r['reimbursed_amount']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="font-weight-bold bg-light">
                        <td colspan="2" class="text-right">TOTAL:</td>
                        <td class="text-center"><?= array_sum(array_column($projectRows, 'total_claims')) ?></td>
                        <td class="text-right"><?= formatRupiah($gTotal) ?></td>
                        <td class="text-right text-success"><?= formatRupiah($gReimb) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php renderReportPrintFooter(); ?>

<?php
$extraJS = <<<'JS'
<script>
$(document).ready(function() {
    initDataTable('#reportTable');
    initSelect2('.select2');
});
</script>
JS;
require_once __DIR__ . '/../../includes/footer.php';
?>
