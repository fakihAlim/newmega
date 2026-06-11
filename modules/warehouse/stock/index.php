<?php
/**
 * Warehouse - Stock Items List
 */
require_once __DIR__ . '/../../../includes/auth.php';
requirePermission('stock');

$pageTitle = 'Papan Stok Barang';
$breadcrumbs = [
    ['label' => 'Warehouse', 'url' => '#'],
    ['label' => 'Stok Barang']
];

// Fetch all active items with their category
$sql = "
    SELECT i.*, c.name as category_name
    FROM items i
    JOIN categories c ON i.category_id = c.id
    WHERE i.is_active = 1
    ORDER BY i.item_code ASC
";
$stmt = $pdo->query($sql);
$items = $stmt->fetchAll();

require_once __DIR__ . '/../../../includes/header.php';
?>

<div class="card card-outline card-info">
    <div class="card-header">
        <h3 class="card-title">Stok Barang Gudang Utama</h3>
    </div>
    <div class="card-body">
        <table id="stockTable" class="table table-bordered table-striped w-100" style="font-size: 13px;">
            <thead class="bg-light">
                <tr>
                    <th width="10%" class="text-center">Kode</th>
                    <th width="15%">Kategori</th>
                    <th width="35%">Nama / Spesifikasi</th>
                    <th width="15%">Lokasi Rak</th>
                    <th width="10%" class="text-center">Min. Stok</th>
                    <th width="15%" class="text-center">Stok Saat Ini</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                <?php 
                    $isDanger = ($item['current_stock'] <= $item['minimum_stock']);
                    $textClass = $isDanger ? 'text-danger font-weight-bold' : 'text-success font-weight-bold';
                ?>
                <tr>
                    <td class="text-center"><strong><?= sanitize($item['item_code']) ?></strong></td>
                    <td><?= sanitize($item['category_name']) ?></td>
                    <td>
                        <?= sanitize($item['description']) ?>
                        <?php if ($item['type_specification']): ?>
                            <br><small class="text-muted"><?= sanitize($item['type_specification']) ?></small>
                        <?php endif; ?>
                        <?php if ($isDanger): ?>
                            <span class="badge badge-danger ml-1">Low</span>
                        <?php endif; ?>
                    </td>
                    <td><?= sanitize($item['warehouse_location']) ?: '-' ?></td>
                    <td class="text-center"><?= (float)$item['minimum_stock'] ?> <?= sanitize($item['uom']) ?></td>
                    <td class="text-center <?= $textClass ?>" style="font-size: 15px;">
                        <?= (float)$item['current_stock'] ?> <?= sanitize($item['uom']) ?>
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
    initDataTable('#stockTable');
});
</script>
JS;
require_once __DIR__ . '/../../../includes/footer.php';
?>
