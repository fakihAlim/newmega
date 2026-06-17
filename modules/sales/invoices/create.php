<?php
/**
 * Sales - Create Invoice (from Quotation)
 */
require_once __DIR__ . '/../../../includes/auth.php';
requirePermission('invoice_create');

$user = getCurrentUser();

// Pre-selected quotation (from Quotation view button)
$preQuotationId = $_GET['quotation_id'] ?? null;

// Fetch approved quotations that can still be invoiced
$stmtQ = $pdo->query("
    SELECT q.id, q.quotation_no, q.total, q.company_id, q.customer_id, q.project_id,
           cust.company_name as customer_name, c.name as company_name
    FROM quotations q
    JOIN customers cust ON q.customer_id = cust.id
    JOIN companies c ON q.company_id = c.id
    WHERE q.status IN ('approved', 'invoiced')
      AND q.total > (
          SELECT COALESCE(SUM(total), 0) 
          FROM invoices 
          WHERE quotation_id = q.id 
            AND status != 'rejected'
      )
    ORDER BY q.id DESC
");
$quotations = $stmtQ->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $quotationId = $_POST['quotation_id'] ?? 0;
    $invoiceDate = $_POST['invoice_date'] ?? date('Y-m-d');
    $terminNo = (int) ($_POST['termin_no'] ?? 1);
    $terminDesc = trim($_POST['termin_description'] ?? '');
    $termConditions = trim($_POST['term_and_conditions'] ?? '');
    $action = $_POST['action'] ?? 'draft';

    // Financials
    $subtotal = parseRupiah($_POST['subtotal'] ?? 0);
    $discount = parseRupiah($_POST['discount_nominal'] ?? 0);
    $tax = parseRupiah($_POST['tax_nominal'] ?? 0);
    $shipping = parseRupiah($_POST['shipping_cost'] ?? 0);
    $grandTotal = parseRupiah($_POST['grand_total'] ?? 0);

    // Items
    $descriptions = $_POST['description'] ?? [];
    $specifications = $_POST['type_specification'] ?? [];
    $qtys = $_POST['qty'] ?? [];
    $uoms = $_POST['uom'] ?? [];
    $matPrices = $_POST['material_unit_price'] ?? [];
    $manPrices = $_POST['manpower_unit_price'] ?? [];
    $amounts = $_POST['amount'] ?? [];

    $status = ($action === 'submit') ? 'pending' : 'draft';

    if (empty($quotationId) || empty($descriptions)) {
        setFlash('danger', 'Data tidak lengkap.');
        header('Location: create.php');
        exit;
    }

    try {
        $pdo->beginTransaction();

        // Get quotation info
        $qInfo = $pdo->prepare("SELECT company_id, customer_id FROM quotations WHERE id = ?");
        $qInfo->execute([$quotationId]);
        $qData = $qInfo->fetch();

        // Get customer abbreviation for doc number
        $cStmt = $pdo->prepare("SELECT abbreviation FROM customers WHERE id = ?");
        $cStmt->execute([$qData['customer_id']]);
        $cAbbr = $cStmt->fetchColumn();

        $invoiceNo = generateDocNumber($pdo, 'INV', $cAbbr);

        $stmt = $pdo->prepare("
            INSERT INTO invoices (invoice_no, quotation_id, company_id, customer_id, invoice_date, termin_no, termin_description, subtotal, shipping, tax, discount, total, term_and_conditions, status, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$invoiceNo, $quotationId, $qData['company_id'], $qData['customer_id'], $invoiceDate, $terminNo, $terminDesc, $subtotal, $shipping, $tax, $discount, $grandTotal, $termConditions, $status, $user['id']]);
        $invId = $pdo->lastInsertId();

        // Insert items
        $insertItem = $pdo->prepare("
            INSERT INTO invoice_items (invoice_id, description, type_specification, qty, uom, material_unit_price, material_total, manpower_unit_price, manpower_total, amount)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        for ($i = 0; $i < count($descriptions); $i++) {
            $desc = trim($descriptions[$i]);
            $qty = parseRupiah($qtys[$i] ?? 0);
            $matP = parseRupiah($matPrices[$i] ?? 0);
            $manP = parseRupiah($manPrices[$i] ?? 0);
            $amt = parseRupiah($amounts[$i] ?? 0);

            if ($desc && $qty > 0) {
                $insertItem->execute([$invId, $desc, $specifications[$i] ?? '', $qty, $uoms[$i] ?? '', $matP, $qty * $matP, $manP, $qty * $manP, $amt]);
            }
        }

        // Update quotation status to invoiced
        $pdo->prepare("UPDATE quotations SET status = 'invoiced' WHERE id = ?")->execute([$quotationId]);

        $pdo->commit();
        setFlash('success', "Invoice $invoiceNo berhasil dibuat.");
        header('Location: index.php');
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        setFlash('danger', 'Gagal: ' . $e->getMessage());
    }
}

// If pre-selected, load quotation items
$preItems = [];
$preQuotation = null;
if ($preQuotationId) {
    $stmtPQ = $pdo->prepare("SELECT * FROM quotations WHERE id = ?");
    $stmtPQ->execute([$preQuotationId]);
    $preQuotation = $stmtPQ->fetch();

    $stmtPI = $pdo->prepare("SELECT * FROM quotation_items WHERE quotation_id = ?");
    $stmtPI->execute([$preQuotationId]);
    $preItems = $stmtPI->fetchAll();
}

$pageTitle = 'Buat Invoice';
$breadcrumbs = [
    ['label' => 'Sales', 'url' => '#'],
    ['label' => 'Invoice', 'url' => 'index.php'],
    ['label' => 'Baru']
];

require_once __DIR__ . '/../../../includes/header.php';
?>

<div class="card card-outline card-primary">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-file-invoice-dollar mr-2"></i> Form Pembuatan Invoice</h3>
    </div>

    <form method="POST" id="invForm">
        <div class="card-body bg-light">
            <div class="row">
                <div class="col-md-6 border-right">
                    <h5 class="mb-3 text-secondary text-uppercase font-weight-bold" style="font-size:12px;">1. Referensi
                        Quotation</h5>

                    <div class="form-group row">
                        <label class="col-sm-4 col-form-label">Quotation <span class="text-danger">*</span></label>
                        <div class="col-sm-8">
                            <select name="quotation_id" id="quotation_id" class="form-control select2" required>
                                <option value="">-- Pilih Quotation --</option>
                                <?php foreach ($quotations as $qt): ?>
                                    <option value="<?= $qt['id'] ?>" data-total="<?= $qt['total'] ?>"
                                        data-customer="<?= sanitize($qt['customer_name']) ?>"
                                        <?= ($preQuotationId == $qt['id']) ? 'selected' : '' ?>>
                                        <?= sanitize($qt['quotation_no']) ?> - <?= sanitize($qt['customer_name']) ?>
                                        (<?= formatRupiah($qt['total']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label class="col-sm-4 col-form-label">Tanggal Invoice <span
                                class="text-danger">*</span></label>
                        <div class="col-sm-8">
                            <input type="date" name="invoice_date" class="form-control" value="<?= date('Y-m-d') ?>"
                                required>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <h5 class="mb-3 text-secondary text-uppercase font-weight-bold" style="font-size:12px;">2. Detail
                        Termin</h5>

                    <div class="form-group row">
                        <label class="col-sm-4 col-form-label">Termin Ke-</label>
                        <div class="col-sm-8">
                            <input type="number" name="termin_no" class="form-control" value="1" min="1">
                        </div>
                    </div>

                    <div class="form-group row">
                        <label class="col-sm-4 col-form-label">Keterangan Termin</label>
                        <div class="col-sm-8">
                            <input type="text" name="termin_description" class="form-control"
                                placeholder="Cth: DP 50%, Pelunasan, dll.">
                        </div>
                    </div>

                    <div class="form-group row">
                        <label class="col-sm-4 col-form-label">Syarat & Ketentuan</label>
                        <div class="col-sm-8">
                            <textarea name="term_and_conditions" class="form-control" rows="2"
                                placeholder="Opsi: syarat pembayaran, garansi, dll."></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <hr class="my-3">

            <!-- Items -->
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="text-secondary text-uppercase font-weight-bold m-0" style="font-size:12px;">3. Daftar
                    Pekerjaan</h5>
                <button type="button" class="btn btn-sm btn-primary" id="btnAddRow"><i class="fas fa-plus mr-1"></i>
                    Tambah Baris</button>
            </div>

            <div class="table-responsive mb-3">
                <table class="table table-bordered table-sm" style="font-size:13px;">
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
                    <tbody id="invItemsBody">
                        <?php if (!empty($preItems)): ?>
                            <?php foreach ($preItems as $pi): ?>
                                <tr class="inv-row">
                                    <td><input type="text" name="description[]" class="form-control form-control-sm"
                                            value="<?= sanitize($pi['description']) ?>" required></td>
                                    <td><input type="text" name="type_specification[]" class="form-control form-control-sm"
                                            value="<?= sanitize($pi['type_specification']) ?>"></td>
                                    <td><input type="text" name="qty[]" class="form-control form-control-sm text-center col-qty"
                                            value="<?= (float) $pi['qty'] ?>" required></td>
                                    <td><input type="text" name="uom[]" class="form-control form-control-sm text-center"
                                            value="<?= sanitize($pi['uom']) ?>"></td>
                                    <td><input type="text" name="material_unit_price[]"
                                            class="form-control form-control-sm text-right input-number col-mat-price"
                                            value="<?= number_format($pi['material_unit_price'], 0, ',', '.') ?>"></td>
                                    <td><input type="text" name="manpower_unit_price[]"
                                            class="form-control form-control-sm text-right input-number col-man-price"
                                            value="<?= number_format($pi['manpower_unit_price'], 0, ',', '.') ?>"></td>
                                    <td><input type="text" name="amount[]"
                                            class="form-control form-control-sm text-right font-weight-bold col-amount"
                                            value="<?= number_format($pi['amount'], 0, ',', '.') ?>" readonly></td>
                                    <td class="text-center"><button type="button"
                                            class="btn btn-danger btn-sm btn-remove-row"><i class="fas fa-times"></i></button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr class="empty-row">
                                <td colspan="8" class="text-center text-muted py-4">Pilih Quotation atau klik Tambah Baris.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Summary -->
            <div class="row">
                <div class="col-md-6 offset-md-6">
                    <table class="table table-sm table-borderless font-weight-bold text-right" style="font-size:14px;">
                        <tr>
                            <td width="40%">Subtotal</td>
                            <td><input type="text" name="subtotal" id="calc_subtotal"
                                    class="form-control text-right form-control-sm font-weight-bold" readonly
                                    value="<?= $preQuotation ? number_format($preQuotation['subtotal'], 0, ',', '.') : '0' ?>">
                            </td>
                        </tr>
                        <tr>
                            <td>Diskon</td>
                            <td>
                                <div class="input-group input-group-sm">
                                    <input type="number" id="calc_discount_pct" class="form-control text-center"
                                        placeholder="%" min="0" max="100" step="0.01">
                                    <div class="input-group-prepend input-group-append"><span class="input-group-text">%
                                            | Rp</span></div>
                                    <input type="text" name="discount_nominal" id="calc_discount_nom"
                                        class="form-control text-right mask-rupiah"
                                        value="<?= $preQuotation ? number_format($preQuotation['discount'], 0, ',', '.') : '0' ?>">
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td>Pajak</td>
                            <td>
                                <div class="input-group input-group-sm">
                                    <input type="number" id="calc_tax_pct" class="form-control text-center"
                                        placeholder="%" min="0" max="100" step="0.01" value="11">
                                    <div class="input-group-prepend input-group-append"><span class="input-group-text">%
                                            | Rp</span></div>
                                    <input type="text" name="tax_nominal" id="calc_tax_nom"
                                        class="form-control text-right mask-rupiah"
                                        value="<?= $preQuotation ? number_format($preQuotation['tax'], 0, ',', '.') : '0' ?>">
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td>Ongkos Kirim</td>
                            <td><input type="text" name="shipping_cost" id="calc_shipping"
                                    class="form-control text-right form-control-sm mask-rupiah"
                                    value="<?= $preQuotation ? number_format($preQuotation['shipping'], 0, ',', '.') : '0' ?>">
                            </td>
                        </tr>
                        <tr style="border-top:2px solid #ccc;">
                            <td class="text-danger" style="font-size:16px;">GRAND TOTAL</td>
                            <td><input type="text" name="grand_total" id="calc_grandtotal"
                                    class="form-control text-right text-danger font-weight-bold form-control-lg"
                                    readonly style="font-size:20px;background-color:#fff8f8;"
                                    value="<?= $preQuotation ? number_format($preQuotation['total'], 0, ',', '.') : '0' ?>">
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <div class="card-footer bg-white text-right">
            <input type="hidden" name="action" id="formAction" value="draft">
            <button type="button" class="btn btn-secondary mr-2" onclick="submitForm('draft')"><i
                    class="fas fa-save mr-1"></i> Simpan Draft</button>
            <button type="button" class="btn btn-success" onclick="submitForm('submit')"><i
                    class="fas fa-paper-plane mr-1"></i> Submit untuk Approval</button>
        </div>
    </form>
</div>

<?php
$extraJS = <<<'JS'
<script>
function parseIdr(str) { if (!str) return 0; if (typeof str==='number') return str; return parseFloat(str.toString().replace(/[^0-9]/g,''))||0; }
function formatIdr(num) { return num.toLocaleString('id-ID'); }

function calculateRow(row) {
    var qty=parseIdr(row.find('.col-qty').val()), matP=parseIdr(row.find('.col-mat-price').val()), manP=parseIdr(row.find('.col-man-price').val());
    row.find('.col-amount').val(formatIdr(qty*(matP+manP)));
    calculateGrandTotal('subtotal');
}
function calculateGrandTotal(source) {
    var subtotal=0;
    $('.col-amount').each(function(){subtotal+=parseIdr($(this).val());});
    $('#calc_subtotal').val(formatIdr(subtotal));
    
    var discPctStr = $('#calc_discount_pct').val();
    var discPct = parseFloat(discPctStr);
    if(source === 'discount_pct' || (source === 'subtotal' && discPctStr !== '')) {
        $('#calc_discount_nom').val(formatIdr((subtotal * (isNaN(discPct) ? 0 : discPct)) / 100));
    }
    
    var discNom=parseIdr($('#calc_discount_nom').val());
    var dpp=subtotal-discNom;
    
    var taxPctStr = $('#calc_tax_pct').val();
    var taxPct = parseFloat(taxPctStr);
    if(source === 'tax_pct' || (source === 'subtotal' && taxPctStr !== '')) {
        var tPct = isNaN(taxPct) ? 0 : taxPct;
        if(tPct > 0 && tPct < 100) {
            var grossUpTotal = dpp / ((100 - tPct) / 100);
            var taxAmount = Math.round(grossUpTotal - dpp);
            $('#calc_tax_nom').val(formatIdr(taxAmount));
        } else {
            $('#calc_tax_nom').val('0');
        }
    }
    
    var taxNom=parseIdr($('#calc_tax_nom').val()), shipping=parseIdr($('#calc_shipping').val());
    $('#calc_grandtotal').val(formatIdr(dpp+taxNom+shipping));
}

$(document).ready(function(){
    initSelect2('.select2');
    
    $('#calc_discount_pct').on('input', function() { calculateGrandTotal('discount_pct'); });
    $('#calc_tax_pct').on('input', function() { calculateGrandTotal('tax_pct'); });
    
    $('#calc_discount_nom').on('input', function() {
        $(this).val(formatIdr(parseIdr($(this).val())));
        $('#calc_discount_pct').val('');
        calculateGrandTotal('discount_nom');
    });
    $('#calc_tax_nom').on('input', function() {
        $(this).val(formatIdr(parseIdr($(this).val())));
        $('#calc_tax_pct').val('');
        calculateGrandTotal('tax_nom');
    });
    $('#calc_shipping').on('input', function() {
        $(this).val(formatIdr(parseIdr($(this).val())));
        calculateGrandTotal('shipping');
    });
    
    var tbody=$('#invItemsBody');
    $('#btnAddRow').on('click',function(){
        if(tbody.find('.empty-row').length>0)tbody.empty();
        tbody.append(`<tr class="inv-row">
            <td><input type="text" name="description[]" class="form-control form-control-sm" required placeholder="Deskripsi"></td>
            <td><input type="text" name="type_specification[]" class="form-control form-control-sm" placeholder="Spek"></td>
            <td><input type="text" name="qty[]" class="form-control form-control-sm text-center col-qty" value="1" required></td>
            <td><input type="text" name="uom[]" class="form-control form-control-sm text-center" value="ls"></td>
            <td><input type="text" name="material_unit_price[]" class="form-control form-control-sm text-right input-number col-mat-price" value="0"></td>
            <td><input type="text" name="manpower_unit_price[]" class="form-control form-control-sm text-right input-number col-man-price" value="0"></td>
            <td><input type="text" name="amount[]" class="form-control form-control-sm text-right font-weight-bold col-amount" value="0" readonly></td>
            <td class="text-center"><button type="button" class="btn btn-danger btn-sm btn-remove-row"><i class="fas fa-times"></i></button></td>
        </tr>`);
    });
    
    tbody.on('input','.col-qty,.col-mat-price,.col-man-price',function(){
        if(!$(this).hasClass('col-qty'))$(this).val(formatIdr(parseIdr($(this).val())));
        calculateRow($(this).closest('tr'));
    });
    tbody.on('click','.btn-remove-row',function(){
        $(this).closest('tr').remove();
        if(tbody.find('.inv-row').length===0)tbody.html('<tr class="empty-row"><td colspan="8" class="text-center text-muted py-4">Kosong.</td></tr>');
        calculateGrandTotal('subtotal');
    });
    
    // Load items when quotation is selected from dropdown
    $('#quotation_id').on('change', function() {
        var qId = $(this).val();
        if (!qId) {
            tbody.html('<tr class="empty-row"><td colspan="8" class="text-center text-muted py-4">Pilih Quotation atau klik Tambah Baris.</td></tr>');
            $('#calc_subtotal').val('0');
            $('#calc_discount_nom').val('0');
            $('#calc_tax_nom').val('0');
            $('#calc_shipping').val('0');
            $('#calc_grandtotal').val('0');
            return;
        }
        
        $.ajax({
            url: APP_URL + '/api/get_quotation_items.php',
            data: { quotation_id: qId },
            dataType: 'json',
            success: function(res) {
                tbody.empty();
                if (res.data && res.data.length > 0) {
                    $.each(res.data, function(i, item) {
                        var matPrice = parseFloat(item.material_unit_price) || 0;
                        var manPrice = parseFloat(item.manpower_unit_price) || 0;
                        var qty = parseFloat(item.qty) || 0;
                        var amount = parseFloat(item.amount) || 0;
                        
                        tbody.append(`<tr class="inv-row">
                            <td><input type="text" name="description[]" class="form-control form-control-sm" value="${$('<div>').text(item.description || '').html()}" required></td>
                            <td><input type="text" name="type_specification[]" class="form-control form-control-sm" value="${$('<div>').text(item.type_specification || '').html()}"></td>
                            <td><input type="text" name="qty[]" class="form-control form-control-sm text-center col-qty" value="${qty}" required></td>
                            <td><input type="text" name="uom[]" class="form-control form-control-sm text-center" value="${$('<div>').text(item.uom || '').html()}"></td>
                            <td><input type="text" name="material_unit_price[]" class="form-control form-control-sm text-right input-number col-mat-price" value="${formatIdr(matPrice)}"></td>
                            <td><input type="text" name="manpower_unit_price[]" class="form-control form-control-sm text-right input-number col-man-price" value="${formatIdr(manPrice)}"></td>
                            <td><input type="text" name="amount[]" class="form-control form-control-sm text-right font-weight-bold col-amount" value="${formatIdr(amount)}" readonly></td>
                            <td class="text-center"><button type="button" class="btn btn-danger btn-sm btn-remove-row"><i class="fas fa-times"></i></button></td>
                        </tr>`);
                    });
                    
                    // Update summary fields from quotation data
                    if (res.quotation) {
                        var q = res.quotation;
                        $('#calc_subtotal').val(formatIdr(parseFloat(q.subtotal) || 0));
                        $('#calc_discount_nom').val(formatIdr(parseFloat(q.discount) || 0));
                        $('#calc_tax_nom').val(formatIdr(parseFloat(q.tax) || 0));
                        $('#calc_shipping').val(formatIdr(parseFloat(q.shipping) || 0));
                        $('#calc_grandtotal').val(formatIdr(parseFloat(q.total) || 0));
                    }
                } else {
                    tbody.html('<tr class="empty-row"><td colspan="8" class="text-center text-muted py-4">Quotation ini tidak memiliki item.</td></tr>');
                }
            },
            error: function() {
                alert('Gagal memuat data quotation.');
            }
        });
    });
    
    // Initial calculation if pre-loaded
    if($('.inv-row').length > 0) calculateGrandTotal('subtotal');
});

function submitForm(t){
    if($('.inv-row').length===0){alert('Daftar pekerjaan kosong.');return;}
    $('#formAction').val(t);
    if(t==='submit'){confirmAction('Submit Invoice?','Invoice akan dikirim untuk approval.',function(){$('#invForm').submit();});}
    else{$('#invForm').submit();}
}
</script>
JS;
require_once __DIR__ . '/../../../includes/footer.php';
?>