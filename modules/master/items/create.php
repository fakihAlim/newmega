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
            
            $manualCode = $_POST['manual_code'] ?? '';
            $manualCode = strtoupper(str_replace(' ', '', $manualCode));
            if (empty($manualCode)) {
                throw new Exception("Kode barang manual wajib diisi.");
            }
            
            // Generate Item Code based on category prefix
            $stmt = $pdo->prepare("SELECT prefix FROM categories WHERE id = ?");
            $stmt->execute([$categoryId]);
            $prefix = $stmt->fetchColumn();
            
            $itemCode = $prefix . '-' . $manualCode;
            
            // Check if already exists
            $stmt = $pdo->prepare("SELECT id FROM items WHERE item_code = ?");
            $stmt->execute([$itemCode]);
            if ($stmt->fetch()) {
                throw new Exception("Kode barang $itemCode sudah digunakan.");
            }

            
            $insert = $pdo->prepare("
                INSERT INTO items (category_id, item_code, description, type_specification, uom, minimum_stock, warehouse_location, remark, stock_type, current_stock, is_active) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 1)
            ");
            
            $insert->execute([$categoryId, $itemCode, $description, $typeSpec, $uom, $stockType === 'stock' ? $minimumStock : 0, $whLocation, $remark, $stockType]);
            
            $newId = $pdo->lastInsertId();
            logActivity('create', 'master_items', "Menambah Barang: {$itemCode} - {$description}", 'items', $newId);
            
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
        <div class="card card-outline card-primary">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-box-open mr-2"></i>Form Tambah Barang</h3>
                <a href="<?= APP_URL ?>/modules/master/items/index.php" class="btn btn-secondary btn-sm float-right"><i class="fas fa-arrow-left mr-1"></i> Kembali</a>
            </div>
            <form method="POST">
                <div class="card-body">
                    
                    <div class="form-group row">
                        <label class="col-sm-4 col-form-label">Kategori <span class="text-danger">*</span></label>
                        <div class="col-sm-8">
                            <select id="categoryId" name="category_id" class="form-control select2" required>
                                <option value="">-- Pilih Kategori --</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>" <?= ($_POST['category_id'] ?? '') == $cat['id'] ? 'selected' : '' ?>>
                                        <?= sanitize($cat['name']) ?> (Prefix: <?= sanitize($cat['prefix']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group row">
                        <label class="col-sm-4 col-form-label">Kode barang <span class="text-danger">*</span></label>
                        <div class="col-sm-8">
                            <input type="text" id="manualCode" name="manual_code" class="form-control" value="<?= sanitize($_POST['manual_code'] ?? '') ?>" required style="text-transform: uppercase;">
                            <div id="codeFeedback" class="mt-1 font-weight-bold" style="font-size: 13px; display: none;"></div>
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
                            <input type="text" id="itemName" name="description" class="form-control check-duplicate" data-type="item" value="<?= sanitize($_POST['description'] ?? '') ?>" required>
                            <div class="duplicate-warning text-danger" style="display:none; font-size: 12px; margin-top: 5px;"></div>
                        </div>
                    </div>
                    
                    <div class="form-group row">
                        <label class="col-sm-4 col-form-label">Tipe / Spesifikasi Tambahan</label>
                        <div class="col-sm-8">
                            <input type="text" name="type_specification" class="form-control" value="<?= sanitize($_POST['type_specification'] ?? '') ?>">
                        </div>
                    </div>
                    
                    <div class="form-group row">
                        <label class="col-sm-4 col-form-label">Satuan Unit (UoM) <span class="text-danger">*</span></label>
                        <div class="col-sm-8">
                            <input type="text" name="uom" class="form-control" value="<?= sanitize($_POST['uom'] ?? '') ?>" placeholder="Cth: PCS, ZAK, M2, LTR" required style="text-transform: uppercase;">
                        </div>
                    </div>
                    
                    <div class="form-group row">
                        <label class="col-sm-4 col-form-label">Catatan Tambahan (Remark)</label>
                        <div class="col-sm-8">
                            <input type="text" name="remark" class="form-control" value="<?= sanitize($_POST['remark'] ?? '') ?>">
                        </div>
                    </div>
                          
                    <div class="form-group row">
                        <label class="col-sm-4 col-form-label">Tipe Distribusi Barang</label>
                        <div class="col-sm-8 pt-2">
                            <div class="custom-control custom-radio mb-2">
                                <input class="custom-control-input" type="radio" id="typeStock" name="stock_type" value="stock" <?= ($_POST['stock_type'] ?? 'stock') === 'stock' ? 'checked' : '' ?>>
                                <label for="typeStock" class="custom-control-label font-weight-normal">Bisa distok di Gudang Umum</label>
                            </div>
                            <div class="custom-control custom-radio">
                                <input class="custom-control-input" type="radio" id="typeDirect" name="stock_type" value="direct" <?= ($_POST['stock_type'] ?? '') === 'direct' ? 'checked' : '' ?>>
                                <label for="typeDirect" class="custom-control-label font-weight-normal">Langsung kirim ke lokasi Proyek (Tanpa Stok)</label>
                            </div>
                        </div>
                    </div>
                    
                    <div id="stockGroup">
                        <div class="form-group row">
                            <label class="col-sm-4 col-form-label">Minimum Stok Alert</label>
                            <div class="col-sm-8">
                                <input type="text" name="minimum_stock" class="form-control input-number" value="<?= sanitize($_POST['minimum_stock'] ?? '0') ?>">
                                <small class="text-muted d-block mt-1">Masukkan angka 0 jika tidak butuh notifikasi stok menipis.</small>
                            </div>
                        </div>
                        
                        <div class="form-group row">
                            <label class="col-sm-4 col-form-label">Lokasi Gudang / Rak Penyimpanan</label>
                            <div class="col-sm-8">
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
    
    // Check Item Code logic
    function checkItemCode() {
        let catId = $('#categoryId').val();
        let manualCode = $('#manualCode').val();
        let feedback = $('#codeFeedback');
        
        // Remove spaces and uppercase locally for feedback visual
        if (manualCode) {
            manualCode = manualCode.toUpperCase().replace(/\s+/g, '');
            $('#manualCode').val(manualCode);
        }
        
        if (!catId || !manualCode) {
            feedback.hide();
            return;
        }
        
        $.ajax({
            url: APP_URL + '/api/check_item_code.php',
            type: 'GET',
            data: { category_id: catId, manual_code: manualCode },
            dataType: 'json',
            success: function(res) {
                if (res.error) return;
                
                feedback.show().removeClass('text-danger text-success text-warning');
                
                if (res.exists) {
                    feedback.addClass('text-danger').html('<i class="fas fa-times-circle"></i> Peringatan: Kode ' + res.full_code + ' sudah digunakan!');
                } else {
                    let msg = '<span class="text-success"><i class="fas fa-check-circle"></i> Kode ' + res.full_code + ' tersedia.</span>';
                    if (res.last_code) {
                        msg += '<br><span class="text-warning"><i class="fas fa-info-circle"></i> Nomor terakhir untuk ini: ' + res.last_code + '</span>';
                    }
                    feedback.html(msg);
                }
            }
        });
    }
    
    $('#categoryId, #manualCode').on('change keyup', function() {
        checkItemCode();
    });
    
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
                    if (d.manual_code) {
                        $('input[name="manual_code"]').val(d.manual_code).trigger('change');
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
