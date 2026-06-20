<?php
/**
 * Sales - Create Quotation
 */
require_once __DIR__ . '/../../../includes/auth.php';
requirePermission('quotation_create');

$user = getCurrentUser();

$companies  = $pdo->query("SELECT id, name, is_default FROM companies ORDER BY name")->fetchAll();
$customers  = $pdo->query("SELECT id, company_name as name, abbreviation FROM customers WHERE is_active = 1 ORDER BY company_name")->fetchAll();
$projects   = $pdo->query("SELECT id, name, customer_id FROM projects WHERE status IN ('planning','active') ORDER BY name")->fetchAll();
$pastQuotations = $pdo->query("SELECT q.id, q.quotation_no, q.total, c.company_name as customer_name FROM quotations q JOIN customers c ON q.customer_id = c.id ORDER BY q.id DESC LIMIT 50")->fetchAll();

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
            
            // Get abbreviation for doc number (Prioritize Project, fallback to Customer)
            $abbr = '';
            if ($projectId) {
                $pStmt = $pdo->prepare("SELECT abbreviation FROM projects WHERE id = ?");
                $pStmt->execute([$projectId]);
                $abbr = $pStmt->fetchColumn();
            }
            
            if (!$abbr) {
                $cStmt = $pdo->prepare("SELECT abbreviation FROM customers WHERE id = ?");
                $cStmt->execute([$customerId]);
                $abbr = $cStmt->fetchColumn();
            }
            
            $quotationNo = generateDocNumber($pdo, 'Q', $abbr);
            
            $stmt = $pdo->prepare("
                INSERT INTO quotations (quotation_no, company_id, customer_id, project_id, quotation_date, valid_from, valid_until, comments, terms_and_conditions, subtotal, shipping, tax, discount, total, status, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$quotationNo, $companyId, $customerId, $projectId, $quotationDate, $validFrom, $validUntil, $comments, $termsCond, $subtotal, $shipping, $tax, $discount, $grandTotal, $status, $user['id']]);
            $qId = $pdo->lastInsertId();
            
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
                    $insertItem->execute([$qId, $desc, $spec, $qty, $uom, $matP, $matT, $manP, $manT, $amt]);
                }
            }
            
            $pdo->commit();
            $msg = $status === 'pending' ? "Quotation $quotationNo berhasil di-submit." : "Quotation $quotationNo disimpan sebagai Draft.";
            setFlash('success', $msg);
            header('Location: index.php');
            exit;
            
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log('[NEWMEGA] ' . $e->getMessage());
            setFlash('danger', 'Gagal membuat Quotation. Terjadi kesalahan sistem.');
        }
    }
    
    if (!empty($errors)) {
        setFlash('danger', implode('<br>', $errors));
    }
}

$pageTitle = 'Buat Quotation';
$breadcrumbs = [
    ['label' => 'Sales', 'url' => '#'],
    ['label' => 'Quotation', 'url' => 'index.php'],
    ['label' => 'Baru']
];

require_once __DIR__ . '/../../../includes/header.php';
?>

<div class="card card-outline card-primary">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-file-alt mr-2"></i> Form Pembuatan Quotation</h3>
    </div>
    
    <form method="POST" id="qForm">
        <div class="card-body bg-light">
            <!-- Header Section -->
            <div class="row">
                <div class="col-md-6 border-right">

                    <div class="form-group row">
                        <label class="col-sm-4 col-form-label">Customer <span class="text-danger">*</span></label>
                        <div class="col-sm-8">
                            <select name="customer_id" id="customer_id" class="form-control select2" required>
                                <option value="">-- Pilih Customer --</option>
                                <?php foreach ($customers as $c): ?>
                                    <option value="<?= $c['id'] ?>" <?= (isset($_POST['customer_id']) && $_POST['customer_id'] == $c['id']) ? 'selected' : '' ?>><?= sanitize($c['name']) ?></option>
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
                                    <option value="<?= $p['id'] ?>" data-customer-id="<?= $p['customer_id'] ?>" <?= (isset($_POST['project_id']) && $_POST['project_id'] == $p['id']) ? 'selected' : '' ?>><?= sanitize($p['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group row">
                        <label class="col-sm-4 col-form-label">Catatan (Pesan)</label>
                        <div class="col-sm-8">
                            <textarea name="comments" class="form-control" rows="1" placeholder="Catatan yang muncul di header..."><?= sanitize($_POST['comments'] ?? '') ?></textarea>
                        </div>
                    </div>
                    
                    <div class="form-group row">
                        <label class="col-sm-4 col-form-label">Syarat & Ketentuan</label>
                        <div class="col-sm-8">
                            <textarea name="terms_and_conditions" class="form-control" rows="1" placeholder="Note 1: ..."><?= sanitize($_POST['terms_and_conditions'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="form-group row">
                        <label class="col-sm-4 col-form-label">Perusahaan (Header) <span class="text-danger">*</span></label>
                        <div class="col-sm-8">
                            <select name="company_id" class="form-control" required>
                                <?php foreach ($companies as $co): ?>
                                    <?php 
                                    $coSelected = false;
                                    if (isset($_POST['company_id'])) {
                                        if ($_POST['company_id'] == $co['id']) {
                                            $coSelected = true;
                                        }
                                    } elseif ($co['is_default']) {
                                        $coSelected = true;
                                    }
                                    ?>
                                    <option value="<?= $co['id'] ?>" <?= $coSelected ? 'selected' : '' ?>><?= sanitize($co['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group row">
                        <label class="col-sm-4 col-form-label">Tanggal Quotation <span class="text-danger">*</span></label>
                        <div class="col-sm-8">
                            <input type="date" name="quotation_date" class="form-control" value="<?= sanitize($_POST['quotation_date'] ?? date('Y-m-d')) ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-group row">
                        <label class="col-sm-4 col-form-label">Berlaku Dari</label>
                        <div class="col-sm-8">
                            <input type="date" name="valid_from" class="form-control" value="<?= sanitize($_POST['valid_from'] ?? date('Y-m-d')) ?>">
                        </div>
                    </div>
                    
                    <div class="form-group row">
                        <label class="col-sm-4 col-form-label">Berlaku Sampai</label>
                        <div class="col-sm-8">
                            <input type="date" name="valid_until" class="form-control" value="<?= sanitize($_POST['valid_until'] ?? date('Y-m-d', strtotime('+30 days'))) ?>">
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Item List Section -->
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <button type="button" class="btn btn-sm btn-outline-info mr-1" data-toggle="modal" data-target="#modalDuplicateQuotation"><i class="fas fa-copy mr-1"></i> Duplikat</button>
                    <button type="button" class="btn btn-sm btn-outline-primary mr-1" data-toggle="modal" data-target="#modalAiQuotation"><i class="fas fa-magic mr-1"></i> Buat dgn AI</button>
                    <button type="button" class="btn btn-sm btn-primary" id="btnAddRow"><i class="fas fa-plus mr-1"></i> Tambah Baris</button>
                </div>
            </div>
            
            <div class="table-responsive mb-3">
                <table class="table table-bordered table-sm mb-0" id="qItemsTable" >
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
                        <?php if (isset($_POST['description']) && is_array($_POST['description']) && count($_POST['description']) > 0): ?>
                            <?php foreach ($_POST['description'] as $i => $desc): ?>
                                <tr class="q-row">
                                    <td><input type="text" name="description[]" class="form-control form-control-sm" required placeholder="Nama pekerjaan/material" value="<?= sanitize($desc) ?>"></td>
                                    <td><input type="text" name="type_specification[]" class="form-control form-control-sm" placeholder="Spek" value="<?= sanitize($_POST['type_specification'][$i] ?? '') ?>"></td>
                                    <td><input type="text" name="qty[]" class="form-control form-control-sm text-center input-number col-qty" value="<?= sanitize($_POST['qty'][$i] ?? '1') ?>" required></td>
                                    <td><input type="text" name="uom[]" class="form-control form-control-sm text-center" value="<?= sanitize($_POST['uom'][$i] ?? '') ?>"></td>
                                    <td><input type="text" name="material_unit_price[]" class="form-control form-control-sm text-right input-number col-mat-price" value="<?= sanitize($_POST['material_unit_price'][$i] ?? '0') ?>"></td>
                                    <td><input type="text" name="manpower_unit_price[]" class="form-control form-control-sm text-right input-number col-man-price" value="<?= sanitize($_POST['manpower_unit_price'][$i] ?? '0') ?>"></td>
                                    <td><input type="text" name="amount[]" class="form-control form-control-sm text-right font-weight-bold col-amount" value="<?= sanitize($_POST['amount'][$i] ?? '0') ?>" readonly></td>
                                    <td class="text-center"><button type="button" class="btn btn-danger btn-sm btn-remove-row"><i class="fas fa-times"></i></button></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr class="empty-row">
                                <td colspan="8" class="text-center text-muted py-4">Belum ada item. Klik "Tambah Baris".</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Summary -->
            <style>
            .summary-table td {
                padding: 3px 5px !important;
                vertical-align: middle !important;
            }
            </style>
            <div class="row mt-0">
                <div class="col-md-6 offset-md-6">
                    <table class="table table-sm table-borderless font-weight-bold text-right summary-table" >
                        <tr>
                            <td width="40%">Subtotal</td>
                            <td><input type="text" name="subtotal" id="calc_subtotal" class="form-control text-right form-control-sm font-weight-bold" readonly value="<?= sanitize($_POST['subtotal'] ?? '0') ?>"></td>
                        </tr>
                        <tr>
                            <td>Diskon</td>
                            <td>
                                <div class="input-group input-group-sm">
                                    <input type="number" name="discount_pct" id="calc_discount_pct" class="form-control text-center" placeholder="%" min="0" max="100" step="0.01" value="<?= sanitize($_POST['discount_pct'] ?? '') ?>">
                                    <div class="input-group-prepend input-group-append"><span class="input-group-text">% | Rp</span></div>
                                    <input type="text" name="discount_nominal" id="calc_discount_nom" class="form-control text-right mask-rupiah" value="<?= sanitize($_POST['discount_nominal'] ?? '0') ?>">
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td>Pajak (PPN)</td>
                            <td>
                                <div class="input-group input-group-sm">
                                    <input type="number" name="tax_pct" id="calc_tax_pct" class="form-control text-center" placeholder="%" min="0" max="100" step="0.01" value="<?= sanitize($_POST['tax_pct'] ?? '11') ?>">
                                    <div class="input-group-prepend input-group-append"><span class="input-group-text">% | Rp</span></div>
                                    <input type="text" name="tax_nominal" id="calc_tax_nom" class="form-control text-right mask-rupiah" value="<?= sanitize($_POST['tax_nominal'] ?? '0') ?>">
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td>Ongkos Kirim</td>
                            <td><input type="text" name="shipping_cost" id="calc_shipping" class="form-control text-right form-control-sm mask-rupiah" value="<?= sanitize($_POST['shipping_cost'] ?? '0') ?>"></td>
                        </tr>
                        <tr>
                            <td class="text-danger" style="font-size:16px;">GRAND TOTAL</td>
                            <td><input type="text" name="grand_total" id="calc_grandtotal" class="form-control text-right text-danger font-weight-bold form-control-lg" readonly style="font-size:20px;background-color:#fff8f8;" value="<?= sanitize($_POST['grand_total'] ?? '0') ?>"></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
        
        <div class="card-footer bg-white text-right">
            <input type="hidden" name="action" id="formAction" value="draft">
            <a href="index.php" class="btn btn-default mr-2">Batal</a>
            <button type="button" class="btn btn-secondary mr-2" onclick="submitForm('draft')">Simpan Draft</button>
            <button type="button" class="btn btn-success" onclick="submitForm('submit')">Kirim untuk Persetujuan</button>
        </div>
    </form>
</div>

<!-- Modal AI -->
<div class="modal fade" id="modalAiQuotation" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-magic mr-2"></i> Buat Quotation dengan AI</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Ceritakan Spesifikasi / Kebutuhan Proyek Anda:</label>
                    <textarea id="aiProjectSpecs" class="form-control" rows="5" placeholder="Contoh: Perbaikan atap gudang seluas 50m2, butuh asbes, paku payung, dan jasa pemasangan 2 hari kerja."></textarea>
                    <small class="text-muted">AI akan membaca narasi ini dan mengubahnya menjadi daftar item material dan jasa lengkap dengan estimasi harganya.</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-primary" id="btnGenerateAi"><i class="fas fa-bolt mr-1"></i> Generate Items</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Duplicate -->
<div class="modal fade" id="modalDuplicateQuotation" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="fas fa-copy mr-2"></i> Duplikat dari Quotation Lama</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Pilih Quotation Sebelumnya:</label>
                    <select id="duplicateQuotationId" class="form-control select2" style="width: 100%;">
                        <option value="">-- Pilih Quotation --</option>
                        <?php foreach ($pastQuotations as $pq): ?>
                            <option value="<?= $pq['id'] ?>">
                                <?= sanitize($pq['quotation_no']) ?> - <?= sanitize($pq['customer_name']) ?> (Rp <?= number_format($pq['total'],0,',','.') ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-info" id="btnProcessDuplicate"><i class="fas fa-copy mr-1"></i> Salin Items</button>
            </div>
        </div>
    </div>
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
    
    // Calculate Nominal Discount from Percent if provided
    if (discPct > 0) { 
        $('#calc_discount_nom').val(formatIdr((subtotal * discPct) / 100)); 
    }
    
    var discNom = parseIdr($('#calc_discount_nom').val());
    var dpp = subtotal - discNom; // DPP = Subtotal - Discount
    
    // Calculate Nominal Tax from Percent if provided (Gross-up formula like Invoice)
    if (taxPct > 0 && taxPct < 100) { 
        var grossUpTotal = dpp / ((100 - taxPct) / 100);
        var taxAmount = Math.round(grossUpTotal - dpp);
        $('#calc_tax_nom').val(formatIdr(taxAmount)); 
    } else {
        $('#calc_tax_nom').val('0');
    }
    
    var taxNom = parseIdr($('#calc_tax_nom').val());
    var shipping = parseIdr($('#calc_shipping').val());
    
    // Grand Total = DPP + Tax + Shipping
    $('#calc_grandtotal').val(formatIdr(dpp + taxNom + shipping));
}

$(document).ready(function() {
    initSelect2('.select2');
    
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
    
    var tbody = $('#qItemsBody');
    var rowIdx = 0;
    
    $('#btnAddRow').on('click', function() {
        if (tbody.find('.empty-row').length > 0) tbody.empty();
        rowIdx++;
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
    
    tbody.on('input', '.col-qty, .col-mat-price, .col-man-price', function() {
        var v = parseIdr($(this).val());
        if (!$(this).hasClass('col-qty')) $(this).val(formatIdr(v));
        calculateRow($(this).closest('tr'));
    });
    
    tbody.on('click', '.btn-remove-row', function() {
        $(this).closest('tr').remove();
        if (tbody.find('.q-row').length === 0) {
            tbody.html('<tr class="empty-row"><td colspan="8" class="text-center text-muted py-4">Belum ada item.</td></tr>');
        }
        calculateGrandTotal();
    });

    // Helper function to append items
    function appendItemsToTable(items) {
        tbody.empty(); // User requested to replace existing rows
        items.forEach(function(d) {
            var html = `
            <tr class="q-row">
                <td><input type="text" name="description[]" class="form-control form-control-sm" required value="${d.description || ''}"></td>
                <td><input type="text" name="type_specification[]" class="form-control form-control-sm" value="${d.type_specification || ''}"></td>
                <td><input type="text" name="qty[]" class="form-control form-control-sm text-center input-number col-qty" value="${d.qty || 1}" required></td>
                <td><input type="text" name="uom[]" class="form-control form-control-sm text-center" value="${d.uom || ''}"></td>
                <td><input type="text" name="material_unit_price[]" class="form-control form-control-sm text-right input-number col-mat-price" value="${formatIdr(d.material_price || 0)}"></td>
                <td><input type="text" name="manpower_unit_price[]" class="form-control form-control-sm text-right input-number col-man-price" value="${formatIdr(d.manpower_price || 0)}"></td>
                <td><input type="text" name="amount[]" class="form-control form-control-sm text-right font-weight-bold col-amount" value="0" readonly></td>
                <td class="text-center"><button type="button" class="btn btn-danger btn-sm btn-remove-row"><i class="fas fa-times"></i></button></td>
            </tr>`;
            tbody.append(html);
        });
        // Recalculate everything
        $('.col-qty').trigger('input');
    }

    // AI Generate Logic
    $('#btnGenerateAi').on('click', function() {
        var specs = $('#aiProjectSpecs').val().trim();
        if (!specs) { toastr.error('Spesifikasi tidak boleh kosong'); return; }
        
        var btn = $(this);
        var originalText = btn.html();
        btn.html('<i class="fas fa-spinner fa-spin"></i> Memproses...').prop('disabled', true);
        
        $.ajax({
            url: APP_URL + '/api/ai_suggest_quotation.php',
            type: 'POST',
            data: { project_specs: specs },
            dataType: 'json',
            success: function(res) {
                btn.html(originalText).prop('disabled', false);
                if (res.success && res.data) {
                    appendItemsToTable(res.data);
                    $('#modalAiQuotation').modal('hide');
                    toastr.success('Item berhasil dibuat oleh AI ✨');
                } else {
                    toastr.error(res.error || 'Gagal generate AI');
                }
            },
            error: function() {
                btn.html(originalText).prop('disabled', false);
                toastr.error('Kesalahan koneksi ke server AI');
            }
        });
    });

    // Duplicate Logic
    $('#btnProcessDuplicate').on('click', function() {
        var qId = $('#duplicateQuotationId').val();
        if (!qId) { toastr.error('Pilih quotation terlebih dahulu'); return; }
        
        var btn = $(this);
        var originalText = btn.html();
        btn.html('<i class="fas fa-spinner fa-spin"></i> Mengambil...').prop('disabled', true);
        
        $.ajax({
            url: APP_URL + '/api/get_quotation_items.php',
            type: 'GET',
            data: { quotation_id: qId },
            dataType: 'json',
            success: function(res) {
                btn.html(originalText).prop('disabled', false);
                if (res.success && res.data) {
                    appendItemsToTable(res.data);
                    $('#modalDuplicateQuotation').modal('hide');
                    toastr.success('Item berhasil diduplikat');
                } else {
                    toastr.error(res.error || 'Gagal menduplikat item');
                }
            },
            error: function() {
                btn.html(originalText).prop('disabled', false);
                toastr.error('Kesalahan koneksi saat mengambil data');
            }
        });
    });

    // Recalculate totals on page load to sync JS states if there are elements already rendered by PHP
    if ($('.q-row').length > 0) {
        calculateGrandTotal();
    }
});

function submitForm(actionType) {
    if ($('.q-row').length === 0) { alert('Daftar pekerjaan masih kosong.'); return; }
    $('#formAction').val(actionType);
    if (actionType === 'submit') {
        confirmAction('Submit Quotation?', 'Quotation akan dikirim untuk approval.', function() { $('#qForm').submit(); });
    } else {
        $('#qForm').submit();
    }
}
</script>
JS;
require_once __DIR__ . '/../../../includes/footer.php';
?>
