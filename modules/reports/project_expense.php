<?php
/**
 * Report - Project Expense (Pengeluaran per Proyek)
 */
require_once __DIR__ . '/../../includes/auth.php';
requirePermission('report_project_expense');

$pageTitle = 'Laporan Pengeluaran Proyek';
$breadcrumbs = [
    ['label' => 'Laporan', 'url' => '#'],
    ['label' => 'Pengeluaran Proyek']
];

// Get filters
$filterStart = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$filterEnd = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$filterProject = $_GET['project_id'] ?? '';
$filterStatus = $_GET['status'] ?? '';

// Build conditions
$conditions = [];
$params = [];

if ($filterProject) {
    $conditions[] = "p.id = ?";
    $params[] = $filterProject;
}
if ($filterStatus) {
    $conditions[] = "p.status = ?";
    $params[] = $filterStatus;
}

$whereClause = "";
if (!empty($conditions)) {
    $whereClause = "WHERE " . implode(" AND ", $conditions);
}

// Modify subqueries to support date filters
$dateCondPO = "";
$dateCondVP = "";
$dateCondMR = "";
if ($filterStart) {
    $dateCondPO .= " AND po.po_date >= " . $pdo->quote($filterStart);
    $dateCondVP .= " AND vp.payment_date >= " . $pdo->quote($filterStart);
    $dateCondMR .= " AND mr.request_date >= " . $pdo->quote($filterStart);
}
if ($filterEnd) {
    $dateCondPO .= " AND po.po_date <= " . $pdo->quote($filterEnd);
    $dateCondVP .= " AND vp.payment_date <= " . $pdo->quote($filterEnd);
    $dateCondMR .= " AND mr.request_date <= " . $pdo->quote($filterEnd);
}

$sql = "
    SELECT 
        p.id, p.name, p.abbreviation, p.status, p.budget,
        (SELECT COUNT(*) FROM material_requests mr WHERE mr.project_id = p.id AND mr.status != 'draft' $dateCondMR) as total_mr,
        (SELECT COALESCE(SUM(po.total), 0) 
         FROM purchase_orders po 
         JOIN po_mr_links pml ON pml.po_id = po.id 
         JOIN material_requests mr ON pml.mr_id = mr.id 
         WHERE mr.project_id = p.id AND po.status NOT IN ('draft','cancelled','rejected') $dateCondPO
        ) as total_po_value,
        (SELECT COALESCE(SUM(vp.amount), 0) 
         FROM vendor_payments vp 
         JOIN purchase_orders po ON vp.po_id = po.id
         JOIN po_mr_links pml ON pml.po_id = po.id
         JOIN material_requests mr ON pml.mr_id = mr.id
         WHERE mr.project_id = p.id $dateCondVP
        ) as total_paid
    FROM projects p
    $whereClause
    ORDER BY p.name
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$projects = $stmt->fetchAll();

// Fetch all projects for filter dropdown
$allProjects = $pdo->query("SELECT id, name FROM projects ORDER BY name ASC")->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/report_print.php';
?>

<?php 
$periodText = '';
if ($filterStart || $filterEnd) {
    $periodText = 'Periode: ' . ($filterStart ? date('d-m-Y', strtotime($filterStart)) : 'Awal') . ' s/d ' . ($filterEnd ? date('d-m-Y', strtotime($filterEnd)) : 'Akhir');
}
renderReportPrintHeader('Laporan Pengeluaran Proyek', $periodText); 
?>

<!-- Filter Card -->
<div class="card d-print-none mb-3">
    <div class="card-body p-3">
        <form method="GET" action="" class="row">
            <div class="col-md-2 col-sm-6 mb-2">
                <label style="font-size:12px;">Tanggal Mulai</label>
                <input type="date" name="start_date" class="form-control form-control-sm" value="<?= htmlspecialchars($filterStart) ?>">
            </div>
            <div class="col-md-2 col-sm-6 mb-2">
                <label style="font-size:12px;">Tanggal Selesai</label>
                <input type="date" name="end_date" class="form-control form-control-sm" value="<?= htmlspecialchars($filterEnd) ?>">
            </div>
            <div class="col-md-4 col-sm-6 mb-2">
                <label style="font-size:12px;">Proyek</label>
                <select name="project_id" class="form-control form-control-sm select2">
                    <option value="">-- Semua Proyek --</option>
                    <?php foreach ($allProjects as $proj): ?>
                        <option value="<?= $proj['id'] ?>" <?= $filterProject == $proj['id'] ? 'selected' : '' ?>>
                            <?= sanitize($proj['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 col-sm-6 mb-2">
                <label style="font-size:12px;">Status Proyek</label>
                <select name="status" class="form-control form-control-sm select2">
                    <option value="">-- Semua Status --</option>
                    <option value="planning" <?= $filterStatus === 'planning' ? 'selected' : '' ?>>Planning</option>
                    <option value="active" <?= $filterStatus === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="completed" <?= $filterStatus === 'completed' ? 'selected' : '' ?>>Completed</option>
                    <option value="cancelled" <?= $filterStatus === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                </select>
            </div>
            <div class="col-md-2 col-sm-12 d-flex align-items-end mb-2">
                <button type="submit" class="btn btn-primary btn-sm btn-block"><i class="fas fa-search mr-1"></i>Filter</button>
                <a href="project_expense.php" class="btn btn-default btn-sm ml-2" title="Reset Filters"><i class="fas fa-sync-alt"></i></a>
            </div>
        </form>
    </div>
</div>

<div class="card card-outline card-primary">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title"><i class="fas fa-chart-bar mr-2"></i> Pengeluaran Per Proyek</h3>
        <div class="ml-auto d-flex gap-2">
            <a href="export_excel.php?<?= http_build_query(array_merge($_GET, ['type' => 'project_expense'])) ?>" class="btn btn-success btn-sm"><i class="fas fa-file-excel mr-1"></i> Export Excel</a>
            <a href="export_csv.php?<?= http_build_query(array_merge($_GET, ['type' => 'project_expense'])) ?>" class="btn btn-info btn-sm ml-1"><i class="fas fa-file-csv mr-1"></i> Export CSV</a>
            <button class="btn btn-default btn-sm ml-1" onclick="window.print()"><i class="fas fa-print mr-1"></i> Cetak</button>
        </div>
    </div>
    <div class="card-body">
        <table id="reportTable" class="table table-bordered table-striped table-hover table-sm w-100" >
            <thead class="bg-light">
                <tr>
                    <th width="25%">Nama Proyek</th>
                    <th width="10%" class="text-center">Status</th>
                    <th width="10%" class="text-center">Jumlah MR</th>
                    <th width="15%" class="text-right">Budget (Rp)</th>
                    <th width="15%" class="text-right">Nilai PO (Rp)</th>
                    <th width="15%" class="text-right">Terbayar (Rp)</th>
                    <th width="10%" class="text-center">% Terpakai</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $grandBudget = 0; $grandPO = 0; $grandPaid = 0;
                foreach ($projects as $p): 
                    $pctUsed = $p['budget'] > 0 ? round(($p['total_po_value'] / $p['budget']) * 100, 1) : 0;
                    $pctClass = $pctUsed > 90 ? 'text-danger' : ($pctUsed > 70 ? 'text-warning' : 'text-success');
                    $grandBudget += $p['budget'];
                    $grandPO += $p['total_po_value'];
                    $grandPaid += $p['total_paid'];
                ?>
                <tr>
                    <td><strong><?= sanitize($p['name']) ?></strong></td>
                    <td class="text-center"><?= getStatusBadge($p['status']) ?></td>
                    <td class="text-center"><?= $p['total_mr'] ?></td>
                    <td class="text-right"><?= formatRupiah($p['budget']) ?></td>
                    <td class="text-right font-weight-bold"><?= formatRupiah($p['total_po_value']) ?></td>
                    <td class="text-right text-success"><?= formatRupiah($p['total_paid']) ?></td>
                    <td class="text-center font-weight-bold <?= $pctClass ?>">
                        <?= $pctUsed ?>%
                        <?php if ($pctUsed > 90): ?>
                            <i class="fas fa-exclamation-triangle ml-1"></i>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr class="font-weight-bold bg-light">
                    <td colspan="3" class="text-right">TOTAL:</td>
                    <td class="text-right"><?= formatRupiah($grandBudget) ?></td>
                    <td class="text-right"><?= formatRupiah($grandPO) ?></td>
                    <td class="text-right text-success"><?= formatRupiah($grandPaid) ?></td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>
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