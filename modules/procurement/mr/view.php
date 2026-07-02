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

// Fetch default company for header logo & info
$company = $pdo->query("SELECT * FROM companies WHERE is_default = 1")->fetch();

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
        logActivity('approve', 'material_request', "Menyetujui Material Request: {$mr['mr_number']}", 'material_requests', $id);
        setFlash('success', "MR {$mr['mr_number']} berhasil disetujui.");
    } elseif ($action === 'reject') {
        $stmt = $pdo->prepare("UPDATE material_requests SET status = 'rejected', approved_by = ?, approved_at = NOW(), reject_reason = ? WHERE id = ?");
        $stmt->execute([$user['id'], $rejectReason, $id]);
        logActivity('reject', 'material_request', "Menolak Material Request: {$mr['mr_number']}", 'material_requests', $id);
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
        <div class="card card-outline card-primary">
            <div class="card-header d-flex justify-content-between align-items-center d-print-none">
                <h3 class="card-title text-primary"><i class="fas fa-file-alt mr-2"></i> Material Request: <strong><?= sanitize($mr['mr_number']) ?></strong></h3>
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
            
            <div class="card-body printable-area p-4 bg-white">
                <!-- 1. Title -->
                <h4 class="text-center font-weight-bold mb-4" style="color: #1e293b; letter-spacing: 1px; text-transform: uppercase;">MATERIAL REQUEST FORM</h4>
                
                <!-- 2. Header Info Table -->
                <table class="table table-bordered table-sm mb-3" style="font-size:13px; color:#000;">
                    <tr>
                        <td width="15%"><strong>Request by</strong></td>
                        <td width="35%">: <?= sanitize($mr['requester_name']) ?></td>
                        <td width="15%"><strong>Form No</strong></td>
                        <td width="35%">: <?= sanitize($mr['mr_number']) ?></td>
                    </tr>
                    <tr>
                        <td><strong>Project</strong></td>
                        <td>: <?= sanitize($mr['project_name']) ?></td>
                        <td><strong>Date</strong></td>
                        <td>: <?= formatDateIndo($mr['request_date']) ?></td>
                    </tr>
                    <tr>
                        <td><strong>Location</strong></td>
                        <td colspan="3">: <?= sanitize($mr['location']) ?></td>
                    </tr>
                </table>
                
                <?php if ($mr['reject_reason']): ?>
                <div class="mb-2" style="font-size: 13px; color:#000;">
                    <strong class="text-danger">Alasan Penolakan:</strong> <?= nl2br(sanitize($mr['reject_reason'])) ?>
                </div>
                <?php endif; ?>
                
                <?php if ($mr['notes']): ?>
                <div class="mb-3" style="font-size: 13px; color:#000;">
                    <strong>Catatan:</strong> <?= nl2br(sanitize($mr['notes'])) ?>
                </div>
                <?php endif; ?>
                
                <!-- 3. Tabel Detail Item -->
                <div class="table-responsive mb-4">
                    <table class="table table-bordered table-sm report-table mb-0" style="width: 100%;">
                        <thead>
                            <tr class="text-center font-weight-bold">
                                <th style="width: 5%;">NO</th>
                                <th style="width: 18%;">KODE BARANG</th>
                                <th>NAMA / DESKRIPSI</th>
                                <th style="width: 25%;">SPESIFIKASI / TIPE</th>
                                <th style="width: 10%;">QTY</th>
                                <th style="width: 10%;">SATUAN</th>
                                <th style="width: 20%;">REMARK</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $no = 1; foreach ($mrItems as $item): ?>
                            <tr>
                                <td class="text-center"><?= $no++ ?></td>
                                <td><strong><?= sanitize($item['item_code']) ?: '-' ?></strong></td>
                                <td><?= sanitize($item['description']) ?></td>
                                <td><?= sanitize($item['type_specification']) ?: '-' ?></td>
                                <td class="text-center font-weight-bold"><?= number_format($item['qty'], 0) ?></td>
                                <td class="text-center"><?= sanitize($item['uom']) ?></td>
                                <td><?= sanitize($item['remark']) ?: '-' ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- 4. Signatures -->
                <div class="row mt-5 pt-3 text-center" style="font-size:14px; color:#000;">
                    <div class="col-sm-4">
                        <p class="mb-5">Dimohon Oleh,</p>
                        <strong><?= sanitize($mr['requester_name']) ?></strong>
                        <p class="text-muted">Karyawan / Proyek</p>
                    </div>
                    <div class="col-sm-4">
                        <p class="mb-5">Diperiksa Oleh,</p>
                        <strong><?= sanitize($user['full_name']) ?></strong>
                        <p class="text-muted">Purchasing</p>
                    </div>
                    <div class="col-sm-4">
                        <p class="mb-5">Menyetujui,</p>
                        <?php if ($mr['status'] === 'approved' || $mr['status'] === 'completed'): ?>
                            <strong><?= sanitize($mr['approver_name']) ?></strong>
                            <p class="text-muted mb-0">Super Admin</p>
                            <span style="font-size:12px; color:#666;">(<?= formatDateIndo($mr['approved_at']) ?>)</span>
                        <?php else: ?>
                            <strong class="text-muted">( Belum Disetujui )</strong>
                        <?php endif; ?>
                    </div>
                </div>
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
                        <label>Alasan Penolakan <span class="text-danger">*</span></label>
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
/* --- Gaya Laporan di Layar --- */
.printable-area {
    color: #1e293b !important;
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif !important;
    font-size: 13px;
}

.kop-separator {
    border-top: 3px solid #1e293b;
    border-bottom: 1px solid #1e293b;
    height: 5px;
    margin-top: 10px;
    margin-bottom: 20px;
}

.info-card {
    background-color: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    padding: 12px 15px;
    height: 100%;
}

.info-title {
    font-size: 11px;
    font-weight: 700;
    color: #64748b;
    letter-spacing: 0.5px;
    margin-bottom: 8px;
    border-bottom: 1px solid #e2e8f0;
    padding-bottom: 5px;
    text-transform: uppercase;
}

.table-info {
    width: 100%;
    font-size: 13px;
    color: #1e293b;
}

.table-info td {
    padding: 3px 0;
    vertical-align: top;
}

.info-label {
    width: 100px;
    font-weight: 600;
    color: #475569;
}

.report-table {
    width: 100% !important;
    border-collapse: collapse !important;
    margin-top: 10px;
}

.report-table th, .report-table td {
    border: 1px solid #cbd5e1 !important;
    padding: 7px 10px !important;
    vertical-align: middle !important;
    font-size: 12.5px !important;
    color: #1e293b !important;
}

.report-table thead th {
    background-color: #f1f5f9 !important;
    color: #1e293b !important;
    font-weight: 700 !important;
    text-transform: uppercase !important;
    letter-spacing: 0.5px !important;
    border-bottom: 2px solid #cbd5e1 !important;
}

.report-table tbody tr:nth-child(even) {
    background-color: #f8fafc;
}

.report-table tbody tr:hover {
    background-color: #f1f5f9;
}

.signature-box {
    background-color: #ffffff;
    border: 1px dashed #cbd5e1;
    border-radius: 8px;
    padding: 15px 12px;
    min-height: 175px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    height: 100%;
}

.signature-title {
    font-size: 11px;
    font-weight: 700;
    color: #475569;
    margin-bottom: 5px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 1px solid #f1f5f9;
    padding-bottom: 4px;
}

.signature-space {
    height: 70px;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
}

.signature-date {
    font-size: 10px;
    color: #64748b;
    background-color: #f1f5f9;
    padding: 2px 6px;
    border-radius: 4px;
}

.signature-role-staff {
    font-size: 11px;
    color: #64748b;
}

.signature-name {
    font-weight: 700;
    color: #0f172a;
    text-decoration: underline;
    font-size: 13px;
    text-transform: uppercase;
    display: block;
}

.signature-role {
    font-size: 11px;
    color: #64748b;
    margin-top: 2px;
}

.approved-stamp {
    border: 2px dashed #10b981;
    color: #10b981 !important;
    font-weight: 800;
    border-radius: 6px;
    padding: 4px 12px;
    transform: rotate(-3deg);
    display: inline-block;
    text-transform: uppercase;
    background-color: rgba(16, 185, 129, 0.05);
    box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.02);
}

.approved-stamp .stamp-icon {
    font-size: 16px;
    margin-bottom: 2px;
}

.approved-stamp .stamp-text {
    font-size: 13px;
    letter-spacing: 1px;
    line-height: 1;
}

.approved-stamp .stamp-date {
    font-size: 8px;
    margin-top: 3px;
    font-weight: 500;
    color: #047857 !important;
}

/* --- Gaya Khusus Saat Cetak --- */
@media print {
    @page {
        size: A4 portrait;
        margin: 12mm 15mm;
    }
    body { 
        background-color: white !important; 
        color: #000000 !important;
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
    .info-card {
        background-color: #ffffff !important;
        border: 1px solid #cbd5e1 !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
    /* Memaksa border berwarna hitam solid saat diprint */
    .report-table th, .report-table td {
        border: 1px solid #000000 !important;
        color: #000000 !important;
        font-size: 11.5px !important;
        padding: 6px 8px !important;
    }
    /* Memaksa warna background abu-abu tercetak */
    .report-table thead th {
        background-color: #f1f5f9 !important;
        color: #000000 !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
    .report-table tbody tr:nth-child(even) {
        background-color: #f8fafc !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
    .signature-box {
        border: 1px dashed #94a3b8 !important;
        background-color: #ffffff !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
    .approved-stamp {
        background-color: rgba(16, 185, 129, 0.05) !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
}
</style>

<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>
