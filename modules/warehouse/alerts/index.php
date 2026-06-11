<?php
/**
 * Warehouse - Stock Alerts List
 */
require_once __DIR__ . '/../../../includes/auth.php';
requirePermission('stock_alerts');

$pageTitle = 'Peringatan Stok Minimum';
$breadcrumbs = [
    ['label' => 'Warehouse', 'url' => '#'],
    ['label' => 'Stok Minimum']
];

// Fetch items below minimum stock
$sql = "
    SELECT i.*, c.name as category_name
    FROM items i
    JOIN categories c ON i.category_id = c.id
    WHERE i.is_active = 1 AND i.current_stock <= i.minimum_stock
    ORDER BY (i.minimum_stock - i.current_stock) DESC
";
$stmt = $pdo->query($sql);
$items = $stmt->fetchAll();

require_once __DIR__ . '/../../../includes/header.php';
?>

<div class="alert alert-warning">
    <h5><i class="icon fas fa-exclamation-triangle"></i> Peringatan Inventori!</h5>
    Daftar barang di bawah ini memiliki stok sama dengan atau kurang dari batas aman (Minimum Stock). Segera lakukan <strong>Material Request (MR)</strong> atau pengadaan untuk mencegah kehabisan stok.
</div>

<div class="card card-outline card-danger">
    <div class="card-header">
        <h3 class="card-title text-danger font-weight-bold">Barang Kritis (Defisit)</h3>
    </div>
    <div class="card-body">
        <table id="alertsTable" class="table table-bordered table-striped w-100" style="font-size: 13px;">
            <thead class="bg-light">
                <tr>
                    <th width="10%" class="text-center">Kode</th>
                    <th width="15%">Kategori</th>
                    <th width="35%">Nama / Spesifikasi</th>
                    <th width="10%" class="text-center">Min. Stok</th>
                    <th width="10%" class="text-center text-danger">Stok Ada</th>
                    <th width="20%" class="text-center">Kekurangan Stok</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                <?php 
                    $gap = $item['minimum_stock'] - $item['current_stock'];
                ?>
                <tr>
                    <td class="text-center"><strong><?= sanitize($item['item_code']) ?></strong></td>
                    <td><?= sanitize($item['category_name']) ?></td>
                    <td>
                        <?= sanitize($item['description']) ?>
                        <?php if ($item['type_specification']): ?>
                            <br><small class="text-muted"><?= sanitize($item['type_specification']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td class="text-center"><?= (float)$item['minimum_stock'] ?></td>
                    <td class="text-center text-danger font-weight-bold">
                        <?= (float)$item['current_stock'] ?>
                    </td>
                    <td class="text-center">
                        <span class="badge badge-danger px-2 py-1" style="font-size: 13px;">
                            Defisit: <?= (float)$gap ?> <?= sanitize($item['uom']) ?>
                        </span>
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
    initDataTable('#alertsTable');
});
</script>
JS;
require_once __DIR__ . '/../../../includes/footer.php';
?>
