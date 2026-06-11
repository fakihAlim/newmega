<?php
/**
 * Finance - Edit Claim Nota (only draft status)
 */
require_once __DIR__ . '/../../../includes/auth.php';
requirePermission('claim_nota', 'edit');

$user = getCurrentUser();
$id = (int)($_GET['id'] ?? 0);

// Fetch existing claim
$stmt = $pdo->prepare("SELECT * FROM claim_notas WHERE id = ?");
$stmt->execute([$id]);
$claim = $stmt->fetch();

if (!$claim) {
    setFlash('danger', 'Claim Nota tidak ditemukan.');
    header('Location: index.php');
    exit;
}

if ($claim['status'] !== 'draft') {
    setFlash('warning', 'Hanya claim dengan status Draft yang bisa diedit.');
    header('Location: view.php?id=' . $id);
    exit;
}

// Fetch existing items
$stmtItems = $pdo->prepare("SELECT * FROM claim_nota_items WHERE claim_id = ? ORDER BY id");
$stmtItems->execute([$id]);
$claimItems = $stmtItems->fetchAll();

// Fetch projects
$projects = $pdo->query("SELECT id, name, abbreviation FROM projects WHERE status IN ('planning','active') ORDER BY name")->fetchAll();

// Fetch companies
$companies = $pdo->query("SELECT id, name FROM companies ORDER BY is_default DESC, name")->fetchAll();

// Fetch items for autocomplete
$items = $pdo->query("SELECT id, item_code, description, uom FROM items WHERE is_active = 1 ORDER BY description")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $claimDate = $_POST['claim_date'] ?? date('Y-m-d');
    $projectId = (int)($_POST['project_id'] ?? 0);
    $companyId = (int)($_POST['company_id'] ?? 0);
    $employeeName = trim($_POST['employee_name'] ?? '');
    $storeName = trim($_POST['store_name'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $action = $_POST['action'] ?? 'draft';
    
    $itemIds = $_POST['item_id'] ?? [];
    $itemNames = $_POST['item_name'] ?? [];
    $itemQtys = $_POST['item_qty'] ?? [];
    $itemUoms = $_POST['item_uom'] ?? [];
    $itemPrices = $_POST['item_price'] ?? [];
    
    if (empty($projectId) || empty($companyId) || empty($employeeName)) {
        setFlash('danger', 'Proyek, Perusahaan, dan Nama Karyawan wajib diisi.');
        header('Location: edit.php?id=' . $id);
        exit;
    }
    
    try {
        $pdo->beginTransaction();
        
        $status = ($action === 'submit') ? 'pending' : 'draft';
        
        // Handle receipt photo upload
        $receiptPhoto = $claim['receipt_photo'];
        if (!empty($_FILES['receipt_photo']['name'])) {
            $uploadDir = BASE_PATH . '/uploads/claim_receipts';
            $uploadResult = uploadFile($_FILES['receipt_photo'], $uploadDir, ['jpg','jpeg','png','pdf']);
            if ($uploadResult['success']) {
                $receiptPhoto = $uploadResult['filename'];
            }
        }
        
        // Calculate subtotal
        $subtotal = 0;
        foreach ($itemNames as $i => $name) {
            if (empty(trim($name))) continue;
            $qty = (float)str_replace(['.', ','], ['', '.'], $itemQtys[$i] ?? 0);
            $price = (float)str_replace(['.', ','], ['', '.'], $itemPrices[$i] ?? 0);
            $subtotal += $qty * $price;
        }
        
        // Update header
        $stmt = $pdo->prepare("
            UPDATE claim_notas SET claim_date = ?, project_id = ?, company_id = ?, employee_name = ?, store_name = ?, subtotal = ?, notes = ?, receipt_photo = ?, status = ?
            WHERE id = ?
        ");
        $stmt->execute([$claimDate, $projectId, $companyId, $employeeName, $storeName, $subtotal, $notes, $receiptPhoto, $status, $id]);
        
        // Delete old items and re-insert
        $pdo->prepare("DELETE FROM claim_nota_items WHERE claim_id = ?")->execute([$id]);
        
        $stmtItem = $pdo->prepare("
            INSERT INTO claim_nota_items (claim_id, item_id, item_name, qty, uom, unit_price, total)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        foreach ($itemNames as $i => $name) {
            if (empty(trim($name))) continue;
            $itemId = !empty($itemIds[$i]) ? (int)$itemIds[$i] : null;
            $qty = (float)str_replace(['.', ','], ['', '.'], $itemQtys[$i] ?? 0);
            $price = (float)str_replace(['.', ','], ['', '.'], $itemPrices[$i] ?? 0);
            $total = $qty * $price;
            $uom = $itemUoms[$i] ?? '';
            
            $stmtItem->execute([$id, $itemId, trim($name), $qty, $uom, $price, $total]);
        }
        
        $pdo->commit();
        
        $msg = ($action === 'submit') ? 'Claim Nota berhasil diupdate dan disubmit.' : 'Claim Nota berhasil diupdate.';
        setFlash('success', $msg);
        header('Location: index.php');
        exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        setFlash('danger', 'Gagal mengupdate claim: ' . $e->getMessage());
        header('Location: edit.php?id=' . $id);
        exit;
    }
}

$pageTitle = 'Edit Claim Nota';
$breadcrumbs = [
    ['label' => 'Finance', 'url' => '#'],
    ['label' => 'Claim Nota', 'url' => 'index.php'],
    ['label' => 'Edit - ' . $claim['claim_number']]
];

require_once __DIR__ . '/../../../includes/header.php';
?>

<form action="" method="POST" enctype="multipart/form-data" id="formClaim">
<div class="row">
    <div class="col-md-8">
        <!-- Header -->
        <div class="card card-primary card-outline">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-edit mr-2"></i> Edit Claim: <?= sanitize($claim['claim_number']) ?></h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Tanggal Claim <span class="text-danger">*</span></label>
                            <input type="date" name="claim_date" class="form-control" value="<?= $claim['claim_date'] ?>" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Nama Karyawan <span class="text-danger">*</span></label>
                            <input type="text" name="employee_name" class="form-control" value="<?= sanitize($claim['employee_name']) ?>" required>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Proyek <span class="text-danger">*</span></label>
                            <select class="form-control select2" name="project_id" required style="width:100%;">
                                <option value="">-- Pilih Proyek --</option>
                                <?php foreach ($projects as $p): ?>
                                    <option value="<?= $p['id'] ?>" <?= $p['id'] == $claim['project_id'] ? 'selected' : '' ?>>
                                        [<?= sanitize($p['abbreviation']) ?>] <?= sanitize($p['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Perusahaan (PT) <span class="text-danger">*</span></label>
                            <select class="form-control select2" name="company_id" required style="width:100%;">
                                <option value="">-- Pilih Perusahaan --</option>
                                <?php foreach ($companies as $c): ?>
                                    <option value="<?= $c['id'] ?>" <?= $c['id'] == $claim['company_id'] ? 'selected' : '' ?>>
                                        <?= sanitize($c['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Nama Toko</label>
                            <input type="text" name="store_name" class="form-control" value="<?= sanitize($claim['store_name']) ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Upload Foto Nota <small class="text-muted">(opsional)</small></label>
                            <?php if ($claim['receipt_photo']): ?>
                                <div class="mb-1"><small class="text-success"><i class="fas fa-check-circle"></i> File tersimpan: <?= sanitize($claim['receipt_photo']) ?></small></div>
                            <?php endif; ?>
                            <div class="custom-file">
                                <input type="file" class="custom-file-input" id="receipt_photo" name="receipt_photo" accept=".jpg,.jpeg,.png,.pdf">
                                <label class="custom-file-label" for="receipt_photo">Ganti file...</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label>Catatan</label>
                    <textarea name="notes" class="form-control" rows="2"><?= sanitize($claim['notes']) ?></textarea>
                </div>
            </div>
        </div>

        <!-- Items -->
        <div class="card card-success card-outline">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title"><i class="fas fa-list mr-2"></i> Detail Item</h3>
                <button type="button" class="btn btn-success btn-sm ml-auto" id="btnAddRow">
                    <i class="fas fa-plus mr-1"></i> Tambah Item
                </button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered mb-0" id="itemsTable" style="font-size: 13px;">
                        <thead class="bg-light">
                            <tr>
                                <th width="5%" class="text-center">#</th>
                                <th width="30%">Nama Item</th>
                                <th width="10%">Qty</th>
                                <th width="10%">Satuan</th>
                                <th width="18%">Harga Satuan (Rp)</th>
                                <th width="18%" class="text-right">Total (Rp)</th>
                                <th width="5%" class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="itemsBody">
                            <?php foreach ($claimItems as $idx => $ci): ?>
                            <tr class="item-row">
                                <td class="text-center row-number"><?= $idx + 1 ?></td>
                                <td>
                                    <input type="hidden" name="item_id[]" class="item-id" value="<?= $ci['item_id'] ?: '' ?>">
                                    <select class="form-control form-control-sm item-select select2-item" style="width:100%;">
                                        <option value="">-- Pilih dari Master --</option>
                                        <?php foreach ($items as $it): ?>
                                            <option value="<?= $it['id'] ?>" data-name="<?= sanitize($it['description']) ?>" data-uom="<?= sanitize($it['uom']) ?>"
                                                <?= $ci['item_id'] == $it['id'] ? 'selected' : '' ?>>
                                                [<?= sanitize($it['item_code']) ?>] <?= sanitize($it['description']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input type="text" name="item_name[]" class="form-control form-control-sm mt-1 item-name" value="<?= sanitize($ci['item_name']) ?>" required>
                                </td>
                                <td><input type="text" name="item_qty[]" class="form-control form-control-sm item-qty" value="<?= (int)$ci['qty'] ?>" required></td>
                                <td><input type="text" name="item_uom[]" class="form-control form-control-sm item-uom" value="<?= sanitize($ci['uom']) ?>"></td>
                                <td><input type="text" name="item_price[]" class="form-control form-control-sm item-price rupiah-input" value="<?= number_format($ci['unit_price'], 0, ',', '.') ?>"></td>
                                <td class="text-right font-weight-bold item-total">Rp <?= number_format($ci['total'], 0, ',', '.') ?></td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-danger btn-xs btn-remove-row" title="Hapus"><i class="fas fa-times"></i></button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="bg-light font-weight-bold">
                                <td colspan="5" class="text-right">TOTAL:</td>
                                <td class="text-right text-primary" id="grandTotal">Rp <?= number_format($claim['subtotal'], 0, ',', '.') ?></td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="card card-body">
            <div class="d-flex justify-content-between">
                <a href="index.php" class="btn btn-default"><i class="fas fa-arrow-left mr-1"></i> Kembali</a>
                <div>
                    <button type="submit" name="action" value="draft" class="btn btn-secondary">
                        <i class="fas fa-save mr-1"></i> Simpan Draft
                    </button>
                    <button type="submit" name="action" value="submit" class="btn btn-primary ml-2">
                        <i class="fas fa-paper-plane mr-1"></i> Submit untuk Approval
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Side Panel -->
    <div class="col-md-4">
        <div class="card card-outline card-info">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-info-circle mr-1"></i> Info Claim</h3>
            </div>
            <div class="card-body" style="font-size:13px;">
                <p><strong>No. Claim:</strong> <?= sanitize($claim['claim_number']) ?></p>
                <p><strong>Status:</strong> <?= getStatusBadge($claim['status']) ?></p>
                <p><strong>Dibuat:</strong> <?= formatDateTime($claim['created_at']) ?></p>
            </div>
        </div>
    </div>
</div>
</form>

<?php
$itemsJson = json_encode($items);
$extraJS = <<<JS
<script>
var masterItems = {$itemsJson};

$(document).ready(function() {
    initSelect2();
    
    $('.custom-file-input').on('change', function() {
        var fileName = $(this).val().split('\\\\').pop();
        $(this).next('.custom-file-label').text(fileName || 'Ganti file...');
    });
    
    $(document).on('change', '.item-select', function() {
        var row = $(this).closest('.item-row');
        var opt = $(this).find(':selected');
        if (opt.val()) {
            row.find('.item-name').val(opt.data('name'));
            row.find('.item-uom').val(opt.data('uom'));
            row.find('.item-id').val(opt.val());
        } else {
            row.find('.item-id').val('');
        }
    });
    
    $(document).on('keyup change', '.item-qty, .item-price', function() {
        calculateRow($(this).closest('.item-row'));
        calculateGrandTotal();
    });
    
    $('#btnAddRow').click(function() {
        var newRow = $('#itemsBody .item-row:first').clone();
        newRow.find('.item-id').val('');
        newRow.find('.item-name').val('');
        newRow.find('.item-qty').val('1');
        newRow.find('.item-uom').val('');
        newRow.find('.item-price').val('');
        newRow.find('.item-total').text('Rp 0');
        newRow.find('.select2-container').remove();
        newRow.find('.item-select').removeClass('select2-hidden-accessible').removeAttr('data-select2-id').removeAttr('aria-hidden').removeAttr('tabindex').val('');
        $('#itemsBody').append(newRow);
        initSelect2();
        renumberRows();
    });
    
    $(document).on('click', '.btn-remove-row', function() {
        if ($('#itemsBody .item-row').length > 1) {
            $(this).closest('.item-row').remove();
            renumberRows();
            calculateGrandTotal();
        }
    });
    
    $(document).on('keyup', '.rupiah-input', function() {
        var val = $(this).val().replace(/[^0-9]/g, '');
        if (val) $(this).val(parseInt(val).toLocaleString('id-ID'));
    });
});

function initSelect2() {
    $('.select2').select2({ theme: 'bootstrap4' });
    $('.select2-item').each(function() {
        if (!$(this).data('select2')) {
            $(this).select2({ theme: 'bootstrap4', placeholder: '-- Pilih dari Master --', allowClear: true });
        }
    });
}

function calculateRow(row) {
    var qty = parseFloat(row.find('.item-qty').val().replace(/[^0-9]/g, '')) || 0;
    var price = parseFloat(row.find('.item-price').val().replace(/[^0-9]/g, '')) || 0;
    row.find('.item-total').text('Rp ' + (qty * price).toLocaleString('id-ID'));
}

function calculateGrandTotal() {
    var grand = 0;
    $('#itemsBody .item-row').each(function() {
        var qty = parseFloat($(this).find('.item-qty').val().replace(/[^0-9]/g, '')) || 0;
        var price = parseFloat($(this).find('.item-price').val().replace(/[^0-9]/g, '')) || 0;
        grand += qty * price;
    });
    $('#grandTotal').text('Rp ' + grand.toLocaleString('id-ID'));
}

function renumberRows() {
    $('#itemsBody .item-row').each(function(i) { $(this).find('.row-number').text(i + 1); });
}
</script>
JS;
require_once __DIR__ . '/../../../includes/footer.php';
?>
