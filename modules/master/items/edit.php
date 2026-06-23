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
            logActivity('update', 'master_items', "Memperbarui Barang: {$item['item_code']} - {$description}", 'items', $id);
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
        <div class="card card-outline card-primary">
            <div class="card-header">
                <h3 class="card-title">Form Edit Barang</h3>
                <a href="<?= APP_URL ?>/modules/master/items/index.php" class="btn btn-secondary btn-sm float-right"><i class="fas fa-arrow-left mr-1"></i> Kembali</a>
            </div>
            <form method="POST">
                <div class="card-body">
                    
                    <div class="form-group row">
                        <label class="col-sm-4 col-form-label">Kode Barang <span class="text-danger">*</span></label>
                        <div class="col-sm-8">
                            <input type="text" class="form-control" value="<?= sanitize($item['item_code']) ?>" readonly>
                            <small class="text-muted d-block mt-1">Kode barang tidak dapat diubah.</small>
                        </div>
                    </div>
                    
                    <div class="form-group row">
                        <label class="col-sm-4 col-form-label">Kategori <span class="text-danger">*</span></label>
                        <div class="col-sm-8">
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
                            <small class="text-muted d-block mt-1">Kategori tidak dapat diubah setelah dibuat.</small>
                        </div>
                    </div>
                    
                    <div class="form-group row">
                        <label class="col-sm-4 col-form-label d-flex justify-content-between align-items-center">
                            <span>Nama / Deskripsi Barang <span class="text-danger">*</span></span>
                            <button type="button" class="btn btn-sm btn-outline-primary border-0 py-0" id="btnAiSuggest" title="Lengkapi Otomatis dengan AI">
                                <i class="fas fa-magic"></i> AI Auto-Fill
                            </button>
                        </label>
                        <div class="col-sm-8">
                            <input type="text" id="itemName" name="description" class="form-control check-duplicate" data-type="item" data-id="<?= $id ?>" value="<?= sanitize($item['description']) ?>" required>
                            <div class="duplicate-warning text-danger" style="display:none; font-size: 12px; margin-top: 5px;"></div>
                        </div>
                    </div>
                    
                    <div class="form-group row">
                        <label class="col-sm-4 col-form-label">Tipe / Spesifikasi Tambahan</label>
                        <div class="col-sm-8">
                            <input type="text" name="type_specification" class="form-control" value="<?= sanitize($item['type_specification']) ?>">
                        </div>
                    </div>
                    
                    <div class="form-group row">
                        <label class="col-sm-4 col-form-label">Satuan Unit (UoM) <span class="text-danger">*</span></label>
                        <div class="col-sm-8">
                            <input type="text" name="uom" class="form-control" value="<?= sanitize($item['uom']) ?>" required style="text-transform: uppercase;">
                        </div>
                    </div>
                    
                    <div class="form-group row">
                        <label class="col-sm-4 col-form-label">Catatan Tambahan (Remark)</label>
                        <div class="col-sm-8">
                            <input type="text" name="remark" class="form-control" value="<?= sanitize($item['remark']) ?>">
                        </div>
                    </div>

                    <div class="form-group row">
                        <label class="col-sm-4 col-form-label">Stok Saat Ini (Hanya Info)</label>
                        <div class="col-sm-8">
                            <input type="text" class="form-control text-primary font-weight-bold" value="<?= number_format($item['current_stock'], 0) ?> <?= sanitize($item['uom']) ?>" readonly>
                        </div>
                    </div>
                    
                    <div class="form-group row">
                        <label class="col-sm-4 col-form-label">Tipe Distribusi Barang</label>
                        <div class="col-sm-8 pt-2">
                            <div class="custom-control custom-radio mb-2">
                                <input class="custom-control-input" type="radio" id="typeStock" name="stock_type" value="stock" <?= $item['stock_type'] === 'stock' ? 'checked' : '' ?>>
                                <label for="typeStock" class="custom-control-label font-weight-normal">Bisa distok di Gudang Umum</label>
                            </div>
                            <div class="custom-control custom-radio">
                                <input class="custom-control-input" type="radio" id="typeDirect" name="stock_type" value="direct" <?= $item['stock_type'] === 'direct' ? 'checked' : '' ?>>
                                <label for="typeDirect" class="custom-control-label font-weight-normal">Langsung kirim ke lokasi Proyek (Tanpa Stok)</label>
                            </div>
                        </div>
                    </div>
                    
                    <div id="stockGroup">
                        <div class="form-group row">
                            <label class="col-sm-4 col-form-label">Minimum Stok Alert</label>
                            <div class="col-sm-8">
                                <input type="text" name="minimum_stock" class="form-control input-number" value="<?= sanitize($item['minimum_stock'] ?? '0') ?>">
                                <small class="text-muted d-block mt-1">Masukkan angka 0 jika tidak butuh notifikasi stok menipis.</small>
                            </div>
                        </div>
                        
                        <div class="form-group row">
                            <label class="col-sm-4 col-form-label">Lokasi Gudang / Rak Penyimpanan</label>
                            <div class="col-sm-8">
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
    
    // AI Auto-Fill feature
    $('#btnAiSuggest').on('click', function() {
        let itemName = $('#itemName').val().trim();
        if (!itemName) {
            toastr.error('Silakan ketik nama barang terlebih dahulu!');
            return;
        }
        
        let btn = $(this);
        let originalHtml = btn.html();
        btn.html('<i class="fas fa-spinner fa-spin"></i> Memproses...').prop('disabled', true);
        
        $.ajax({
            url: APP_URL + '/api/ai_suggest_item.php',
            type: 'POST',
            data: { item_name: itemName },
            dataType: 'json',
            success: function(res) {
                btn.html(originalHtml).prop('disabled', false);
                if (res.success && res.data) {
                    let d = res.data;
                    // Category is readonly in edit mode, so we skip it
                    if (d.type_specification) {
                        $('input[name="type_specification"]').val(d.type_specification);
                    }
                    if (d.uom) {
                        $('input[name="uom"]').val(d.uom);
                    }
                    toastr.success('Data berhasil dilengkapi oleh AI ✨');
                } else {
                    toastr.error(res.error || 'Gagal memproses AI.');
                }
            },
            error: function(err) {
                btn.html(originalHtml).prop('disabled', false);
                toastr.error('Terjadi kesalahan saat menghubungi API.');
            }
        });
    });
});
</script>
JS;
require_once __DIR__ . '/../../../includes/footer.php';
?>
