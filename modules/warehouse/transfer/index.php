<?php
/**
 * Warehouse - Goods Transfer List
 */
require_once __DIR__ . '/../../../includes/auth.php';
requirePermission('transfer_list');

$pageTitle = 'Transfer Barang (Gudang ke Proyek)';
$breadcrumbs = [
    ['label' => 'Warehouse', 'url' => '#'],
    ['label' => 'Transfer Barang']
];

$user = getCurrentUser();

$sql = "
    SELECT wt.*, p.name as project_name, u.full_name as transfer_user
    FROM warehouse_transfers wt
    JOIN projects p ON wt.to_project_id = p.id
    LEFT JOIN users u ON wt.transferred_by = u.id
    ORDER BY wt.id DESC
";
$stmt = $pdo->query($sql);
$transfers = $stmt->fetchAll();

require_once __DIR__ . '/../../../includes/header.php';
?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title">Histori Transfer Barang</h3>
        <?php if (canAccess('transfer_list')): // Create implicitly given via list for now?>
            <a href="<?= APP_URL ?>/modules/warehouse/transfer/create.php" class="btn btn-primary btn-sm ml-auto">
                <i class="fas fa-truck-loading mr-1"></i> Buat Surat Jalan Keluar
            </a>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <table id="transferTable" class="table table-bordered table-striped w-100" style="font-size: 13px;">
            <thead>
                <tr>
                    <th width="15%">No. Transfer</th>
                    <th width="15%">Tanggal</th>
                    <th width="30%">Proyek Tujuan</th>
                    <th width="15%">Petugas (Admin)</th>
                    <th width="15%" class="text-center">Status</th>
                    <th width="10%" class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($transfers as $tr): ?>
                <tr>
                    <td><strong><?= sanitize($tr['transfer_number']) ?></strong></td>
                    <td><?= date('d-m-Y', strtotime($tr['transfer_date'])) ?></td>
                    <td><?= sanitize($tr['project_name']) ?></td>
                    <td><?= sanitize($tr['transfer_user']) ?></td>
                    <td class="text-center">
                        <?= getStatusBadge($tr['status']) ?>
                    </td>
                    <td class="text-center">
                        <a href="<?= APP_URL ?>/modules/warehouse/transfer/view.php?id=<?= $tr['id'] ?>" class="btn btn-info btn-sm" data-toggle="tooltip" title="Lihat/Selesaikan">
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
    initDataTable('#transferTable');
});
</script>
JS;
require_once __DIR__ . '/../../../includes/footer.php';
?>
