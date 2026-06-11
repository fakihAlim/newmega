<?php
/**
 * Finance - Create Claim Nota
 */
require_once __DIR__ . '/../../../includes/auth.php';
requirePermission('claim_nota', 'create');

$user = getCurrentUser();

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
    $action = $_POST['action'] ?? 'draft'; // 'draft' or 'submit'
    
    // Items
    $itemIds = $_POST['item_id'] ?? [];
    $itemNames = $_POST['item_name'] ?? [];
    $itemQtys = $_POST['item_qty'] ?? [];
    $itemUoms = $_POST['item_uom'] ?? [];
    $itemPrices = $_POST['item_price'] ?? [];
    
    if (empty($projectId) || empty($companyId) || empty($employeeName)) {
        setFlash('danger', 'Proyek, Perusahaan, dan Nama Karyawan wajib diisi.');
        header('Location: create.php');
        exit;
    }
    
    if (empty($itemNames) || count(array_filter($itemNames)) === 0) {
        setFlash('danger', 'Minimal 1 item harus diisi.');
        header('Location: create.php');
        exit;
    }
    
    try {
        $pdo->beginTransaction();
        
        // Get project abbreviation for claim number
        $projStmt = $pdo->prepare("SELECT abbreviation FROM projects WHERE id = ?");
        $projStmt->execute([$projectId]);
        $projAbbr = $projStmt->fetchColumn() ?: 'GEN';
        
        $claimNumber = generateDocNumber($pdo, 'CLM', $projAbbr);
        $status = ($action === 'submit') ? 'pending' : 'draft';
        
        // Handle receipt photo upload
        $receiptPhoto = null;
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
        
        // Insert header
        $stmt = $pdo->prepare("
            INSERT INTO claim_notas (claim_number, claim_date, project_id, company_id, claimed_by, employee_name, store_name, subtotal, notes, receipt_photo, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$claimNumber, $claimDate, $projectId, $companyId, $user['id'], $employeeName, $storeName, $subtotal, $notes, $receiptPhoto, $status]);
        $claimId = $pdo->lastInsertId();
        
        // Insert items
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
            
            $stmtItem->execute([$claimId, $itemId, trim($name), $qty, $uom, $price, $total]);
        }
        
        $pdo->commit();
        
        $msg = ($action === 'submit') ? 'Claim Nota berhasil dibuat dan disubmit untuk approval.' : 'Claim Nota berhasil disimpan sebagai draft.';
        setFlash('success', $msg);
        header('Location: index.php');
        exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        setFlash('danger', 'Gagal menyimpan claim: ' . $e->getMessage());
        header('Location: create.php');
        exit;
    }
}

$pageTitle = 'Buat Claim Nota';
$breadcrumbs = [
    ['label' => 'Finance', 'url' => '#'],
    ['label' => 'Claim Nota', 'url' => 'index.php'],
    ['label' => 'Buat Baru']
];

require_once __DIR__ . '/../../../includes/header.php';
?>

<form action="" method="POST" enctype="multipart/form-data" id="formClaim">
<div class="row">
    <div class="col-md-8">
        <!-- Header -->
        <div class="card card-primary card-outline">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-receipt mr-2"></i> Informasi Claim</h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Tanggal Claim <span class="text-danger">*</span></label>
                            <input type="date" name="claim_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Nama Karyawan <span class="text-danger">*</span></label>
                            <input type="text" name="employee_name" class="form-control" value="<?= sanitize($user['full_name']) ?>" required placeholder="Nama karyawan yang melakukan pengeluaran">
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
                                    <option value="<?= $p['id'] ?>" data-abbr="<?= sanitize($p['abbreviation']) ?>">
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
                                    <option value="<?= $c['id'] ?>"><?= sanitize($c['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Nama Toko</label>
                            <input type="text" name="store_name" class="form-control" placeholder="Nama toko tempat belanja">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Upload Foto Nota <small class="text-muted">(opsional, jpg/png/pdf)</small></label>
                            <div class="custom-file">
                                <input type="file" class="custom-file-input" id="receipt_photo" name="receipt_photo" accept=".jpg,.jpeg,.png,.pdf">
                                <label class="custom-file-label" for="receipt_photo">Pilih file...</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label>Catatan</label>
                    <textarea name="notes" class="form-control" rows="2" placeholder="Catatan tambahan (opsional)"></textarea>
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
                            <tr class="item-row">
                                <td class="text-center row-number">1</td>
                                <td>
                                    <input type="hidden" name="item_id[]" class="item-id" value="">
                                    <select class="form-control form-control-sm item-select select2-item" style="width:100%;">
                                        <option value="">-- Pilih dari Master --</option>
                                        <?php foreach ($items as $it): ?>
                                            <option value="<?= $it['id'] ?>" data-name="<?= sanitize($it['description']) ?>" data-uom="<?= sanitize($it['uom']) ?>">
                                                [<?= sanitize($it['item_code']) ?>] <?= sanitize($it['description']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input type="text" name="item_name[]" class="form-control form-control-sm mt-1 item-name" placeholder="Atau ketik manual..." required>
                                </td>
                                <td><input type="text" name="item_qty[]" class="form-control form-control-sm item-qty" value="1" required></td>
                                <td><input type="text" name="item_uom[]" class="form-control form-control-sm item-uom" placeholder="pcs"></td>
                                <td><input type="text" name="item_price[]" class="form-control form-control-sm item-price rupiah-input" placeholder="0"></td>
                                <td class="text-right font-weight-bold item-total">Rp 0</td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-danger btn-xs btn-remove-row" title="Hapus"><i class="fas fa-times"></i></button>
                                </td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr class="bg-light font-weight-bold">
                                <td colspan="5" class="text-right">TOTAL:</td>
                                <td class="text-right text-primary" id="grandTotal">Rp 0</td>
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
        <div class="card card-outline card-warning">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-info-circle mr-1"></i> Petunjuk</h3>
            </div>
            <div class="card-body" style="font-size:13px;">
                <p>Claim Nota digunakan untuk mengklaim dana pribadi karyawan yang dipakai untuk keperluan perusahaan/proyek.</p>
                <ul>
                    <li>Pilih <strong>Proyek</strong> dan <strong>Perusahaan</strong> terkait.</li>
                    <li>Item bisa dipilih dari <strong>Master Data</strong> atau diketik <strong>manual</strong>.</li>
                    <li>Upload foto nota/struk sebagai bukti (opsional).</li>
                    <li><strong>Draft</strong> = belum diajukan, <strong>Submit</strong> = langsung ke approval.</li>
                </ul>
            </div>
        </div>
    </div>
</div>
</form>

<?php
// Build items JSON for JS
$itemsJson = json_encode($items);
$extraJS = <<<JS
<script>
var masterItems = {$itemsJson};

$(document).ready(function() {
    initSelect2();
    
    // File input label
    $('.custom-file-input').on('change', function() {
        var fileName = $(this).val().split('\\\\').pop();
        $(this).next('.custom-file-label').text(fileName || 'Pilih file...');
    });
    
    // Item select change - fill name & uom
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
    
    // Manual name input clears master selection
    $(document).on('input', '.item-name', function() {
        var row = $(this).closest('.item-row');
        var sel = row.find('.item-select');
        if (sel.val() && $(this).val() !== sel.find(':selected').data('name')) {
            row.find('.item-id').val('');
        }
    });
    
    // Calculate totals
    $(document).on('keyup change', '.item-qty, .item-price', function() {
        calculateRow($(this).closest('.item-row'));
        calculateGrandTotal();
    });
    
    // Add row
    $('#btnAddRow').click(function() {
        var newRow = $('#itemsBody .item-row:first').clone();
        // Clear values
        newRow.find('.item-id').val('');
        newRow.find('.item-name').val('');
        newRow.find('.item-qty').val('1');
        newRow.find('.item-uom').val('');
        newRow.find('.item-price').val('');
        newRow.find('.item-total').text('Rp 0');
        
        // Destroy existing select2 and reinit
        newRow.find('.select2-container').remove();
        newRow.find('.item-select').removeClass('select2-hidden-accessible').removeAttr('data-select2-id').removeAttr('aria-hidden').removeAttr('tabindex');
        // Reset select
        newRow.find('.item-select').val('');
        
        $('#itemsBody').append(newRow);
        initSelect2();
        renumberRows();
    });
    
    // Remove row
    $(document).on('click', '.btn-remove-row', function() {
        if ($('#itemsBody .item-row').length > 1) {
            $(this).closest('.item-row').remove();
            renumberRows();
            calculateGrandTotal();
        } else {
            Swal.fire('Info', 'Minimal harus ada 1 item.', 'info');
        }
    });
    
    // Rupiah formatting
    $(document).on('keyup', '.rupiah-input', function() {
        var val = $(this).val().replace(/[^0-9]/g, '');
        if (val) {
            $(this).val(parseInt(val).toLocaleString('id-ID'));
        }
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
    var total = qty * price;
    row.find('.item-total').text('Rp ' + total.toLocaleString('id-ID'));
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
    $('#itemsBody .item-row').each(function(i) {
        $(this).find('.row-number').text(i + 1);
    });
}
</script>
JS;
require_once __DIR__ . '/../../../includes/footer.php';
?>
