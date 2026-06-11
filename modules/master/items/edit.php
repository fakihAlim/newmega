<?php
/**
 * Master Items - Edit
 */
require_once __DIR__ . '/../../../includes/auth.php';
requirePermission('master_items');

$id = $_GET['id'] ?? 0;
$stmt = $pdo->prepare("SELECT * FROM items WHERE id = ?");
$stmt->execute([$id]);
$item = $stmt->fetch();

if (!$item) {
    setFlash('danger', 'Barang tidak ditemukan.');
    header('Location: ' . APP_URL . '/modules/master/items/index.php');
    exit;
}

$pageTitle = 'Edit Barang: ' . sanitize($item['item_code']);
$breadcrumbs = [
    ['label' => 'Master Data', 'url' => '#'],
    ['label' => 'Barang', 'url' => APP_URL . '/modules/master/items/index.php'],
    ['label' => 'Edit']
];

// Fetch categories for dropdown
$categories = $pdo->query("SELECT id, name, prefix FROM categories ORDER BY name ASC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Note: We don't allow changing category/item_code here to prevent data anomalies with existing history
    $description  = trim($_POST['description'] ?? '');
    $uom          = trim($_POST['uom'] ?? '');
    $typeSpec     = trim($_POST['type_specification'] ?? '');
    $whLocation   = trim($_POST['warehouse_location'] ?? '');
    $remark       = trim($_POST['remark'] ?? '');
    $stockType    = $_POST['stock_type'] ?? 'stock';
    $minimumStock = parseRupiah($_POST['minimum_stock'] ?? '0');
    
    // Validation
    $errors = [];
    if (empty($description)) $errors[] = "Deskripsi barang wajib diisi.";
    if (empty($uom)) $errors[] = "Satuan (UoM) wajib diisi.";
    
    if (empty($errors)) {
        $update = $pdo->prepare("
            UPDATE items 
            SET description = ?, type_specification = ?, uom = ?, minimum_stock = ?, warehouse_location = ?, remark = ?, stock_type = ? 
            WHERE id = ?
        ");
        
        if ($update->execute([$description, $typeSpec, $uom, $stockType === 'stock' ? $minimumStock : 0, $whLocation, $remark, $stockType, $id])) {
            setFlash('success', "Data barang berhasil diperbarui.");
            header('Location: ' . APP_URL . '/modules/master/items/index.php');
            exit;
        } else {
            setFlash('danger', 'Terjadi kesalahan sistem saat menyimpan data.');
        }
    }
    
    if (!empty($errors)) {
        setFlash('danger', implode('<br>', $errors));
        $item['description'] = $description;
        $item['type_specification'] = $typeSpec;
        $item['uom'] = $uom;
        $item['warehouse_location'] = $whLocation;
        $item['remark'] = $remark;
        $item['stock_type'] = $stockType;
        $item['minimum_stock'] = $minimumStock;
    }
}

require_once __DIR__ . '/../../../includes/header.php';
?>

<div class="row">
    <div class="col-md-8 mx-auto">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-edit mr-2"></i>Form Edit Barang</h3>
                <a href="<?= APP_URL ?>/modules/master/items/index.php" class="btn btn-secondary btn-sm float-right"><i class="fas fa-arrow-left mr-1"></i> Kembali</a>
            </div>
            <form method="POST">
                <div class="card-body">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Kode Barang <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" value="<?= sanitize($item['item_code']) ?>" readonly>
                                <small class="text-muted">Kode barang tidak dapat diubah.</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Kategori <span class="text-danger">*</span></label>
                                <!-- Just showing the current category, readonly -->
                                <?php 
                                    $catName = '';
                                    foreach($categories as $c) {
                                        if ($c['id'] == $item['category_id']) {
                                            $catName = $c['name'];
                                            break;
                                        }
                                    }
                                ?>
                                <input type="text" class="form-control" value="<?= sanitize($catName) ?>" readonly>
                                <small class="text-muted">Kategori tidak dapat diubah setelah dibuat.</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-7">
                            <div class="form-group">
                                <label>Nama / Deskripsi Barang <span class="text-danger">*</span></label>
                                <input type="text" name="description" class="form-control check-duplicate" data-type="item" data-id="<?= $id ?>" value="<?= sanitize($item['description']) ?>" required>
                                <div class="duplicate-warning text-danger" style="display:none; font-size: 12px; margin-top: 5px;"></div>
                            </div>
                        </div>
                        <div class="col-md-5">
                            <div class="form-group">
                                <label>Tipe / Spesifikasi Tambahan</label>
                                <input type="text" name="type_specification" class="form-control" value="<?= sanitize($item['type_specification']) ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Satuan Unit (UoM) <span class="text-danger">*</span></label>
                                <input type="text" name="uom" class="form-control" value="<?= sanitize($item['uom']) ?>" required style="text-transform: uppercase;">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Catatan Tambahan (Remark)</label>
                                <input type="text" name="remark" class="form-control" value="<?= sanitize($item['remark']) ?>">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Stok Saat Ini (Hanya Info)</label>
                                <input type="text" class="form-control text-primary font-weight-bold" value="<?= number_format($item['current_stock'], 0) ?> <?= sanitize($item['uom']) ?>" readonly>
                            </div>
                        </div>
                    </div>
                    
                    <hr class="my-4">
                    <h5 class="text-primary mb-3">Pengaturan Gudang & Inventaris</h5>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Tipe Distribusi Barang</label>
                                <div class="custom-control custom-radio mb-2">
                                    <input class="custom-control-input" type="radio" id="typeStock" name="stock_type" value="stock" <?= $item['stock_type'] === 'stock' ? 'checked' : '' ?>>
                                    <label for="typeStock" class="custom-control-label">Bisa distok di Gudang Umum</label>
                                </div>
                                <div class="custom-control custom-radio">
                                    <input class="custom-control-input" type="radio" id="typeDirect" name="stock_type" value="direct" <?= $item['stock_type'] === 'direct' ? 'checked' : '' ?>>
                                    <label for="typeDirect" class="custom-control-label">Langsung kirim ke lokasi Proyek (Tanpa Stok)</label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6" id="stockGroup">
                            <div class="form-group">
                                <label>Minimum Stok Alert</label>
                                <input type="text" name="minimum_stock" class="form-control input-number" value="<?= sanitize($item['minimum_stock'] ?? '0') ?>">
                                <small class="text-muted">Masukkan angka 0 jika tidak butuh notifikasi stok menipis.</small>
                            </div>
                            <div class="form-group mt-2">
                                <label>Lokasi Gudang / Rak Penyimpanan</label>
                                <input type="text" name="warehouse_location" class="form-control" value="<?= sanitize($item['warehouse_location']) ?>" placeholder="Cth: Rak A1, Area Luar">
                            </div>
                        </div>
                    </div>
                    
                </div>
                <div class="card-footer text-right">
                    <button type="submit" class="btn btn-warning"><i class="fas fa-save mr-1"></i> Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$extraJS = <<<'JS'
<script>
$(document).ready(function() {
    // Toggle stock group based on stock_type
    $('input[name="stock_type"]').change(function() {
        if ($('#typeDirect').is(':checked')) {
            $('#stockGroup').slideUp();
        } else {
            $('#stockGroup').slideDown();
        }
    });
    
    // Trigger on load
    $('input[name="stock_type"]').trigger('change');
});
</script>
JS;
require_once __DIR__ . '/../../../includes/footer.php';
?>
