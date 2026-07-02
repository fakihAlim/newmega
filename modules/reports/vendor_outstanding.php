<?php
/**
 * Report - Vendor Outstanding (Hutang ke Vendor)
 */
require_once __DIR__ . '/../../includes/auth.php';
requirePermission('report_vendor_outstanding');

$pageTitle = 'Outstanding Supplier (Hutang)';
$breadcrumbs = [
    ['label' => 'Laporan', 'url' => '#'],
    ['label' => 'Outstanding Vendor']
];

// Get filters
$filterStart = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$filterEnd = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$filterVendor = $_GET['vendor_id'] ?? '';
$filterStatus = $_GET['status'] ?? '';

// Build conditions
$conditions = ["po.status NOT IN ('draft', 'cancelled', 'rejected')"];
$params = [];

if ($filterStart) {
    $conditions[] = "po.po_date >= ?";
    $params[] = $filterStart;
}
if ($filterEnd) {
    $conditions[] = "po.po_date <= ?";
    $params[] = $filterEnd;
}
if ($filterVendor) {
    $conditions[] = "po.vendor_id = ?";
    $params[] = $filterVendor;
}
if ($filterStatus) {
    $conditions[] = "po.status = ?";
    $params[] = $filterStatus;
}

$whereClause = "";
if (!empty($conditions)) {
    $whereClause = "WHERE " . implode(" AND ", $conditions);
}

// Fetch all POs with outstanding balance
$sql = "
    SELECT 
        po.id, po.po_number, po.po_date, po.total as po_total, po.status as po_status,
        v.company_name as vendor_name,
        COALESCE(SUM(vp.amount), 0) as total_paid
    FROM purchase_orders po
    JOIN vendors v ON po.vendor_id = v.id
    LEFT JOIN vendor_payments vp ON vp.po_id = po.id
    $whereClause
    GROUP BY po.id
    ORDER BY (po.total - COALESCE(SUM(vp.amount), 0)) DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$pos = $stmt->fetchAll();

// Fetch all vendors for filter dropdown
$vendors = $pdo->query("SELECT id, company_name FROM vendors ORDER BY company_name ASC")->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/report_print.php';
?>

<?php 
$periodText = '';
if ($filterStart || $filterEnd) {
    $periodText = 'Periode: ' . ($filterStart ? date('d-m-Y', strtotime($filterStart)) : 'Awal') . ' s/d ' . ($filterEnd ? date('d-m-Y', strtotime($filterEnd)) : 'Akhir');
}
renderReportPrintHeader('Rekap Hutang ke Supplier', $periodText); 
?>

<!-- Filter Card -->
<div class="card card-outline card-primary d-print-none mb-3">
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
                <label style="font-size:12px;">Supplier / Vendor</label>
                <select name="vendor_id" class="form-control form-control-sm select2">
                    <option value="">-- Semua Supplier --</option>
                    <?php foreach ($vendors as $v): ?>
                        <option value="<?= $v['id'] ?>" <?= $filterVendor == $v['id'] ? 'selected' : '' ?>>
                            <?= sanitize($v['company_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 col-sm-6 mb-2">
                <label style="font-size:12px;">Status PO</label>
                <select name="status" class="form-control form-control-sm select2">
                    <option value="">-- Semua Status --</option>
                    <option value="pending" <?= $filterStatus === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="approved" <?= $filterStatus === 'approved' ? 'selected' : '' ?>>Approved</option>
                    <option value="partially_received" <?= $filterStatus === 'partially_received' ? 'selected' : '' ?>>Partially Received</option>
                    <option value="completed" <?= $filterStatus === 'completed' ? 'selected' : '' ?>>Completed</option>
                </select>
            </div>
            <div class="col-md-2 col-sm-12 d-flex align-items-end mb-2">
                <button type="submit" class="btn btn-primary btn-sm btn-block"><i class="fas fa-search mr-1"></i>Filter</button>
                <a href="vendor_outstanding.php" class="btn btn-default btn-sm ml-2" title="Reset Filters"><i class="fas fa-sync-alt"></i></a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title">Rekap Hutang ke Supplier (Outstanding)</h3>
        <div class="ml-auto d-flex gap-2">
            <a href="export_excel.php?<?= http_build_query(array_merge($_GET, ['type' => 'vendor_outstanding'])) ?>" class="btn btn-success btn-sm"><i class="fas fa-file-excel mr-1"></i> Export Excel</a>
            <button class="btn btn-default btn-sm ml-1" onclick="window.print()"><i class="fas fa-print mr-1"></i> Cetak</button>
        </div>
    </div>
    <div class="card-body">
        <table id="reportTable" class="table table-bordered table-striped table-hover table-sm w-100" >
            <thead class="bg-light">
                <tr>
                    <th width="12%">No. PO</th>
                    <th width="10%">Tanggal</th>
                    <th width="20%">Vendor</th>
                    <th width="10%" class="text-center">Status PO</th>
                    <th width="15%" class="text-right">Nilai PO (Rp)</th>
                    <th width="15%" class="text-right">Terbayar (Rp)</th>
                    <th width="18%" class="text-right">Outstanding (Rp)</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $grandTotal = 0; $grandPaid = 0; $grandOutstanding = 0;
                foreach ($pos as $po): 
                    $outstanding = $po['po_total'] - $po['total_paid'];
                    $grandTotal += $po['po_total'];
                    $grandPaid += $po['total_paid'];
                    $grandOutstanding += $outstanding;
                    $rowClass = $outstanding > 0 ? '' : 'table-success';
                ?>
                <tr class="<?= $rowClass ?>">
                    <td>
                        <a href="<?= APP_URL ?>/modules/procurement/po/view.php?id=<?= $po['id'] ?>" class="font-weight-bold text-info" target="_blank">
                            <?= sanitize($po['po_number']) ?>
                        </a>
                    </td>
                    <td><?= date('d-m-Y', strtotime($po['po_date'])) ?></td>
                    <td><?= sanitize($po['vendor_name']) ?></td>
                    <td class="text-center"><?= getStatusBadge($po['po_status']) ?></td>
                    <td class="text-right"><?= formatRupiah($po['po_total']) ?></td>
                    <td class="text-right text-success"><?= formatRupiah($po['total_paid']) ?></td>
                    <td class="text-right font-weight-bold <?= $outstanding > 0 ? 'text-danger' : 'text-success' ?>" style="font-size:14px;">
                        <?= formatRupiah($outstanding) ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr class="font-weight-bold bg-light" style="font-size:14px;">
                    <td colspan="4" class="text-right">GRAND TOTAL:</td>
                    <td class="text-right"><?= formatRupiah($grandTotal) ?></td>
                    <td class="text-right text-success"><?= formatRupiah($grandPaid) ?></td>
                    <td class="text-right text-danger"><?= formatRupiah($grandOutstanding) ?></td>
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