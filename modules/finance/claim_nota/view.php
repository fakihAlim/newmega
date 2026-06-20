<?php
/**
 * Finance - Claim Nota View / Action
 */
require_once __DIR__ . '/../../../includes/auth.php';
requirePermission('claim_nota');

$id = $_GET['id'] ?? 0;

// Fetch Claim Header
$stmt = $pdo->prepare("
    SELECT c.*, comp.name as company_name, u.full_name as creator_name, u2.full_name as approver_name
    FROM nota_claims c
    LEFT JOIN companies comp ON c.company_id = comp.id
    LEFT JOIN users u ON c.created_by = u.id
    LEFT JOIN users u2 ON c.employee_id = u2.id -- if employee_id exists, we can get account name, else fallback is employee_name
    WHERE c.id = ?
");
$stmt->execute([$id]);
$claim = $stmt->fetch();

if (!$claim) {
    setFlash('danger', 'Klaim Nota tidak ditemukan.');
    header('Location: ' . APP_URL . '/modules/finance/claim_nota/index.php');
    exit;
}

$user = getCurrentUser();

// Handle Status Update Actions (Approve, Reject, Pay)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!hasRole(['super_admin', 'finance'])) {
        setFlash('danger', 'Anda tidak memiliki hak akses untuk mengubah status klaim ini.');
        header("Location: view.php?id=$id");
        exit;
    }

    $action = $_POST['action'];
    $reason = trim($_POST['reject_reason'] ?? '');

    if ($action === 'approve') {
        $upStmt = $pdo->prepare("UPDATE nota_claims SET status = 'approved', reject_reason = NULL WHERE id = ?");
        $upStmt->execute([$id]);
        setFlash('success', "Klaim {$claim['claim_number']} berhasil disetujui.");
    } elseif ($action === 'pay') {
        $upStmt = $pdo->prepare("UPDATE nota_claims SET status = 'paid' WHERE id = ?");
        $upStmt->execute([$id]);
        setFlash('success', "Klaim {$claim['claim_number']} ditandai sebagai Telah Dibayar (Lunas).");
    } elseif ($action === 'reject') {
        if (empty($reason)) {
            setFlash('danger', 'Alasan penolakan wajib diisi.');
        } else {
            $upStmt = $pdo->prepare("UPDATE nota_claims SET status = 'rejected', reject_reason = ? WHERE id = ?");
            $upStmt->execute([$reason, $id]);
            setFlash('danger', "Klaim {$claim['claim_number']} telah ditolak.");
        }
    }

    header("Location: view.php?id=$id");
    exit;
}

// Fetch Claim Items
$stmtItems = $pdo->prepare("
    SELECT ci.*, p.name as project_name 
    FROM nota_claim_items ci
    LEFT JOIN projects p ON ci.project_id = p.id
    WHERE ci.claim_id = ?
    ORDER BY ci.id ASC
");
$stmtItems->execute([$id]);
$claimItems = $stmtItems->fetchAll();

// Group items by group_name
$groupedItems = [];
foreach ($claimItems as $item) {
    $group = !empty($item['group_name']) ? trim($item['group_name']) : 'Money change';
    $groupedItems[$group][] = $item;
}

$pageTitle = 'Detail Klaim: ' . sanitize($claim['claim_number']);
$breadcrumbs = [
    ['label' => 'Finance', 'url' => '#'],
    ['label' => 'Claim Nota', 'url' => APP_URL . '/modules/finance/claim_nota/index.php'],
    ['label' => 'Detail']
];

require_once __DIR__ . '/../../../includes/header.php';
?>

<div class="row">
    <div class="col-md-12">
        <div class="card card-outline card-primary">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title text-primary"><i class="fas fa-file-invoice-dollar mr-2"></i> Klaim Nota: <strong><?= sanitize($claim['claim_number']) ?></strong></h3>
                <div class="ml-auto">
                    <!-- Status Badge -->
                    <?php
                    $badge = 'secondary';
                    $label = ucfirst($claim['status']);
                    if ($claim['status'] === 'pending') { $badge = 'warning'; $label = 'Menunggu Approval'; }
                    if ($claim['status'] === 'approved') { $badge = 'success'; $label = 'Disetujui'; }
                    if ($claim['status'] === 'paid') { $badge = 'info'; $label = 'Lunas (Paid)'; }
                    if ($claim['status'] === 'rejected') { $badge = 'danger'; $label = 'Ditolak'; }
                    ?>
                    <span class="badge badge-<?= $badge ?> mr-2 p-2" style="font-size: 14px;"><?= $label ?></span>
                    
                    <button class="btn btn-default btn-sm mr-1" onclick="window.print()"><i class="fas fa-print mr-1"></i> Cetak</button>
                    <a href="export_excel.php?id=<?= $id ?>" class="btn btn-success btn-sm mr-1"><i class="fas fa-file-excel mr-1"></i> Ekspor Excel</a>
                    <a href="index.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left mr-1"></i> Kembali</a>
                </div>
            </div>

            <div class="card-body printable-area p-4 bg-white">
                <h3 class="text-center font-weight-bold mb-4 text-uppercase">Nota Developer dan Kantor</h3>

                <table class="table table-sm table-borderless table-header-info mb-4">
                    <tr>
                        <td width="15%" class="font-weight-bold">Karyawan</td>
                        <td width="45%">: <?= sanitize($claim['employee_name']) ?></td>
                        <td width="15%" class="font-weight-bold">No. Klaim</td>
                        <td width="25%">: <span class="font-weight-bold text-danger"><?= sanitize($claim['claim_number']) ?></span></td>
                    </tr>
                    <tr>
                        <td class="font-weight-bold">Perusahaan</td>
                        <td>: <?= sanitize($claim['company_name']) ?></td>
                        <td class="font-weight-bold">Tanggal</td>
                        <td>: <?= date('d-M-Y', strtotime($claim['claim_date'])) ?></td>
                    </tr>
                    <?php if ($claim['notes']): ?>
                        <tr>
                            <td class="font-weight-bold">Catatan</td>
                            <td colspan="3">: <?= nl2br(sanitize($claim['notes'])) ?></td>
                        </tr>
                    <?php endif; ?>
                </table>

                <?php if ($claim['reject_reason']): ?>
                    <div class="alert alert-danger mb-4 d-print-none">
                        <strong>Alasan Penolakan:</strong><br>
                        <?= nl2br(sanitize($claim['reject_reason'])) ?>
                    </div>
                <?php endif; ?>

                <!-- Grouped Items Tables -->
                <?php foreach ($groupedItems as $groupName => $items): ?>
                    <div class="group-section mb-4">
                        <h5 class="font-weight-bold text-dark mb-1" style="font-size:15px; border-bottom: 2px solid #dee2e6; padding-bottom: 4px;">
                            <?= sanitize($groupName) ?>
                        </h5>
                        
                        <table class="table table-bordered table-sm excel-table mb-2">
                            <thead>
                                <tr class="bg-light">
                                    <th width="12%" class="text-center">Tanggal</th>
                                    <th width="50%">item</th>
                                    <th width="8%" class="text-center">Pcs</th>
                                    <th width="15%" class="text-right">Harga</th>
                                    <th width="15%" class="text-right">Jumlah</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $groupSubtotal = 0;
                                foreach ($items as $item): 
                                    $groupSubtotal += $item['amount'];
                                ?>
                                    <tr>
                                        <td class="text-center"><?= date('d/m/Y', strtotime($item['item_date'])) ?></td>
                                        <td>
                                            <?= sanitize($item['item_name']) ?>
                                            <?php if ($item['receipt_photo']): ?>
                                                <span class="d-print-none ml-2">
                                                    <a href="<?= APP_URL ?>/assets/uploads/receipts/<?= $item['receipt_photo'] ?>" target="_blank" class="text-info text-xs" title="Lihat Foto Nota">
                                                        <i class="fas fa-image"></i> (Nota)
                                                    </a>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center"><?= number_format($item['qty'], 0, '', '') ?></td>
                                        <td class="text-right"><?= formatRupiah($item['price'], '') ?></td>
                                        <td class="text-right"><?= formatRupiah($item['amount'], '') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <!-- Subtotal row for this group -->
                                <tr class="font-weight-bold bg-light">
                                    <td colspan="4" class="text-right">Total</td>
                                    <td class="text-right"><?= formatRupiah($groupSubtotal, '') ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                <?php endforeach; ?>

                <div class="row mt-4">
                    <div class="col-md-6 offset-md-6 text-right">
                        <div class="border p-2 bg-light d-inline-block text-right" style="min-width: 250px; border-radius: 4px;">
                            <span class="font-weight-normal text-muted">Grand Total:</span><br>
                            <span class="text-xl text-bold text-danger"><?= formatRupiah($claim['total_amount']) ?></span>
                        </div>
                    </div>
                </div>

                <!-- Signatures -->
                <table class="table table-borderless text-center mt-5 signature-table d-print-table" style="width: 100%;">
                    <tr>
                        <td width="33%">Diajukan Oleh,</td>
                        <td width="34%">Disetujui Oleh,</td>
                        <td width="33%">Dibayar Oleh,</td>
                    </tr>
                    <tr>
                        <td style="height: 70px;"></td>
                        <td></td>
                        <td></td>
                    </tr>
                    <tr class="font-weight-bold">
                        <td>( <?= sanitize($claim['employee_name']) ?> )</td>
                        <td>( ____________________ )</td>
                        <td>( ____________________ )</td>
                    </tr>
                    <tr class="text-muted" style="font-size: 11px;">
                        <td>Karyawan / Penerima</td>
                        <td>Finance / Admin</td>
                        <td>Kasir / Pembayar</td>
                    </tr>
                </table>
            </div>

            <!-- Approval actions footer for super_admin / finance -->
            <?php if (hasRole(['super_admin', 'finance'])): ?>
                <div class="card-footer bg-light text-right d-print-none">
                    <?php if ($claim['status'] === 'pending'): ?>
                        <form method="POST" id="formApprove" class="d-inline">
                            <input type="hidden" name="action" value="approve">
                            <button type="button" class="btn btn-success" onclick="confirmAction('Setujui Klaim ini?', 'Klaim ini akan disetujui untuk kemudian dapat dibayarkan.', function() { $('#formApprove').submit(); })">
                                <i class="fas fa-check mr-1"></i> Setujui Klaim
                            </button>
                        </form>
                        
                        <button type="button" class="btn btn-danger ml-2" data-toggle="modal" data-target="#modalReject">
                            <i class="fas fa-times mr-1"></i> Tolak (Reject)
                        </button>
                    <?php elseif ($claim['status'] === 'approved'): ?>
                        <form method="POST" id="formPay" class="d-inline">
                            <input type="hidden" name="action" value="pay">
                            <button type="button" class="btn btn-info" onclick="confirmAction('Bayar Klaim ini?', 'Tandai klaim ini sebagai Lunas/Telah Dibayar ke karyawan.', function() { $('#formPay').submit(); })">
                                <i class="fas fa-money-bill-wave mr-1"></i> Tandai Telah Dibayar
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<!-- Modal Reject -->
<div class="modal fade" id="modalReject" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <form method="POST">
            <?= csrfField() ?>
            <div class="modal-content border-danger">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">Tolak Klaim Nota</h5>
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
                    <button type="submit" class="btn btn-danger">Tolak Klaim</button>
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
    .main-sidebar, .main-header, .d-print-none, .card-footer, .brand-link { display: none !important; }
    .content-wrapper { margin-left: 0 !important; padding: 0 !important; background: white; }
    .card { border: none !important; box-shadow: none !important; margin: 0 !important; padding: 0 !important; }
    .card-header { padding-top: 0 !important; display: none !important; }
    .printable-area { width: 100% !important; padding: 0 !important; margin: 0 !important; }
    
    .excel-table { border-collapse: collapse; width: 100% !important; margin-bottom: 15px !important; }
    .excel-table th, .excel-table td {
        border: 1px solid #000 !important;
        padding: 4px 6px !important;
        background-color: transparent !important;
        color: #000 !important;
        -webkit-print-color-adjust: exact;
    }
    .signature-table td, .signature-table th {
        border: none !important;
    }
    .text-primary, .text-danger { color: #000 !important; }
}

.table-header-info { margin-bottom: 10px; }
.table-header-info td { padding: 0.3rem; vertical-align: top; }
.excel-table { border-collapse: collapse; }
.excel-table th, .excel-table td { border: 1px solid #000 !important; padding: 0.4rem; vertical-align: middle; }
.excel-table thead th { background-color: #f8f9fa; border-bottom: 2px solid #000 !important; }
.signature-table td { padding-top: 15px; }
</style>

<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>
