<?php
/**
 * Procurement - Purchase Order List
 */
require_once __DIR__ . '/../../../includes/auth.php';
requirePermission('po_list');

$pageTitle = 'Purchase Order (PO)';
$breadcrumbs = [
    ['label' => 'Procurement', 'url' => '#'],
    ['label' => 'Purchase Order']
];

$user = getCurrentUser();

// Fetch POs
$sql = "
    SELECT po.*, v.company_name as vendor_name, c.name as company_name, u.full_name as creator_name
    FROM purchase_orders po
    JOIN vendors v ON po.vendor_id = v.id
    JOIN companies c ON po.company_id = c.id
    LEFT JOIN users u ON po.created_by = u.id
    ORDER BY po.id DESC
";
$stmt = $pdo->query($sql);
$orders = $stmt->fetchAll();

require_once __DIR__ . '/../../../includes/header.php';
?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title">Daftar Purchase Order</h3>
        <?php if (canAccess('po_create')): ?>
            <a href="<?= APP_URL ?>/modules/procurement/po/create.php" class="btn btn-primary btn-sm ml-auto">
                <i class="fas fa-plus mr-1"></i> Buat PO Baru
            </a>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <table id="poTable" class="table table-bordered table-striped w-100" style="font-size: 13px;">
            <thead>
                <tr>
                    <th width="12%">No. PO</th>
                    <th width="10%">Tanggal</th>
                    <th width="20%">Vendor</th>
                    <th width="18%">Perusahaan (Header)</th>
                    <th width="15%" class="text-right">Grand Total</th>
                    <th width="12%">Status</th>
                    <th width="13%" class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $o): ?>
                <tr>
                    <td><strong><?= sanitize($o['po_number']) ?></strong></td>
                    <td><?= date('d-m-Y', strtotime($o['po_date'])) ?></td>
                    <td><?= sanitize($o['vendor_name']) ?></td>
                    <td><?= sanitize($o['company_name']) ?></td>
                    <td class="text-right"><?= formatRupiah($o['total']) ?></td>
                    <td>
                        <?= getStatusBadge($o['status']) ?>
                    </td>
                    <td class="text-center">
                        <a href="<?= APP_URL ?>/modules/procurement/po/view.php?id=<?= $o['id'] ?>" class="btn btn-info btn-sm" data-toggle="tooltip" title="Lihat Detail">
                            <i class="fas fa-eye"></i>
                        </a>
                        
                        <?php if (in_array($o['status'], ['draft', 'pending']) && ($user['role'] === 'super_admin' || $user['id'] == $o['created_by'])): ?>
                        <a href="<?= APP_URL ?>/modules/procurement/po/edit.php?id=<?= $o['id'] ?>" class="btn btn-warning btn-sm" data-toggle="tooltip" title="Ubah">
                            <i class="fas fa-edit text-white"></i>
                        </a>
                        <button type="button" class="btn btn-danger btn-sm btn-delete-po" data-id="<?= $o['id'] ?>" data-number="<?= sanitize($o['po_number']) ?>" data-toggle="tooltip" title="Hapus">
                            <i class="fas fa-trash"></i>
                        </button>
                        <?php endif; ?>
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
    initDataTable('#poTable');
    
    // Delete PO handler
    $('.btn-delete-po').on('click', function() {
        var poId = $(this).data('id');
        var poNumber = $(this).data('number');
        confirmAction(
            'Hapus PO ' + poNumber + '?',
            'Data PO akan dihapus permanen dan qty MR yang terkait akan dikembalikan. Tindakan ini tidak dapat dibatalkan.',
            function() {
                window.location.href = APP_URL + '/modules/procurement/po/delete.php?id=' + poId;
            }
        );
    });
});
</script>
JS;
require_once __DIR__ . '/../../../includes/footer.php';
?>
