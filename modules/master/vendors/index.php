<?php
/**
 * Master Vendors - List
 */
require_once __DIR__ . '/../../../includes/auth.php';
requirePermission('master_vendors');

$pageTitle = 'Master Vendor / Supplier';
$breadcrumbs = [
    ['label' => 'Master Data', 'url' => '#'],
    ['label' => 'Vendor']
];

// Handle Delete/Deactivate
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && in_array($_POST['action'], ['status_toggle', 'delete'])) {
    $id = $_POST['id'] ?? 0;
    
    $stmt = $pdo->prepare("SELECT is_active FROM vendors WHERE id = ?");
    $stmt->execute([$id]);
    $vendor = $stmt->fetch();
    
    if ($vendor) {
        if ($_POST['action'] === 'status_toggle') {
            $newStatus = $vendor['is_active'] ? 0 : 1;
            $update = $pdo->prepare("UPDATE vendors SET is_active = ? WHERE id = ?");
            if ($update->execute([$newStatus, $id])) {
                setFlash('success', 'Status vendor berhasil diubah.');
            } else {
                setFlash('danger', 'Gagal mengubah status vendor.');
            }
        } elseif ($_POST['action'] === 'delete') {
            // Check if used
            $stmtUse = $pdo->prepare("SELECT COUNT(*) FROM purchase_orders WHERE vendor_id = ?");
            $stmtUse->execute([$id]);
            $isUsed = $stmtUse->fetchColumn() > 0;
            
            if ($isUsed) {
                setFlash('danger', 'Gagal menghapus! Vendor sudah memiliki histori transaksi (PO).');
            } else {
                try {
                    $delete = $pdo->prepare("DELETE FROM vendors WHERE id = ?");
                    if ($delete->execute([$id])) {
                        setFlash('success', 'Vendor berhasil dihapus secara permanen.');
                    } else {
                        setFlash('danger', 'Gagal menghapus vendor.');
                    }
                } catch (PDOException $e) {
                    if ($e->getCode() == '23000') {
                        setFlash('danger', 'Gagal menghapus! Vendor ini sedang digunakan pada data lain (contoh: Proyek, MR, dll).');
                    } else {
                        setFlash('danger', 'Terjadi kesalahan sistem saat menghapus data.');
                    }
                }
            }
        }
    }
    header('Location: ' . APP_URL . '/modules/master/vendors/index.php');
    exit;
}

// Fetch Vendors
$vendors = $pdo->query("
    SELECT v.*,
    (SELECT COUNT(*) FROM purchase_orders WHERE vendor_id = v.id) as used_count
    FROM vendors v 
    ORDER BY v.company_name ASC
")->fetchAll();

require_once __DIR__ . '/../../../includes/header.php';
?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title">Daftar Vendor / Supplier</h3>
        <div class="ml-auto">
            <a href="<?= APP_URL ?>/modules/master/vendors/create.php" class="btn btn-primary btn-sm">
                <i class="fas fa-plus mr-1"></i> Tambah Vendor
            </a>
            <button type="button" class="btn btn-success btn-sm ml-1" data-toggle="modal" data-target="#importModal">
                <i class="fas fa-file-excel mr-1"></i> Import Excel
            </button>
        </div>
    </div>
    <div class="card-body">
        <table id="vendorsTable" class="table table-bordered table-striped w-100" style="font-size: 13.5px;">
            <thead>
                <tr>
                    <th width="5%">No</th>
                    <th width="8%">Kode</th>
                    <th width="27%">Nama Perusahaan</th>
                    <th width="20%">PIC & Kontak</th>
                    <th width="20%">Termin Pembayaran</th>
                    <th width="10%">Status</th>
                    <th width="10%" class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($vendors as $i => $v): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td>
                        <span class="badge badge-info" style="font-size: 13px;"><?= sanitize($v['abbreviation']) ?></span>
                    </td>
                    <td>
                        <strong class="d-block text-dark"><?= sanitize($v['company_name']) ?></strong>
                        <small class="text-muted d-block mt-1"><?= sanitize($v['address']) ?></small>
                    </td>
                    <td>
                        <div class="mb-1"><i class="fas fa-user text-muted" style="width:15px;"></i> <?= sanitize($v['pic_name']) ?></div>
                        <?php if ($v['phone']): ?>
                        <div class="mb-1"><i class="fas fa-phone text-muted" style="width:15px;"></i> <small><?= sanitize($v['phone']) ?></small></div>
                        <?php endif; ?>
                        <?php if ($v['email']): ?>
                        <div><i class="fas fa-envelope text-muted" style="width:15px;"></i> <small><?= sanitize($v['email']) ?></small></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($v['payment_terms']): ?>
                            <span class="badge badge-light border"><?= sanitize($v['payment_terms']) ?></span>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                    <td><?= getStatusBadge($v['is_active'] ? 'active' : 'cancelled') ?></td>
                    <td class="text-center">
                        <div class="btn-group">
                            <a href="<?= APP_URL ?>/modules/master/vendors/edit.php?id=<?= $v['id'] ?>" class="btn btn-info btn-sm" data-toggle="tooltip" title="Ubah">
                                <i class="fas fa-edit"></i>
                            </a>
                            
                            <?php if ($v['is_active']): ?>
                                <button type="button" class="btn btn-warning btn-sm action-btn" 
                                    data-id="<?= $v['id'] ?>" 
                                    data-name="<?= sanitize($v['company_name']) ?>" 
                                    data-action="status_toggle"
                                    data-status="0"
                                    data-toggle="tooltip" title="Nonaktifkan">
                                    <i class="fas fa-ban text-white"></i>
                                </button>
                            <?php else: ?>
                                <button type="button" class="btn btn-success btn-sm action-btn" 
                                    data-id="<?= $v['id'] ?>" 
                                    data-name="<?= sanitize($v['company_name']) ?>" 
                                    data-action="status_toggle"
                                    data-status="1"
                                    data-toggle="tooltip" title="Aktifkan">
                                    <i class="fas fa-check"></i>
                                </button>
                            <?php endif; ?>
                            
                            <?php if ($v['used_count'] == 0): ?>
                                <button type="button" class="btn btn-danger btn-sm action-btn" 
                                    data-id="<?= $v['id'] ?>" 
                                    data-name="<?= sanitize($v['company_name']) ?>" 
                                    data-action="delete"
                                    data-toggle="tooltip" title="Hapus Permanen">
                                    <i class="fas fa-trash"></i>
                                </button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Hidden Form for Actions -->
<form id="actionForm" method="POST" style="display: none;">
    <input type="hidden" name="action" value="status_toggle">
    <input type="hidden" name="id" id="formId">
</form>

<!-- Import Modal -->
<div class="modal fade" id="importModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <form action="<?= APP_URL ?>/modules/master/import_process.php" method="POST" enctype="multipart/form-data">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Import Data Vendor</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="type" value="vendors">
                    <div class="alert alert-info" style="font-size: 13px;">
                        <i class="fas fa-info-circle mr-1"></i> Gunakan template Excel yang telah disediakan untuk menghindari error saat import.
                    </div>
                    <div class="form-group mb-4">
                        <a href="<?= APP_URL ?>/modules/master/download_template.php?type=vendors" class="btn btn-outline-success btn-sm">
                            <i class="fas fa-download mr-1"></i> Download Template Excel
                        </a>
                    </div>
                    <div class="form-group">
                        <label>Upload File Excel (.xlsx) <span class="text-danger">*</span></label>
                        <input type="file" name="file" class="form-control-file" accept=".xls,.xlsx" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-upload mr-1"></i> Proses Import</button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php
$extraJS = <<<'JS'
<script>
$(document).ready(function() {
    initDataTable('#vendorsTable');

    $('.action-btn').on('click', function() {
        const id = $(this).data('id');
        const name = $(this).data('name');
        const actionType = $(this).data('action');
        const status = $(this).data('status');
        
        let title, msg;
        if (actionType === 'delete') {
            title = 'Hapus Vendor Permanen?';
            msg = `Apakah Anda yakin ingin menghapus vendor "${name}" secara permanen? Data ini tidak dapat dikembalikan!`;
        } else {
            title = status == '1' ? 'Aktifkan Vendor?' : 'Nonaktifkan Vendor?';
            msg = status == '1' ? 
                `Vendor "${name}" akan kembali muncul dalam pilihan pembuatan PO.` : 
                `Vendor "${name}" tidak akan muncul lagi saat pembuatan PO baru.`;
        }
            
        confirmAction(title, msg, function() {
            $('#actionForm input[name="action"]').val(actionType);
            $('#formId').val(id);
            $('#actionForm').submit();
        });
    });
});
</script>
JS;
require_once __DIR__ . '/../../../includes/footer.php';
?>
