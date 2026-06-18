<?php
/**
 * Finance - Customer Payment List
 */
require_once __DIR__ . '/../../../includes/auth.php';
requirePermission('customer_payments');

$pageTitle = 'Penerimaan Pembayaran Customer';
$breadcrumbs = [
    ['label' => 'Finance', 'url' => '#'],
    ['label' => 'Penerimaan Customer']
];

$user = getCurrentUser();

// Set default filters
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$customerId = $_GET['customer_id'] ?? '';
$paymentMethod = $_GET['payment_method'] ?? '';

// Fetch customers for filter dropdown
$customers = $pdo->query("SELECT id, company_name FROM customers ORDER BY company_name ASC")->fetchAll();

// Get unique payment methods
$methodsStmt = $pdo->query("SELECT DISTINCT payment_method FROM customer_payments WHERE payment_method IS NOT NULL AND payment_method != '' ORDER BY payment_method ASC");
$paymentMethods = $methodsStmt->fetchAll(PDO::FETCH_COLUMN);

// Build conditions
$conditions = [];
$params = [];

if ($startDate) {
    $conditions[] = "cp.payment_date >= ?";
    $params[] = $startDate;
}
if ($endDate) {
    $conditions[] = "cp.payment_date <= ?";
    $params[] = $endDate;
}
if ($customerId) {
    $conditions[] = "inv.customer_id = ?";
    $params[] = $customerId;
}
if ($paymentMethod) {
    $conditions[] = "cp.payment_method = ?";
    $params[] = $paymentMethod;
}

$whereClause = "";
if (!empty($conditions)) {
    $whereClause = "WHERE " . implode(" AND ", $conditions);
}

// Fetch all customer payments with Invoice & Customer details
$sql = "
    SELECT cp.*, 
           inv.invoice_no, inv.total as invoice_total, inv.status as invoice_status,
           cust.company_name as customer_name,
           u.full_name as receiver_name
    FROM customer_payments cp
    JOIN invoices inv ON cp.invoice_id = inv.id
    JOIN customers cust ON inv.customer_id = cust.id
    LEFT JOIN users u ON cp.received_by = u.id
    $whereClause
    ORDER BY cp.id DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$payments = $stmt->fetchAll();

require_once __DIR__ . '/../../../includes/header.php';
?>

<!-- Filter Card -->
<div class="card d-print-none mb-3">
    <div class="card-body p-3">
        <form method="GET" action="" class="form-horizontal">
            <div class="row">
                <div class="col-md-2 col-sm-6 mb-2">
                    <label style="font-size:12px;">Tanggal Mulai</label>
                    <input type="date" name="start_date" class="form-control form-control-sm" value="<?= htmlspecialchars($startDate) ?>">
                </div>
                <div class="col-md-2 col-sm-6 mb-2">
                    <label style="font-size:12px;">Tanggal Selesai</label>
                    <input type="date" name="end_date" class="form-control form-control-sm" value="<?= htmlspecialchars($endDate) ?>">
                </div>
                <div class="col-md-3 col-sm-6 mb-2">
                    <label style="font-size:12px;">Customer</label>
                    <select name="customer_id" class="form-control form-control-sm select2">
                        <option value="">-- Semua Customer --</option>
                        <?php foreach ($customers as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= $customerId == $c['id'] ? 'selected' : '' ?>><?= sanitize($c['company_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 col-sm-6 mb-2">
                    <label style="font-size:12px;">Metode Pembayaran</label>
                    <select name="payment_method" class="form-control form-control-sm select2">
                        <option value="">-- Semua Metode --</option>
                        <?php foreach ($paymentMethods as $m): ?>
                            <option value="<?= htmlspecialchars($m) ?>" <?= $paymentMethod == $m ? 'selected' : '' ?>><?= sanitize($m) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 col-sm-12 d-flex align-items-end mb-2">
                    <button type="submit" class="btn btn-primary btn-sm btn-block"><i class="fas fa-search mr-1"></i>Filter</button>
                    <a href="index.php" class="btn btn-default btn-sm ml-2" title="Reset Filters"><i class="fas fa-sync-alt"></i></a>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title"><i class="fas fa-hand-holding-usd mr-2"></i> Riwayat Penerimaan Customer</h3>
        <?php if (canAccess('customer_payments')): ?>
            <a href="<?= APP_URL ?>/modules/finance/customer_payments/create.php" class="btn btn-primary btn-sm ml-auto">
                <i class="fas fa-plus mr-1"></i> Catat Penerimaan Baru
            </a>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <table id="cpTable" class="table table-bordered table-striped w-100" style="font-size: 13px;">
            <thead>
                <tr>
                    <th width="12%">Tanggal</th>
                    <th width="12%">No. Invoice</th>
                    <th width="20%">Customer</th>
                    <th width="14%" class="text-right">Nilai Invoice</th>
                    <th width="14%" class="text-right">Diterima</th>
                    <th width="10%">Metode</th>
                    <th width="10%">Referensi</th>
                    <th width="8%" class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($payments as $p): ?>
                <tr>
                    <td><?= date('d-m-Y', strtotime($p['payment_date'])) ?></td>
                    <td><strong class="text-info"><?= sanitize($p['invoice_no']) ?></strong></td>
                    <td><?= sanitize($p['customer_name']) ?></td>
                    <td class="text-right"><?= formatRupiah($p['invoice_total']) ?></td>
                    <td class="text-right font-weight-bold text-success"><?= formatRupiah($p['amount']) ?></td>
                    <td><?= sanitize($p['payment_method']) ?: '-' ?></td>
                    <td><?= sanitize($p['reference_no']) ?: '-' ?></td>
                    <td class="text-center">
                        <a href="<?= APP_URL ?>/modules/finance/customer_payments/view.php?id=<?= $p['id'] ?>" class="btn btn-info btn-sm" data-toggle="tooltip" title="Lihat Kwitansi">
                            <i class="fas fa-eye"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
$extraJS = <<<'JS'
<script>
$(document).ready(function() {
    initSelect2('.select2');
    initDataTable('#cpTable');
});
</script>
JS;
require_once __DIR__ . '/../../../includes/footer.php';
?>
