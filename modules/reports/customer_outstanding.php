<?php
/**
 * Report - Customer Outstanding (Piutang)
 */
require_once __DIR__ . '/../../includes/auth.php';
requirePermission('report_customer_outstanding');

$pageTitle = 'Outstanding Customer (Piutang)';
$breadcrumbs = [
    ['label' => 'Laporan', 'url' => '#'],
    ['label' => 'Outstanding Customer']
];

// Get filters
$filterStart = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$filterEnd = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$filterCustomer = $_GET['customer_id'] ?? '';
$filterStatus = $_GET['status'] ?? '';

// Build conditions
$conditions = ["inv.status NOT IN ('draft', 'rejected')"];
$params = [];

if ($filterStart) {
    $conditions[] = "inv.invoice_date >= ?";
    $params[] = $filterStart;
}
if ($filterEnd) {
    $conditions[] = "inv.invoice_date <= ?";
    $params[] = $filterEnd;
}
if ($filterCustomer) {
    $conditions[] = "inv.customer_id = ?";
    $params[] = $filterCustomer;
}
if ($filterStatus) {
    $conditions[] = "inv.status = ?";
    $params[] = $filterStatus;
}

$whereClause = "";
if (!empty($conditions)) {
    $whereClause = "WHERE " . implode(" AND ", $conditions);
}

// Fetch all Invoices with outstanding balance
$sql = "
    SELECT 
        inv.id, inv.invoice_no, inv.invoice_date, inv.total as inv_total, inv.status as inv_status,
        inv.termin_no, inv.termin_description,
        cust.company_name as customer_name,
        q.quotation_no,
        COALESCE(SUM(cp.amount), 0) as total_received
    FROM invoices inv
    JOIN customers cust ON inv.customer_id = cust.id
    JOIN quotations q ON inv.quotation_id = q.id
    LEFT JOIN customer_payments cp ON cp.invoice_id = inv.id
    $whereClause
    GROUP BY inv.id
    ORDER BY (inv.total - COALESCE(SUM(cp.amount), 0)) DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$invoices = $stmt->fetchAll();

// Fetch all customers for filter dropdown
$customers = $pdo->query("SELECT id, company_name FROM customers ORDER BY company_name ASC")->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/report_print.php';
?>

<?php 
$periodText = '';
if ($filterStart || $filterEnd) {
    $periodText = 'Periode: ' . ($filterStart ? date('d-m-Y', strtotime($filterStart)) : 'Awal') . ' s/d ' . ($filterEnd ? date('d-m-Y', strtotime($filterEnd)) : 'Akhir');
}
renderReportPrintHeader('Rekap Piutang Customer (Outstanding)', $periodText); 
?>

<!-- Filter Card -->
<div class="card card-default d-print-none mb-3">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-filter mr-2"></i>Filter Data</h3>
        <div class="card-tools">
            <button type="button" class="btn btn-tool" data-card-widget="collapse">
                <i class="fas fa-minus"></i>
            </button>
        </div>
    </div>
    <form method="GET" action="">
        <div class="card-body">
            <div class="row">
                <div class="col-md col-sm-6">
                    <div class="form-group mb-2 mb-md-0">
                        <label>Tanggal Mulai</label>
                        <input type="date" name="start_date" class="form-control form-control-sm" value="<?= htmlspecialchars($filterStart) ?>">
                    </div>
                </div>
                <div class="col-md col-sm-6">
                    <div class="form-group mb-2 mb-md-0">
                        <label>Tanggal Selesai</label>
                        <input type="date" name="end_date" class="form-control form-control-sm" value="<?= htmlspecialchars($filterEnd) ?>">
                    </div>
                </div>
                <div class="col-md col-sm-6">
                    <div class="form-group mb-2 mb-md-0">
                        <label>Pelanggan / Customer</label>
                        <select name="customer_id" class="form-control form-control-sm select2">
                            <option value="">-- Semua Customer --</option>
                            <?php foreach ($customers as $c): ?>
                                <option value="<?= $c['id'] ?>" <?= $filterCustomer == $c['id'] ? 'selected' : '' ?>>
                                    <?= sanitize($c['company_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md col-sm-6">
                    <div class="form-group mb-2 mb-md-0">
                        <label>Status Invoice</label>
                        <select name="status" class="form-control form-control-sm">
                            <option value="">-- Semua Status --</option>
                            <option value="pending" <?= $filterStatus === 'pending' ? 'selected' : '' ?>>Pending Approval</option>
                            <option value="approved" <?= $filterStatus === 'approved' ? 'selected' : '' ?>>Approved</option>
                            <option value="sent" <?= $filterStatus === 'sent' ? 'selected' : '' ?>>Sent</option>
                            <option value="partial_paid" <?= $filterStatus === 'partial_paid' ? 'selected' : '' ?>>Partial Paid</option>
                            <option value="paid" <?= $filterStatus === 'paid' ? 'selected' : '' ?>>Paid (Lunas)</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
        <div class="card-footer text-right">
            <a href="customer_outstanding.php" class="btn btn-secondary mr-2"><i class="fas fa-undo mr-1"></i> Reset</a>
            <button type="submit" class="btn btn-primary"><i class="fas fa-search mr-1"></i> Filter</button>
        </div>
    </form>
</div>

<div class="card card-outline card-info">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title"><i class="fas fa-file-invoice-dollar mr-2"></i> Rekap Piutang Customer (Outstanding)</h3>
        <div class="ml-auto d-flex gap-2">
            <a href="export_excel.php?<?= http_build_query(array_merge($_GET, ['type' => 'customer_outstanding'])) ?>" class="btn btn-success btn-sm"><i class="fas fa-file-excel mr-1"></i> Export Excel</a>
            <a href="export_csv.php?<?= http_build_query(array_merge($_GET, ['type' => 'customer_outstanding'])) ?>" class="btn btn-info btn-sm ml-1"><i class="fas fa-file-csv mr-1"></i> Export CSV</a>
            <button class="btn btn-default btn-sm ml-1" onclick="window.print()"><i class="fas fa-print mr-1"></i> Cetak</button>
        </div>
    </div>
    <div class="card-body">
        <table id="reportTable" class="table table-bordered table-striped w-100" style="font-size: 13px;">
            <thead class="bg-light">
                <tr>
                    <th width="12%">No. Invoice</th>
                    <th width="10%">Tanggal</th>
                    <th width="18%">Customer</th>
                    <th width="8%">Termin</th>
                    <th width="10%" class="text-center">Status</th>
                    <th width="14%" class="text-right">Nilai Invoice</th>
                    <th width="14%" class="text-right">Diterima</th>
                    <th width="14%" class="text-right">Piutang</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $grandTotal = 0; $grandReceived = 0; $grandOutstanding = 0;
                foreach ($invoices as $inv): 
                    $outstanding = $inv['inv_total'] - $inv['total_received'];
                    $grandTotal += $inv['inv_total'];
                    $grandReceived += $inv['total_received'];
                    $grandOutstanding += $outstanding;
                    $rowClass = $outstanding <= 0 ? 'table-success' : '';
                ?>
                <tr class="<?= $rowClass ?>">
                    <td>
                        <a href="<?= APP_URL ?>/modules/sales/invoices/view.php?id=<?= $inv['id'] ?>" class="font-weight-bold text-info" target="_blank">
                            <?= sanitize($inv['invoice_no']) ?>
                        </a>
                    </td>
                    <td><?= date('d-m-Y', strtotime($inv['invoice_date'])) ?></td>
                    <td><?= sanitize($inv['customer_name']) ?></td>
                    <td>T<?= $inv['termin_no'] ?></td>
                    <td class="text-center"><?= getStatusBadge($inv['inv_status']) ?></td>
                    <td class="text-right"><?= formatRupiah($inv['inv_total']) ?></td>
                    <td class="text-right text-success"><?= formatRupiah($inv['total_received']) ?></td>
                    <td class="text-right font-weight-bold <?= $outstanding > 0 ? 'text-danger' : 'text-success' ?>" style="font-size:14px;">
                        <?= formatRupiah($outstanding) ?>
                        <?php if ($outstanding <= 0): ?>
                            <i class="fas fa-check-circle text-success ml-1"></i>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr class="font-weight-bold bg-light" style="font-size:14px;">
                    <td colspan="5" class="text-right">GRAND TOTAL:</td>
                    <td class="text-right"><?= formatRupiah($grandTotal) ?></td>
                    <td class="text-right text-success"><?= formatRupiah($grandReceived) ?></td>
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
    $('.select2').select2({
        theme: 'bootstrap4',
        width: '100%'
    });
});
</script>
JS;
require_once __DIR__ . '/../../includes/footer.php';
?>
