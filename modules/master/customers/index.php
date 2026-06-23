<?php
/**
 * Master Customers - List
 */
require_once __DIR__ . '/../../../includes/auth.php';
requirePermission('master_customers');

$pageTitle = 'Master Customer / Klien';
$breadcrumbs = [
    ['label' => 'Master Data', 'url' => '#'],
    ['label' => 'Customer']
];

// Handle Delete/Deactivate
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && in_array($_POST['action'], ['status_toggle', 'delete'])) {
    $id = $_POST['id'] ?? 0;
    
    $stmt = $pdo->prepare("SELECT is_active FROM customers WHERE id = ?");
    $stmt->execute([$id]);
    $customer = $stmt->fetch();
    
    if ($customer) {
        if ($_POST['action'] === 'status_toggle') {
            $newStatus = $customer['is_active'] ? 0 : 1;
            $update = $pdo->prepare("UPDATE customers SET is_active = ? WHERE id = ?");
            if ($update->execute([$newStatus, $id])) {
                $statusText = $newStatus ? 'mengaktifkan' : 'menonaktifkan';
                logActivity('update', 'master_customers', ucfirst($statusText) . " customer ID {$id}", 'customers', $id);
                setFlash('success', 'Status customer berhasil diubah.');
            } else {
                setFlash('danger', 'Gagal mengubah status customer.');
            }
        } elseif ($_POST['action'] === 'delete') {
            // Check if used
            $stmtUse = $pdo->prepare("
                SELECT 
                (SELECT COUNT(*) FROM quotations WHERE customer_id = ?) +
                (SELECT COUNT(*) FROM invoices WHERE customer_id = ?) +
                (SELECT COUNT(*) FROM projects WHERE customer_id = ?) as used_count
            ");
            $stmtUse->execute([$id, $id, $id]);
            $isUsed = $stmtUse->fetchColumn() > 0;
            
            if ($isUsed) {
                setFlash('danger', 'Gagal menghapus! Customer sudah memiliki histori transaksi (Quotation/Invoice/Proyek).');
            } else {
                try {
                    $delete = $pdo->prepare("DELETE FROM customers WHERE id = ?");
                    if ($delete->execute([$id])) {
                        logActivity('delete', 'master_customers', "Menghapus data customer ID {$id} secara permanen");
                        setFlash('success', 'Customer berhasil dihapus secara permanen.');
                    } else {
                        setFlash('danger', 'Gagal menghapus customer.');
                    }
                } catch (PDOException $e) {
                    if ($e->getCode() == '23000') {
                        setFlash('danger', 'Gagal menghapus! Customer ini sedang digunakan pada data Proyek atau data lainnya.');
                    } else {
                        setFlash('danger', 'Terjadi kesalahan sistem saat menghapus data.');
                    }
                }
            }
        }
    }
    header('Location: ' . APP_URL . '/modules/master/customers/index.php');
    exit;
}

// Fetch Customers
$customers = $pdo->query("
    SELECT c.*,
    (SELECT COUNT(*) FROM quotations WHERE customer_id = c.id) +
    (SELECT COUNT(*) FROM invoices WHERE customer_id = c.id) +
    (SELECT COUNT(*) FROM projects WHERE customer_id = c.id) as used_count
    FROM customers c 
    ORDER BY c.company_name ASC
")->fetchAll();

require_once __DIR__ . '/../../../includes/header.php';
?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title">Daftar Customer / Klien</h3>
        <div class="ml-auto">
            <?php if (canAccess('master_customers', 'create')): ?>
            <a href="<?= APP_URL ?>/modules/master/customers/create.php" class="btn btn-primary btn-sm">
                <i class="fas fa-plus mr-1"></i> Tambah Customer
            </a>
            <button type="button" class="btn btn-success btn-sm ml-1" data-toggle="modal" data-target="#importModal">
                <i class="fas fa-file-excel mr-1"></i> Import Excel
            </button>
            <?php endif; ?>
            <a href="<?= APP_URL ?>/modules/master/export_excel.php?type=customers" class="btn btn-info btn-sm ml-1">
                <i class="fas fa-file-excel mr-1"></i> Export Excel
            </a>
            <button onclick="window.print()" class="btn btn-secondary btn-sm ml-1">
                <i class="fas fa-print mr-1"></i> Cetak
            </button>
        </div>
    </div>
    <div class="card-body">
        <table id="customersTable" class="table table-bordered table-striped table-hover table-sm w-100" >
            <thead>
                <tr>
                    <th width="5%">No</th>
                    <th width="8%">Kode</th>
                    <th width="32%">Nama Customer / Perusahaan</th>
                    <th width="20%">Kontak Utama</th>
                    <th width="25%">Alamat</th>
                    <th width="10%">Status</th>
                    <th width="10%" class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($customers as $i => $c): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td>
                        <span class="badge badge-primary" style="font-size: 13px;"><?= sanitize($c['abbreviation']) ?></span>
                    </td>
                    <td>
                        <strong class="d-block text-dark"><?= sanitize($c['company_name']) ?></strong>
                        <?php if ($c['pic_name']): ?>
                            <small class="text-muted"><i class="fas fa-user mr-1"></i> <?= sanitize($c['pic_name']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($c['phone']): ?>
                        <div class="mb-1"><i class="fas fa-phone text-muted" style="width:15px;"></i> <small><?= sanitize($c['phone']) ?></small></div>
                        <?php endif; ?>
                        <?php if ($c['email']): ?>
                        <div><i class="fas fa-envelope text-muted" style="width:15px;"></i> <small><?= sanitize($c['email']) ?></small></div>
                        <?php endif; ?>
                    </td>
                    <td><small><?= sanitize($c['address']) ?></small></td>
                    <td><?= getStatusBadge($c['is_active'] ? 'active' : 'cancelled') ?></td>
                    <td class="text-center">
                        <div class="btn-group">
                            <?php if (canAccess('master_customers', 'edit')): ?>
                            <a href="<?= APP_URL ?>/modules/master/customers/edit.php?id=<?= $c['id'] ?>" class="btn btn-info btn-sm" data-toggle="tooltip" title="Ubah">
                                <i class="fas fa-edit"></i>
                            </a>
                            
                            <?php if ($c['is_active']): ?>
                                <button type="button" class="btn btn-warning btn-sm action-btn" 
                                    data-id="<?= $c['id'] ?>" 
                                    data-name="<?= sanitize($c['company_name']) ?>" 
                                    data-action="status_toggle"
                                    data-status="0"
                                    data-toggle="tooltip" title="Nonaktifkan">
                                    <i class="fas fa-ban text-white"></i>
                                </button>
                            <?php else: ?>
                                <button type="button" class="btn btn-success btn-sm action-btn" 
                                    data-id="<?= $c['id'] ?>" 
                                    data-name="<?= sanitize($c['company_name']) ?>" 
                                    data-action="status_toggle"
                                    data-status="1"
                                    data-toggle="tooltip" title="Aktifkan">
                                    <i class="fas fa-check"></i>
                                </button>
                            <?php endif; ?>
                            <?php endif; ?>
                            
                            <?php if (canAccess('master_customers', 'delete')): ?>
                            <?php if ($c['used_count'] == 0): ?>
                                <button type="button" class="btn btn-danger btn-sm action-btn" 
                                    data-id="<?= $c['id'] ?>" 
                                    data-name="<?= sanitize($c['company_name']) ?>" 
                                    data-action="delete"
                                    data-toggle="tooltip" title="Hapus Permanen">
                                    <i class="fas fa-trash"></i>
                                </button>
                            <?php endif; ?>
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
                    <h5 class="modal-title">Import Data Customer</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="type" value="customers">
                    <div class="alert alert-info" style="font-size: 13px;">
                        <i class="fas fa-info-circle mr-1"></i> Gunakan template Excel yang telah disediakan untuk menghindari error saat import.
                    </div>
                    <div class="form-group mb-4">
                        <a href="<?= APP_URL ?>/modules/master/download_template.php?type=customers" class="btn btn-outline-success btn-sm">
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
    initDataTable('#customersTable');

    $('.action-btn').on('click', function() {
        const id = $(this).data('id');
        const name = $(this).data('name');
        const actionType = $(this).data('action');
        const status = $(this).data('status');
        
        let title, msg;
        if (actionType === 'delete') {
            title = 'Hapus Customer Permanen?';
            msg = `Apakah Anda yakin ingin menghapus customer "${name}" secara permanen? Data ini tidak dapat dikembalikan!`;
        } else {
            title = status == '1' ? 'Aktifkan Customer?' : 'Nonaktifkan Customer?';
            msg = status == '1' ? 
                `Customer "${name}" akan kembali muncul dalam pilihan.` : 
                `Customer "${name}" tidak akan muncul lagi di daftar pembuatan Quotation.`;
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
