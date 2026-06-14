<?php
/**
 * Finance - Claim Nota Create
 */
require_once __DIR__ . '/../../../includes/auth.php';
requirePermission('claim_nota', 'create');

$pageTitle = 'Input Claim Nota Baru';
$breadcrumbs = [
    ['label' => 'Finance', 'url' => '#'],
    ['label' => 'Claim Nota', 'url' => APP_URL . '/modules/finance/claim_nota/index.php'],
    ['label' => 'Baru']
];

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $employeeOption = $_POST['employee_option'] ?? 'select';
    $employeeId     = $_POST['employee_id'] ?? null;
    $employeeName   = trim($_POST['employee_name'] ?? '');
    $companyId      = $_POST['company_id'] ?? '';
    $claimDate      = $_POST['claim_date'] ?? date('Y-m-d');
    $notes          = trim($_POST['notes'] ?? '');
    $status         = $_POST['status'] ?? 'pending';

    // Item details
    $itemDates  = $_POST['item_date'] ?? [];
    $projectIds = $_POST['project_id'] ?? [];
    $groupNames = $_POST['group_name'] ?? [];
    $itemNames  = $_POST['item_name'] ?? [];
    $qtys       = $_POST['qty'] ?? [];
    $prices     = $_POST['price'] ?? [];

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

            // Fetch company details to generate abbreviation for Claim Number
            $compStmt = $pdo->prepare("SELECT name FROM companies WHERE id = ?");
            $compStmt->execute([$companyId]);
            $companyName = $compStmt->fetchColumn() ?: 'GEN';
            $companyAbbr = generateAbbreviation($companyName);

            // Generate claim number (e.g. CLM-MKM-26-0001)
            $claimNumber = generateDocNumber($pdo, 'CLM', $companyAbbr);

            // Insert Header
            $stmtHeader = $pdo->prepare("
                INSERT INTO nota_claims (claim_number, company_id, employee_name, employee_id, claim_date, total_amount, status, notes, created_by)
                VALUES (?, ?, ?, ?, ?, 0.00, ?, ?, ?)
            ");
            $stmtHeader->execute([$claimNumber, $companyId, $employeeName, $employeeId, $claimDate, $status, $notes, $user['id']]);
            $claimId = $pdo->lastInsertId();

            $totalClaimAmount = 0;

            // Insert items
            $stmtItem = $pdo->prepare("
                INSERT INTO nota_claim_items (claim_id, item_date, project_id, group_name, item_name, qty, price, amount, receipt_photo)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            for ($i = 0; $i < count($itemNames); $i++) {
                $i_date  = $itemDates[$i] ?? $claimDate;
                $i_proj  = !empty($projectIds[$i]) ? $projectIds[$i] : null;
                $i_group = !empty($groupNames[$i]) ? trim($groupNames[$i]) : 'Money change';
                $i_name  = trim($itemNames[$i] ?? '');
                $i_qty   = parseRupiah($qtys[$i] ?? '1');
                $i_price = parseRupiah($prices[$i] ?? '0');
                $i_amount = $i_qty * $i_price;

                if (empty($i_name)) {
                    continue; // Skip empty rows
                }

                // Handle Photo Upload
                $photoFilename = null;
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
                    } else {
                        throw new Exception("Gagal mengunggah foto nota pada baris " . ($i + 1) . ": " . $uploadResult['message']);
                    }
                }

                $stmtItem->execute([
                    $claimId,
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
            $updateHeader->execute([$totalClaimAmount, $claimId]);

            $pdo->commit();

            setFlash('success', "Klaim Nota $claimNumber berhasil disimpan.");
            header('Location: ' . APP_URL . '/modules/finance/claim_nota/index.php');
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = "Terjadi kesalahan: " . $e->getMessage();
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
        <h3 class="card-title text-primary"><i class="fas fa-file-invoice-dollar mr-2"></i>Form Input Claim Nota</h3>
        <a href="index.php" class="btn btn-secondary btn-sm float-right"><i class="fas fa-arrow-left mr-1"></i> Kembali</a>
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
                        <div class="custom-control custom-switch mb-2">
                            <input type="checkbox" class="custom-control-input" id="switchManualEmployee" name="employee_option" value="manual" <?= (isset($_POST['employee_option']) && $_POST['employee_option'] === 'manual') ? 'checked' : '' ?>>
                            <label class="custom-control-label font-weight-normal text-muted" for="switchManualEmployee">Input nama manual (bukan akun sistem)</label>
                        </div>
                        
                        <!-- Dropdown select -->
                        <div id="employeeSelectWrapper">
                            <select name="employee_id" id="employee_id" class="form-control select2" style="width: 100%;">
                                <option value="">-- Pilih Karyawan --</option>
                                <?php foreach ($users as $usr): ?>
                                    <option value="<?= $usr['id'] ?>" <?= (($_POST['employee_id'] ?? '') == $usr['id']) ? 'selected' : '' ?>>
                                        <?= sanitize($usr['full_name']) ?><?= $usr['employee_code'] ? ' (' . sanitize($usr['employee_code']) . ')' : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- Free text input -->
                        <div id="employeeInputWrapper" class="d-none">
                            <input type="text" name="employee_name" id="employee_name" class="form-control" placeholder="Ketik nama karyawan..." value="<?= htmlspecialchars($_POST['employee_name'] ?? '') ?>">
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="form-group">
                        <label>Diklaim Ke Perusahaan <span class="text-danger">*</span></label>
                        <select name="company_id" class="form-control select2" required style="width: 100%;">
                            <option value="">-- Pilih Perusahaan --</option>
                            <?php foreach ($companies as $comp): ?>
                                <option value="<?= $comp['id'] ?>" <?= (($_POST['company_id'] ?? '') == $comp['id']) ? 'selected' : '' ?>><?= sanitize($comp['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="form-group">
                        <label>Tanggal Klaim <span class="text-danger">*</span></label>
                        <input type="date" name="claim_date" class="form-control" value="<?= htmlspecialchars($_POST['claim_date'] ?? date('Y-m-d')) ?>" required>
                    </div>
                </div>

                <div class="col-md-12">
                    <div class="form-group">
                        <label>Catatan Klaim</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="Catatan opsional mengenai reimburse ini..."><?= htmlspecialchars($_POST['notes'] ?? '') ?></textarea>
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
                        <!-- Rows injected via JS -->
                    </tbody>
                </table>
            </div>

            <div class="row mt-3">
                <div class="col-md-6 offset-md-6 text-right">
                    <h4>Grand Total: <span class="text-danger font-weight-bold" id="grandTotalDisplay">Rp 0</span></h4>
                </div>
            </div>

        </div>

        <div class="card-footer bg-white text-right">
            <input type="hidden" name="status" id="formStatus" value="pending">
            <a href="index.php" class="btn btn-default mr-2"><i class="fas fa-times mr-1"></i> Batal</a>
            <button type="button" class="btn btn-primary" onclick="submitForm('pending')"><i class="fas fa-save mr-1"></i> Simpan Klaim</button>
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
    // Trigger switch on load in case of post validation failure
    $('#switchManualEmployee').trigger('change');

    // Dynamic Row Logic
    var template = $('#rowTemplate').html();
    var tbody = $('#itemsBody');
    var rowIndex = 0;

    function addRow() {
        var html = $(template);
        
        // Give unique name to photo input so that index matches PHP side uploads if we manipulate rows,
        // but since we rely on array index, we keep it as photo[] or align them.
        // Actually, photo[] works perfectly if we do NOT remove files from the request.
        
        tbody.append(html);
        calculateRowAmount(html);
        rowIndex++;
    }

    // Add first row by default
    addRow();

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
        var qty = parseRupiahString(row.find('.qty-input').val()) || 0;
        var price = parseRupiahString(row.find('.price-input').val()) || 0;
        var amount = qty * price;
        
        row.find('.amount-column').text(formatRupiahJS(amount));
        calculateGrandTotal();
    }

    function calculateGrandTotal() {
        var grandTotal = 0;
        tbody.find('.item-row').each(function() {
            var qty = parseRupiahString($(this).find('.qty-input').val()) || 0;
            var price = parseRupiahString($(this).find('.price-input').val()) || 0;
            grandTotal += (qty * price);
        });
        $('#grandTotalDisplay').text(formatRupiahJS(grandTotal));
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

    $('#formStatus').val(status);
    form.submit();
}
</script>
JS;
require_once __DIR__ . '/../../../includes/footer.php';
?>
