<?php
/**
 * Procurement - Purchase Order Edit (Drafts Only)
 */
require_once __DIR__ . '/../../../includes/auth.php';
requirePermission('po_create');

$id = $_GET['id'] ?? 0;

$stmt = $pdo->prepare("SELECT * FROM purchase_orders WHERE id = ?");
$stmt->execute([$id]);
$po = $stmt->fetch();

if (!$po) {
    setFlash('danger', 'PO tidak ditemukan.');
    header('Location: ' . APP_URL . '/modules/procurement/po/index.php');
    exit;
}

$user = getCurrentUser();

// Authorization: Only creator or super admin
if ($user['role'] !== 'super_admin' && $po['created_by'] != $user['id']) {
    setFlash('danger', 'Anda tidak memiliki akses untuk mengubah PO ini.');
    header('Location: ' . APP_URL . '/modules/procurement/po/index.php');
    exit;
}

// Logic: Only Draft or Pending can be edited
if (!in_array($po['status'], ['draft', 'pending'])) {
    setFlash('danger', 'PO yang sudah di-approve tidak dapat diubah.');
    header('Location: ' . APP_URL . '/modules/procurement/po/index.php');
    exit;
}

$pageTitle = 'Edit PO: ' . sanitize($po['po_number']);
$breadcrumbs = [
    ['label' => 'Procurement', 'url' => '#'],
    ['label' => 'PO', 'url' => APP_URL . '/modules/procurement/po/index.php'],
    ['label' => 'Edit']
];

// Fetch Dependencies
$vendors_sql   = "SELECT id, company_name as name, abbreviation, payment_terms FROM vendors WHERE is_active = 1 ORDER BY company_name ASC";
$vendors       = $pdo->query($vendors_sql)->fetchAll();

$companies_sql = "SELECT id, name, is_default FROM companies ORDER BY name ASC";
$companies     = $pdo->query($companies_sql)->fetchAll();

$items_sql     = "SELECT id, item_code, description, uom, type_specification FROM items WHERE is_active = 1 ORDER BY description ASC";
$items         = $pdo->query($items_sql)->fetchAll();
$itemsJson     = json_encode($items);

// Fetch PO Items
$stmtItems = $pdo->prepare("SELECT mr_item_id as is_mr_linked, i.* FROM purchase_order_items i WHERE i.po_id = ?");
$stmtItems->execute([$id]);
$poItems = $stmtItems->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $vendorId        = $_POST['vendor_id'] ?? null;
    $companyId       = $_POST['company_id'] ?? null;
    $poDate          = $_POST['po_date'] ?? date('Y-m-d');
    $deliveryAddress = trim($_POST['delivery_address'] ?? '');
    $deliveryContact = trim($_POST['delivery_contact'] ?? '');
    $deliveryAttn    = trim($_POST['delivery_attn'] ?? '');
    $deliveryDate    = $_POST['delivery_date'] ?? null;
    $terms           = trim($_POST['terms'] ?? '');
    $requestedBy     = trim($_POST['requested_by'] ?? '');
    $additionalNotes = trim($_POST['additional_notes'] ?? '');
    $action          = $_POST['action'] ?? 'draft'; // draft or submit
    
    // Financials
    $subtotal   = parseRupiah($_POST['subtotal'] ?? 0);
    $discount   = parseRupiah($_POST['discount_nominal'] ?? 0);
    $tax        = parseRupiah($_POST['tax_nominal'] ?? 0);
    $shipping   = parseRupiah($_POST['shipping_cost'] ?? 0);
    $otherCost  = parseRupiah($_POST['other_cost'] ?? 0);
    $grandTotal = parseRupiah($_POST['grand_total'] ?? 0);
    
    // Items
    $itemIds     = $_POST['item_id'] ?? [];
    $mrItemIds   = $_POST['mr_item_id'] ?? [];
    $mrIdsForLink= $_POST['mr_header_id'] ?? [];
    $itemNames   = $_POST['item_name'] ?? [];
    $qtys        = $_POST['qty'] ?? [];
    $uoms        = $_POST['uom'] ?? [];
    $unitPrices  = $_POST['unit_price'] ?? [];
    $itemDiscs   = $_POST['item_discount'] ?? [];
    $itemTotals  = $_POST['item_total'] ?? [];
    
    $status = ($action === 'submit') ? 'pending' : 'draft';
    
    $errors = [];
    if (empty($vendorId)) $errors[] = "Vendor wajib dipilih.";
    if (empty($companyId)) $errors[] = "Perusahaan Header wajib dipilih.";
    if (empty($itemNames) || count($itemNames) === 0) $errors[] = "Minimal harus ada 1 barang yang dipesan.";
    
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            // Revert MR Quantities first before wiping to calculate net changes properly.
            // Since it's a draft, maybe it was created before and qty reserved.
            $oldPoItems = $pdo->prepare("SELECT mr_item_id, qty FROM purchase_order_items WHERE po_id = ?");
            $oldPoItems->execute([$id]);
            foreach ($oldPoItems->fetchAll() as $oldMri) {
                if ($oldMri['mr_item_id']) {
                    // deduct the previously reserved qty
                    $pdo->prepare("UPDATE material_request_items SET qty_ordered = qty_ordered - ? WHERE id = ?")->execute([$oldMri['qty'], $oldMri['mr_item_id']]);
                }
            }
            
            // Delete old items and links
            $pdo->prepare("DELETE FROM purchase_order_items WHERE po_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM po_mr_links WHERE po_id = ?")->execute([$id]);
            
            // Update PO Header
            $stmt = $pdo->prepare("
                UPDATE purchase_orders SET
                    vendor_id = ?, company_id = ?, po_date = ?, delivery_address = ?, delivery_contact = ?, delivery_attn = ?, delivery_date = ?,
                    terms = ?, requested_by = ?, subtotal = ?, discount = ?, tax = ?, shipping = ?, other_cost = ?, total = ?, additional_notes = ?, status = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $vendorId, $companyId, $poDate, $deliveryAddress, $deliveryContact, $deliveryAttn, 
                $deliveryDate ?: null, $terms, $requestedBy, $subtotal, $discount, $tax, $shipping, $otherCost, $grandTotal, 
                $additionalNotes, $status, $id
            ]);
            
            // Insert Re-Items
            $insertItem = $pdo->prepare("
                INSERT INTO purchase_order_items (
                    po_id, item_id, mr_item_id, item_name, qty, uom, unit_price, discount_item, total
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $updateMrItem = $pdo->prepare("UPDATE material_request_items SET qty_ordered = qty_ordered + ? WHERE id = ?");
            
            $uniqueMrIds = [];
            
            for ($i = 0; $i < count($itemNames); $i++) {
                $i_name = trim($itemNames[$i]);
                $i_qty  = parseRupiah($qtys[$i] ?? '0');
                
                if ($i_name && $i_qty > 0) {
                    $i_id       = !empty($itemIds[$i]) ? $itemIds[$i] : null;
                    $mri_id     = !empty($mrItemIds[$i]) ? $mrItemIds[$i] : null;
                    $mrh_id     = !empty($mrIdsForLink[$i]) ? $mrIdsForLink[$i] : null;
                    $i_uom      = $uoms[$i] ?? '';
                    $i_price    = parseRupiah($unitPrices[$i] ?? '0');
                    $i_disc     = parseRupiah($itemDiscs[$i] ?? '0');
                    $i_total    = parseRupiah($itemTotals[$i] ?? '0');
                    
                    $insertItem->execute([
                        $id, $i_id, $mri_id, $i_name, $i_qty, $i_uom, $i_price, $i_disc, $i_total
                    ]);
                    
                    if ($mrh_id) {
                        $uniqueMrIds[$mrh_id] = true;
                    }
                    if ($mri_id) {
                        $updateMrItem->execute([$i_qty, $mri_id]);
                    }
                }
            }
            
            // Insert po_mr_links
            $insertLink = $pdo->prepare("INSERT INTO po_mr_links (po_id, mr_id) VALUES (?, ?)");
            foreach (array_keys($uniqueMrIds) as $mrh_id) {
                $insertLink->execute([$id, $mrh_id]);
            }
            
            $pdo->commit();
            
            $msg = $status === 'pending' ? "PO {$po['po_number']} berhasil di-submit untuk persetujuan." : "Draft PO {$po['po_number']} berhasil diperbarui.";
            setFlash('success', $msg);
            header('Location: ' . APP_URL . '/modules/procurement/po/index.php');
            exit;
            
        } catch (Exception $e) {
            $pdo->rollBack();
            setFlash('danger', "Terjadi kesalahan sistem: " . $e->getMessage());
        }
    }
    
    if (!empty($errors)) {
        setFlash('danger', implode('<br>', $errors));
    }
}

require_once __DIR__ . '/../../../includes/header.php';
?>

<div class="card card-outline card-warning">
    <div class="card-header">
        <h3 class="card-title text-warning font-weight-bold"><i class="fas fa-edit mr-2"></i> Edit Purchase Order (<?= ucfirst($po['status']) ?>)</h3>
        <a href="<?= APP_URL ?>/modules/procurement/po/index.php" class="btn btn-secondary btn-sm float-right"><i class="fas fa-arrow-left mr-1"></i> Kembali</a>
    </div>
    
    <form method="POST" id="poForm">
        <div class="card-body bg-light">
            <!-- Header Section -->
            <div class="row">
                <!-- Left Col -->
                <div class="col-md-6 border-right">
                    <h5 class="mb-3 text-secondary text-uppercase font-weight-bold" style="font-size:12px;letter-spacing:1px;">1. Informasi Suplier (Vendor)</h5>
                    
                    <div class="form-group row">
                        <label class="col-sm-4 col-form-label">Vendor <span class="text-danger">*</span></label>
                        <div class="col-sm-8">
                            <select name="vendor_id" id="vendor_id" class="form-control select2" required>
                                <option value="">-- Pilih Vendor --</option>
                                <?php foreach ($vendors as $v): ?>
                                    <option value="<?= $v['id'] ?>" data-terms="<?= htmlspecialchars($v['payment_terms']) ?>" <?= $po['vendor_id'] == $v['id'] ? 'selected' : '' ?>><?= sanitize($v['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group row">
                        <label class="col-sm-4 col-form-label">Tgl. Order <span class="text-danger">*</span></label>
                        <div class="col-sm-8">
                            <input type="date" name="po_date" class="form-control" value="<?= sanitize($po['po_date']) ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-group row">
                        <label class="col-sm-4 col-form-label">Termin Bayar</label>
                        <div class="col-sm-8">
                            <input type="text" name="terms" id="terms" class="form-control" value="<?= sanitize($po['terms']) ?>">
                        </div>
                    </div>
                    
                    <div class="form-group row">
                        <label class="col-sm-4 col-form-label">Diminta Oleh</label>
                        <div class="col-sm-8">
                            <input type="text" name="requested_by" class="form-control" value="<?= sanitize($po['requested_by']) ?>" placeholder="Nama peminta barang">
                        </div>
                    </div>

                    <div class="form-group row">
                        <label class="col-sm-4 col-form-label">Catatan PO</label>
                        <div class="col-sm-8">
                            <textarea name="additional_notes" class="form-control" rows="2"><?= sanitize($po['additional_notes']) ?></textarea>
                        </div>
                    </div>
                </div>
                
                <!-- Right Col -->
                <div class="col-md-6">
                    <h5 class="mb-3 text-secondary text-uppercase font-weight-bold" style="font-size:12px;letter-spacing:1px;">2. Informasi Pengiriman (Delivery)</h5>
                    
                    <div class="form-group row">
                        <label class="col-sm-4 col-form-label">Penagih (Header) <span class="text-danger">*</span></label>
                        <div class="col-sm-8">
                            <select name="company_id" id="company_id" class="form-control" required>
                                <?php foreach ($companies as $c): ?>
                                    <option value="<?= $c['id'] ?>" <?= $po['company_id'] == $c['id'] ? 'selected' : '' ?>><?= sanitize($c['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group row">
                        <label class="col-sm-4 col-form-label">Alamat Kirim</label>
                        <div class="col-sm-8">
                            <textarea name="delivery_address" id="delivery_address" class="form-control" rows="2"><?= sanitize($po['delivery_address']) ?></textarea>
                        </div>
                    </div>
                    
                    <div class="form-group row">
                        <label class="col-sm-4 col-form-label">Kontak Penerima</label>
                        <div class="col-sm-8">
                            <input type="text" name="delivery_attn" id="delivery_attn" class="form-control" value="<?= sanitize($po['delivery_attn']) ?>">
                        </div>
                    </div>
                    
                    <div class="form-group row">
                        <label class="col-sm-4 col-form-label">Tgl. Kirim <small class="text-muted">(opsi)</small></label>
                        <div class="col-sm-8">
                            <input type="date" name="delivery_date" class="form-control" value="<?= sanitize($po['delivery_date']) ?>">
                        </div>
                    </div>
                </div>
            </div>
            
            <hr class="my-3">
            
            <!-- Item List Section -->
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="text-secondary text-uppercase font-weight-bold m-0" style="font-size:12px;letter-spacing:1px;">3. Daftar Barang</h5>
                <div>
                    <button type="button" class="btn btn-sm btn-info mr-2" id="btnModalMR"><i class="fas fa-hand-holding-box mr-1"></i> Tarik dari MR</button>
                    <button type="button" class="btn btn-sm btn-primary" id="btnAddRowManual"><i class="fas fa-plus mr-1"></i> Tambah Manual</button>
                </div>
            </div>
            
            <div class="table-responsive mb-3" style="min-height: 200px;">
                <table class="table table-bordered table-sm" id="poItemsTable" style="font-size:13px;">
                    <thead class="bg-dark text-white">
                        <tr>
                            <th width="25%">Nama / Deskripsi Barang</th>
                            <th width="10%">No. MR</th>
                            <th width="10%" class="text-center">Qty</th>
                            <th width="8%" class="text-center">Satuan</th>
                            <th width="15%" class="text-right">Harga Satuan</th>
                            <th width="12%" class="text-right">Diskon/Item</th>
                            <th width="15%" class="text-right">Total</th>
                            <th width="5%" class="text-center"><i class="fas fa-trash"></i></th>
                        </tr>
                    </thead>
                    <tbody id="poItemsBody">
                        <?php if (count($poItems) == 0): ?>
                        <tr class="empty-row">
                            <td colspan="8" class="text-center text-muted py-4">Belum ada barang. Silakan Tarik dari MR atau Tambah Manual.</td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($poItems as $pitem): ?>
                            <?php $isManual = !$pitem['is_mr_linked']; ?>
                            <tr class="po-row">
                                <td>
                                    <?php if ($isManual): ?>
                                        <select name="item_id[]" class="form-control form-control-sm dt-item-select" required>
                                            <option value="">-- Pilih Master Barang --</option>
                                            <?php foreach ($items as $m_item): ?>
                                            <option value="<?= $m_item['id'] ?>" data-uom="<?= sanitize($m_item['uom']) ?>" data-description="<?= sanitize($m_item['description']) ?>" <?= $pitem['item_id'] == $m_item['id'] ? 'selected' : '' ?>><?= sanitize($m_item['item_code'].' - '.$m_item['description']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <input type="hidden" name="item_name[]" class="dt-item-name" value="<?= sanitize($pitem['item_name']) ?>">
                                    <?php else: ?>
                                        <input type="hidden" name="item_id[]" value="<?= $pitem['item_id'] ?>">
                                        <input type="hidden" name="item_name[]" value="<?= sanitize($pitem['item_name']) ?>" class="dt-item-name">
                                        <input type="text" class="form-control form-control-sm" value="<?= sanitize($pitem['item_name']) ?>" readonly>
                                    <?php endif; ?>
                                    
                                    <input type="hidden" name="mr_item_id[]" value="<?= $pitem['mr_item_id'] ?>">
                                    <input type="hidden" name="mr_header_id[]" value=""> <!-- Hard to backward infer here safely inside UI, we can just omit or read DB -->
                                </td>
                                <td><span class="badge <?= $isManual ? 'badge-secondary' : 'badge-info' ?>"><?= $isManual ? 'Manual' : 'MR-Linked' ?></span></td>
                                <td>
                                    <input type="text" name="qty[]" class="form-control form-control-sm align-right input-number col-qty" value="<?= formatRupiah($pitem['qty'], '') ?>" required>
                                </td>
                                <td>
                                    <input type="text" name="uom[]" class="form-control form-control-sm text-center dt-uom" value="<?= sanitize($pitem['uom']) ?>" <?= $isManual ? '' : 'readonly' ?>>
                                </td>
                                <td>
                                    <input type="text" name="unit_price[]" class="form-control form-control-sm text-right input-number col-price" value="<?= formatRupiah($pitem['unit_price'], '') ?>" required>
                                </td>
                                <td>
                                    <input type="text" name="item_discount[]" class="form-control form-control-sm text-right input-number col-disc" value="<?= formatRupiah($pitem['discount_item'], '') ?>">
                                </td>
                                <td>
                                    <input type="text" name="item_total[]" class="form-control form-control-sm text-right font-weight-bold col-total readonly-bg" value="<?= formatRupiah($pitem['total'], '') ?>" readonly>
                                </td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-danger btn-sm btn-remove-row"><i class="fas fa-times"></i></button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Summary Calc Section -->
            <div class="row">
                <div class="col-md-6 offset-md-6">
                    <table class="table table-sm table-borderless font-weight-bold text-right" style="font-size:14px;">
                        <tr>
                            <td width="40%">Subtotal</td>
                            <td width="60%">
                                <input type="text" name="subtotal" id="calc_subtotal" class="form-control text-right form-control-sm font-weight-bold readonly-bg" readonly value="<?= formatRupiah($po['subtotal'], '') ?>">
                            </td>
                        </tr>
                        <tr>
                            <td>Diskon (Global)</td>
                            <td>
                                <div class="input-group input-group-sm">
                                    <input type="number" id="calc_discount_pct" class="form-control text-center" placeholder="%" min="0" max="100" step="0.01">
                                    <div class="input-group-prepend input-group-append">
                                        <span class="input-group-text">% | Rp</span>
                                    </div>
                                    <input type="text" name="discount_nominal" id="calc_discount_nom" class="form-control text-right mask-rupiah" value="<?= formatRupiah($po['discount'], '') ?>">
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td>Pajak Dasar (Tx)</td>
                            <td>
                                <div class="input-group input-group-sm">
                                    <input type="number" id="calc_tax_pct" class="form-control text-center" placeholder="%" min="0" max="100" step="0.01">
                                    <div class="input-group-prepend input-group-append">
                                        <span class="input-group-text">% | Rp</span>
                                    </div>
                                    <input type="text" name="tax_nominal" id="calc_tax_nom" class="form-control text-right mask-rupiah" value="<?= formatRupiah($po['tax'], '') ?>">
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td>Ongkos Kirim</td>
                            <td>
                                <input type="text" name="shipping_cost" id="calc_shipping" class="form-control text-right form-control-sm mask-rupiah" value="<?= formatRupiah($po['shipping'], '') ?>">
                            </td>
                        </tr>
                        <tr>
                            <td>Biaya Lainnya</td>
                            <td>
                                <input type="text" name="other_cost" id="calc_other" class="form-control text-right form-control-sm mask-rupiah" value="<?= formatRupiah($po['other_cost'], '') ?>">
                            </td>
                        </tr>
                        <tr style="border-top: 2px solid #ccc;">
                            <td class="text-danger" style="font-size:16px;">GRAND TOTAL</td>
                            <td>
                                <input type="text" name="grand_total" id="calc_grandtotal" class="form-control text-right text-danger font-weight-bold form-control-lg" readonly style="font-size: 20px; background-color: #fff8f8;" value="<?= formatRupiah($po['total'], '') ?>">
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
        
        <div class="card-footer bg-white d-flex justify-content-between">
            <a href="<?= APP_URL ?>/modules/procurement/po/index.php" class="btn btn-default"><i class="fas fa-times mr-1"></i> Batal</a>
            <div>
                <input type="hidden" name="action" id="formAction" value="draft">
                <button type="button" class="btn btn-secondary mr-2" onclick="submitForm('draft')"><i class="fas fa-save mr-1"></i> Simpan Draft</button>
                <button type="button" class="btn btn-success" onclick="submitForm('submit')"><i class="fas fa-paper-plane mr-1"></i> Submit untuk Approval</button>
            </div>
        </div>
    </form>
</div>

<!-- Modal Select MR Items -->
<div class="modal fade" id="modalMRItems" tabindex="-1" role="dialog" aria-hidden="true">
    <!-- Same as create modal structure -->
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-list-ul mr-2"></i> Pilih Item dari Material Request</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" style="background:#f8f9fa;">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover table-sm w-100" id="dt_mr_items" style="font-size:12px; background:white;">
                        <thead class="bg-light">
                            <tr>
                                <th width="5%" class="text-center"><input type="checkbox" id="checkAllMR"></th>
                                <th width="15%">No. MR</th>
                                <th width="15%">Proyek</th>
                                <th width="35%">Nama Barang (Spek)</th>
                                <th width="10%" class="text-center">Sisa Qty</th>
                                <th width="10%">Satuan</th>
                                <th width="10%">Catatan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Loaded via AJAX -->
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-primary" id="btnImportMR">Import Terpilih ke PO</button>
            </div>
        </div>
    </div>
</div>

<?php
$extraJS = <<<'JS'
<script>
const masterItems = ###ITEMS_JSON###;

function parseIdr(str) {
    if (!str && str !== 0) return 0;
    if (typeof str === 'number') return str;
    var parsed = str.toString().replace(/[^0-9]/g, '');
    return parsed === '' ? 0 : parseFloat(parsed);
}
function formatIdr(num) {
    return num.toLocaleString('id-ID');
}

function rebindMasking() {
    $('.input-number:not(.masked)').addClass('masked').on('input', function() {
        var num = parseIdr($(this).val());
        $(this).val(formatIdr(num));
    });
}

function calculateRow(row) {
    var qty = parseIdr(row.find('.col-qty').val());
    var price = parseIdr(row.find('.col-price').val());
    var disc = parseIdr(row.find('.col-disc').val());
    
    var total = (qty * price) - disc;
    if (total < 0) total = 0;
    
    row.find('.col-total').val(formatIdr(total));
    calculateGrandTotal();
}

function calculateGrandTotal() {
    var subtotal = 0;
    $('.col-total').each(function() {
        subtotal += parseIdr($(this).val());
    });
    
    $('#calc_subtotal').val(formatIdr(subtotal));
    
    var discPct = parseFloat($('#calc_discount_pct').val()) || 0;
    var taxPct = parseFloat($('#calc_tax_pct').val()) || 0;
    
    if (discPct > 0) {
        var discNom = (subtotal * discPct) / 100;
        $('#calc_discount_nom').val(formatIdr(discNom));
    }
    
    var discNominal = parseIdr($('#calc_discount_nom').val());
    var dpp = subtotal - discNominal;
    
    if (taxPct > 0) {
        var taxNom = (dpp * taxPct) / 100;
        $('#calc_tax_nom').val(formatIdr(taxNom));
    }
    
    var taxNominal = parseIdr($('#calc_tax_nom').val());
    var shipping = parseIdr($('#calc_shipping').val());
    var other = parseIdr($('#calc_other').val());
    
    var grand = dpp + taxNominal + shipping + other;
    $('#calc_grandtotal').val(formatIdr(grand));
}

$(document).ready(function() {
    initSelect2('.select2');
    initSelect2('.dt-item-select'); // For existing manual items
    rebindMasking();
    updateItemSelects();
    // In edit view, let's recalculate init to sync formulas
    calculateGrandTotal(); 
    
    $('#vendor_id').on('change', function() {
        var term = $(this).find('option:selected').data('terms');
        if (term) $('#terms').val(term);
    });
    
    $('#calc_discount_pct').on('input', calculateGrandTotal);
    $('#calc_tax_pct').on('input', calculateGrandTotal);
    $('#calc_discount_nom, #calc_tax_nom, #calc_shipping, #calc_other').on('input', function() {
        var v = parseIdr($(this).val());
        $(this).val(formatIdr(v));
        calculateGrandTotal();
    });

    var tbody = $('#poItemsBody');
    
    function generateRowHTML(data) {
        var isManual = (data.mr_item_id === null || data.mr_item_id === '');
        
        var nameInput = '';
        if (isManual) {
            var options = '<option value="">-- Pilih Master Barang --</option>';
            masterItems.forEach(function(i) {
                options += `<option value="${i.id}" data-uom="${i.uom}" data-description="${i.description}">${i.item_code} - ${i.description}</option>`;
            });
            nameInput = `<select name="item_id[]" class="form-control form-control-sm dt-item-select" required>${options}</select>
                         <input type="hidden" name="item_name[]" class="dt-item-name" value="">`;
        } else {
            nameInput = `<input type="hidden" name="item_id[]" value="${data.item_id}">
                         <input type="hidden" name="item_name[]" value="${data.item_name}" class="dt-item-name">
                         <input type="text" class="form-control form-control-sm" value="${data.item_name}" readonly>`;
        }
        
        return `
        <tr class="po-row">
            <td>
                ${nameInput}
                <input type="hidden" name="mr_item_id[]" value="${data.mr_item_id || ''}">
                <input type="hidden" name="mr_header_id[]" value="${data.mr_header_id || ''}">
            </td>
            <td><span class="badge ${isManual ? 'badge-secondary' : 'badge-info'}">${data.mr_number || 'Manual'}</span></td>
            <td>
                <input type="text" name="qty[]" class="form-control form-control-sm align-right input-number col-qty" value="${formatIdr(data.qty)}" required>
            </td>
            <td>
                <input type="text" name="uom[]" class="form-control form-control-sm text-center dt-uom" value="${data.uom || ''}" ${isManual ? '' : 'readonly'}>
            </td>
            <td>
                <input type="text" name="unit_price[]" class="form-control form-control-sm text-right input-number col-price" value="${formatIdr(data.price || 0)}" required>
            </td>
            <td>
                <input type="text" name="item_discount[]" class="form-control form-control-sm text-right input-number col-disc" value="0">
            </td>
            <td>
                <input type="text" name="item_total[]" class="form-control form-control-sm text-right font-weight-bold col-total readonly-bg" value="0" readonly>
            </td>
            <td class="text-center">
                <button type="button" class="btn btn-danger btn-sm btn-remove-row"><i class="fas fa-times"></i></button>
            </td>
        </tr>
        `;
    }
    
    $('#btnAddRowManual').on('click', function() {
        if (tbody.find('.empty-row').length > 0) tbody.empty();
        var html = generateRowHTML({ mr_item_id: null, item_id: '', item_name: '', qty: 1, uom: '', mr_number: '' });
        var $row = $(html);
        tbody.append($row);
        $row.find('.dt-item-select').select2({ theme: 'bootstrap4', width: '100%' });
        rebindMasking();
        updateItemSelects();
    });
    
    tbody.on('change', '.dt-item-select', function() {
        var row = $(this).closest('tr');
        var opt = $(this).find('option:selected');
        if (opt.val()) {
            row.find('.dt-item-name').val(opt.data('description'));
            row.find('.dt-uom').val(opt.data('uom'));
        } else {
            row.find('.dt-item-name').val('');
            row.find('.dt-uom').val('');
        }
        updateItemSelects();
    });
    
    tbody.on('input', '.col-qty, .col-price, .col-disc', function() { calculateRow($(this).closest('tr')); });
    
    tbody.on('click', '.btn-remove-row', function() {
        $(this).closest('tr').remove();
        if (tbody.find('.po-row').length === 0) {
            tbody.html('<tr class="empty-row"><td colspan="8" class="text-center text-muted py-4">Belum ada barang. Silakan Tarik dari MR atau Tambah Manual.</td></tr>');
        }
        calculateGrandTotal();
        updateItemSelects();
    });
    
    var dtMR;
    $('#btnModalMR').on('click', function() {
        $('#modalMRItems').modal('show');
        if (!dtMR) {
            dtMR = $('#dt_mr_items').DataTable({
                ajax: APP_URL + '/api/get_approved_mr_items.php',
                autoWidth: false,
                columns: [
                    { 
                        data: 'mr_item_id', className: 'text-center',
                        render: function(data, type, row) { return `<input type="checkbox" class="mr-check" value='${JSON.stringify(row)}'>`; }
                    },
                    { data: 'mr_number' },
                    { data: 'project_name', render: function(d) { return d || '-'; } },
                    { 
                        data: 'description',
                        render: function(data, type, row) { 
                            var code = '';
                            return `<strong>${code}${data}</strong>` + (row.type_specification ? ` (${row.type_specification})` : ''); 
                        }
                    },
                    { data: 'qty_available', className: 'text-center font-weight-bold', render: function(d) { return formatIdr(parseFloat(d)); } },
                    { data: 'uom' },
                    { data: 'remark', render: function(d) { return d || '-'; } }
                ],
                order: [[1, 'desc']]
            });
        } else {
            dtMR.ajax.reload(null, false);
        }
        $('#checkAllMR').prop('checked', false);
    });
    
    $('#checkAllMR').on('change', function() { $('.mr-check').prop('checked', this.checked); });
    
    $('#btnImportMR').on('click', function() {
        if (tbody.find('.empty-row').length > 0) tbody.empty();
        var imported = 0;
        var requesterNames = [];
        $('.mr-check:checked').each(function() {
            var rowData = JSON.parse($(this).val());
            var exist = false;
            $('input[name="mr_item_id[]"]').each(function() { if ($(this).val() == rowData.mr_item_id) exist = true; });
            
            if (!exist) {
                var itemName = rowData.description + (rowData.type_specification ? ` (${rowData.type_specification})` : '');
                var html = generateRowHTML({
                    mr_item_id: rowData.mr_item_id,
                    mr_header_id: rowData.mr_id || '',
                    item_id: rowData.item_id,
                    item_name: itemName,
                    qty: parseFloat(rowData.qty_available),
                    uom: rowData.uom,
                    mr_number: rowData.mr_number
                });
                var $htmlRow = $(html);
                tbody.append($htmlRow);
                calculateRow($htmlRow);
                imported++;
                
                // Track requester names for auto-fill
                if (rowData.requester_name && requesterNames.indexOf(rowData.requester_name) === -1) {
                    requesterNames.push(rowData.requester_name);
                }
            }
        });
        
        // Auto-fill Requested By if single requester, leave for manual if multiple
        var reqByField = $('input[name="requested_by"]');
        if (requesterNames.length === 1 && !reqByField.val()) {
            reqByField.val(requesterNames[0]);
        }
        
        rebindMasking();
        $('#modalMRItems').modal('hide');
        if (imported > 0) toastr.success(imported + " item MR berhasil ditarik.");
        updateItemSelects();
    });
    
    function updateItemSelects() {
        var selectedIds = [];
        $('.dt-item-select').each(function() {
            if ($(this).val()) {
                selectedIds.push($(this).val());
            }
        });
        
        $('.dt-item-select').each(function() {
            var currentSelect = $(this);
            var currentVal = currentSelect.val();
            
            var changed = false;
            currentSelect.find('option').each(function() {
                var shouldDisable = (this.value !== "" && this.value !== currentVal && selectedIds.includes(this.value));
                if ($(this).prop('disabled') !== shouldDisable) {
                    $(this).prop('disabled', shouldDisable);
                    changed = true;
                }
            });
            
            if (changed && currentSelect.hasClass("select2-hidden-accessible")) {
                currentSelect.select2('destroy').select2({ theme: 'bootstrap4', width: '100%' });
            }
        });
    }
});

function submitForm(actionType) {
    var form = $('#poForm');
    if (!$('#vendor_id').val()) { showError('Vendor harus dipilih.'); return; }
    if (!$('#company_id').val()) { showError('Perusahaan penagih harus dipilih.'); return; }
    if ($('.po-row').length === 0) { showError('Daftar barang masih kosong.'); return; }
    
    var validQty = true;
    var validPrice = true;
    $('.col-qty').each(function() { if (parseIdr($(this).val()) <= 0) validQty = false; });
    $('.col-price').each(function() { if (parseIdr($(this).val()) < 0) validPrice = false; });
    
    if (!validQty) { showError('Terdapat jumlah Qty barang bernilai 0.'); return; }
    
    $('#formAction').val(actionType);
    if (actionType === 'submit') {
        confirmAction('Submit PO?', 'Setelah PO di-submit, Anda tidak dapat mengubah datanya lagi. Lanjutkan?', function() { form.submit(); });
    } else {
        form.submit();
    }
}
</script>
JS;

$extraJS = str_replace('###ITEMS_JSON###', $itemsJson, $extraJS);

require_once __DIR__ . '/../../../includes/footer.php';
?>
