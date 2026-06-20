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

<div class="card card-outline card-primary">
    <div class="card-header d-flex justify-content-between align-items-center d-print-none">
        <h3 class="card-title text-primary"><i class="fas fa-file-invoice-dollar mr-2"></i> Invoice: <strong><?= sanitize($inv['invoice_no']) ?></strong></h3>
        <div class="ml-auto">
            <?= getStatusBadge($inv['status']) ?>
            <button class="btn btn-default btn-sm ml-3" onclick="window.print()"><i class="fas fa-print mr-1"></i> Cetak Invoice</button>
            <a href="index.php" class="btn btn-secondary btn-sm ml-1"><i class="fas fa-arrow-left mr-1"></i> Kembali</a>
        </div>
    </div>
    
    <div class="card-body printable-area p-4 bg-white">
        <!-- Title and Invoice No/Date -->
        <div class="d-flex justify-content-between align-items-start mb-4">
            <div style="flex: 1;"></div>
            <div style="flex: 1; text-align: center;">
                <h1 class="font-weight-bold m-0" style="font-size: 48px; letter-spacing: 1px;">INVOICE</h1>
            </div>
            <div style="flex: 1; text-align: right;">
                <table class="table-sm table-borderless font-weight-bold" style="margin-left: auto;">
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
        <div class="row no-gutters mb-4" style="gap: 20px; display: flex;">
            <div class="report-info-col col p-3" style="border: 1px solid #e2e8f0; border-radius: 6px; background-color: #f8fafc; flex: 1;">
                <div style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: #64748b; letter-spacing: 0.5px; margin-bottom: 6px; border-bottom: 1px solid #e2e8f0; padding-bottom: 4px;">From</div>
                <h4 class="font-weight-bold mb-1" style="font-size: 20px;"><?= sanitize($inv['company_name']) ?></h4>
                <div style="font-size: 12px; line-height: 1.5; color: #334155;">
                    <?= nl2br(sanitize($inv['company_address'])) ?><br>
                    <?= sanitize($inv['company_city']) ?>, <?= sanitize($inv['company_province']) ?>, Indonesia.<br>
                    Email: <?= sanitize($inv['company_email']) ?> | Phone: <?= sanitize($inv['company_phone']) ?>
                </div>
            </div>
            <div class="report-info-col col p-3" style="border: 1px solid #e2e8f0; border-radius: 6px; background-color: #f8fafc; flex: 1;">
                <div style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: #64748b; letter-spacing: 0.5px; margin-bottom: 6px; border-bottom: 1px solid #e2e8f0; padding-bottom: 4px;">To</div>
                <h4 class="font-weight-bold mb-1" style="font-size: 20px;"><?= sanitize($inv['customer_name']) ?></h4>
                <div style="font-size: 12px; line-height: 1.5; color: #334155;">
                    <?= nl2br(sanitize($inv['customer_address'])) ?><br>
                    Phone: <?= sanitize($inv['customer_phone']) ?><br>
                    <?php if ($inv['customer_pic']): ?>
                        PIC: <?= sanitize($inv['customer_pic']) ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Items Table -->
        <div class="table-responsive mb-4">
            <table class="table table-bordered table-sm report-table mb-0" style="width: 100%;">
                <thead>
                    <tr class="text-center font-weight-bold">
                        <th rowspan="2" class="align-middle">No</th>
                        <th rowspan="2" class="align-middle text-left">Description</th>
                        <th rowspan="2" class="align-middle text-center">Type</th>
                        <th rowspan="2" class="align-middle text-right">Qty</th>
                        <th rowspan="2" class="align-middle text-center">Uom</th>
                        <th colspan="2" class="text-center">MATERIAL</th>
                        <th colspan="2" class="text-center">MANPOWER</th>
                        <th rowspan="2" class="align-middle text-right">Amount</th>
                    </tr>
                    <tr class="text-center font-weight-bold">
                        <th class="text-right">Unit Price</th>
                        <th class="text-right">Total Material</th>
                        <th class="text-right">Unit Price</th>
                        <th class="text-right">Total Manpower</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $no = 1; foreach ($items as $item): ?>
                    <tr>
                        <td class="text-center align-middle"><?= $no++ ?></td>
                        <td class="align-middle"><?= sanitize($item['description']) ?></td>
                        <td class="align-middle text-center"><?= sanitize($item['type_specification']) ?: '-' ?></td>
                        <td class="text-right align-middle"><?= number_format($item['qty'], 0, ',', '.') ?></td>
                        <td class="text-center align-middle"><?= sanitize($item['uom']) ?></td>
                        <td class="text-right align-middle"><?= number_format($item['material_unit_price'], 0, ',', '.') ?></td>
                        <td class="text-right align-middle"><?= number_format($item['material_total'], 0, ',', '.') ?></td>
                        <td class="text-right align-middle"><?= number_format($item['manpower_unit_price'], 0, ',', '.') ?></td>
                        <td class="text-right align-middle"><?= number_format($item['manpower_total'], 0, ',', '.') ?></td>
                        <td class="text-right align-middle"><?= number_format($item['amount'], 0, ',', '.') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Summary & Term Condition -->
        <div class="row no-gutters mb-4" style="gap: 20px; display: flex;">
            <div class="col pr-0 d-flex flex-column" style="flex: 7;">
                <div class="p-2 px-3 font-weight-bold report-terms-header">
                    Term and Conditions :
                </div>
                <div class="p-3 flex-grow-1 report-terms-body">
                    <?= $inv['term_and_conditions'] ? nl2br(sanitize($inv['term_and_conditions'])) : 'pembayaran TT 100% , Rekening<br>BCA (1051613566)<br>a.n PT Mega Karya Modern' ?>
                </div>
            </div>

            <div class="col pl-0" style="flex: 5;">
                <table class="table-sm table-bordered report-summary-table w-100 h-100">
                    <tr>
                        <td class="report-summary-label">Subtotal</td>
                        <td class="report-summary-value"><?= number_format($inv['subtotal'], 0, ',', '.') ?></td>
                    </tr>
                    <tr>
                        <td class="report-summary-label">Shipping</td>
                        <td class="report-summary-value"><?= number_format($inv['shipping'], 0, ',', '.') ?></td>
                    </tr>
                    <tr>
                        <td class="report-summary-label">Tax</td>
                        <td class="report-summary-value"><?= number_format($inv['tax'], 0, ',', '.') ?></td>
                    </tr>
                    <tr>
                        <td class="report-summary-label">Discount</td>
                        <td class="report-summary-value"><?= number_format($inv['discount'], 0, ',', '.') ?></td>
                    </tr>
                    <tr>
                        <td class="report-summary-total-label">Total</td>
                        <td class="report-summary-total-value"><?= number_format($inv['total'], 0, ',', '.') ?></td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Signatures -->
        <div class="d-flex justify-content-end mt-4 pt-2" style="font-size: 12px; color: #0f172a;">
            <div style="width: 250px; text-align: center; padding: 10px;">
                <p class="mb-5 font-weight-bold text-uppercase" style="color: #334155;">Hormat Kami,</p>
                <div style="height: 60px;"></div>
                <strong class="text-uppercase" style="text-decoration: underline; color: #0f172a;">
                    <?= sanitize($inv['creator_name']) ?>
                </strong><br>
                <span style="color: #64748b; font-size: 11px;">Authorized Signature</span>
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
/* Report Screen Styles */
.printable-area {
    color: #000000 !important;
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif !important;
    font-size: 13px;
}

.printable-area * {
    color: #000000 !important;
}

.report-table {
    width: 100% !important;
    border-collapse: collapse !important;
}

.report-table th, .report-table td {
    border: 1px solid #cbd5e1 !important;
    padding: 5px 10px !important;
    vertical-align: middle !important;
    font-size: 13px !important;
}

.report-table thead th {
    background-color: #f1f5f9 !important;
    font-weight: 700 !important;
    text-transform: uppercase !important;
    letter-spacing: 0.5px !important;
}

.report-terms-header {
    background-color: #f1f5f9 !important;
    border: 1px solid #cbd5e1;
    font-size: 13px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-top-left-radius: 6px;
    border-top-right-radius: 6px;
}

.report-terms-body {
    border: 1px solid #cbd5e1;
    border-top: none;
    font-size: 12px;
    border-bottom-left-radius: 6px;
    border-bottom-right-radius: 6px;
}

.report-summary-table {
    border-collapse: collapse !important;
    font-size: 13px !important;
    border: 1px solid #cbd5e1 !important;
}

.report-summary-table td {
    padding: 6px 12px !important;
    border: 1px solid #cbd5e1 !important;
}

.report-summary-label {
    text-align: left !important;
    font-weight: 600;
    background-color: transparent !important;
}

.report-summary-value {
    text-align: right !important;
    font-weight: 600;
}

.report-summary-total-label {
    text-align: left !important;
    font-weight: 800;
    font-size: 14px;
    background-color: transparent !important;
}

.report-summary-total-value {
    text-align: right !important;
    font-weight: 800;
    font-size: 15px;
}

@media print {
    @page {
        size: A4 portrait;
        margin: 15mm;
    }
    body { 
        background-color: white !important; 
    }
    .main-sidebar, .main-header, .d-print-none, .card-footer, .breadcrumb, .content-header { 
        display: none !important; 
    }
    .content-wrapper { 
        margin-left: 0 !important; 
        padding: 0 !important; 
        background: none !important;
    }
    .card { 
        border: none !important; 
        box-shadow: none !important; 
    }
    .card-header { 
        display: none !important; 
    }
    .printable-area { 
        width: 100% !important; 
        margin: 0 !important; 
        padding: 0 !important; 
    }
    .report-table th, .report-table td,
    .report-terms-header, .report-terms-body,
    .report-summary-table, .report-summary-table td,
    .report-info-col {
        border: 1px solid #000000 !important;
    }
    .report-table th, .report-table td {
        font-size: 13px !important;
    }
    .report-table thead th,
    .report-terms-header {
        background-color: #f1f5f9 !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
    .report-summary-table td {
        background-color: transparent !important;
    }
}
</style>

<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>
