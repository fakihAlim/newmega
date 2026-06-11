<?php
/**
 * Finance - Vendor Payment List
 */
require_once __DIR__ . '/../../../includes/auth.php';
requirePermission('vendor_payments');

$pageTitle = 'Pembayaran Vendor';
$breadcrumbs = [
    ['label' => 'Finance', 'url' => '#'],
    ['label' => 'Pembayaran Vendor']
];

$user = getCurrentUser();

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
    ORDER BY vp.id DESC
";
$stmt = $pdo->query($sql);
$payments = $stmt->fetchAll();

require_once __DIR__ . '/../../../includes/header.php';
?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title"><i class="fas fa-money-check-alt mr-2"></i> Riwayat Pembayaran Vendor</h3>
        <?php if (canAccess('vendor_payments')): ?>
            <a href="<?= APP_URL ?>/modules/finance/vendor_payments/create.php" class="btn btn-primary btn-sm ml-auto">
                <i class="fas fa-plus mr-1"></i> Catat Pembayaran Baru
            </a>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <table id="vpTable" class="table table-bordered table-striped w-100" style="font-size: 13px;">
            <thead>
                <tr>
                    <th width="12%">Tanggal</th>
                    <th width="12%">No. PO</th>
                    <th width="18%">Vendor</th>
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
    initDataTable('#vpTable');
});
</script>
JS;
require_once __DIR__ . '/../../../includes/footer.php';
?>
