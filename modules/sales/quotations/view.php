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

<div class="card card-outline card-primary">
    <div class="card-header d-flex justify-content-between align-items-center d-print-none">
        <h3 class="card-title text-primary"><i class="fas fa-file-alt mr-2"></i> Quotation: <strong><?= sanitize($q['quotation_no']) ?></strong></h3>
        <div class="ml-auto">
            <?= getStatusBadge($q['status']) ?>
            <button class="btn btn-default btn-sm ml-3" onclick="window.print()"><i class="fas fa-print mr-1"></i> Cetak</button>
            <?php if ($q['status'] === 'approved'): ?>
                <a href="<?= APP_URL ?>/modules/sales/invoices/create.php?quotation_id=<?= $q['id'] ?>" class="btn btn-success btn-sm ml-1"><i class="fas fa-file-invoice-dollar mr-1"></i> Buat Invoice</a>
            <?php endif; ?>
            <a href="index.php" class="btn btn-secondary btn-sm ml-1"><i class="fas fa-arrow-left mr-1"></i> Kembali</a>
        </div>
    </div>
    
    <div class="card-body printable-area p-4 bg-white">
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
                
                <table class="table table-sm table-borderless" >
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

        <!-- 3. Tabel Detail Item -->
        <div class="table-responsive mb-4">
            <table class="table table-bordered table-sm report-table mb-0" style="width: 100%;">
                <thead>
                    <tr class="text-center font-weight-bold">
                        <th rowspan="2" style="width: 5%;">NO</th>
                        <th rowspan="2">DESCRIPTION</th>
                        <th rowspan="2" style="width: 15%;">TYPE</th>
                        <th rowspan="2" style="width: 6%;">QTY</th>
                        <th rowspan="2" style="width: 6%;">UOM</th>
                        <th colspan="2" style="width: 18%;">MATERIAL</th>
                        <th colspan="2" style="width: 18%;">MANPOWER</th>
                        <th rowspan="2" style="width: 12%;">AMOUNT</th>
                    </tr>
                    <tr class="text-center font-weight-bold">
                        <th style="font-size: 10px; width: 9%;">UNIT PRICE</th>
                        <th style="font-size: 10px; width: 9%;">TOTAL MATERIAL</th>
                        <th style="font-size: 10px; width: 9%;">UNIT PRICE</th>
                        <th style="font-size: 10px; width: 9%;">TOTAL MANPOWER</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $no = 1; foreach ($items as $item): ?>
                    <tr>
                        <td class="text-center"><?= $no++ ?></td>
                        <td><?= sanitize($item['description']) ?></td>
                        <td><?= sanitize($item['type_specification']) ?: '-' ?></td>
                        <td class="text-right"><?= number_format($item['qty'], 0) ?></td>
                        <td class="text-center"><?= sanitize($item['uom']) ?></td>
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

        <!-- 4. Catatan (Terms) & Ringkasan Biaya (Summary) -->
        <div class="row no-gutters mb-4" style="gap: 20px; display: flex;">
            <!-- Catatan / Terms -->
            <div class="col pr-0 d-flex flex-column" style="flex: 7;">
                <div class="p-2 px-3 font-weight-bold report-terms-header">
                    Term and Conditions :
                </div>
                <div class="p-3 flex-grow-1 report-terms-body">
                    <?= $q['terms_and_conditions'] ? nl2br(sanitize($q['terms_and_conditions'])) : '<span class="text-muted">No terms provided.</span>' ?>
                </div>

                <?php if ($q['reject_reason']): ?>
                <div class="alert alert-danger mt-3 d-print-none" style="font-size: 12px;">
                    <strong>Alasan Penolakan:</strong><br><?= nl2br(sanitize($q['reject_reason'])) ?>
                </div>
                <?php endif; ?>
            </div>
            <!-- Summary -->
            <div class="col pl-0" style="flex: 5;">
                <table class="table-sm table-bordered report-summary-table w-100 h-100">
                    <tr>
                        <td class="report-summary-label">Subtotal</td>
                        <td class="report-summary-value"><?= number_format($q['subtotal'], 0, ',', '.') ?></td>
                    </tr>
                    <tr>
                        <td class="report-summary-label">Shipping</td>
                        <td class="report-summary-value"><?= number_format($q['shipping'], 0, ',', '.') ?></td>
                    </tr>
                    <tr>
                        <td class="report-summary-label">Tax</td>
                        <td class="report-summary-value"><?= number_format($q['tax'], 0, ',', '.') ?></td>
                    </tr>
                    <tr>
                        <td class="report-summary-label">Discount</td>
                        <td class="report-summary-value">- <?= number_format($q['discount'], 0, ',', '.') ?></td>
                    </tr>
                    <tr>
                        <td class="report-summary-total-label">Total</td>
                        <td class="report-summary-total-value"><?= number_format($q['total'], 0, ',', '.') ?></td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- 5. Bagian Tanda Tangan (Signature) -->
        <div class="d-flex justify-content-between mt-4 pt-2" style="font-size: 12px;">
            <div style="width: 200px; text-align: center; padding: 10px;">
                <p class="mb-5 font-weight-bold text-uppercase">Dibuat Oleh,</p>
                <div style="height: 60px;"></div>
                <strong class="text-uppercase" style="text-decoration: underline;">
                    <?= sanitize($q['creator_name']) ?>
                </strong>
            </div>
            <div style="width: 200px; text-align: center; padding: 10px;">
                <p class="mb-5 font-weight-bold text-uppercase">Menyetujui,</p>
                <div style="height: 60px;"></div>
                <strong class="text-uppercase" style="text-decoration: underline;">
                    <?php if ($q['approver_name']): ?>
                        <?= sanitize($q['approver_name']) ?>
                    <?php else: ?>
                        <span class="text-muted" style="text-decoration: none;">( Belum Disetujui )</span>
                    <?php endif; ?>
                </strong>
            </div>
            <div style="width: 200px; text-align: center; padding: 10px;">
                <p class="mb-5 font-weight-bold text-uppercase">Customer,</p>
                <div style="height: 60px;"></div>
                <strong class="text-uppercase" style="text-decoration: underline;">
                    ( ................................... )
                </strong>
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
/* --- Gaya Laporan di Layar --- */
.printable-area {
    color: #000000 !important;
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif !important;
    font-size: 13px;
}

/* Memaksa semua teks di area laporan berwarna hitam */
.printable-area * {
    color: #000000 !important;
}

.report-table {
    width: 100% !important;
    border-collapse: collapse !important;
}

/* Font size 13px & padding baris 5px */
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

/* --- Gaya Khusus Saat Cetak --- */
@media print {
    @page {
        size: A4 portrait;
        margin: 15mm;
    }
    body { 
        background-color: white !important; 
    }
    /* Sembunyikan elemen non-cetak */
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
    /* Memaksa border berwarna hitam solid saat diprint */
    .report-table th, .report-table td,
    .report-terms-header, .report-terms-body,
    .report-summary-table, .report-summary-table td,
    .report-info-col {
        border: 1px solid #000000 !important;
    }
    .report-table th, .report-table td {
        font-size: 13px !important;
    }
    /* Memaksa warna background abu-abu tercetak */
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
