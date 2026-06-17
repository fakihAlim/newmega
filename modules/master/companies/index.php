<?php
/**
 * Company Management - List
 */
require_once __DIR__ . '/../../../includes/auth.php';
requirePermission('master_companies');

$pageTitle = 'Perusahaan (Header Dokumen)';
$breadcrumbs = [
    ['label' => 'Master Data', 'url' => '#'],
    ['label' => 'Perusahaan']
];

// Handle Set Default
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'set_default') {
    $id = $_POST['id'] ?? 0;
    
    // Reset all
    $pdo->query("UPDATE companies SET is_default = 0");
    // Set default
    $stmt = $pdo->prepare("UPDATE companies SET is_default = 1 WHERE id = ?");
    if ($stmt->execute([$id])) {
        setFlash('success', 'Perusahaan default berhasil diubah.');
    }
    header('Location: ' . APP_URL . '/modules/master/companies/index.php');
    exit;
}

// Handle Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $id = $_POST['id'] ?? 0;
    
    // Check if used in transactions
    $check = $pdo->prepare("
        SELECT 
        (SELECT COUNT(*) FROM purchase_orders WHERE company_id = ?) +
        (SELECT COUNT(*) FROM warehouse_transfers WHERE company_id = ?) as used_count
    ");
    $check->execute([$id, $id]);
    $used_count = $check->fetchColumn();
    
    if ($used_count > 0) {
        setFlash('danger', 'Perusahaan tidak dapat dihapus karena sudah memiliki histori transaksi (PO/Transfer).');
    } else {
        $stmt = $pdo->prepare("SELECT logo FROM companies WHERE id = ?");
        $stmt->execute([$id]);
        $company = $stmt->fetch();
        
        $delete = $pdo->prepare("DELETE FROM companies WHERE id = ?");
        if ($delete->execute([$id])) {
            // Remove file
            if ($company['logo'] && file_exists(LOGOS_PATH . '/' . $company['logo'])) {
                unlink(LOGOS_PATH . '/' . $company['logo']);
            }
            // Check if deleted was default, make first one default
            $pdo->query("UPDATE companies SET is_default = 1 ORDER BY id ASC LIMIT 1");
            
            setFlash('success', 'Perusahaan berhasil dihapus.');
        } else {
            setFlash('danger', 'Gagal menghapus perusahaan.');
        }
    }
    header('Location: ' . APP_URL . '/modules/master/companies/index.php');
    exit;
}

// Fetch Companies
$companies = $pdo->query("
    SELECT c.*,
    (SELECT COUNT(*) FROM purchase_orders WHERE company_id = c.id) +
    (SELECT COUNT(*) FROM warehouse_transfers WHERE company_id = c.id) as used_count
    FROM companies c 
    ORDER BY c.is_default DESC, c.id ASC
")->fetchAll();

require_once __DIR__ . '/../../../includes/header.php';
?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title">Daftar Header Perusahaan</h3>
        <div class="ml-auto">
            <a href="<?= APP_URL ?>/modules/master/companies/create.php" class="btn btn-primary btn-sm">
                <i class="fas fa-plus mr-1"></i> Tambah Perusahaan
            </a>
            <button type="button" class="btn btn-success btn-sm ml-1" data-toggle="modal" data-target="#importModal">
                <i class="fas fa-file-excel mr-1"></i> Import Excel
            </button>
        </div>
    </div>
    <div class="card-body">
        <table id="companiesTable" class="table table-bordered table-striped w-100" style="font-size: 13.5px;">
            <thead>
                <tr>
                    <th width="5%">No</th>
                    <th width="8%">Logo</th>
                    <th width="32%">Nama Perusahaan & Alamat</th>
                    <th width="25%">Kontak</th>
                    <th width="15%">Utama (Default)</th>
                    <th width="15%" class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($companies as $i => $c): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td class="text-center">
                        <?php if ($c['logo']): ?>
                            <img src="<?= getCompanyLogo($c['logo']) ?>" alt="Logo" style="max-height: 40px; max-width: 60px;">
                        <?php else: ?>
                            <span class="text-muted"><i class="fas fa-image fa-2x"></i></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <strong class="d-block text-dark" style="font-size: 15px;"><?= sanitize($c['name']) ?></strong>
                        <small class="text-muted d-block mt-1">
                            <?= sanitize($c['address']) ?><br>
                            <?= sanitize($c['city']) ?><?= $c['province'] ? ', ' . sanitize($c['province']) : '' ?> <?= sanitize($c['postal_code']) ?>
                        </small>
                    </td>
                    <td>
                        <?php if ($c['email']): ?>
                        <div class="mb-1"><i class="fas fa-envelope text-muted" style="width:15px;"></i> <small><?= sanitize($c['email']) ?></small></div>
                        <?php endif; ?>
                        <?php if ($c['phone']): ?>
                        <div><i class="fas fa-phone text-muted" style="width:15px;"></i> <small><?= sanitize($c['phone']) ?></small></div>
                        <?php endif; ?>
                    </td>
                    <td class="text-center">
                        <?php if ($c['is_default']): ?>
                            <span class="badge badge-success"><i class="fas fa-check-circle mr-1"></i> Default</span>
                        <?php else: ?>
                            <button class="btn btn-outline-secondary btn-sm action-btn" data-id="<?= $c['id'] ?>" data-action="set_default">
                                Set Default
                            </button>
                        <?php endif; ?>
                    </td>
                    <td class="text-center">
                        <div class="btn-group">
                            <a href="<?= APP_URL ?>/modules/master/companies/edit.php?id=<?= $c['id'] ?>" class="btn btn-info btn-sm" data-toggle="tooltip" title="Ubah">
                                <i class="fas fa-edit"></i>
                            </a>
                            <?php if ($c['used_count'] == 0 && !$c['is_default']): ?>
                            <button type="button" class="btn btn-danger btn-sm action-btn" 
                                data-id="<?= $c['id'] ?>" 
                                data-name="<?= sanitize($c['name']) ?>" 
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
    <input type="hidden" name="action" id="formAction">
    <input type="hidden" name="id" id="formId">
</form>

<!-- Import Modal -->
<div class="modal fade" id="importModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <form action="<?= APP_URL ?>/modules/master/import_process.php" method="POST" enctype="multipart/form-data">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Import Data Perusahaan</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="type" value="companies">
                    <div class="alert alert-info" style="font-size: 13px;">
                        <i class="fas fa-info-circle mr-1"></i> Gunakan template Excel yang telah disediakan untuk menghindari error saat import.
                    </div>
                    <div class="form-group mb-4">
                        <a href="<?= APP_URL ?>/modules/master/download_template.php?type=companies" class="btn btn-outline-success btn-sm">
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
    initDataTable('#companiesTable');

    $('.action-btn').on('click', function() {
        const id = $(this).data('id');
        const action = $(this).data('action');
        
        if (action === 'delete') {
            const name = $(this).data('name');
            confirmAction('Hapus Perusahaan?', `Anda yakin ingin menghapus "${name}"?`, function() {
                $('#formAction').val(action);
                $('#formId').val(id);
                $('#actionForm').submit();
            });
        } else if (action === 'set_default') {
            $('#formAction').val(action);
            $('#formId').val(id);
            $('#actionForm').submit();
        }
    });
});
</script>
JS;
require_once __DIR__ . '/../../../includes/footer.php';
?>
