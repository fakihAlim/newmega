<?php
/**
 * Sales - View / Approve Quotation
 */
require_once __DIR__ . '/../../../includes/auth.php';
requirePermission('quotation_view');

$id = $_GET['id'] ?? 0;

$sql = "
    SELECT q.*, 
           c.name as company_name, c.address as company_address, c.city as company_city, c.province as company_province, 
           c.phone as company_phone, c.email as company_email, c.logo as company_logo,
           cust.company_name as customer_name, cust.address as customer_address, cust.phone as customer_phone, cust.pic_name as customer_pic, cust.email as customer_email,
           p.name as project_name,
           u.full_name as creator_name, u2.full_name as approver_name
    FROM quotations q
    JOIN companies c ON q.company_id = c.id
    JOIN customers cust ON q.customer_id = cust.id
    LEFT JOIN projects p ON q.project_id = p.id
    LEFT JOIN users u ON q.created_by = u.id
    LEFT JOIN users u2 ON q.approved_by = u2.id
    WHERE q.id = ?
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);
$q = $stmt->fetch();

if (!$q) {
    setFlash('danger', 'Quotation tidak ditemukan.');
    header('Location: index.php');
    exit;
}

$user = getCurrentUser();

// Handle Approval / Rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!canAccess('quotation_approve')) {
        setFlash('danger', 'Anda tidak memiliki hak akses.');
        header("Location: view.php?id=$id");
        exit;
    }
    if ($q['status'] !== 'pending') {
        setFlash('danger', 'Hanya Quotation berstatus Pending yang dapat diproses.');
        header("Location: view.php?id=$id");
        exit;
    }
    
    $action = $_POST['action'];
    $rejectReason = trim($_POST['reject_reason'] ?? '');
    
    if ($action === 'approve') {
        $pdo->prepare("UPDATE quotations SET status = 'approved', approved_by = ?, approved_at = NOW() WHERE id = ?")->execute([$user['id'], $id]);
        setFlash('success', "Quotation {$q['quotation_no']} disetujui.");
    } elseif ($action === 'reject') {
        $pdo->prepare("UPDATE quotations SET status = 'rejected', approved_by = ?, approved_at = NOW(), reject_reason = ? WHERE id = ?")->execute([$user['id'], $rejectReason, $id]);
        setFlash('danger', "Quotation {$q['quotation_no']} ditolak.");
    }
    header("Location: view.php?id=$id");
    exit;
}

$stmtItems = $pdo->prepare("SELECT * FROM quotation_items WHERE quotation_id = ?");
$stmtItems->execute([$id]);
$items = $stmtItems->fetchAll();

$pageTitle = 'Detail Quotation: ' . sanitize($q['quotation_no']);
$breadcrumbs = [
    ['label' => 'Sales', 'url' => '#'],
    ['label' => 'Quotation', 'url' => 'index.php'],
    ['label' => 'Detail']
];

require_once __DIR__ . '/../../../includes/header.php';
?>

<div class="card card-outline card-info">
    <div class="card-header d-flex justify-content-between align-items-center d-print-none">
        <h3 class="card-title text-info"><i class="fas fa-file-alt mr-2"></i> Quotation: <strong><?= sanitize($q['quotation_no']) ?></strong></h3>
        <div class="ml-auto">
            <?= getStatusBadge($q['status']) ?>
            <button class="btn btn-default btn-sm ml-3" onclick="window.print()"><i class="fas fa-print mr-1"></i> Cetak</button>
            <?php if ($q['status'] === 'approved'): ?>
                <a href="<?= APP_URL ?>/modules/sales/invoices/create.php?quotation_id=<?= $q['id'] ?>" class="btn btn-success btn-sm ml-1"><i class="fas fa-file-invoice-dollar mr-1"></i> Buat Invoice</a>
            <?php endif; ?>
            <a href="index.php" class="btn btn-secondary btn-sm ml-1"><i class="fas fa-arrow-left mr-1"></i> Kembali</a>
        </div>
    </div>
    
    <div class="card-body printable-area p-5">
        <div class="row mb-5 pb-3">
            <div class="col-sm-7">
                <div class="d-flex align-items-center mb-3">
                    <?php if ($q['company_logo']): ?>
                        <img src="<?= getCompanyLogo($q['company_logo']) ?>" alt="Logo" style="height:65px; margin-right:20px;">
                    <?php endif; ?>
                    <div>
                        <h3 class="mb-0 font-weight-bold" style="color:#000;"><?= sanitize($q['company_name']) ?></h3>
                        <div style="font-size:13px; line-height:1.4;">
                            <?= sanitize($q['company_address']) ?><br>
                            <?= sanitize($q['company_city']) ?>, <?= sanitize($q['company_province']) ?><br>
                            Email: <?= sanitize($q['company_email']) ?> | Phone: <?= sanitize($q['company_phone']) ?>
                        </div>
                    </div>
                </div>

                <div class="mt-4">
                    <h6 class="text-uppercase font-weight-bold mb-2" style="font-size:13px;">Quotation for:</h6>
                    <div style="font-size:14px; line-height:1.5;">
                        <strong style="color:#000;"><?= sanitize($q['customer_name']) ?></strong><br>
                        <?= nl2br(sanitize($q['customer_address'])) ?><br>
                        Email: <?= sanitize($q['customer_email'] ?? '-') ?><br>
                        Phone: <?= sanitize($q['customer_phone'] ?? '-') ?>
                    </div>
                </div>
            </div>
            <div class="col-sm-5 pl-4">
                <h1 class="font-weight-bold text-uppercase mb-4" style="letter-spacing:1px; font-size:32px;">QUOTATION</h1>
                
                <table class="table table-sm table-borderless" style="font-size:14px;">
                    <tr>
                        <td width="40%" class="font-weight-bold p-0">Quotation No</td>
                        <td class="p-0">: <span class="font-weight-bold"><?= sanitize($q['quotation_no']) ?></span></td>
                    </tr>
                    <tr>
                        <td class="font-weight-bold p-0">Date</td>
                        <td class="p-0">: <?= date('j-M-Y', strtotime($q['quotation_date'])) ?></td>
                    </tr>
                    <tr>
                        <td class="font-weight-bold p-0">Customer ID</td>
                        <td class="p-0">: <span><?= substr(strtoupper($q['customer_name']), 0, 3) . str_pad($q['customer_id'], 4, '0', STR_PAD_LEFT) ?></span></td>
                    </tr>
                    <tr>
                        <td class="font-weight-bold p-0">Quotation valid until</td>
                        <td class="p-0">: <?= $q['valid_until'] ? date('j-M-Y', strtotime($q['valid_until'])) : '-' ?></td>
                    </tr>
                </table>

                <?php if ($q['comments']): ?>
                <div class="mt-3 p-2 bg-light" style="border:1px solid #ddd; font-size:13px;">
                    <strong style="color:#000;">Comments:</strong><br>
                    <span style="color:#000;"><?= nl2br(sanitize($q['comments'])) ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Items Table -->
        <div class="table-responsive mb-4">
            <table class="table quotation-table">
                <thead>
                    <tr>
                        <th rowspan="2" style="width: 1%; min-width: 30px;">NO</th>
                        <th rowspan="2" style="width: 25%;">DESCRIPTION</th>
                        <th rowspan="2" style="width: 20%;">TYPE</th>
                        <th rowspan="2" style="width: 1%; min-width: 40px;">QTY</th>
                        <th rowspan="2" style="width: 1%; min-width: 40px;">UOM</th>
                        <th colspan="2" style="width: 18%;">MATERIAL</th>
                        <th colspan="2" style="width: 18%;">MANPOWER</th>
                        <th rowspan="2" style="width: 10%;">AMOUNT</th>
                    </tr>
                    <tr>
                        <th style="font-size: 10px; width: 9%;">UNIT PRICE</th>
                        <th style="font-size: 10px; width: 9%;">TOTAL MATERIAL</th>
                        <th style="font-size: 10px; width: 9%;">UNIT PRICE</th>
                        <th style="font-size: 10px; width: 9%;">TOTAL MANPOWER</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $no = 1; foreach ($items as $item): ?>
                    <tr>
                        <td class="text-right"><?= $no++ ?></td>
                        <td><?= sanitize($item['description']) ?></td>
                        <td><?= sanitize($item['type_specification']) ?: '-' ?></td>
                        <td class="text-right"><?= number_format($item['qty'], 0) ?></td>
                        <td><?= sanitize($item['uom']) ?></td>
                        <td class="text-right"><?= number_format($item['material_unit_price'], 0, ',', '.') ?></td>
                        <td class="text-right"><?= number_format($item['material_total'], 0, ',', '.') ?></td>
                        <td class="text-right"><?= number_format($item['manpower_unit_price'], 0, ',', '.') ?></td>
                        <td class="text-right"><?= number_format($item['manpower_total'], 0, ',', '.') ?></td>
                        <td class="text-right"><?= number_format($item['amount'], 0, ',', '.') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Summary & Terms -->
        <div class="row no-gutters">
            <div class="col-sm-7 pt-2 pr-3 d-flex flex-column">
                <div class="p-1 px-2 text-white font-weight-bold" style="background-color: #666 !important; border: 1px solid #000; font-size: 12px;">
                    Term and Conditions :
                </div>
                <div class="p-2 flex-grow-1" style="border: 1px solid #000 !important; font-size: 11px; border-top: none !important; color: #000; min-height: 100px;">
                    <?= $q['terms_and_conditions'] ? nl2br(sanitize($q['terms_and_conditions'])) : '<span class="text-muted">No terms provided.</span>' ?>
                </div>

                <?php if ($q['reject_reason']): ?>
                <div class="alert alert-danger mt-3 d-print-none" style="font-size: 12px;">
                    <strong>Alasan Penolakan:</strong><br><?= nl2br(sanitize($q['reject_reason'])) ?>
                </div>
                <?php endif; ?>
            </div>
            <div class="col-sm-5 pt-2">
                <table class="table table-sm table-bordered text-right font-weight-bold mb-0" style="font-size:13px; border: 1px solid #000;">
                    <tr>
                        <td width="55%" class="bg-light px-2" style="border: 1px solid #000;">Subtotal</td>
                        <td class="px-2" style="border: 1px solid #000;"><?= number_format($q['subtotal'], 0, ',', '.') ?></td>
                    </tr>
                    <tr>
                        <td class="bg-light px-2" style="border: 1px solid #000;">Shipping</td>
                        <td class="px-2" style="border: 1px solid #000;"><?= number_format($q['shipping'], 0, ',', '.') ?></td>
                    </tr>
                    <tr>
                        <td class="bg-light px-2" style="border: 1px solid #000;">Tax</td>
                        <td class="px-2" style="border: 1px solid #000;"><?= number_format($q['tax'], 0, ',', '.') ?></td>
                    </tr>
                    <tr>
                        <td class="bg-light px-2" style="border: 1px solid #000;">Discount</td>
                        <td class="px-2" style="border: 1px solid #000;">- <?= number_format($q['discount'], 0, ',', '.') ?></td>
                    </tr>
                    <tr style="background-color: #f2f2f2;">
                        <td class="text-dark px-2" style="border: 1px solid #000; font-size: 15px;">Total</td>
                        <td class="text-dark px-2" style="border: 1px solid #000; font-size: 16px;"><?= number_format($q['total'], 0, ',', '.') ?></td>
                    </tr>
                </table>
            </div>
        </div>
        
        <!-- Signatures -->
        <div class="row mt-5 pt-3 text-center" style="font-size:14px;">
            <div class="col-sm-4">
                <p class="mb-5">Dibuat Oleh,</p>
                <strong><?= sanitize($q['creator_name']) ?></strong>
            </div>
            <div class="col-sm-4">
                <p class="mb-5">Menyetujui,</p>
                <?php if ($q['approver_name']): ?>
                    <strong><?= sanitize($q['approver_name']) ?></strong>
                <?php else: ?>
                    <strong class="text-muted">( Belum Disetujui )</strong>
                <?php endif; ?>
            </div>
            <div class="col-sm-4">
                <p class="mb-5">Customer,</p>
                <strong>( ................................... )</strong>
            </div>
        </div>
    </div>
    
    <!-- Approval Actions -->
    <?php if ($q['status'] === 'pending' && canAccess('quotation_approve')): ?>
    <div class="card-footer bg-light text-right d-print-none">
        <form method="POST" id="formApprove" class="d-inline">
            <input type="hidden" name="action" value="approve">
            <button type="button" class="btn btn-success" onclick="confirmAction('Setujui Quotation?', 'Quotation ini akan disetujui.', function() { $('#formApprove').submit(); })">
                <i class="fas fa-check mr-1"></i> Approve
            </button>
        </form>
        <button type="button" class="btn btn-danger ml-2" data-toggle="modal" data-target="#modalReject">
            <i class="fas fa-times mr-1"></i> Reject
        </button>
    </div>
    <?php endif; ?>
</div>

<!-- Modal Reject -->
<div class="modal fade" id="modalReject" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST">
            <div class="modal-content border-danger">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">Tolak Quotation</h5>
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
.quotation-table {
    border-collapse: collapse !important;
    width: 100% !important;
    border: 1px solid #000 !important;
    font-family: Arial, sans-serif !important;
    font-size: 11px !important;
    margin-bottom: 0 !important;
}
.quotation-table th, .quotation-table td {
    border: 1px solid #000 !important;
    padding: 3px 5px !important;
    color: #000 !important;
    vertical-align: middle !important;
    line-height: 1.2 !important;
}
.quotation-table thead th {
    background-color: #ffffff !important;
    font-weight: bold !important;
    text-transform: uppercase !important;
    text-align: center !important;
    vertical-align: middle !important;
}
.quotation-table td.text-right {
    text-align: right !important;
}
.quotation-table td.text-center {
    text-align: center !important;
}
.quotation-table td.text-left {
    text-align: left !important;
}

@media print {
    @page {
        size: A4 portrait;
        margin: 10mm;
    }
    * {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
    body { background-color: white !important; color: black !important; }
    .main-sidebar, .main-header, .d-print-none, .card-footer, .breadcrumb, .content-header { display: none !important; }
    .content-wrapper { margin-left: 0 !important; padding: 0 !important; }
    .card { border: none !important; box-shadow: none !important; }
    .card-body { border: none !important; }
    .card-header { display: none !important; }
    .printable-area { width: 100% !important; padding: 0 !important; border: none !important; color: #000 !important; }
    .printable-area * { color: #000 !important; }
    .quotation-table th { background-color: #ffffff !important; color: #000 !important; }
    /* Ensure borders print properly */
    .quotation-table, .quotation-table th, .quotation-table td { border: 1px solid #000 !important; }
}
.printable-area { color: #000 !important; }
.printable-area * { color: #000 !important; }
</style>

<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>
