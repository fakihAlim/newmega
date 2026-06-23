<?php
/**
 * Finance - Create Vendor Payment
 */
require_once __DIR__ . '/../../../includes/auth.php';
requirePermission('vendor_payments');

$user = getCurrentUser();

// Fetch POs eligible for payment (approved, partially_received, completed)
$stmtPOs = $pdo->query("
    SELECT po.id, po.po_number, po.total, po.po_date, v.company_name as vendor_name,
           v.bank_name as vendor_bank_name, v.bank_account as vendor_bank_account, v.bank_holder as vendor_bank_holder,
           COALESCE(SUM(vp.amount), 0) as total_paid
    FROM purchase_orders po
    JOIN vendors v ON po.vendor_id = v.id
    LEFT JOIN vendor_payments vp ON vp.po_id = po.id
    WHERE po.status IN ('approved', 'partially_received', 'completed')
    GROUP BY po.id
    HAVING po.total > total_paid
    ORDER BY po.id DESC
");
$eligiblePOs = $stmtPOs->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $poId = $_POST['po_id'] ?? 0;
    $paymentDate = $_POST['payment_date'] ?? date('Y-m-d');
    $amount = (float)str_replace(['.', ','], ['', '.'], $_POST['amount'] ?? 0);
    $paymentMethod = $_POST['payment_method'] ?? '';
    $paymentTerm = $_POST['payment_term'] ?? '';
    $bankName = $_POST['bank_name'] ?? '';
    $bankAccount = $_POST['bank_account'] ?? '';
    $referenceNo = $_POST['reference_no'] ?? '';
    $notes = trim($_POST['notes'] ?? '');
    
    if (empty($poId) || $amount <= 0) {
        setFlash('danger', 'PO dan Jumlah pembayaran harus valid.');
        header('Location: create.php');
        exit;
    }
    
    // Re-validate outstanding
    $stmtCheck = $pdo->prepare("
        SELECT po.total, COALESCE(SUM(vp.amount), 0) as total_paid
        FROM purchase_orders po
        LEFT JOIN vendor_payments vp ON vp.po_id = po.id
        WHERE po.id = ?
        GROUP BY po.id
    ");
    $stmtCheck->execute([$poId]);
    $check = $stmtCheck->fetch();
    
    $outstanding = $check['total'] - $check['total_paid'];
    
    if ($amount > $outstanding) {
        setFlash('danger', 'Jumlah pembayaran (' . formatRupiah($amount) . ') melebihi sisa outstanding (' . formatRupiah($outstanding) . ').');
        header('Location: create.php');
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO vendor_payments (po_id, payment_date, amount, payment_method, payment_term, bank_name, bank_account, reference_no, notes, paid_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$poId, $paymentDate, $amount, $paymentMethod, $paymentTerm, $bankName, $bankAccount, $referenceNo, $notes, $user['id']]);
        $paymentId = $pdo->lastInsertId();
        
        $stmtPoNo = $pdo->prepare("SELECT po_number FROM purchase_orders WHERE id = ?");
        $stmtPoNo->execute([$poId]);
        $poNo = $stmtPoNo->fetchColumn();
        logActivity('create', 'finance', "Mencatat pembayaran supplier untuk PO: {$poNo} sebesar Rp " . number_format($amount, 0, ',', '.'), 'vendor_payments', $paymentId);
        
        setFlash('success', 'Pembayaran vendor berhasil dicatat.');
        header('Location: index.php');
        exit;
        
    } catch (Exception $e) {
        error_log('[NEWMEGA] ' . $e->getMessage());
        setFlash('danger', 'Gagal menyimpan pembayaran. Terjadi kesalahan sistem.');
    }
}

$pageTitle = 'Catat Pembayaran Supplier';
$breadcrumbs = [
    ['label' => 'Finance', 'url' => '#'],
    ['label' => 'Pembayaran Supplier', 'url' => 'index.php'],
    ['label' => 'Baru']
];

require_once __DIR__ . '/../../../includes/header.php';
?>

<div class="row">
    <div class="col-md-12">
        <form method="POST" id="formPayment">
            <div class="card card-outline card-primary">
                <div class="card-header">
                    <h3 class="card-title text-primary font-weight-bold">Detail Pembayaran</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group row">
                                <label class="col-sm-4 col-form-label">Pilih PO <span class="text-danger">*</span></label>
                                <div class="col-sm-8">
                                    <select class="form-control select2" name="po_id" id="po_id" required>
                                        <option value="">-- Pilih PO --</option>
                                        <?php foreach ($eligiblePOs as $po): ?>
                                            <option value="<?= $po['id'] ?>" 
                                                    data-total="<?= $po['total'] ?>" 
                                                    data-paid="<?= $po['total_paid'] ?>"
                                                    data-vendor="<?= sanitize($po['vendor_name']) ?>"
                                                    data-bank-name="<?= sanitize($po['vendor_bank_name']) ?>"
                                                    data-bank-account="<?= sanitize($po['vendor_bank_account']) ?>"
                                                    data-bank-holder="<?= sanitize($po['vendor_bank_holder']) ?>">
                                                <?= sanitize($po['po_number']) ?> - <?= sanitize($po['vendor_name']) ?> (<?= formatRupiah($po['total']) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group row">
                                <label class="col-sm-4 col-form-label">Tgl Bayar <span class="text-danger">*</span></label>
                                <div class="col-sm-8">
                                    <input type="date" name="payment_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- PO Info Panel -->
                    <div id="poInfoPanel" class="alert alert-info" style="display:none;">
                        <div class="row">
                            <div class="col-md-4"><strong>Supplier:</strong> <span id="infoVendor">-</span></div>
                            <div class="col-md-4"><strong>Total PO:</strong> <span id="infoTotal">-</span></div>
                            <div class="col-md-4"><strong>Sisa Outstanding:</strong> <span id="infoOutstanding" class="text-danger font-weight-bold">-</span></div>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group row">
                                <label class="col-sm-4 col-form-label">Jumlah (Rp) <span class="text-danger">*</span></label>
                                <div class="col-sm-8">
                                    <input type="text" name="amount" id="inputAmount" class="form-control" required placeholder="Contoh: 5.000.000">
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group row">
                                <label class="col-sm-4 col-form-label">Metode</label>
                                <div class="col-sm-8">
                                    <select name="payment_method" class="form-control">
                                        <option value="">-- Pilih Metode --</option>
                                        <option value="Transfer Bank">Transfer Bank</option>
                                        <option value="Cash">Cash (Tunai)</option>
                                        <option value="Cek / Giro">Cek / Giro</option>
                                        <option value="E-Wallet">E-Wallet</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group row">
                                <label class="col-sm-4 col-form-label">Termin</label>
                                <div class="col-sm-8">
                                    <input type="text" name="payment_term" class="form-control" placeholder="Cth: DP 50%">
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group row">
                                <label class="col-sm-4 col-form-label">Bank</label>
                                <div class="col-sm-8">
                                    <input type="text" name="bank_name" id="bankName" class="form-control" placeholder="Cth: BCA">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group row">
                                <label class="col-sm-4 col-form-label">Rekening</label>
                                <div class="col-sm-8">
                                    <input type="text" name="bank_account" id="bankAccount" class="form-control" placeholder="Cth: 123456789">
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group row">
                                <label class="col-sm-4 col-form-label">No. Ref / Bukti</label>
                                <div class="col-sm-8">
                                    <input type="text" name="reference_no" class="form-control" placeholder="Cth: TRF-0001234">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group row">
                                <label class="col-sm-4 col-form-label">Catatan</label>
                                <div class="col-sm-8">
                                    <textarea name="notes" class="form-control" rows="2" placeholder="Opsional..."></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer text-right">
                    <a href="index.php" class="btn btn-default">Batal</a>
                    <button type="submit" class="btn btn-primary ml-2">Simpan Pembayaran</button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php
$extraJS = <<<'JS'
<script>
$(document).ready(function() {
    initSelect2('.select2');
    
    // PO Selection Handler
    $('#po_id').on('change', function() {
        let opt = $(this).find(':selected');
        if (!opt.val()) {
            $('#poInfoPanel').hide();
            return;
        }
        
        let total = parseFloat(opt.data('total')) || 0;
        let paid = parseFloat(opt.data('paid')) || 0;
        let outstanding = total - paid;
        let vendor = opt.data('vendor');
        let bankName = opt.data('bank-name');
        let bankAccount = opt.data('bank-account');
        
        $('#infoVendor').text(vendor);
        $('#infoTotal').text('Rp ' + total.toLocaleString('id-ID'));
        $('#infoOutstanding').text('Rp ' + outstanding.toLocaleString('id-ID'));
        $('#poInfoPanel').show();
        
        // Pre-fill bank info
        if (bankName) $('#bankName').val(bankName);
        if (bankAccount) $('#bankAccount').val(bankAccount);
    });
    
    // Format Rupiah on input
    $('#inputAmount').on('keyup', function() {
        let val = $(this).val().replace(/[^0-9]/g, '');
        if (val) {
            $(this).val(parseInt(val).toLocaleString('id-ID'));
        }
    });
});
</script>
JS;
require_once __DIR__ . '/../../../includes/footer.php';
?>
