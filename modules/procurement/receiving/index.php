<?php
/**
 * Procurement - Goods Receiving List
 */
require_once __DIR__ . '/../../../includes/auth.php';
requirePermission('receiving_list');

$pageTitle = 'Penerimaan Barang (Goods Receipt)';
$breadcrumbs = [
    ['label' => 'Procurement', 'url' => '#'],
    ['label' => 'Penerimaan Barang']
];

$user = getCurrentUser();

// Fetch Goods Receivings
$sql = "
    SELECT gr.*, po.po_number, v.company_name as vendor_name, u.full_name as receiver_name, p.name as project_name
    FROM goods_receivings gr
    JOIN purchase_orders po ON gr.po_id = po.id
    JOIN vendors v ON po.vendor_id = v.id
    LEFT JOIN users u ON gr.received_by = u.id
    LEFT JOIN projects p ON gr.project_id = p.id
    ORDER BY gr.id DESC
";
$stmt = $pdo->query($sql);
$receivings = $stmt->fetchAll();

require_once __DIR__ . '/../../../includes/header.php';
?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title">Daftar Penerimaan Barang</h3>
        <?php if (canAccess('receiving_list')): // Assuming if they can list, they can create for now, or add specific permission ?>
            <a href="<?= APP_URL ?>/modules/procurement/receiving/create.php" class="btn btn-primary btn-sm ml-auto">
                <i class="fas fa-plus mr-1"></i> Terima Barang Baru
            </a>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <table id="grTable" class="table table-bordered table-striped w-100" style="font-size: 13px;">
            <thead>
                <tr>
                    <th width="12%">Tgl Terima</th>
                    <th width="15%">No. Surat Jalan</th>
                    <th width="15%">No. PO Ref.</th>
                    <th width="20%">Vendor</th>
                    <th width="15%">Lokasi Terima</th>
                    <th width="13%">Penerima</th>
                    <th width="10%" class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($receivings as $gr): ?>
                <tr>
                    <td><?= date('d-m-Y', strtotime($gr['receive_date'])) ?></td>
                    <td><strong><?= sanitize($gr['surat_jalan_no']) ?></strong></td>
                    <td>
                        <a href="<?= APP_URL ?>/modules/procurement/po/view.php?id=<?= $gr['po_id'] ?>" target="_blank" class="text-info font-weight-bold">
                            <?= sanitize($gr['po_number']) ?>
                        </a>
                    </td>
                    <td><?= sanitize($gr['vendor_name']) ?></td>
                    <td>
                        <?php if ($gr['received_at'] === 'warehouse'): ?>
                            <span class="badge badge-primary">Gudang Utama</span>
                        <?php else: ?>
                            <span class="badge badge-success">Proyek</span><br>
                            <small class="text-muted"><?= sanitize($gr['project_name']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td><?= sanitize($gr['receiver_name']) ?></td>
                    <td class="text-center">
                        <a href="<?= APP_URL ?>/modules/procurement/receiving/view.php?id=<?= $gr['id'] ?>" class="btn btn-info btn-sm" data-toggle="tooltip" title="Lihat Detail SJ">
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
    initDataTable('#grTable');
});
</script>
JS;
require_once __DIR__ . '/../../../includes/footer.php';
?>
