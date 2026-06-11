<?php
/**
 * Procurement - Material Request View / Approval
 */
require_once __DIR__ . '/../../../includes/auth.php';
requirePermission('mr_view');

$id = $_GET['id'] ?? 0;

$stmt = $pdo->prepare("
    SELECT m.*, p.name as project_name, u.full_name as requester_name, u2.full_name as approver_name 
    FROM material_requests m
    LEFT JOIN projects p ON m.project_id = p.id
    LEFT JOIN users u ON m.requested_by = u.id
    LEFT JOIN users u2 ON m.approved_by = u2.id
    WHERE m.id = ?
");
$stmt->execute([$id]);
$mr = $stmt->fetch();

if (!$mr) {
    setFlash('danger', 'MR tidak ditemukan.');
    header('Location: ' . APP_URL . '/modules/procurement/mr/index.php');
    exit;
}

$user = getCurrentUser();

// Handle Approval / Rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!canAccess('mr_approve')) {
        setFlash('danger', 'Anda tidak memiliki hak akses untuk menyetujui MR.');
        header("Location: view.php?id=$id");
        exit;
    }
    
    if ($mr['status'] !== 'pending') {
        setFlash('danger', 'Hanya MR berstatus Pending yang dapat di-approve atau di-reject.');
        header("Location: view.php?id=$id");
        exit;
    }
    
    $action = $_POST['action'];
    $rejectReason = trim($_POST['reject_reason'] ?? '');
    
    if ($action === 'approve') {
        $stmt = $pdo->prepare("UPDATE material_requests SET status = 'approved', approved_by = ?, approved_at = NOW() WHERE id = ?");
        $stmt->execute([$user['id'], $id]);
        setFlash('success', "MR {$mr['mr_number']} berhasil disetujui.");
    } elseif ($action === 'reject') {
        $stmt = $pdo->prepare("UPDATE material_requests SET status = 'rejected', approved_by = ?, approved_at = NOW(), reject_reason = ? WHERE id = ?");
        $stmt->execute([$user['id'], $rejectReason, $id]);
        setFlash('danger', "MR {$mr['mr_number']} telah ditolak.");
    }
    
    header("Location: view.php?id=$id");
    exit;
}

// Fetch MR Items
$stmtItem = $pdo->prepare("SELECT mri.*, i.item_code FROM material_request_items mri LEFT JOIN items i ON mri.item_id = i.id WHERE mri.mr_id = ?");
$stmtItem->execute([$id]);
$mrItems = $stmtItem->fetchAll();

$pageTitle = 'Detail MR: ' . sanitize($mr['mr_number']);
$breadcrumbs = [
    ['label' => 'Procurement', 'url' => '#'],
    ['label' => 'MR', 'url' => APP_URL . '/modules/procurement/mr/index.php'],
    ['label' => 'Detail']
];

require_once __DIR__ . '/../../../includes/header.php';
?>

<div class="row">
    <div class="col-md-12">
        <div class="card card-outline card-info">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title text-info"><i class="fas fa-file-alt mr-2"></i> Material Request: <strong><?= sanitize($mr['mr_number']) ?></strong></h3>
                <div class="ml-auto">
                    <!-- Status Badge -->
                    <?php
                        $badge = 'secondary';
                        $label = ucfirst($mr['status']);
                        if ($mr['status'] === 'pending') { $badge = 'warning'; $label = 'Menunggu Approval'; }
                        if ($mr['status'] === 'approved') { $badge = 'success'; $label = 'Disetujui'; }
                        if ($mr['status'] === 'rejected') { $badge = 'danger'; $label = 'Ditolak'; }
                        if ($mr['status'] === 'completed') { $badge = 'info'; $label = 'Selesai (PO)'; }
                    ?>
                    <span class="badge badge-<?= $badge ?> mr-3 p-2" style="font-size: 14px;"><?= $label ?></span>
                    
                    <button class="btn btn-default btn-sm" onclick="window.print()"><i class="fas fa-print mr-1"></i> Cetak</button>
                    <a href="<?= APP_URL ?>/modules/procurement/mr/index.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left mr-1"></i> Kembali</a>
                </div>
            </div>
            
            <div class="card-body printable-area">
                
                <h3 class="text-center font-weight-bold mb-4" style="text-transform: uppercase;">Material Request Form</h3>
                
                <table class="table table-sm table-borderless table-header-info mb-3">
                    <tr>
                        <td width="15%" class="font-weight-bold">Request by</td>
                        <td width="50%">: <?= sanitize($mr['requester_name']) ?></td>
                        <td width="15%" class="font-weight-bold">Form No</td>
                        <td width="20%">: <span class="font-weight-bold text-danger"><?= sanitize($mr['mr_number']) ?></span></td>
                    </tr>
                    <tr>
                        <td class="font-weight-bold">Project</td>
                        <td>: <?= sanitize($mr['project_name']) ?></td>
                        <td class="font-weight-bold">Date</td>
                        <td>: <?= date('d-M-Y', strtotime($mr['request_date'])) ?></td>
                    </tr>
                    <tr>
                        <td class="font-weight-bold">Location</td>
                        <td colspan="3">: <?= nl2br(sanitize($mr['location'])) ?></td>
                    </tr>
                </table>
                
                <?php if ($mr['reject_reason']): ?>
                <div class="alert alert-danger mb-4 d-print-none">
                    <strong>Alasan Penolakan:</strong><br>
                    <?= nl2br(sanitize($mr['reject_reason'])) ?>
                </div>
                <?php endif; ?>
                
                <?php if ($mr['notes']): ?>
                <div class="mb-4 text-muted" style="font-size: 14px;">
                    <strong>Catatan:</strong> <?= nl2br(sanitize($mr['notes'])) ?>
                </div>
                <?php endif; ?>
                
                <div class="table-responsive">
                    <table class="table table-bordered table-sm excel-table">
                        <thead>
                            <tr>
                                <th width="5%" class="text-center font-weight-bold">NO</th>
                                <th width="15%" class="font-weight-bold">Item Code</th>
                                <th width="30%" class="font-weight-bold">Description</th>
                                <th width="15%" class="font-weight-bold">Type</th>
                                <th width="10%" class="text-center font-weight-bold">Qty</th>
                                <th width="10%" class="font-weight-bold">Uom</th>
                                <th width="15%" class="font-weight-bold">Remark</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $no = 1; foreach ($mrItems as $item): ?>
                            <tr>
                                <td class="text-center"><?= $no++ ?></td>
                                <td><?= sanitize($item['item_code']) ?: '-' ?></td>
                                <td><?= sanitize($item['description']) ?></td>
                                <td><?= sanitize($item['type_specification']) ?: '-' ?></td>
                                <td class="text-center"><?= number_format($item['qty'], 0, '', '') ?></td>
                                <td><?= sanitize($item['uom']) ?></td>
                                <td><?= sanitize($item['remark']) ?: '-' ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <table class="table table-borderless text-center mt-4 signature-table" style="width: 100%;">
                    <tr>
                        <td width="33%" class="font-weight-bold">Requester Signature</td>
                        <td width="34%" class="font-weight-bold">Purchasing</td>
                        <td width="33%" class="font-weight-bold">Approver</td>
                    </tr>
                    <tr>
                        <td style="height: 80px;"></td>
                        <td></td>
                        <td></td>
                    </tr>
                    <tr>
                        <td>(_________________________)</td>
                        <td>(_________________________)</td>
                        <td>(_________________________)</td>
                    </tr>
                </table>
            </div>
            
            <?php if ($mr['status'] === 'pending' && canAccess('mr_approve')): ?>
            <div class="card-footer bg-light text-right d-print-none">
                <form method="POST" id="formApprove" class="d-inline">
                    <input type="hidden" name="action" value="approve">
                    <button type="button" class="btn btn-success" onclick="confirmAction('Setujui MR?', 'MR ini akan disetujui dan dapat dilanjutkan ke pembuatan Purchase Order.', function() { $('#formApprove').submit(); })">
                        <i class="fas fa-check mr-1"></i> Approve Request
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
                    <h5 class="modal-title">Tolak Material Request</h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="reject">
                    <div class="form-group">
                        <label>Alasan Penolakan <span class="text-white">*</span></label>
                        <textarea name="reject_reason" class="form-control" rows="3" required placeholder="Wajib diisi agar pemohon mengetahui alasan ditolak"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger">Tolak MR</button>
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
    body { background-color: white !important; font-family: Arial, sans-serif !important; }
    .main-sidebar, .main-header, .d-print-none, .card-footer { display: none !important; }
    .content-wrapper { margin-left: 0 !important; padding: 0 !important; background: white; }
    .card { border: none !important; box-shadow: none !important; margin: 0 !important; padding: 0 !important; }
    .card-header { padding-top: 0 !important; display: none !important; }
    .printable-area { width: 100% !important; padding: 0 !important; margin: 0 !important; }
    
    .excel-table th, .excel-table td {
        border: 1px solid #000 !important;
        padding: 4px !important;
        background-color: transparent !important;
        color: #000 !important;
        -webkit-print-color-adjust: exact;
    }
    .signature-table td, .signature-table th {
        border: none !important;
    }
    .text-primary, .text-danger { color: #000 !important; }
}

.table-header-info { margin-bottom: 5px; }
.table-header-info td { padding: 0.2rem; vertical-align: top; }
.excel-table { border-collapse: collapse; }
.excel-table th, .excel-table td { border: 1px solid #000 !important; padding: 0.3rem; vertical-align: middle; }
.excel-table thead th { background-color: #f8f9fa; border-bottom: 2px solid #000 !important; }
</style>

<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>
