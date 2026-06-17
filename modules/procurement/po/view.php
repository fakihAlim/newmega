<?php
/**
 * Procurement - Purchase Order View / Approval
 */
require_once __DIR__ . '/../../../includes/auth.php';
requirePermission('po_view');

$id = $_GET['id'] ?? 0;

$sql = "
    SELECT po.*, 
           v.company_name as vendor_name, v.address as vendor_address, v.phone as vendor_phone, v.email as vendor_email, v.pic_name as vendor_contact,
           c.name as company_name, c.address as company_address, c.city as company_city, c.province as company_province, c.phone as company_phone, c.email as company_email, c.logo as company_logo,
           u.full_name as creator_name,
           u2.full_name as approver_name
    FROM purchase_orders po
    JOIN vendors v ON po.vendor_id = v.id
    JOIN companies c ON po.company_id = c.id
    LEFT JOIN users u ON po.created_by = u.id
    LEFT JOIN users u2 ON po.approved_by = u2.id
    WHERE po.id = ?
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);
$po = $stmt->fetch();

if (!$po) {
    setFlash('danger', 'Purchase Order tidak ditemukan.');
    header('Location: ' . APP_URL . '/modules/procurement/po/index.php');
    exit;
}

$user = getCurrentUser();

// Handle Approval / Rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!canAccess('po_approve')) {
        setFlash('danger', 'Anda tidak memiliki hak akses untuk menyetujui PO.');
        header("Location: view.php?id=$id");
        exit;
    }

    if ($po['status'] !== 'pending') {
        setFlash('danger', 'Hanya PO berstatus Pending yang dapat di-approve atau di-reject.');
        header("Location: view.php?id=$id");
        exit;
    }

    $action = $_POST['action'];
    $rejectReason = trim($_POST['reject_reason'] ?? '');

    try {
        $pdo->beginTransaction();

        if ($action === 'approve') {
            $stmt = $pdo->prepare("UPDATE purchase_orders SET status = 'approved', approved_by = ?, approved_at = NOW() WHERE id = ?");
            $stmt->execute([$user['id'], $id]);
            setFlash('success', "PO {$po['po_number']} berhasil disetujui.");
        } elseif ($action === 'reject') {
            $stmt = $pdo->prepare("UPDATE purchase_orders SET status = 'rejected', approved_by = ?, approved_at = NOW(), reject_reason = ? WHERE id = ?");
            $stmt->execute([$user['id'], $rejectReason, $id]);

            // Revert MR Quantities since PO is rejected
            $oldPoItems = $pdo->prepare("SELECT mr_item_id, qty FROM purchase_order_items WHERE po_id = ?");
            $oldPoItems->execute([$id]);
            foreach ($oldPoItems->fetchAll() as $oldMri) {
                if ($oldMri['mr_item_id']) {
                    $pdo->prepare("UPDATE material_request_items SET qty_ordered = qty_ordered - ? WHERE id = ?")->execute([$oldMri['qty'], $oldMri['mr_item_id']]);
                }
            }
            // Optional: delete po_mr_links? Usually kept for tracking.

            setFlash('danger', "PO {$po['po_number']} telah ditolak.");
        }
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log('[NEWMEGA] ' . $e->getMessage());
        setFlash('danger', 'Terjadi kesalahan sistem. Silakan coba lagi atau hubungi administrator.');
    }

    header("Location: view.php?id=$id");
    exit;
}

// Fetch PO Items
$stmtItems = $pdo->prepare("SELECT * FROM purchase_order_items WHERE po_id = ?");
$stmtItems->execute([$id]);
$poItems = $stmtItems->fetchAll();

$pageTitle = 'Detail PO: ' . sanitize($po['po_number']);
$breadcrumbs = [
    ['label' => 'Procurement', 'url' => '#'],
    ['label' => 'PO', 'url' => APP_URL . '/modules/procurement/po/index.php'],
    ['label' => 'Detail']
];

require_once __DIR__ . '/../../../includes/header.php';
?>

<div class="row">
    <div class="col-md-12">
        <div class="card card-outline card-info">
            <div class="card-header d-flex justify-content-between align-items-center d-print-none">
                <h3 class="card-title text-info"><i class="fas fa-file-invoice mr-2"></i> Purchase Order:
                    <strong><?= sanitize($po['po_number']) ?></strong>
                </h3>
                <div class="ml-auto">
                    <?= getStatusBadge($po['status']) ?>
                    <button class="btn btn-default btn-sm ml-3" onclick="window.print()"><i
                            class="fas fa-print mr-1"></i> Cetak PO</button>
                    <a href="<?= APP_URL ?>/modules/procurement/po/index.php" class="btn btn-secondary btn-sm ml-1"><i
                            class="fas fa-arrow-left mr-1"></i> Kembali</a>
                </div>
            </div>

            <div class="card-body printable-area p-5">

                <!-- Main Company Header -->
                <div class="text-center mb-4">
                    <div class="d-flex justify-content-center align-items-center mb-2">
                        <?php if ($po['company_logo']): ?>
                            <img src="<?= getCompanyLogo($po['company_logo']) ?>" alt="Logo"
                                style="height: 80px; margin-right: 20px;">
                        <?php endif; ?>
                        <h1 class="font-weight-bold mb-0" style="font-size: 42px; letter-spacing: 2px; color: #000; text-transform: uppercase;">
                            <?= sanitize($po['company_name']) ?>
                        </h1>
                    </div>
                    <p class="mb-0" style="font-size: 14px; color: #333;"><?= sanitize($po['company_address']) ?>,
                        <?= sanitize($po['company_city']) ?>, <?= sanitize($po['company_province']) ?>, Indonesia
                    </p>
                </div>

                <hr style="border: 0.5px solid #000; margin-bottom: 20px;">

                <!-- Title & Metadata Box -->
                <div class="row no-gutters mb-3 align-items-end">
                    <div class="col-sm-7">
                        <h1 class="font-weight-bold" style="font-size: 38px; color: #000; margin-bottom: 0;">Purchase
                            Order</h1>
                    </div>
                    <div class="col-sm-5">
                        <table class="table table-sm table-bordered mb-0"
                            style="font-size: 13px; border: 1px solid #000;">
                            <tr>
                                <td width="35%" class="font-weight-bold bg-light px-2" style="border: 1px solid #000;">
                                    PO NO :</td>
                                <td class="px-2 font-weight-bold" style="border: 1px solid #000; color: #000;">
                                    <?= sanitize($po['po_number']) ?>
                                </td>
                            </tr>
                            <tr>
                                <td class="font-weight-bold bg-light px-2" style="border: 1px solid #000;">Date :</td>
                                <td class="px-2 font-weight-bold" style="border: 1px solid #000;">
                                    <?= date('d-M-Y', strtotime($po['po_date'])) ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <!-- Section 1: Supplier & Delivery Address -->
                <div class="row no-gutters mb-0">
                    <div class="col-sm-7 bg-secondary text-white font-weight-bold px-2 py-1 border"
                        style="background-color: #666 !important; border: 1px solid #000 !important; font-size: 12px;">
                        SUPPLIER</div>
                    <div class="col-sm-5 bg-secondary text-white font-weight-bold px-2 py-1 border"
                        style="background-color: #666 !important; border: 1px solid #000 !important; border-left: none !important; font-size: 12px;">
                        DELIVERY ADDRESS / LOKASI</div>
                </div>
                <div class="row no-gutters mb-3"
                    style="min-height: 100px; border-left: 1px solid #000; border-right: 1px solid #000; border-bottom: 1px solid #000;">
                    <div class="col-sm-7 p-2 border-right" style="border-right: 1px solid #000 !important;">
                        <h6 class="font-weight-bold mb-1" style="color: #000;"><?= sanitize($po['vendor_name']) ?></h6>
                        <div class="mb-2" style="font-size: 11px; color: #000; line-height: 1.2;">
                            <?= nl2br(sanitize($po['vendor_address'])) ?>
                        </div>
                        <table class="table-sm table-borderless mt-2" style="font-size: 12px;">
                            <tr>
                                <td width="80px" class="font-weight-bold p-0">Terms</td>
                                <td class="p-0">: <span
                                        style="color: #000;"><?= sanitize($po['terms']) ?: 'CASH' ?></span></td>
                            </tr>
                            <tr>
                                <td class="font-weight-bold p-0">Phone no</td>
                                <td class="p-0">: <span
                                        style="color: #000;"><?= sanitize($po['vendor_phone']) ?: '-' ?></span></td>
                            </tr>
                            <tr>
                                <td class="font-weight-bold p-0">Email</td>
                                <td class="p-0">: <span
                                        style="color: #000;"><?= sanitize($po['vendor_email']) ?: '-' ?></span></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-sm-5 p-2">
                        <div class="font-weight-bold mb-2" style="color: #000; font-size: 12px; line-height: 1.2;">
                            <?= nl2br(sanitize($po['delivery_address'])) ?: 'Sesuai alamat perusahaan' ?>
                        </div>
                        <table class="table-sm table-borderless mt-2" style="font-size: 12px;">
                            <tr>
                                <td width="80px" class="font-weight-bold p-0">Contact</td>
                                <td class="p-0">: <span
                                        style="color: #000;"><?= sanitize($po['delivery_contact']) ?: '-' ?></span></td>
                            </tr>
                            <tr>
                                <td class="font-weight-bold p-0">Attn</td>
                                <td class="p-0">: <span
                                        style="color: #000;"><?= sanitize($po['delivery_attn']) ?: '-' ?></span></td>
                            </tr>
                        </table>
                    </div>
                </div>

                <!-- Section 2: Dates & Requested By -->
                <div class="row no-gutters mb-0">
                    <div class="col-sm-3 bg-secondary text-white font-weight-bold text-center py-1 border"
                        style="background-color: #888 !important; border: 1px solid #000 !important; font-size: 12px;">
                        Delivery Date</div>
                    <div class="col-sm-9 bg-secondary text-white font-weight-bold text-center py-1 border"
                        style="background-color: #888 !important; border: 1px solid #000 !important; border-left: none !important; font-size: 12px;">
                        Requested By</div>
                </div>
                <div class="row no-gutters mb-3 text-center font-weight-bold" style="font-size: 13px;">
                    <div class="col-sm-3 py-1"
                        style="border-left: 1px solid #000 !important; border-bottom: 1px solid #000 !important; border-right: 1px solid #000 !important; color: #000;">
                        <?= $po['delivery_date'] ? date('d-M-Y', strtotime($po['delivery_date'])) : '-' ?>
                    </div>
                    <div class="col-sm-9 py-1"
                        style="border-right: 1px solid #000 !important; border-bottom: 1px solid #000 !important; color: #000;">
                        <?= sanitize($po['requested_by']) ?: '-' ?>
                    </div>
                </div>

                <!-- Items Table -->
                <div class="table-responsive mb-4">
                    <table class="table table-bordered table-sm print-table"
                        style="font-size:11px; border: 1px solid #000;">
                        <thead class="text-center" style="background-color: #666; color: #fff;">
                            <tr>
                                <th width="5%" style="border: 1px solid #000;">NO</th>
                                <th width="40%" style="border: 1px solid #000;">ITEM NAME</th>
                                <th width="8%" style="border: 1px solid #000;">QTY</th>
                                <th width="7%" style="border: 1px solid #000;">UOM</th>
                                <th width="12%" style="border: 1px solid #000;">ITEM PRICE</th>
                                <th width="12%" style="border: 1px solid #000;">DISCOUNT</th>
                                <th width="16%" style="border: 1px solid #000;">TOTAL</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $no = 1;
                            foreach ($poItems as $item): ?>
                                <tr>
                                    <td class="text-center align-middle" style="border: 1px solid #000;"><?= $no++ ?></td>
                                    <td class="align-middle px-2" style="border: 1px solid #000; color: #000;">
                                        <strong><?= sanitize($item['item_name']) ?></strong>
                                    </td>
                                    <td class="text-center align-middle" style="border: 1px solid #000; color: #000;">
                                        <?= number_format($item['qty'], 0, ',', '.') ?>
                                    </td>
                                    <td class="text-center align-middle" style="border: 1px solid #000; color: #000;">
                                        <?= sanitize($item['uom']) ?>
                                    </td>
                                    <td class="text-right align-middle px-2" style="border: 1px solid #000; color: #000;">
                                        <?= number_format($item['unit_price'], 0, ',', '.') ?>
                                    </td>
                                    <td class="text-right align-middle px-2" style="border: 1px solid #000; color: #000;">
                                        <?= $item['discount_item'] > 0 ? '-' . number_format($item['discount_item'], 0, ',', '.') : '0' ?>
                                    </td>
                                    <td class="text-right align-middle font-weight-bold px-2"
                                        style="border: 1px solid #000; color: #000;">
                                        <?= number_format($item['total'], 0, ',', '.') ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Summary & Notes Section -->
                <div class="row no-gutters">
                    <!-- Additional Notes (Left) -->
                    <div class="col-sm-7 pt-2 pr-3 d-flex flex-column">
                        <div class="p-1 px-2 text-white font-weight-bold"
                            style="background-color: #666 !important; border: 1px solid #000; font-size: 12px;">
                            Additional Notes :</div>
                        <div class="p-2 flex-grow-1"
                            style="border: 1px solid #000 !important; font-size: 11px; border-top: none !important; color: #333;">
                            <?= $po['additional_notes'] ? nl2br(sanitize($po['additional_notes'])) : '<span class="text-muted italic">No additional notes provided.</span>' ?>
                        </div>

                        <?php if ($po['reject_reason']): ?>
                            <div class="alert alert-danger mt-3 d-print-none" style="font-size: 12px;">
                                <strong>Alasan Penolakan:</strong><br><?= nl2br(sanitize($po['reject_reason'])) ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Summary Table (Right) -->
                    <div class="col-sm-5 pt-2">
                        <table class="table table-sm table-bordered text-right font-weight-bold mb-0"
                            style="font-size:13px; border: 1px solid #000;">
                            <tr>
                                <td width="60%" class="bg-light px-2" style="border: 1px solid #000;">SHIPPING</td>
                                <td width="40%" class="px-2" style="border: 1px solid #000; color: #000;">
                                    <?= number_format($po['shipping'], 0, ',', '.') ?>
                                </td>
                            </tr>
                            <tr>
                                <td class="bg-light px-2" style="border: 1px solid #000;">OTHER</td>
                                <td class="px-2" style="border: 1px solid #000; color: #000;">
                                    <?= number_format($po['other_cost'], 0, ',', '.') ?>
                                </td>
                            </tr>
                            <tr>
                                <td class="bg-light px-2" style="border: 1px solid #000;">TAX</td>
                                <td class="px-2" style="border: 1px solid #000; color: #000;">
                                    <?= number_format($po['tax'], 0, ',', '.') ?>
                                </td>
                            </tr>
                            <tr>
                                <td class="bg-light px-2" style="border: 1px solid #000;">DISCOUNT</td>
                                <td class="px-2" style="border: 1px solid #000; color: #000;">
                                    <?= number_format($po['discount'], 0, ',', '.') ?>
                                </td>
                            </tr>
                            <tr style="background-color: #f2f2f2;">
                                <td class="px-2" style="border: 1px solid #000; font-size: 15px;">ORDER TOTAL</td>
                                <td class="px-2" style="border: 1px solid #000; font-size: 16px; color: #000;">
                                    <?= number_format($po['total'], 0, ',', '.') ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <!-- Signatures -->
                <div class="row mt-5 pt-3 text-center" style="font-size:14px;">
                    <div class="col-sm-4">
                        <p class="mb-5">Dibuat Oleh,</p>
                        <strong><?= sanitize($po['creator_name']) ?></strong>
                        <p class="text-muted">Procurement / Admin</p>
                    </div>
                    <div class="col-sm-4">
                        <p class="mb-5">Menyetujui,</p>
                        <?php if ($po['status'] === 'approved' || $po['status'] === 'completed' || $po['status'] === 'partially_received'): ?>
                            <strong><?= sanitize($po['approver_name']) ?></strong>
                            <p class="text-muted">Direktur / Finance Manager</p>
                        <?php else: ?>
                            <strong class="text-muted">( Belum Disetujui )</strong>
                        <?php endif; ?>
                    </div>
                    <div class="col-sm-4">
                        <p class="mb-5">Vendor,</p>
                        <strong>( ......................................... )</strong>
                    </div>
                </div>

            </div>

            <!-- Approval Actions -->
            <?php if ($po['status'] === 'pending' && canAccess('po_approve')): ?>
                <div class="card-footer bg-light text-right d-print-none">
                    <form method="POST" id="formApprove" class="d-inline">
                        <input type="hidden" name="action" value="approve">
                        <button type="button" class="btn btn-success"
                            onclick="confirmAction('Setujui Purchase Order?', 'PO ini akan disetujui dan sah untuk dikirim ke Vendor.', function() { $('#formApprove').submit(); })">
                            <i class="fas fa-check mr-1"></i> Approve PO
                        </button>
                    </form>

                    <button type="button" class="btn btn-danger ml-2" data-toggle="modal" data-target="#modalReject">
                        <i class="fas fa-times mr-1"></i> Reject
                    </button>
                </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<!-- Modal Reject -->
<div class="modal fade" id="modalReject" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <form method="POST">
            <div class="modal-content border-danger">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">Tolak Purchase Order</h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="reject">
                    <div class="form-group">
                        <label>Alasan Penolakan <span class="text-danger">*</span></label>
                        <textarea name="reject_reason" class="form-control" rows="3" required
                            placeholder="Jelaskan pengerutan harga / alasan reject"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger">Tolak PO</button>
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

        * {
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
            color-adjust: exact !important;
        }

        html, body, .wrapper, .content-wrapper, .content, .container-fluid, .card, .card-body {
            background-color: white !important;
        }
        
        body {
            padding: 0;
            margin: 0;
        }

        .main-sidebar,
        .main-header,
        .d-print-none,
        .card-footer,
        .breadcrumb,
        .content-header {
            display: none !important;
        }

        .content-wrapper {
            margin-left: 0 !important;
            padding: 0 !important;
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
            border: none !important;
            padding: 0 !important;
            color: #000 !important;
        }

        .printable-area * {
            color: #000 !important;
        }

        .print-table th {
            background-color: #f4f6f9 !important;
            color: #000 !important;
        }
    }
    
    .printable-area {
        color: #000 !important;
    }
    
    .printable-area * {
        color: #000 !important;
    }

    .summary-table td {
        padding: 0.3rem 0;
    }

    .print-table th,
    .print-table td {
        vertical-align: middle;
    }
</style>

<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>