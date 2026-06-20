<?php
/**
 * Finance - Suplier Payment List
 */
require_once __DIR__ . '/../../../includes/auth.php';
requirePermission('vendor_payments');

$pageTitle = 'Pembayaran Suplier';
$breadcrumbs = [
    ['label' => 'Finance', 'url' => '#'],
    ['label' => 'Pembayaran Suplier']
];

$user = getCurrentUser();

// Set default filters
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$vendorId = $_GET['vendor_id'] ?? '';
$paymentMethod = $_GET['payment_method'] ?? '';

// Fetch vendors for filter dropdown
$vendors = $pdo->query("SELECT id, company_name FROM vendors ORDER BY company_name ASC")->fetchAll();

// Get unique payment methods
$methodsStmt = $pdo->query("SELECT DISTINCT payment_method FROM vendor_payments WHERE payment_method IS NOT NULL AND payment_method != '' ORDER BY payment_method ASC");
$paymentMethods = $methodsStmt->fetchAll(PDO::FETCH_COLUMN);

// Build conditions
$conditions = [];
$params = [];

if ($startDate) {
    $conditions[] = "vp.payment_date >= ?";
    $params[] = $startDate;
}
if ($endDate) {
    $conditions[] = "vp.payment_date <= ?";
    $params[] = $endDate;
}
if ($vendorId) {
    $conditions[] = "po.vendor_id = ?";
    $params[] = $vendorId;
}
if ($paymentMethod) {
    $conditions[] = "vp.payment_method = ?";
    $params[] = $paymentMethod;
}

$whereClause = "";
if (!empty($conditions)) {
    $whereClause = "WHERE " . implode(" AND ", $conditions);
}

// Fetch all vendor payments with PO & Vendor details
$sql = "
    SELECT vp.*, 
           po.po_number, po.total as po_total, po.status as po_status,
           v.company_name as vendor_name,
           u.full_name as payer_name
    FROM vendor_payments vp
    JOIN purchase_orders po ON vp.po_id = po.id
    JOIN vendors v ON po.vendor_id = v.id
    LEFT JOIN users u ON vp.paid_by = u.id
    $whereClause
    ORDER BY vp.id DESC
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
                    <label style="font-size:12px;">Suplier / Vendor</label>
                    <select name="vendor_id" class="form-control form-control-sm select2">
                        <option value="">-- Semua Suplier --</option>
                        <?php foreach ($vendors as $v): ?>
                            <option value="<?= $v['id'] ?>" <?= $vendorId == $v['id'] ? 'selected' : '' ?>><?= sanitize($v['company_name']) ?></option>
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
        <h3 class="card-title"><i class="fas fa-money-check-alt mr-2"></i> Riwayat Pembayaran Suplier</h3>
        <?php if (canAccess('vendor_payments')): ?>
            <a href="<?= APP_URL ?>/modules/finance/vendor_payments/create.php" class="btn btn-primary btn-sm ml-auto">
                <i class="fas fa-plus mr-1"></i> Catat Pembayaran Baru
            </a>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <table id="vpTable" class="table table-bordered table-striped table-hover table-sm w-100" >
            <thead>
                <tr>
                    <th width="12%">Tanggal</th>
                    <th width="12%">No. PO</th>
                    <th width="18%">Suplier</th>
                    <th width="13%" class="text-right">Nilai PO</th>
                    <th width="13%" class="text-right">Dibayar</th>
                    <th width="10%">Metode</th>
                    <th width="12%">No. Referensi</th>
                    <th width="10%" class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($payments as $p): ?>
                <tr>
                    <td><?= date('d-m-Y', strtotime($p['payment_date'])) ?></td>
                    <td>
                        <a href="<?= APP_URL ?>/modules/procurement/po/view.php?id=<?= $p['po_id'] ?>" class="text-info font-weight-bold" target="_blank">
                            <?= sanitize($p['po_number']) ?>
                        </a>
                    </td>
                    <td><?= sanitize($p['vendor_name']) ?></td>
                    <td class="text-right"><?= formatRupiah($p['po_total']) ?></td>
                    <td class="text-right font-weight-bold text-success"><?= formatRupiah($p['amount']) ?></td>
                    <td><?= sanitize($p['payment_method']) ?: '-' ?></td>
                    <td><?= sanitize($p['reference_no']) ?: '-' ?></td>
                    <td class="text-center">
                        <a href="<?= APP_URL ?>/modules/finance/vendor_payments/view.php?id=<?= $p['id'] ?>" class="btn btn-info btn-sm" data-toggle="tooltip" title="Lihat Bukti Bayar">
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
    initDataTable('#vpTable');
});
</script>
JS;
require_once __DIR__ . '/../../../includes/footer.php';
?>
