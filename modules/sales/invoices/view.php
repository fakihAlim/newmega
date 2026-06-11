<?php
/**
 * Sales - View / Approve Invoice
 */
require_once __DIR__ . '/../../../includes/auth.php';
requirePermission('invoice_view');

$id = $_GET['id'] ?? 0;

$sql = "
    SELECT inv.*, 
           c.name as company_name, c.address as company_address, c.city as company_city, c.province as company_province,
           c.phone as company_phone, c.email as company_email, c.logo as company_logo,
           cust.company_name as customer_name, cust.address as customer_address, cust.phone as customer_phone, cust.pic_name as customer_pic,
           q.quotation_no,
           u.full_name as creator_name, u2.full_name as approver_name
    FROM invoices inv
    JOIN companies c ON inv.company_id = c.id
    JOIN customers cust ON inv.customer_id = cust.id
    JOIN quotations q ON inv.quotation_id = q.id
    LEFT JOIN users u ON inv.created_by = u.id
    LEFT JOIN users u2 ON inv.approved_by = u2.id
    WHERE inv.id = ?
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);
$inv = $stmt->fetch();

if (!$inv) {
    setFlash('danger', 'Invoice tidak ditemukan.');
    header('Location: index.php');
    exit;
}

$user = getCurrentUser();

// Handle Approval / Rejection / Send
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $rejectReason = trim($_POST['reject_reason'] ?? '');
    
    if ($action === 'approve' || $action === 'reject') {
        if (!canAccess('invoice_approve')) {
            setFlash('danger', 'Anda tidak memiliki hak akses.');
            header("Location: view.php?id=$id");
            exit;
        }
        if ($inv['status'] !== 'pending') {
            setFlash('danger', 'Hanya Invoice berstatus Pending yang bisa di-approve/reject.');
            header("Location: view.php?id=$id");
            exit;
        }
        
        if ($action === 'approve') {
            $pdo->prepare("UPDATE invoices SET status = 'approved', approved_by = ?, approved_at = NOW() WHERE id = ?")->execute([$user['id'], $id]);
            setFlash('success', "Invoice {$inv['invoice_no']} disetujui.");
        } else {
            $pdo->prepare("UPDATE invoices SET status = 'rejected', approved_by = ?, approved_at = NOW(), reject_reason = ? WHERE id = ?")->execute([$user['id'], $rejectReason, $id]);
            setFlash('danger', "Invoice {$inv['invoice_no']} ditolak.");
        }
    } elseif ($action === 'send') {
        if ($inv['status'] !== 'approved') {
            setFlash('danger', 'Hanya Invoice berstatus Approved yang bisa ditandai Sent.');
            header("Location: view.php?id=$id");
            exit;
        }
        $pdo->prepare("UPDATE invoices SET status = 'sent' WHERE id = ?")->execute([$id]);
        setFlash('info', "Invoice {$inv['invoice_no']} ditandai sebagai 'Sent'.");
    }
    
    header("Location: view.php?id=$id");
    exit;
}

$stmtItems = $pdo->prepare("SELECT * FROM invoice_items WHERE invoice_id = ?");
$stmtItems->execute([$id]);
$items = $stmtItems->fetchAll();

// Payment summary
$stmtPay = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM customer_payments WHERE invoice_id = ?");
$stmtPay->execute([$id]);
$totalPaid = $stmtPay->fetchColumn();
$outstanding = $inv['total'] - $totalPaid;

$pageTitle = 'Detail Invoice: ' . sanitize($inv['invoice_no']);
$breadcrumbs = [
    ['label' => 'Sales', 'url' => '#'],
    ['label' => 'Invoice', 'url' => 'index.php'],
    ['label' => 'Detail']
];

require_once __DIR__ . '/../../../includes/header.php';
?>

<div class="card card-outline card-info">
    <div class="card-header d-flex justify-content-between align-items-center d-print-none">
        <h3 class="card-title text-info"><i class="fas fa-file-invoice-dollar mr-2"></i> Invoice: <strong><?= sanitize($inv['invoice_no']) ?></strong></h3>
        <div class="ml-auto">
            <?= getStatusBadge($inv['status']) ?>
            <button class="btn btn-default btn-sm ml-3" onclick="window.print()"><i class="fas fa-print mr-1"></i> Cetak Invoice</button>
            <a href="index.php" class="btn btn-secondary btn-sm ml-1"><i class="fas fa-arrow-left mr-1"></i> Kembali</a>
        </div>
    </div>
    
    <div class="card-body printable-area p-4 bg-white" style="color: #000; font-family: Arial, sans-serif;">
        <!-- Title and Invoice No/Date -->
        <div class="d-flex justify-content-between align-items-start mb-4">
            <div style="flex: 1;"></div>
            <div style="flex: 1; text-align: center;">
                <h1 class="font-weight-bold m-0" style="font-size: 48px; color: #000; letter-spacing: 1px;">INVOICE</h1>
            </div>
            <div style="flex: 1; text-align: right;">
                <table class="table-sm table-borderless font-weight-bold" style="font-size: 15px; margin-left: auto;">
                    <tr>
                        <td class="text-left pr-2 pb-0">Invoice No</td>
                        <td class="text-left pb-0"><?= sanitize($inv['invoice_no']) ?></td>
                    </tr>
                    <tr>
                        <td class="text-left pr-2 pt-0">Date</td>
                        <td class="text-left pt-0"><?= date('j-M-Y', strtotime($inv['invoice_date'])) ?></td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- From and To -->
        <div class="row no-gutters mb-3" style="border: 1px solid #000;">
            <div class="col-sm-7 p-2" style="border-right: 1px solid #000 !important;">
                <div style="font-size: 14px;">From</div>
                <h4 class="font-weight-bold mb-1" style="color: #000; font-size: 18px;"><?= sanitize($inv['company_name']) ?></h4>
                <div style="font-size: 13px; line-height: 1.5; color: #000;">
                    <?= nl2br(sanitize($inv['company_address'])) ?><br>
                    <?= sanitize($inv['company_city']) ?>, <?= sanitize($inv['company_province']) ?>, Indonesia. Kode Pos : ....<br>
                    Email: <?= sanitize($inv['company_email']) ?> ,Phone: <?= sanitize($inv['company_phone']) ?>
                </div>
            </div>
            <div class="col-sm-5 p-2">
                <div style="font-size: 14px; font-weight: bold;">To</div>
                <h4 class="font-weight-bold mb-1" style="color: #000; font-size: 18px;"><?= sanitize($inv['customer_name']) ?></h4>
                <div style="font-size: 13px; color: #000; line-height: 1.5;">
                    <?= nl2br(sanitize($inv['customer_address'])) ?><br>
                    Phone: <?= sanitize($inv['customer_phone']) ?><br>
                </div>
            </div>
        </div>

        <!-- Items Table -->
        <div class="table-responsive mb-3">
            <table class="table table-bordered table-sm print-table mb-0" style="font-size: 13px; border: 1px solid #000; border-collapse: collapse;">
                <thead style="background-color: #f2f2f2 !important;">
                    <tr class="text-center font-weight-bold" style="color: #000;">
                        <th rowspan="2" class="align-bottom p-1" style="border: 1px solid #000; border-bottom: 2px solid #000;">No</th>
                        <th rowspan="2" class="align-bottom p-1" style="border: 1px solid #000; border-bottom: 2px solid #000; text-align: left;">Description</th>
                        <th rowspan="2" class="align-bottom p-1" style="border: 1px solid #000; border-bottom: 2px solid #000;">Type</th>
                        <th rowspan="2" class="align-bottom p-1" style="border: 1px solid #000; border-bottom: 2px solid #000;">Qty</th>
                        <th rowspan="2" class="align-bottom p-1" style="border: 1px solid #000; border-bottom: 2px solid #000;">Uom</th>
                        <th colspan="2" class="p-1" style="border: 1px solid #000;">MATERIAL</th>
                        <th colspan="2" class="p-1" style="border: 1px solid #000;">MANPOWER</th>
                        <th rowspan="2" class="align-bottom p-1" style="border: 1px solid #000; border-bottom: 2px solid #000;">Amount</th>
                    </tr>
                    <tr class="text-center font-weight-bold" style="color: #000;">
                        <th class="p-1" style="border: 1px solid #000; border-bottom: 2px solid #000;">Unit Price</th>
                        <th class="p-1" style="border: 1px solid #000; border-bottom: 2px solid #000;">Total Material</th>
                        <th class="p-1" style="border: 1px solid #000; border-bottom: 2px solid #000;">Unit Price</th>
                        <th class="p-1" style="border: 1px solid #000; border-bottom: 2px solid #000;">Total Manpower</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $no = 1; foreach ($items as $item): ?>
                    <tr>
                        <td class="text-right p-1" style="border: 1px solid #000; color: #000;"><?= $no++ ?></td>
                        <td class="p-1" style="border: 1px solid #000; color: #000;"><?= sanitize($item['description']) ?></td>
                        <td class="p-1" style="border: 1px solid #000; color: #000;"><?= sanitize($item['type_specification']) ?: '-' ?></td>
                        <td class="text-right p-1" style="border: 1px solid #000; color: #000;"><?= number_format($item['qty'], 0, ',', '.') ?></td>
                        <td class="p-1" style="border: 1px solid #000; color: #000;"><?= sanitize($item['uom']) ?></td>
                        <td class="text-right p-1" style="border: 1px solid #000; color: #000;"><?= number_format($item['material_unit_price'], 0, ',', '.') ?></td>
                        <td class="text-right p-1" style="border: 1px solid #000; color: #000;"><?= number_format($item['material_total'], 0, ',', '.') ?></td>
                        <td class="text-right p-1" style="border: 1px solid #000; color: #000;"><?= number_format($item['manpower_unit_price'], 0, ',', '.') ?></td>
                        <td class="text-right p-1" style="border: 1px solid #000; color: #000;"><?= number_format($item['manpower_total'], 0, ',', '.') ?></td>
                        <td class="text-right p-1" style="border: 1px solid #000; color: #000;"><?= number_format($item['amount'], 0, ',', '.') ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <!-- Empty row to match exactly the design layout -->
                    <tr>
                        <td class="text-right p-1" style="border: 1px solid #000; color: #000;"><?= $no ?></td>
                        <td class="p-1" style="border: 1px solid #000;"></td>
                        <td class="p-1" style="border: 1px solid #000;"></td>
                        <td class="p-1" style="border: 1px solid #000;"></td>
                        <td class="p-1" style="border: 1px solid #000;"></td>
                        <td class="p-1" style="border: 1px solid #000;"></td>
                        <td class="p-1" style="border: 1px solid #000;"></td>
                        <td class="p-1" style="border: 1px solid #000;"></td>
                        <td class="p-1" style="border: 1px solid #000;"></td>
                        <td class="p-1" style="border: 1px solid #000;"></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Summary & Term Condition -->
        <div class="row no-gutters">
            <div class="col-sm-7 pr-1 d-flex flex-column">
                <div class="p-1 px-2 text-white font-weight-bold" style="background-color: #666 !important; border: 1px solid #000; font-size: 12px;">
                    Term and Conditions :
                </div>
                <div class="p-2 flex-grow-1" style="border: 1px solid #000 !important; font-size: 11px; border-top: none !important; color: #000; min-height: 60px;">
                    <?= $inv['term_and_conditions'] ? nl2br(sanitize($inv['term_and_conditions'])) : 'pembayaran TT 100% , Rekening<br>BCA (1051613566)<br>a.n PT Mega Karya Modern' ?>
                </div>
            </div>

            <div class="col-sm-5 pl-0">
                <table class="table-sm table-bordered w-100 h-100" style="font-size:14px; border: 1px solid #000;">
                    <tr>
                        <td class="text-left pl-3 py-1 color-black bg-light" style="border: 1px solid #000;">Subtotal</td>
                        <td class="py-1 text-right px-2" style="color: #000; border: 1px solid #000;"><?= number_format($inv['subtotal'], 0, ',', '.') ?></td>
                    </tr>
                    <tr>
                        <td class="text-left pl-3 py-1 color-black bg-light" style="border: 1px solid #000;">Shipping</td>
                        <td class="py-1 text-right px-2" style="color: #000; border: 1px solid #000;"><?= number_format($inv['shipping'], 0, ',', '.') ?></td>
                    </tr>
                    <tr>
                        <td class="text-left pl-3 py-1 color-black bg-light" style="border: 1px solid #000;">Tax</td>
                        <td class="py-1 text-right px-2" style="color: #000; border: 1px solid #000;"><?= number_format($inv['tax'], 0, ',', '.') ?></td>
                    </tr>
                    <tr>
                        <td class="text-left pl-3 py-1 color-black bg-light" style="border: 1px solid #000;">Discount</td>
                        <td class="py-1 text-right px-2" style="color: #000; border: 1px solid #000;"><?= number_format($inv['discount'], 0, ',', '.') ?></td>
                    </tr>
                    <tr style="background-color: #f2f2f2;">
                        <td class="text-left pl-3 py-1 font-weight-bold color-black" style="border: 1px solid #000; font-size: 15px;">Total</td>
                        <td class="py-1 font-weight-bold text-right px-2" style="color: #000; font-size: 16px; border: 1px solid #000;"><?= number_format($inv['total'], 0, ',', '.') ?></td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Signatures -->
        <div class="row mt-4 pt-2 text-center" style="font-size:12px; color: #000;">
            <div class="col-sm-4 offset-sm-8">
                <div style="padding: 10px;">
                    <p class="mb-5 font-weight-bold text-uppercase">Hormat Kami,</p>
                    <div style="height: 60px;"></div>
                    <strong class="text-uppercase" style="text-decoration: underline;">
                        <?= sanitize($inv['creator_name']) ?>
                    </strong><br>
                    <span>Authorized Signature</span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Alerts / Payment Status -->
    <div class="px-4 pb-4">
        <?php if ($inv['reject_reason']): ?>
        <div class="alert alert-danger mt-3 d-print-none" style="font-size: 12px;">
            <strong>Alasan Penolakan:</strong><br><?= nl2br(sanitize($inv['reject_reason'])) ?>
        </div>
        <?php endif; ?>
        
        <?php if ($totalPaid > 0): ?>
        <div class="alert alert-success mt-3 d-print-none" style="font-size: 12px;">
            <strong>Status Pembayaran:</strong><br>
            Total Diterima: <strong><?= formatRupiah($totalPaid) ?></strong> |
            Sisa: <strong class="<?= $outstanding > 0 ? 'text-danger' : '' ?>"><?= formatRupiah($outstanding) ?></strong>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Actions -->
    <?php if ($inv['status'] === 'pending' && canAccess('invoice_approve')): ?>
    <div class="card-footer bg-light text-right d-print-none">
        <form method="POST" id="formApprove" class="d-inline">
            <input type="hidden" name="action" value="approve">
            <button type="button" class="btn btn-success" onclick="confirmAction('Setujui Invoice?', 'Invoice ini akan disetujui.', function() { $('#formApprove').submit(); })">
                <i class="fas fa-check mr-1"></i> Approve
            </button>
        </form>
        <button type="button" class="btn btn-danger ml-2" data-toggle="modal" data-target="#modalReject">
            <i class="fas fa-times mr-1"></i> Reject
        </button>
    </div>
    <?php endif; ?>
    
    <?php if ($inv['status'] === 'approved' && canAccess('invoice_view')): ?>
    <div class="card-footer bg-light text-right d-print-none">
        <form method="POST" id="formSend" class="d-inline">
            <input type="hidden" name="action" value="send">
            <button type="button" class="btn btn-primary" onclick="confirmAction('Kirim Invoice?', 'Invoice akan ditandai sebagai Sent ke Customer.', function() { $('#formSend').submit(); })">
                <i class="fas fa-paper-plane mr-1"></i> Tandai Sent
            </button>
        </form>
    </div>
    <?php endif; ?>
</div>

<!-- Modal Reject -->
<div class="modal fade" id="modalReject" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST">
            <div class="modal-content border-danger">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">Tolak Invoice</h5>
                    <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="reject">
                    <div class="form-group">
                        <label>Alasan Penolakan <span class="text-danger">*</span></label>
                        <textarea name="reject_reason" class="form-control" rows="3" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger">Tolak</button>
                </div>
            </div>
        </form>
    </div>
</div>

<style>
@media print {
    @page {
        size: A4 portrait;
        margin: 10mm;
    }
    body { background-color: white !important; }
    .main-sidebar, .main-header, .d-print-none, .card-footer, .breadcrumb, .content-header { display: none !important; }
    .content-wrapper { margin-left: 0 !important; padding: 0 !important; }
    .card { border: none !important; box-shadow: none !important; }
    .card-header { display: none !important; }
    .printable-area { width: 100% !important; margin: 0 !important; padding: 0 !important; color: #000 !important; }
    .printable-area * { color: #000 !important; }
    .color-black { color: #000 !important; }
    .print-table thead, .print-table thead th { background-color: #f2f2f2 !important; -webkit-print-color-adjust: exact; color: #000 !important; }
    /* Ensure borders print properly */
    .print-table, .print-table th, .print-table td { border: 1px solid #000 !important; }
}
.printable-area { color: #000 !important; }
.printable-area * { color: #000 !important; }
.color-black { color: #000 !important; }
</style>

<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>
