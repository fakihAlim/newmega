<?php
/**
 * Finance - Claim Nota Edit
 */
require_once __DIR__ . '/../../../includes/auth.php';
requirePermission('claim_nota', 'edit');

$id = $_GET['id'] ?? 0;

// Fetch Claim Header
$stmt = $pdo->prepare("SELECT * FROM nota_claims WHERE id = ?");
$stmt->execute([$id]);
$claim = $stmt->fetch();

if (!$claim) {
    setFlash('danger', 'Klaim Nota tidak ditemukan.');
    header('Location: ' . APP_URL . '/modules/finance/claim_nota/index.php');
    exit;
}

// Security: Prevent editing approved or paid claims
if (!in_array($claim['status'], ['pending', 'rejected'])) {
    setFlash('danger', 'Klaim Nota yang telah disetujui atau dibayar tidak dapat diedit.');
    header('Location: ' . APP_URL . '/modules/finance/claim_nota/view.php?id=' . $id);
    exit;
}

$user = getCurrentUser();

// Fetch Companies
$companies = $pdo->query("SELECT id, name FROM companies ORDER BY name ASC")->fetchAll();

// Fetch Projects
$projects = $pdo->query("SELECT id, name FROM projects ORDER BY name ASC")->fetchAll();

// Fetch Users (for employee selection) with their employee_code
$users = $pdo->query("
    SELECT u.id, u.full_name, e.employee_code 
    FROM users u 
    LEFT JOIN employees e ON u.id = e.user_id 
    WHERE u.is_active = 1 
    ORDER BY u.full_name ASC
")->fetchAll();

// Fetch existing groups for autocomplete datalist suggestions
$existingGroups = $pdo->query("SELECT DISTINCT group_name FROM nota_claim_items WHERE group_name IS NOT NULL AND group_name != '' ORDER BY group_name ASC")->fetchAll(PDO::FETCH_COLUMN);

// Fetch existing items
$stmtItems = $pdo->prepare("SELECT * FROM nota_claim_items WHERE claim_id = ? ORDER BY id ASC");
$stmtItems->execute([$id]);
$claimItems = $stmtItems->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $employeeOption = $_POST['employee_option'] ?? 'select';
    $employeeId     = $_POST['employee_id'] ?? null;
    $employeeName   = trim($_POST['employee_name'] ?? '');
    $companyId      = $_POST['company_id'] ?? '';
    $claimDate      = $_POST['claim_date'] ?? date('Y-m-d');
    $notes          = trim($_POST['notes'] ?? '');
    $status         = $claim['status']; // retain previous status (pending/rejected)

    // Item details
    $itemDates       = $_POST['item_date'] ?? [];
    $projectIds      = $_POST['project_id'] ?? [];
    $groupNames      = $_POST['group_name'] ?? [];
    $itemNames       = $_POST['item_name'] ?? [];
    $qtys            = $_POST['qty'] ?? [];
    $prices          = $_POST['price'] ?? [];
    $existingPhotos  = $_POST['existing_photo'] ?? [];

    $errors = [];

    // Validation
    if (empty($companyId)) {
        $errors[] = "Perusahaan wajib dipilih.";
    }
    if (empty($claimDate)) {
        $errors[] = "Tanggal Klaim wajib diisi.";
    }
    if ($employeeOption === 'select') {
        if (empty($employeeId)) {
            $errors[] = "Karyawan wajib dipilih.";
        } else {
            // Get selected employee's full name
            $empStmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
            $empStmt->execute([$employeeId]);
            $employeeName = $empStmt->fetchColumn();
        }
    } else {
        if (empty($employeeName)) {
            $errors[] = "Nama Karyawan wajib diisi secara manual.";
        }
        $employeeId = null; // Clear if manual input is chosen
    }

    if (empty($itemNames) || count($itemNames) === 0) {
        $errors[] = "Minimal harus ada 1 item yang diklaim.";
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // Fetch company details to generate abbreviation for Claim Number if company changes
            $compStmt = $pdo->prepare("SELECT name FROM companies WHERE id = ?");
            $compStmt->execute([$companyId]);
            $companyName = $compStmt->fetchColumn() ?: 'GEN';
            $companyAbbr = generateAbbreviation($companyName);

            // Re-generate claim number only if company changed
            $claimNumber = $claim['claim_number'];
            if ($companyId != $claim['company_id']) {
                $claimNumber = generateDocNumber($pdo, 'CLM', $companyAbbr);
            }

            // Update Header
            $stmtHeader = $pdo->prepare("
                UPDATE nota_claims 
                SET claim_number = ?, company_id = ?, employee_name = ?, employee_id = ?, claim_date = ?, notes = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmtHeader->execute([$claimNumber, $companyId, $employeeName, $employeeId, $claimDate, $notes, $id]);

            // Keep track of old photos to clean up or retain
            $photosToKeep = [];

            // Temporarily delete old items (we will insert them fresh)
            // But we must check what files on disk are no longer used.
            // Let's retrieve all current file names first
            $oldItemsStmt = $pdo->prepare("SELECT receipt_photo FROM nota_claim_items WHERE claim_id = ?");
            $oldItemsStmt->execute([$id]);
            $oldPhotos = $oldItemsStmt->fetchAll(PDO::FETCH_COLUMN);

            $pdo->prepare("DELETE FROM nota_claim_items WHERE claim_id = ?")->execute([$id]);

            $totalClaimAmount = 0;

            // Re-insert items
            $stmtItem = $pdo->prepare("
                INSERT INTO nota_claim_items (claim_id, item_date, project_id, group_name, item_name, qty, price, amount, receipt_photo)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            for ($i = 0; $i < count($itemNames); $i++) {
                $i_date  = $itemDates[$i] ?? $claimDate;
                $i_proj  = !empty($projectIds[$i]) ? $projectIds[$i] : null;
                $i_group = !empty($groupNames[$i]) ? trim($groupNames[$i]) : 'Money change';
                $i_name  = trim($itemNames[$i] ?? '');
                $i_qty   = parseQty($qtys[$i] ?? '1');
                $i_price = parseRupiah($prices[$i] ?? '0');
                $i_amount = $i_qty * $i_price;
                $i_existing_photo = $existingPhotos[$i] ?? null;

                if (empty($i_name)) {
                    continue; // Skip empty rows
                }

                // Handle Photo Upload
                $photoFilename = $i_existing_photo; // default to old one
                if (isset($_FILES['photo']['name'][$i]) && $_FILES['photo']['error'][$i] === UPLOAD_ERR_OK) {
                    $fileArray = [
                        'name'     => $_FILES['photo']['name'][$i],
                        'type'     => $_FILES['photo']['type'][$i],
                        'tmp_name' => $_FILES['photo']['tmp_name'][$i],
                        'error'    => $_FILES['photo']['error'][$i],
                        'size'     => $_FILES['photo']['size'][$i]
                    ];
                    $uploadResult = uploadFile($fileArray, UPLOADS_PATH . '/receipts');
                    if ($uploadResult['success']) {
                        $photoFilename = $uploadResult['filename'];
                        
                        // Delete old file if replaced
                        if ($i_existing_photo && file_exists(UPLOADS_PATH . '/receipts/' . $i_existing_photo)) {
                            @unlink(UPLOADS_PATH . '/receipts/' . $i_existing_photo);
                        }
                    } else {
                        throw new Exception("Gagal mengunggah foto nota pada baris " . ($i + 1) . ": " . $uploadResult['message']);
                    }
                }

                if ($photoFilename) {
                    $photosToKeep[] = $photoFilename;
                }

                $stmtItem->execute([
                    $id,
                    $i_date,
                    $i_proj,
                    $i_group,
                    $i_name,
                    $i_qty,
                    $i_price,
                    $i_amount,
                    $photoFilename
                ]);

                $totalClaimAmount += $i_amount;
            }

            // Update header's total amount
            $updateHeader = $pdo->prepare("UPDATE nota_claims SET total_amount = ? WHERE id = ?");
            $updateHeader->execute([$totalClaimAmount, $id]);

            // Clean up files that are deleted/no longer in the list
            foreach ($oldPhotos as $oldPhoto) {
                if ($oldPhoto && !in_array($oldPhoto, $photosToKeep)) {
                    $oldPath = UPLOADS_PATH . '/receipts/' . $oldPhoto;
                    if (file_exists($oldPath)) {
                        @unlink($oldPath);
                    }
                }
            }

            $pdo->commit();
            
            logActivity('update', 'finance', "Memperbarui Klaim Nota: {$claimNumber} untuk {$employeeName} menjadi sebesar Rp " . number_format($totalClaimAmount, 0, ',', '.'), 'nota_claims', $id);
            
            setFlash('success', "Klaim Nota $claimNumber berhasil diperbarui.");
            header('Location: ' . APP_URL . '/modules/finance/claim_nota/view.php?id=' . $id);
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            error_log('[NEWMEGA] ' . $e->getMessage());
            $errors[] = 'Terjadi kesalahan sistem saat menyimpan klaim.';
        }
    }

    if (!empty($errors)) {
        setFlash('danger', implode('<br>', $errors));
    }
}

require_once __DIR__ . '/../../../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title text-warning"><i class="fas fa-edit mr-2"></i>Edit Claim Nota: <strong><?= sanitize($claim['claim_number']) ?></strong></h3>
        <a href="view.php?id=<?= $id ?>" class="btn btn-secondary btn-sm float-right"><i class="fas fa-arrow-left mr-1"></i> Batal</a>
    </div>

    <form method="POST" enctype="multipart/form-data" id="claimForm">
        <?= csrfField() ?>
        <div class="card-body bg-light">
            <!-- Header Section -->
            <h5 class="mb-3 text-secondary text-uppercase" style="font-size:14px;letter-spacing:1px;font-weight:600;">1. Informasi Klaim</h5>
            <div class="row">
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Karyawan <span class="text-danger">*</span></label>
                        <?php $isManual = is_null($claim['employee_id']); ?>
                        <div class="custom-control custom-switch mb-2">
                            <input type="checkbox" class="custom-control-input" id="switchManualEmployee" name="employee_option" value="manual" <?= $isManual ? 'checked' : '' ?>>
                            <label class="custom-control-label font-weight-normal text-muted" for="switchManualEmployee">Input nama manual (bukan akun sistem)</label>
                        </div>
                        
                        <!-- Dropdown select -->
                        <div id="employeeSelectWrapper" class="<?= $isManual ? 'd-none' : '' ?>">
                            <select name="employee_id" id="employee_id" class="form-control select2" style="width: 100%;">
                                <option value="">-- Pilih Karyawan --</option>
                                <?php foreach ($users as $usr): ?>
                                    <option value="<?= $usr['id'] ?>" <?= ($claim['employee_id'] == $usr['id']) ? 'selected' : '' ?>>
                                        <?= sanitize($usr['full_name']) ?><?= $usr['employee_code'] ? ' (' . sanitize($usr['employee_code']) . ')' : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- Free text input -->
                        <div id="employeeInputWrapper" class="<?= $isManual ? '' : 'd-none' ?>">
                            <input type="text" name="employee_name" id="employee_name" class="form-control" placeholder="Ketik nama karyawan..." value="<?= htmlspecialchars($claim['employee_name']) ?>">
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="form-group">
                        <label>Diklaim Ke Perusahaan <span class="text-danger">*</span></label>
                        <select name="company_id" class="form-control select2" required style="width: 100%;">
                            <option value="">-- Pilih Perusahaan --</option>
                            <?php foreach ($companies as $comp): ?>
                                <option value="<?= $comp['id'] ?>" <?= ($claim['company_id'] == $comp['id']) ? 'selected' : '' ?>><?= sanitize($comp['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="form-group">
                        <label>Tanggal Klaim <span class="text-danger">*</span></label>
                        <input type="date" name="claim_date" class="form-control" value="<?= htmlspecialchars($claim['claim_date']) ?>" required>
                    </div>
                </div>

                <div class="col-md-12">
                    <div class="form-group">
                        <label>Catatan Klaim</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="Catatan opsional..."><?= htmlspecialchars($claim['notes'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>

            <hr class="my-4">

            <!-- Detail Items Section -->
            <h5 class="mb-3 text-secondary text-uppercase d-flex justify-content-between align-items-center" style="font-size:14px;letter-spacing:1px;font-weight:600;">
                2. Rincian Nota & Pengeluaran
                <button type="button" class="btn btn-sm btn-info" id="btnAddRow"><i class="fas fa-plus"></i> Tambah Baris</button>
            </h5>

            <div class="table-responsive">
                <table class="table table-bordered table-sm" id="itemsTable">
                    <thead class="thead-dark">
                        <tr>
                            <th width="12%">Tanggal Nota</th>
                            <th width="18%">Pilih Proyek (Opsional)</th>
                            <th width="18%">Kelompok Pengeluaran <span class="text-danger">*</span></th>
                            <th width="20%">Nama Item/Deskripsi <span class="text-danger">*</span></th>
                            <th width="8%">Pcs (Qty)</th>
                            <th width="12%">Harga Satuan</th>
                            <th width="12%">Jumlah</th>
                            <th width="10%">Foto Nota</th>
                            <th width="5%" class="text-center"><i class="fas fa-trash"></i></th>
                        </tr>
                    </thead>
                    <tbody id="itemsBody">
                        <?php foreach ($claimItems as $index => $item): ?>
                            <tr class="item-row">
                                <td>
                                    <input type="date" name="item_date[]" class="form-control form-control-sm item-date-input" value="<?= htmlspecialchars($item['item_date']) ?>" required>
                                </td>
                                <td>
                                    <select name="project_id[]" class="form-control form-control-sm project-select">
                                        <option value="">-- Tanpa Proyek --</option>
                                        <?php foreach ($projects as $proj): ?>
                                            <option value="<?= $proj['id'] ?>" <?= ($item['project_id'] == $proj['id']) ? 'selected' : '' ?>><?= sanitize($proj['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td>
                                    <input type="text" name="group_name[]" class="form-control form-control-sm group-input" value="<?= htmlspecialchars($item['group_name']) ?>" list="groupSuggestions" required>
                                </td>
                                <td>
                                    <input type="text" name="item_name[]" class="form-control form-control-sm" value="<?= htmlspecialchars($item['item_name']) ?>" required>
                                </td>
                                <td>
                                    <input type="text" name="qty[]" class="form-control form-control-sm input-number qty-input text-center" value="<?= (float)$item['qty'] ?>" required>
                                </td>
                                <td>
                                    <input type="text" name="price[]" class="form-control form-control-sm input-rupiah price-input text-right" value="<?= number_format($item['price'], 0, ',', '.') ?>" required>
                                </td>
                                <td class="text-right text-bold text-muted amount-column" style="vertical-align:middle; padding-right:10px;">
                                    Rp <?= number_format($item['amount'], 0, ',', '.') ?>
                                </td>
                                <td>
                                    <input type="hidden" name="existing_photo[]" value="<?= htmlspecialchars($item['receipt_photo'] ?? '') ?>">
                                    <?php if ($item['receipt_photo']): ?>
                                        <div class="mb-1" style="font-size:11px;">
                                            <a href="<?= APP_URL ?>/assets/uploads/receipts/<?= $item['receipt_photo'] ?>" target="_blank" class="text-info">
                                                <i class="fas fa-image"></i> Lihat Nota
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                    <input type="file" name="photo[]" class="form-control-file photo-input" accept="image/*,application/pdf">
                                </td>
                                <td class="text-center" style="vertical-align:middle;">
                                    <button type="button" class="btn btn-danger btn-sm btn-remove-row"><i class="fas fa-times"></i></button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="row mt-3">
                <div class="col-md-6 offset-md-6 text-right">
                    <h4>Grand Total: <span class="text-danger font-weight-bold" id="grandTotalDisplay">Rp <?= number_format($claim['total_amount'], 0, ',', '.') ?></span></h4>
                </div>
            </div>

        </div>

        <div class="card-footer bg-white text-right">
            <a href="view.php?id=<?= $id ?>" class="btn btn-default mr-2"><i class="fas fa-times mr-1"></i> Batal</a>
            <button type="button" class="btn btn-warning" onclick="submitForm('pending')"><i class="fas fa-save mr-1"></i> Simpan Perubahan</button>
        </div>
    </form>
</div>

<!-- Template Row for Items -->
<template id="rowTemplate">
    <tr class="item-row">
        <td>
            <input type="date" name="item_date[]" class="form-control form-control-sm item-date-input" value="<?= date('Y-m-d') ?>" required>
        </td>
        <td>
            <select name="project_id[]" class="form-control form-control-sm project-select">
                <option value="">-- Tanpa Proyek --</option>
                <?php foreach ($projects as $proj): ?>
                    <option value="<?= $proj['id'] ?>"><?= sanitize($proj['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </td>
        <td>
            <input type="text" name="group_name[]" class="form-control form-control-sm group-input" value="Money change" placeholder="Cth: Money change / Developer" list="groupSuggestions" required>
        </td>
        <td>
            <input type="text" name="item_name[]" class="form-control form-control-sm" placeholder="Deskripsi barang..." required>
        </td>
        <td>
            <input type="text" name="qty[]" class="form-control form-control-sm input-number qty-input text-center" value="1" required>
        </td>
        <td>
            <input type="text" name="price[]" class="form-control form-control-sm input-rupiah price-input text-right" value="0" required>
        </td>
        <td class="text-right text-bold text-muted amount-column" style="vertical-align:middle; padding-right:10px;">
            Rp 0
        </td>
        <td>
            <input type="hidden" name="existing_photo[]" value="">
            <input type="file" name="photo[]" class="form-control-file photo-input" accept="image/*,application/pdf">
        </td>
        <td class="text-center" style="vertical-align:middle;">
            <button type="button" class="btn btn-danger btn-sm btn-remove-row"><i class="fas fa-times"></i></button>
        </td>
    </tr>
</template>

<datalist id="groupSuggestions">
    <?php foreach ($existingGroups as $grp): ?>
        <option value="<?= htmlspecialchars($grp) ?>"></option>
    <?php endforeach; ?>
</datalist>

<?php
$extraJS = <<<'JS'
<script>
$(document).ready(function() {
    $('.select2').select2({
        theme: 'bootstrap4',
        width: '100%'
    });

    // Employee Selection toggle switch logic
    $('#switchManualEmployee').on('change', function() {
        if ($(this).is(':checked')) {
            $('#employeeSelectWrapper').addClass('d-none');
            $('#employee_id').val('').trigger('change').prop('required', false);
            
            $('#employeeInputWrapper').removeClass('d-none');
            $('#employee_name').prop('required', true);
        } else {
            $('#employeeInputWrapper').addClass('d-none');
            $('#employee_name').val('').prop('required', false);
            
            $('#employeeSelectWrapper').removeClass('d-none');
            $('#employee_id').prop('required', true);
        }
    });

    // Dynamic Row Logic
    var template = $('#rowTemplate').html();
    var tbody = $('#itemsBody');

    // If table is empty on load, add one row
    if (tbody.find('.item-row').length === 0) {
        addRow();
    }

    function addRow() {
        var html = $(template);
        tbody.append(html);
        calculateRowAmount(html);
    }

    $('#btnAddRow').on('click', function() {
        addRow();
    });

    // Remove row logic
    tbody.on('click', '.btn-remove-row', function() {
        if (tbody.find('.item-row').length > 1) {
            $(this).closest('tr').remove();
            calculateGrandTotal();
        } else {
            showError('Harus ada minimal 1 baris item pengeluaran.');
        }
    });

    // On changing Project in a row, auto-fill the Group Name field
    tbody.on('change', '.project-select', function() {
        var row = $(this).closest('tr');
        var selectedText = $(this).find('option:selected').text();
        var groupInput = row.find('.group-input');
        
        if ($(this).val() !== '') {
            groupInput.val(selectedText);
        } else {
            groupInput.val('Money change');
        }
    });

    // Recalculate amounts on Qty or Price inputs
    tbody.on('input', '.qty-input, .price-input', function() {
        var row = $(this).closest('tr');
        calculateRowAmount(row);
    });

    function calculateRowAmount(row) {
        var qty = parseQtyString(row.find('.qty-input').val()) || 0;
        var price = parseRupiahString(row.find('.price-input').val()) || 0;
        var amount = qty * price;
        
        row.find('.amount-column').text(formatRupiahJS(amount));
        calculateGrandTotal();
    }

    function calculateGrandTotal() {
        var grandTotal = 0;
        tbody.find('.item-row').each(function() {
            var qty = parseQtyString($(this).find('.qty-input').val()) || 0;
            var price = parseRupiahString($(this).find('.price-input').val()) || 0;
            grandTotal += (qty * price);
        });
        $('#grandTotalDisplay').text(formatRupiahJS(grandTotal));
    }

    function parseQtyString(str) {
        if (!str) return 0;
        var clean = str.toString().replace(/,/g, '.');
        clean = clean.replace(/[^0-9.-]/g, '');
        return parseFloat(clean) || 0;
    }

    function parseRupiahString(str) {
        if (!str) return 0;
        return parseFloat(str.replace(/[^0-9]/g, '')) || 0;
    }

    function formatRupiahJS(num) {
        return 'Rp ' + num.toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
    }
});

function submitForm(status) {
    var form = $('#claimForm');
    
    // Validations
    if (!$('#switchManualEmployee').is(':checked') && !$('#employee_id').val()) {
        showError('Pilih Karyawan terlebih dahulu.');
        return;
    }
    if ($('#switchManualEmployee').is(':checked') && !$('#employee_name').val().trim()) {
        showError('Ketik Nama Karyawan terlebih dahulu.');
        return;
    }
    if (!$('select[name="company_id"]').val()) {
        showError('Pilih Perusahaan terlebih dahulu.');
        return;
    }

    var hasEmptyItem = false;
    $('input[name="item_name[]"]').each(function() {
        if (!$(this).val().trim()) {
            hasEmptyItem = true;
        }
    });

    if (hasEmptyItem) {
        showError('Semua baris Item/Deskripsi wajib diisi.');
        return;
    }

    form.submit();
}
</script>
JS;
require_once __DIR__ . '/../../../includes/footer.php';
?>
