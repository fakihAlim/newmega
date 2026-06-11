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
    ORDER BY cp.id DESC
";
$stmt = $pdo->query($sql);
$payments = $stmt->fetchAll();

require_once __DIR__ . '/../../../includes/header.php';
?>

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
    initDataTable('#cpTable');
});
</script>
JS;
require_once __DIR__ . '/../../../includes/footer.php';
?>
