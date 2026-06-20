<?php
/**
 * Finance - Create Customer Payment (Penerimaan)
 */
require_once __DIR__ . '/../../../includes/auth.php';
requirePermission('customer_payments');

$user = getCurrentUser();

// Fetch Invoices eligible for payment (approved, sent, partial_paid)
$stmtInv = $pdo->query("
    SELECT inv.id, inv.invoice_no, inv.total, inv.invoice_date, 
           cust.company_name as customer_name,
           COALESCE(SUM(cp.amount), 0) as total_paid
    FROM invoices inv
    JOIN customers cust ON inv.customer_id = cust.id
    LEFT JOIN customer_payments cp ON cp.invoice_id = inv.id
    WHERE inv.status IN ('approved', 'sent', 'partial_paid')
    GROUP BY inv.id
    HAVING inv.total > total_paid
    ORDER BY inv.id DESC
");
$eligibleInvoices = $stmtInv->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $invoiceId = $_POST['invoice_id'] ?? 0;
    $paymentDate = $_POST['payment_date'] ?? date('Y-m-d');
    $amount = (float)str_replace(['.', ','], ['', '.'], $_POST['amount'] ?? 0);
    $paymentMethod = $_POST['payment_method'] ?? '';
    $referenceNo = $_POST['reference_no'] ?? '';
    $notes = trim($_POST['notes'] ?? '');
    
    if (empty($invoiceId) || $amount <= 0) {
        setFlash('danger', 'Invoice dan Jumlah harus valid.');
        header('Location: create.php');
        exit;
    }
    
    // Re-validate outstanding
    $stmtCheck = $pdo->prepare("
        SELECT inv.total, COALESCE(SUM(cp.amount), 0) as total_paid
        FROM invoices inv
        LEFT JOIN customer_payments cp ON cp.invoice_id = inv.id
        WHERE inv.id = ?
        GROUP BY inv.id
    ");
    $stmtCheck->execute([$invoiceId]);
    $check = $stmtCheck->fetch();
    $outstanding = $check['total'] - $check['total_paid'];
    
    if ($amount > $outstanding) {
        setFlash('danger', 'Jumlah penerimaan (' . formatRupiah($amount) . ') melebihi sisa outstanding (' . formatRupiah($outstanding) . ').');
        header('Location: create.php');
        exit;
    }
    
    try {
        $pdo->beginTransaction();
        
        // Insert payment
        $stmt = $pdo->prepare("
            INSERT INTO customer_payments (invoice_id, payment_date, amount, payment_method, reference_no, notes, received_by)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$invoiceId, $paymentDate, $amount, $paymentMethod, $referenceNo, $notes, $user['id']]);
        
        // Re-check total paid to update invoice status
        $stmtNewTotal = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM customer_payments WHERE invoice_id = ?");
        $stmtNewTotal->execute([$invoiceId]);
        $newTotalPaid = $stmtNewTotal->fetchColumn();
        
        if ($newTotalPaid >= $check['total']) {
            $pdo->prepare("UPDATE invoices SET status = 'paid' WHERE id = ?")->execute([$invoiceId]);
        } else {
            $pdo->prepare("UPDATE invoices SET status = 'partial_paid' WHERE id = ?")->execute([$invoiceId]);
        }
        
        $pdo->commit();
        setFlash('success', 'Penerimaan pembayaran customer berhasil dicatat.');
        header('Location: index.php');
        exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log('[NEWMEGA] ' . $e->getMessage());
        setFlash('danger', 'Gagal menyimpan penerimaan. Terjadi kesalahan sistem.');
    }
}

$pageTitle = 'Catat Penerimaan Pelanggan';
$breadcrumbs = [
    ['label' => 'Finance', 'url' => '#'],
    ['label' => 'Penerimaan Pelanggan', 'url' => 'index.php'],
    ['label' => 'Baru']
];

require_once __DIR__ . '/../../../includes/header.php';
?>

<div class="row">
    <div class="col-md-12">
        <form method="POST" id="formPayment">
            <div class="card card-outline card-primary">
                <div class="card-header">
                    <h3 class="card-title text-primary font-weight-bold">Detail Penerimaan</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group row">
                                <label class="col-sm-4 col-form-label">Pilih Invoice <span class="text-danger">*</span></label>
                                <div class="col-sm-8">
                                    <select class="form-control select2" name="invoice_id" id="invoice_id" required>
                                        <option value="">-- Pilih Invoice --</option>
                                        <?php foreach ($eligibleInvoices as $inv): ?>
                                            <option value="<?= $inv['id'] ?>" 
                                                    data-total="<?= $inv['total'] ?>" 
                                                    data-paid="<?= $inv['total_paid'] ?>"
                                                    data-customer="<?= sanitize($inv['customer_name']) ?>">
                                                <?= sanitize($inv['invoice_no']) ?> - <?= sanitize($inv['customer_name']) ?> (<?= formatRupiah($inv['total']) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group row">
                                <label class="col-sm-4 col-form-label">Tgl Terima <span class="text-danger">*</span></label>
                                <div class="col-sm-8">
                                    <input type="date" name="payment_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Invoice Info Panel -->
                    <div id="invInfoPanel" class="alert alert-info" style="display:none;">
                        <div class="row">
                            <div class="col-md-4"><strong>Pelanggan:</strong> <span id="infoCustomer">-</span></div>
                            <div class="col-md-4"><strong>Total Invoice:</strong> <span id="infoTotal">-</span></div>
                            <div class="col-md-4"><strong>Sisa Outstanding:</strong> <span id="infoOutstanding" class="text-danger font-weight-bold">-</span></div>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group row">
                                <label class="col-sm-4 col-form-label">Jumlah (Rp) <span class="text-danger">*</span></label>
                                <div class="col-sm-8">
                                    <input type="text" name="amount" id="inputAmount" class="form-control" required placeholder="Contoh: 10.000.000">
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
                                <label class="col-sm-4 col-form-label">No. Referensi</label>
                                <div class="col-sm-8">
                                    <input type="text" name="reference_no" class="form-control" placeholder="Cth: TRF-xxx">
                                </div>
                            </div>
                        </div>
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
                    <button type="submit" class="btn btn-primary ml-2">Simpan Penerimaan</button>
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
    
    // Invoice Selection Handler
    $('#invoice_id').on('change', function() {
        let opt = $(this).find(':selected');
        if (!opt.val()) {
            $('#invInfoPanel').hide();
            return;
        }
        
        let total = parseFloat(opt.data('total')) || 0;
        let paid = parseFloat(opt.data('paid')) || 0;
        let outstanding = total - paid;
        let customer = opt.data('customer');
        
        $('#infoCustomer').text(customer);
        $('#infoTotal').text('Rp ' + total.toLocaleString('id-ID'));
        $('#infoOutstanding').text('Rp ' + outstanding.toLocaleString('id-ID'));
        $('#invInfoPanel').show();
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
