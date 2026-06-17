<?php
/**
 * Master Items - Create
 */
require_once __DIR__ . '/../../../includes/auth.php';
requirePermission('master_items');

$pageTitle = 'Tambah Barang / Material';
$breadcrumbs = [
    ['label' => 'Master Data', 'url' => '#'],
    ['label' => 'Barang', 'url' => APP_URL . '/modules/master/items/index.php'],
    ['label' => 'Tambah']
];

// Fetch categories for dropdown
$categories = $pdo->query("SELECT id, name, prefix FROM categories ORDER BY name ASC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $categoryId   = $_POST['category_id'] ?? '';
    $description  = trim($_POST['description'] ?? '');
    $uom          = trim($_POST['uom'] ?? '');
    $typeSpec     = trim($_POST['type_specification'] ?? '');
    $whLocation   = trim($_POST['warehouse_location'] ?? '');
    $remark       = trim($_POST['remark'] ?? '');
    $stockType    = $_POST['stock_type'] ?? 'stock';
    $minimumStock = parseRupiah($_POST['minimum_stock'] ?? '0');
    
    // Validation
    $errors = [];
    if (empty($categoryId)) $errors[] = "Kategori wajib dipilih.";
    if (empty($description)) $errors[] = "Deskripsi barang wajib diisi.";
    if (empty($uom)) $errors[] = "Satuan (UoM) wajib diisi.";
    
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            // Generate Item Code based on category prefix
            $stmt = $pdo->prepare("SELECT prefix FROM categories WHERE id = ?");
            $stmt->execute([$categoryId]);
            $prefix = $stmt->fetchColumn();
            
            // Get last sequence for this category
            $stmt = $pdo->prepare("
                SELECT item_code 
                FROM items 
                WHERE category_id = ?
                ORDER BY CAST(SUBSTRING_INDEX(item_code, '-', -1) AS UNSIGNED) DESC 
                LIMIT 1
            ");
            $stmt->execute([$categoryId]);
            $lastCode = $stmt->fetchColumn();
            
            $nextSeq = 1;
            if ($lastCode) {
                $parts = explode('-', $lastCode);
                $nextSeq = intval(end($parts)) + 1;
            }
            
            $itemCode = $prefix . '-' . str_pad($nextSeq, 4, '0', STR_PAD_LEFT);
            
            $insert = $pdo->prepare("
                INSERT INTO items (category_id, item_code, description, type_specification, uom, minimum_stock, warehouse_location, remark, stock_type, current_stock, is_active) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 1)
            ");
            
            $insert->execute([$categoryId, $itemCode, $description, $typeSpec, $uom, $stockType === 'stock' ? $minimumStock : 0, $whLocation, $remark, $stockType]);
            
            $pdo->commit();
            setFlash('success', "Barang berhasil ditambahkan dengan kode: $itemCode");
            header('Location: ' . APP_URL . '/modules/master/items/index.php');
            exit;
            
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log('[NEWMEGA] ' . $e->getMessage());
            setFlash('danger', 'Terjadi kesalahan sistem. Silakan coba lagi atau hubungi administrator.');
        }
    }
    
    if (!empty($errors)) {
        setFlash('danger', implode('<br>', $errors));
    }
}

require_once __DIR__ . '/../../../includes/header.php';
?>

<div class="row">
    <div class="col-md-8 mx-auto">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-box-open mr-2"></i>Form Tambah Barang</h3>
                <a href="<?= APP_URL ?>/modules/master/items/index.php" class="btn btn-secondary btn-sm float-right"><i class="fas fa-arrow-left mr-1"></i> Kembali</a>
            </div>
            <form method="POST">
                <div class="card-body">
                    
                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group">
                                <label>Kategori <span class="text-danger">*</span></label>
                                <select name="category_id" class="form-control select2" required>
                                    <option value="">-- Pilih Kategori --</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= $cat['id'] ?>" <?= ($_POST['category_id'] ?? '') == $cat['id'] ? 'selected' : '' ?>>
                                            <?= sanitize($cat['name']) ?> (Prefix: <?= sanitize($cat['prefix']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted">Kode barang akan digenerate otomatis berdasarkan prefix kategori yang dipilih.</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-7">
                            <div class="form-group">
                                <label class="d-flex justify-content-between align-items-center w-100">
                                    <span>Nama / Deskripsi Barang <span class="text-danger">*</span></span>
                                    <button type="button" class="btn btn-sm btn-outline-primary border-0" id="btnAiSuggest" title="Lengkapi Otomatis dengan AI">
                                        <i class="fas fa-magic"></i> AI Auto-Fill
                                    </button>
                                </label>
                                <input type="text" id="itemName" name="description" class="form-control check-duplicate" data-type="item" value="<?= sanitize($_POST['description'] ?? '') ?>" required>
                                <div class="duplicate-warning text-danger" style="display:none; font-size: 12px; margin-top: 5px;"></div>
                            </div>
                        </div>
                        <div class="col-md-5">
                            <div class="form-group">
                                <label>Tipe / Spesifikasi Tambahan</label>
                                <input type="text" name="type_specification" class="form-control" value="<?= sanitize($_POST['type_specification'] ?? '') ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Satuan Unit (UoM) <span class="text-danger">*</span></label>
                                <input type="text" name="uom" class="form-control" value="<?= sanitize($_POST['uom'] ?? '') ?>" placeholder="Cth: PCS, ZAK, M2, LTR" required style="text-transform: uppercase;">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Catatan Tambahan (Remark)</label>
                                <input type="text" name="remark" class="form-control" value="<?= sanitize($_POST['remark'] ?? '') ?>">
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
                                    <input class="custom-control-input" type="radio" id="typeStock" name="stock_type" value="stock" <?= ($_POST['stock_type'] ?? 'stock') === 'stock' ? 'checked' : '' ?>>
                                    <label for="typeStock" class="custom-control-label">Bisa distok di Gudang Umum</label>
                                </div>
                                <div class="custom-control custom-radio">
                                    <input class="custom-control-input" type="radio" id="typeDirect" name="stock_type" value="direct" <?= ($_POST['stock_type'] ?? '') === 'direct' ? 'checked' : '' ?>>
                                    <label for="typeDirect" class="custom-control-label">Langsung kirim ke lokasi Proyek (Tanpa Stok)</label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6" id="stockGroup">
                            <div class="form-group">
                                <label>Minimum Stok Alert</label>
                                <input type="text" name="minimum_stock" class="form-control input-number" value="<?= sanitize($_POST['minimum_stock'] ?? '0') ?>">
                                <small class="text-muted">Masukkan angka 0 jika tidak butuh notifikasi stok menipis.</small>
                            </div>
                            <div class="form-group mt-2">
                                <label>Lokasi Gudang / Rak Penyimpanan</label>
                                <input type="text" name="warehouse_location" class="form-control" value="<?= sanitize($_POST['warehouse_location'] ?? '') ?>" placeholder="Cth: Rak A1, Area Luar">
                            </div>
                        </div>
                    </div>
                    
                </div>
                <div class="card-footer text-right">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Simpan Barang</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$extraJS = <<<'JS'
<script>
$(document).ready(function() {
    initSelect2('.select2');
    
    // Toggle stock group input based on stock_type
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
                    if (d.category_id) {
                        $('select[name="category_id"]').val(d.category_id).trigger('change');
                    }
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
