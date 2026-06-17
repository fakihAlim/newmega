<?php
/**
 * Sales - Edit Quotation
 */
require_once __DIR__ . '/../../../includes/auth.php';
requirePermission('quotation_create');

$id = $_GET['id'] ?? 0;

// Fetch Quotation Header
$stmt = $pdo->prepare("SELECT * FROM quotations WHERE id = ?");
$stmt->execute([$id]);
$q = $stmt->fetch();

if (!$q) {
    setFlash('danger', 'Quotation tidak ditemukan.');
    header('Location: index.php');
    exit;
}

// Logic: Only Draft can be edited
if ($q['status'] !== 'draft') {
    setFlash('danger', 'Hanya quotation berstatus draft yang dapat diubah.');
    header('Location: index.php');
    exit;
}

$user = getCurrentUser();

// Authorization: Only creator or super admin
if ($user['role'] !== 'super_admin' && $q['created_by'] != $user['id']) {
    setFlash('danger', 'Anda tidak memiliki akses untuk mengubah quotation ini.');
    header('Location: index.php');
    exit;
}

// Fetch Dependencies
$companies  = $pdo->query("SELECT id, name, is_default FROM companies ORDER BY name")->fetchAll();
$customers  = $pdo->query("SELECT id, company_name as name, abbreviation FROM customers WHERE is_active = 1 ORDER BY company_name")->fetchAll();
$projects   = $pdo->query("SELECT id, name, customer_id FROM projects WHERE status IN ('planning','active') OR id = ".($q['project_id'] ?: 0)." ORDER BY name")->fetchAll();

// Fetch Items
$stmtItems = $pdo->prepare("SELECT * FROM quotation_items WHERE quotation_id = ? ORDER BY id ASC");
$stmtItems->execute([$id]);
$qItems = $stmtItems->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $companyId     = $_POST['company_id'] ?? null;
    $customerId    = $_POST['customer_id'] ?? null;
    $projectId     = $_POST['project_id'] ?: null;
    $quotationDate = $_POST['quotation_date'] ?? date('Y-m-d');
    $validFrom     = $_POST['valid_from'] ?: null;
    $validUntil    = $_POST['valid_until'] ?: null;
    $comments      = trim($_POST['comments'] ?? '');
    $termsCond     = trim($_POST['terms_and_conditions'] ?? '');
    $action        = $_POST['action'] ?? 'draft';
    
    // Financials
    $subtotal  = parseRupiah($_POST['subtotal'] ?? 0);
    $discount  = parseRupiah($_POST['discount_nominal'] ?? 0);
    $tax       = parseRupiah($_POST['tax_nominal'] ?? 0);
    $shipping  = parseRupiah($_POST['shipping_cost'] ?? 0);
    $grandTotal = parseRupiah($_POST['grand_total'] ?? 0);
    
    // Items
    $descriptions   = $_POST['description'] ?? [];
    $specifications = $_POST['type_specification'] ?? [];
    $qtys           = $_POST['qty'] ?? [];
    $uoms           = $_POST['uom'] ?? [];
    $matPrices      = $_POST['material_unit_price'] ?? [];
    $manPrices      = $_POST['manpower_unit_price'] ?? [];
    $amounts        = $_POST['amount'] ?? [];
    
    $status = ($action === 'submit') ? 'pending' : 'draft';
    
    $errors = [];
    if (empty($companyId)) $errors[] = 'Perusahaan Header harus dipilih.';
    if (empty($customerId)) $errors[] = 'Customer harus dipilih.';
    if (empty($descriptions) || count($descriptions) === 0) $errors[] = 'Minimal 1 item pekerjaan.';
    
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            // Delete old items
            $pdo->prepare("DELETE FROM quotation_items WHERE quotation_id = ?")->execute([$id]);
            
            // Update Header
            $stmt = $pdo->prepare("
                UPDATE quotations SET 
                    company_id = ?, customer_id = ?, project_id = ?, quotation_date = ?, 
                    valid_from = ?, valid_until = ?, comments = ?, terms_and_conditions = ?, 
                    subtotal = ?, shipping = ?, tax = ?, discount = ?, total = ?, status = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $companyId, $customerId, $projectId, $quotationDate, 
                $validFrom, $validUntil, $comments, $termsCond, 
                $subtotal, $shipping, $tax, $discount, $grandTotal, $status, $id
            ]);
            
            // Insert New Items
            $insertItem = $pdo->prepare("
                INSERT INTO quotation_items (quotation_id, description, type_specification, qty, uom, material_unit_price, material_total, manpower_unit_price, manpower_total, amount)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            for ($i = 0; $i < count($descriptions); $i++) {
                $desc = trim($descriptions[$i]);
                $spec = trim($specifications[$i] ?? '');
                $qty = parseRupiah($qtys[$i] ?? 0);
                $uom = $uoms[$i] ?? '';
                $matP = parseRupiah($matPrices[$i] ?? 0);
                $manP = parseRupiah($manPrices[$i] ?? 0);
                $matT = $qty * $matP;
                $manT = $qty * $manP;
                $amt = parseRupiah($amounts[$i] ?? 0);
                
                if ($desc && $qty > 0) {
                    $insertItem->execute([$id, $desc, $spec, $qty, $uom, $matP, $matT, $manP, $manT, $amt]);
                }
            }
            
            $pdo->commit();
            $msg = $status === 'pending' ? "Quotation {$q['quotation_no']} berhasil di-submit." : "Perubahan Quotation {$q['quotation_no']} berhasil disimpan.";
            setFlash('success', $msg);
            header('Location: index.php');
            exit;
            
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log('[NEWMEGA] ' . $e->getMessage());
            setFlash('danger', 'Gagal memperbarui Quotation. Terjadi kesalahan sistem.');
        }
    }
}

$pageTitle = 'Edit Quotation: ' . sanitize($q['quotation_no']);
$breadcrumbs = [
    ['label' => 'Sales', 'url' => '#'],
    ['label' => 'Quotation', 'url' => 'index.php'],
    ['label' => 'Edit']
];

require_once __DIR__ . '/../../../includes/header.php';
?>

<div class="card card-outline card-warning">
    <div class="card-header">
        <h3 class="card-title text-warning font-weight-bold"><i class="fas fa-edit mr-2"></i> Form Edit Quotation</h3>
        <a href="index.php" class="btn btn-secondary btn-sm float-right"><i class="fas fa-arrow-left mr-1"></i> Kembali</a>
    </div>
    
    <form method="POST" id="qForm">
        <div class="card-body bg-light">
            <div class="row">
                <div class="col-md-6 border-right">
                    <h5 class="mb-3 text-secondary text-uppercase font-weight-bold" style="font-size:12px;letter-spacing:1px;">1. Informasi Customer</h5>
                    
                    <div class="form-group row">
                        <label class="col-sm-4 col-form-label">Customer <span class="text-danger">*</span></label>
                        <div class="col-sm-8">
                            <select name="customer_id" id="customer_id" class="form-control select2" required>
                                <option value="">-- Pilih Customer --</option>
                                <?php foreach ($customers as $c): ?>
                                    <option value="<?= $c['id'] ?>" <?= $q['customer_id'] == $c['id'] ? 'selected' : '' ?>><?= sanitize($c['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group row">
                        <label class="col-sm-4 col-form-label">Proyek <small class="text-muted">(opsi)</small></label>
                        <div class="col-sm-8">
                            <select name="project_id" id="project_id" class="form-control select2">
                                <option value="">-- Tanpa Proyek --</option>
                                <?php foreach ($projects as $p): ?>
                                    <option value="<?= $p['id'] ?>" data-customer-id="<?= $p['customer_id'] ?>" <?= $q['project_id'] == $p['id'] ? 'selected' : '' ?>><?= sanitize($p['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group row">
                        <label class="col-sm-4 col-form-label">Catatan (Pesan)</label>
                        <div class="col-sm-8">
                            <textarea name="comments" class="form-control" rows="2"><?= sanitize($q['comments']) ?></textarea>
                        </div>
                    </div>
                    
                    <div class="form-group row">
                        <label class="col-sm-4 col-form-label">Syarat & Ketentuan</label>
                        <div class="col-sm-8">
                            <textarea name="terms_and_conditions" class="form-control" rows="3"><?= sanitize($q['terms_and_conditions']) ?></textarea>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <h5 class="mb-3 text-secondary text-uppercase font-weight-bold" style="font-size:12px;letter-spacing:1px;">2. Informasi Dokumen</h5>
                    
                    <div class="form-group row">
                        <label class="col-sm-4 col-form-label">Perusahaan (Header) <span class="text-danger">*</span></label>
                        <div class="col-sm-8">
                            <select name="company_id" class="form-control" required>
                                <?php foreach ($companies as $co): ?>
                                    <option value="<?= $co['id'] ?>" <?= $q['company_id'] == $co['id'] ? 'selected' : '' ?>><?= sanitize($co['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group row">
                        <label class="col-sm-4 col-form-label">Tanggal Quotation <span class="text-danger">*</span></label>
                        <div class="col-sm-8">
                            <input type="date" name="quotation_date" class="form-control" value="<?= sanitize($q['quotation_date']) ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-group row">
                        <label class="col-sm-4 col-form-label">Berlaku Dari</label>
                        <div class="col-sm-8">
                            <input type="date" name="valid_from" class="form-control" value="<?= sanitize($q['valid_from']) ?>">
                        </div>
                    </div>
                    
                    <div class="form-group row">
                        <label class="col-sm-4 col-form-label">Berlaku Sampai</label>
                        <div class="col-sm-8">
                            <input type="date" name="valid_until" class="form-control" value="<?= sanitize($q['valid_until']) ?>">
                        </div>
                    </div>
                </div>
            </div>
            
            <hr class="my-3">
            
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="text-secondary text-uppercase font-weight-bold m-0" style="font-size:12px;letter-spacing:1px;">3. Daftar Pekerjaan / Barang</h5>
                <button type="button" class="btn btn-sm btn-primary" id="btnAddRow"><i class="fas fa-plus mr-1"></i> Tambah Baris</button>
            </div>
            
            <div class="table-responsive mb-3">
                <table class="table table-bordered table-sm" id="qItemsTable" style="font-size:13px;">
                    <thead class="bg-dark text-white">
                        <tr>
                            <th width="25%">Deskripsi Pekerjaan</th>
                            <th width="10%">Spesifikasi</th>
                            <th width="7%" class="text-center">Qty</th>
                            <th width="6%">Satuan</th>
                            <th width="13%" class="text-right">Hrg Material</th>
                            <th width="13%" class="text-right">Hrg Manpower</th>
                            <th width="15%" class="text-right">Amount</th>
                            <th width="5%" class="text-center"><i class="fas fa-trash"></i></th>
                        </tr>
                    </thead>
                    <tbody id="qItemsBody">
                        <?php foreach ($qItems as $item): ?>
                        <tr class="q-row">
                            <td><input type="text" name="description[]" class="form-control form-control-sm" required value="<?= sanitize($item['description']) ?>"></td>
                            <td><input type="text" name="type_specification[]" class="form-control form-control-sm" value="<?= sanitize($item['type_specification']) ?>"></td>
                            <td><input type="text" name="qty[]" class="form-control form-control-sm text-center input-number col-qty" value="<?= (float)$item['qty'] ?>" required></td>
                            <td><input type="text" name="uom[]" class="form-control form-control-sm text-center" value="<?= sanitize($item['uom']) ?>"></td>
                            <td><input type="text" name="material_unit_price[]" class="form-control form-control-sm text-right input-number col-mat-price" value="<?= number_format($item['material_unit_price'], 0, ',', '.') ?>"></td>
                            <td><input type="text" name="manpower_unit_price[]" class="form-control form-control-sm text-right input-number col-man-price" value="<?= number_format($item['manpower_unit_price'], 0, ',', '.') ?>"></td>
                            <td><input type="text" name="amount[]" class="form-control form-control-sm text-right font-weight-bold col-amount" value="<?= number_format($item['amount'], 0, ',', '.') ?>" readonly></td>
                            <td class="text-center"><button type="button" class="btn btn-danger btn-sm btn-remove-row"><i class="fas fa-times"></i></button></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="row">
                <div class="col-md-6 offset-md-6">
                    <table class="table table-sm table-borderless font-weight-bold text-right" style="font-size:14px;">
                        <tr>
                            <td width="40%">Subtotal</td>
                            <td><input type="text" name="subtotal" id="calc_subtotal" class="form-control text-right form-control-sm font-weight-bold" readonly value="<?= number_format($q['subtotal'], 0, ',', '.') ?>"></td>
                        </tr>
                        <tr>
                            <td>Diskon</td>
                            <td>
                                <div class="input-group input-group-sm">
                                    <input type="number" id="calc_discount_pct" class="form-control text-center" placeholder="%" min="0" max="100" step="0.01">
                                    <div class="input-group-prepend input-group-append"><span class="input-group-text">% | Rp</span></div>
                                    <input type="text" name="discount_nominal" id="calc_discount_nom" class="form-control text-right mask-rupiah" value="<?= number_format($q['discount'], 0, ',', '.') ?>">
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td>Pajak (PPN)</td>
                            <td>
                                <div class="input-group input-group-sm">
                                    <?php $taxPct = ($q['subtotal'] - $q['discount']) > 0 ? round(($q['tax'] / ($q['subtotal'] - $q['discount'])) * 100, 2) : 11; ?>
                                    <input type="number" id="calc_tax_pct" class="form-control text-center" placeholder="%" min="0" max="100" step="0.01" value="<?= $taxPct ?>">
                                    <div class="input-group-prepend input-group-append"><span class="input-group-text">% | Rp</span></div>
                                    <input type="text" name="tax_nominal" id="calc_tax_nom" class="form-control text-right mask-rupiah" value="<?= number_format($q['tax'], 0, ',', '.') ?>">
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td>Ongkos Kirim</td>
                            <td><input type="text" name="shipping_cost" id="calc_shipping" class="form-control text-right form-control-sm mask-rupiah" value="<?= number_format($q['shipping'], 0, ',', '.') ?>"></td>
                        </tr>
                        <tr style="border-top: 2px solid #ccc;">
                            <td class="text-danger" style="font-size:16px;">GRAND TOTAL</td>
                            <td><input type="text" name="grand_total" id="calc_grandtotal" class="form-control text-right text-danger font-weight-bold form-control-lg" readonly style="font-size:20px;background-color:#fff8f8;" value="<?= number_format($q['total'], 0, ',', '.') ?>"></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
        
        <div class="card-footer bg-white text-right">
            <input type="hidden" name="action" id="formAction" value="draft">
            <button type="button" class="btn btn-secondary mr-2" onclick="submitForm('draft')"><i class="fas fa-save mr-1"></i> Perbarui Draft</button>
            <button type="button" class="btn btn-success" onclick="submitForm('submit')"><i class="fas fa-paper-plane mr-1"></i> Submit Quotation</button>
        </div>
    </form>
</div>

<?php
$extraJS = <<<'JS'
<script>
function parseIdr(str) {
    if (!str) return 0;
    if (typeof str === 'number') return str;
    return parseFloat(str.toString().replace(/[^0-9]/g, '')) || 0;
}
function formatIdr(num) { return num.toLocaleString('id-ID'); }

function calculateRow(row) {
    var qty = parseIdr(row.find('.col-qty').val());
    var matP = parseIdr(row.find('.col-mat-price').val());
    var manP = parseIdr(row.find('.col-man-price').val());
    var amount = qty * (matP + manP);
    row.find('.col-amount').val(formatIdr(amount));
    calculateGrandTotal();
}

function calculateGrandTotal() {
    var subtotal = 0;
    $('.col-amount').each(function() { subtotal += parseIdr($(this).val()); });
    $('#calc_subtotal').val(formatIdr(subtotal));
    
    var discPct = parseFloat($('#calc_discount_pct').val()) || 0;
    var taxPct = parseFloat($('#calc_tax_pct').val()) || 0;
    
    if (discPct > 0) { $('#calc_discount_nom').val(formatIdr((subtotal * discPct) / 100)); }
    var discNom = parseIdr($('#calc_discount_nom').val());
    var dpp = subtotal - discNom;
    if (taxPct > 0 && taxPct < 100) { 
        var grossUpTotal = dpp / ((100 - taxPct) / 100);
        var taxAmount = Math.round(grossUpTotal - dpp);
        $('#calc_tax_nom').val(formatIdr(taxAmount)); 
    } else {
        $('#calc_tax_nom').val('0');
    }
    var taxNom = parseIdr($('#calc_tax_nom').val());
    var shipping = parseIdr($('#calc_shipping').val());
    $('#calc_grandtotal').val(formatIdr(dpp + taxNom + shipping));
}

$(document).ready(function() {
    initSelect2('.select2');
    calculateGrandTotal(); // Initial calc

    // Auto-select customer based on selected project
    $('#project_id').on('change', function() {
        var customerId = $(this).find('option:selected').data('customer-id');
        if (customerId) {
            $('#customer_id').val(customerId).trigger('change');
        }
    });

    $('#calc_discount_pct, #calc_tax_pct').on('input', calculateGrandTotal);
    $('#calc_discount_nom, #calc_tax_nom, #calc_shipping').on('input', function() {
        $(this).val(formatIdr(parseIdr($(this).val())));
        calculateGrandTotal();
    });
    
    $('#btnAddRow').on('click', function() {
        var tbody = $('#qItemsBody');
        if (tbody.find('.empty-row').length > 0) tbody.empty();
        var html = `
        <tr class="q-row">
            <td><input type="text" name="description[]" class="form-control form-control-sm" required placeholder="Nama pekerjaan/material"></td>
            <td><input type="text" name="type_specification[]" class="form-control form-control-sm" placeholder="Spek"></td>
            <td><input type="text" name="qty[]" class="form-control form-control-sm text-center input-number col-qty" value="1" required></td>
            <td><input type="text" name="uom[]" class="form-control form-control-sm text-center" value="ls"></td>
            <td><input type="text" name="material_unit_price[]" class="form-control form-control-sm text-right input-number col-mat-price" value="0"></td>
            <td><input type="text" name="manpower_unit_price[]" class="form-control form-control-sm text-right input-number col-man-price" value="0"></td>
            <td><input type="text" name="amount[]" class="form-control form-control-sm text-right font-weight-bold col-amount" value="0" readonly></td>
            <td class="text-center"><button type="button" class="btn btn-danger btn-sm btn-remove-row"><i class="fas fa-times"></i></button></td>
        </tr>`;
        tbody.append(html);
    });
    
    $('#qItemsBody').on('input', '.col-qty, .col-mat-price, .col-man-price', function() {
        var v = parseIdr($(this).val());
        if (!$(this).hasClass('col-qty')) $(this).val(formatIdr(v));
        calculateRow($(this).closest('tr'));
    });
    
    $('#qItemsBody').on('click', '.btn-remove-row', function() {
        $(this).closest('tr').remove();
        if ($('#qItemsBody').find('.q-row').length === 0) {
            $('#qItemsBody').html('<tr class="empty-row"><td colspan="8" class="text-center text-muted py-4">Belum ada item.</td></tr>');
        }
        calculateGrandTotal();
    });
});

function submitForm(actionType) {
    if ($('.q-row').length === 0) { alert('Daftar pekerjaan masih kosong.'); return; }
    $('#formAction').val(actionType);
    if (actionType === 'submit') {
        confirmAction('Submit Quotation?', 'Data akan dikirim untuk approval. Lanjutkan?', function() { $('#qForm').submit(); });
    } else {
        $('#qForm').submit();
    }
}
</script>
JS;

require_once __DIR__ . '/../../../includes/footer.php';
?>
