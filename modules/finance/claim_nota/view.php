<?php
/**
 * Finance - View Claim Nota (Detail, Approve, Reimburse)
 */
require_once __DIR__ . '/../../../includes/auth.php';
requirePermission('claim_nota');

$user = getCurrentUser();
$id = (int)($_GET['id'] ?? 0);

// Fetch claim with relations
$stmt = $pdo->prepare("
    SELECT cn.*, 
           p.name as project_name, p.abbreviation as project_abbr,
           c.name as company_name,
           u.full_name as claimer_name,
           ua.full_name as approver_name,
           ur.full_name as reimburser_name
    FROM claim_notas cn
    JOIN projects p ON cn.project_id = p.id
    JOIN companies c ON cn.company_id = c.id
    LEFT JOIN users u ON cn.claimed_by = u.id
    LEFT JOIN users ua ON cn.approved_by = ua.id
    LEFT JOIN users ur ON cn.reimbursed_by = ur.id
    WHERE cn.id = ?
");
$stmt->execute([$id]);
$claim = $stmt->fetch();

if (!$claim) {
    setFlash('danger', 'Claim Nota tidak ditemukan.');
    header('Location: index.php');
    exit;
}

// Fetch items
$stmtItems = $pdo->prepare("
    SELECT ci.*, i.item_code 
    FROM claim_nota_items ci 
    LEFT JOIN items i ON ci.item_id = i.id 
    WHERE ci.claim_id = ? ORDER BY ci.id
");
$stmtItems->execute([$id]);
$claimItems = $stmtItems->fetchAll();

// Handle Approve action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_approve'])) {
    if ($claim['status'] !== 'pending') {
        setFlash('warning', 'Hanya claim Pending yang bisa di-approve.');
    } else {
        $pdo->prepare("UPDATE claim_notas SET status = 'approved', approved_by = ?, approved_at = NOW() WHERE id = ?")
            ->execute([$user['id'], $id]);
        setFlash('success', 'Claim Nota berhasil di-approve.');
    }
    header('Location: view.php?id=' . $id);
    exit;
}

// Handle Reject action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_reject'])) {
    $rejectReason = trim($_POST['reject_reason'] ?? '');
    if ($claim['status'] !== 'pending') {
        setFlash('warning', 'Hanya claim Pending yang bisa di-reject.');
    } else {
        $pdo->prepare("UPDATE claim_notas SET status = 'rejected', approved_by = ?, approved_at = NOW(), reject_reason = ? WHERE id = ?")
            ->execute([$user['id'], $rejectReason, $id]);
        setFlash('danger', 'Claim Nota telah di-reject.');
    }
    header('Location: view.php?id=' . $id);
    exit;
}

// Handle Reimburse action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_reimburse'])) {
    if ($claim['status'] !== 'approved') {
        setFlash('warning', 'Hanya claim Approved yang bisa di-reimburse.');
    } else {
        $reimbDate = $_POST['reimburse_date'] ?? date('Y-m-d');
        $reimbMethod = $_POST['reimburse_method'] ?? '';
        $reimbRef = $_POST['reimburse_reference'] ?? '';
        
        $pdo->prepare("UPDATE claim_notas SET is_reimbursed = 1, reimbursed_at = ?, reimbursed_by = ?, reimbursement_method = ?, reimbursement_reference = ? WHERE id = ?")
            ->execute([$reimbDate, $user['id'], $reimbMethod, $reimbRef, $id]);
        setFlash('success', 'Reimbursement berhasil dicatat.');
    }
    header('Location: view.php?id=' . $id);
    exit;
}

// Refresh claim data after any action
$stmt->execute([$id]);
$claim = $stmt->fetch();

$pageTitle = 'Detail Claim Nota';
$breadcrumbs = [
    ['label' => 'Finance', 'url' => '#'],
    ['label' => 'Claim Nota', 'url' => 'index.php'],
    ['label' => $claim['claim_number']]
];

require_once __DIR__ . '/../../../includes/header.php';
?>

<div class="row">
    <div class="col-md-8">
        <!-- Header Info -->
        <div class="card card-primary card-outline">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title"><i class="fas fa-receipt mr-2"></i> <?= sanitize($claim['claim_number']) ?></h3>
                <div>
                    <?php if ($claim['is_reimbursed']): ?>
                        <?= getStatusBadge('reimbursed') ?>
                    <?php else: ?>
                        <?= getStatusBadge($claim['status']) ?>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-sm table-borderless" style="font-size:13px;">
                            <tr><td class="text-muted" width="40%">No. Claim</td><td><strong><?= sanitize($claim['claim_number']) ?></strong></td></tr>
                            <tr><td class="text-muted">Tanggal</td><td><?= formatDate($claim['claim_date']) ?></td></tr>
                            <tr><td class="text-muted">Karyawan</td><td><strong><?= sanitize($claim['employee_name']) ?></strong></td></tr>
                            <tr><td class="text-muted">Dibuat oleh</td><td><?= sanitize($claim['claimer_name']) ?></td></tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-sm table-borderless" style="font-size:13px;">
                            <tr><td class="text-muted" width="40%">Proyek</td><td><span class="badge badge-light"><?= sanitize($claim['project_abbr']) ?></span> <?= sanitize($claim['project_name']) ?></td></tr>
                            <tr><td class="text-muted">Perusahaan</td><td><?= sanitize($claim['company_name']) ?></td></tr>
                            <tr><td class="text-muted">Nama Toko</td><td><?= sanitize($claim['store_name']) ?: '-' ?></td></tr>
                            <tr><td class="text-muted">Catatan</td><td><?= sanitize($claim['notes']) ?: '-' ?></td></tr>
                        </table>
                    </div>
                </div>
                
                <?php if ($claim['receipt_photo']): ?>
                <div class="mt-2">
                    <strong><i class="fas fa-camera mr-1"></i> Foto Nota:</strong>
                    <?php 
                    $ext = strtolower(pathinfo($claim['receipt_photo'], PATHINFO_EXTENSION));
                    $photoUrl = APP_URL . '/uploads/claim_receipts/' . $claim['receipt_photo'];
                    ?>
                    <?php if ($ext === 'pdf'): ?>
                        <a href="<?= $photoUrl ?>" target="_blank" class="btn btn-sm btn-outline-info ml-2">
                            <i class="fas fa-file-pdf mr-1"></i> Lihat PDF
                        </a>
                    <?php else: ?>
                        <div class="mt-2">
                            <a href="<?= $photoUrl ?>" target="_blank">
                                <img src="<?= $photoUrl ?>" class="img-thumbnail" style="max-height:300px;" alt="Nota">
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Items Table -->
        <div class="card card-outline card-success">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-list mr-2"></i> Detail Item</h3>
            </div>
            <div class="card-body p-0">
                <table class="table table-bordered table-striped mb-0" style="font-size:13px;">
                    <thead class="bg-light">
                        <tr>
                            <th width="5%" class="text-center">No</th>
                            <th width="10%">Kode</th>
                            <th width="30%">Nama Item</th>
                            <th width="10%" class="text-center">Qty</th>
                            <th width="8%">Satuan</th>
                            <th width="17%" class="text-right">Harga Satuan</th>
                            <th width="17%" class="text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $no = 1; foreach ($claimItems as $ci): ?>
                        <tr>
                            <td class="text-center"><?= $no++ ?></td>
                            <td><?= $ci['item_code'] ? sanitize($ci['item_code']) : '<span class="text-muted">Manual</span>' ?></td>
                            <td><?= sanitize($ci['item_name']) ?></td>
                            <td class="text-center"><?= (float)$ci['qty'] ?></td>
                            <td><?= sanitize($ci['uom']) ?: '-' ?></td>
                            <td class="text-right"><?= formatRupiah($ci['unit_price']) ?></td>
                            <td class="text-right font-weight-bold"><?= formatRupiah($ci['total']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="bg-light font-weight-bold">
                            <td colspan="6" class="text-right">TOTAL:</td>
                            <td class="text-right text-primary" style="font-size:15px;"><?= formatRupiah($claim['subtotal']) ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <!-- Action buttons -->
        <div class="mb-3">
            <a href="index.php" class="btn btn-default"><i class="fas fa-arrow-left mr-1"></i> Kembali</a>
            <?php if ($claim['status'] === 'draft'): ?>
                <a href="edit.php?id=<?= $id ?>" class="btn btn-warning ml-1"><i class="fas fa-edit mr-1"></i> Edit</a>
                <form action="submit.php" method="POST" class="d-inline ml-1">
                    <input type="hidden" name="id" value="<?= $id ?>">
                    <button type="submit" class="btn btn-primary" onclick="return confirm('Submit claim ini untuk approval?')">
                        <i class="fas fa-paper-plane mr-1"></i> Submit
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- Right Panel -->
    <div class="col-md-4">
        <!-- Status Trail -->
        <div class="card card-outline card-secondary">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-history mr-1"></i> Status Trail</h3>
            </div>
            <div class="card-body" style="font-size:13px;">
                <div class="timeline timeline-inverse" style="margin:0;">
                    <div class="time-label"><span class="bg-secondary">Dibuat</span></div>
                    <div><i class="fas fa-plus bg-primary"></i>
                        <div class="timeline-item">
                            <span class="time"><i class="fas fa-clock"></i> <?= formatDateTime($claim['created_at']) ?></span>
                            <h3 class="timeline-header">Dibuat oleh <?= sanitize($claim['claimer_name']) ?></h3>
                        </div>
                    </div>
                    
                    <?php if ($claim['status'] !== 'draft'): ?>
                    <div class="time-label"><span class="bg-warning">Disubmit</span></div>
                    <div><i class="fas fa-paper-plane bg-warning"></i>
                        <div class="timeline-item">
                            <h3 class="timeline-header">Menunggu Approval</h3>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($claim['status'] === 'approved' || $claim['status'] === 'rejected'): ?>
                    <div class="time-label"><span class="bg-<?= $claim['status'] === 'approved' ? 'success' : 'danger' ?>">
                        <?= $claim['status'] === 'approved' ? 'Approved' : 'Rejected' ?>
                    </span></div>
                    <div><i class="fas fa-<?= $claim['status'] === 'approved' ? 'check' : 'times' ?> bg-<?= $claim['status'] === 'approved' ? 'success' : 'danger' ?>"></i>
                        <div class="timeline-item">
                            <span class="time"><i class="fas fa-clock"></i> <?= formatDateTime($claim['approved_at']) ?></span>
                            <h3 class="timeline-header"><?= $claim['status'] === 'approved' ? 'Disetujui' : 'Ditolak' ?> oleh <?= sanitize($claim['approver_name']) ?></h3>
                            <?php if ($claim['reject_reason']): ?>
                                <div class="timeline-body text-danger"><?= sanitize($claim['reject_reason']) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($claim['is_reimbursed']): ?>
                    <div class="time-label"><span class="bg-info">Reimbursed</span></div>
                    <div><i class="fas fa-money-bill-wave bg-info"></i>
                        <div class="timeline-item">
                            <span class="time"><i class="fas fa-clock"></i> <?= formatDate($claim['reimbursed_at']) ?></span>
                            <h3 class="timeline-header">Reimbursed oleh <?= sanitize($claim['reimburser_name']) ?></h3>
                            <div class="timeline-body">
                                Metode: <?= sanitize($claim['reimbursement_method']) ?: '-' ?><br>
                                Ref: <?= sanitize($claim['reimbursement_reference']) ?: '-' ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Approval Panel (for pending claims) -->
        <?php if ($claim['status'] === 'pending' && (canAccess('claim_nota', 'edit'))): ?>
        <div class="card card-outline card-warning">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-gavel mr-1"></i> Approval</h3>
            </div>
            <div class="card-body">
                <form action="" method="POST" class="mb-2">
                    <button type="submit" name="action_approve" value="1" class="btn btn-success btn-block" 
                            onclick="return confirm('Approve claim nota ini?')">
                        <i class="fas fa-check mr-1"></i> Approve
                    </button>
                </form>
                <hr>
                <form action="" method="POST">
                    <div class="form-group">
                        <label class="text-danger"><i class="fas fa-times-circle mr-1"></i> Reject</label>
                        <textarea name="reject_reason" class="form-control" rows="2" placeholder="Alasan penolakan..."></textarea>
                    </div>
                    <button type="submit" name="action_reject" value="1" class="btn btn-danger btn-block"
                            onclick="return confirm('Reject claim nota ini?')">
                        <i class="fas fa-times mr-1"></i> Reject
                    </button>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <!-- Reimbursement Panel (for approved claims) -->
        <?php if ($claim['status'] === 'approved' && !$claim['is_reimbursed'] && canAccess('claim_nota', 'edit')): ?>
        <div class="card card-outline card-info">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-money-bill-wave mr-1"></i> Reimbursement</h3>
            </div>
            <div class="card-body">
                <form action="" method="POST">
                    <div class="form-group">
                        <label>Tanggal Reimburse</label>
                        <input type="date" name="reimburse_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Metode Pembayaran</label>
                        <select name="reimburse_method" class="form-control">
                            <option value="">-- Pilih --</option>
                            <option value="Transfer Bank">Transfer Bank</option>
                            <option value="Cash">Cash (Tunai)</option>
                            <option value="E-Wallet">E-Wallet</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>No. Referensi</label>
                        <input type="text" name="reimburse_reference" class="form-control" placeholder="No. transfer/bukti">
                    </div>
                    <button type="submit" name="action_reimburse" value="1" class="btn btn-info btn-block"
                            onclick="return confirm('Catat reimbursement untuk claim ini?')">
                        <i class="fas fa-check-circle mr-1"></i> Catat Reimbursement
                    </button>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>
