<?php
/**
 * Master Categories - List
 */
require_once __DIR__ . '/../../../includes/auth.php';
requirePermission('master_categories');

$pageTitle = 'Master Kategori';
$breadcrumbs = [
    ['label' => 'Master Data', 'url' => '#'],
    ['label' => 'Kategori']
];

// Handle Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $id = $_POST['id'] ?? 0;
    
    // Check if used in items
    $check = $pdo->prepare("SELECT COUNT(*) FROM items WHERE category_id = ?");
    $check->execute([$id]);
    $usedInItems = $check->fetchColumn();
    
    if ($usedInItems > 0) {
        setFlash('danger', 'Kategori tidak dapat dihapus karena sedang digunakan pada data barang.');
    } else {
        $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
        if ($stmt->execute([$id])) {
            setFlash('success', 'Kategori berhasil dihapus.');
        } else {
            setFlash('danger', 'Gagal menghapus kategori.');
        }
    }
    header('Location: ' . APP_URL . '/modules/master/categories/index.php');
    exit;
}

// Fetch Categories
$categories = $pdo->query("
    SELECT c.*, 
    (SELECT COUNT(*) FROM items WHERE category_id = c.id) as item_count 
    FROM categories c 
    ORDER BY c.name ASC
")->fetchAll();

require_once __DIR__ . '/../../../includes/header.php';
?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title">Daftar Kategori Barang</h3>
        <div class="ml-auto">
            <a href="<?= APP_URL ?>/modules/master/categories/create.php" class="btn btn-primary btn-sm">
                <i class="fas fa-plus mr-1"></i> Tambah Kategori
            </a>
            <button type="button" class="btn btn-success btn-sm ml-1" data-toggle="modal" data-target="#importModal">
                <i class="fas fa-file-excel mr-1"></i> Import Excel
            </button>
            <a href="<?= APP_URL ?>/modules/master/export_excel.php?type=categories" class="btn btn-info btn-sm ml-1">
                <i class="fas fa-file-excel mr-1"></i> Export Excel
            </a>
            <button onclick="window.print()" class="btn btn-secondary btn-sm ml-1">
                <i class="fas fa-print mr-1"></i> Cetak
            </button>
        </div>
    </div>
    <div class="card-body">
        <table id="categoriesTable" class="table table-bordered table-striped table-hover table-sm w-100" >
            <thead>
                <tr>
                    <th width="5%">No</th>
                    <th width="25%">Nama Kategori</th>
                    <th width="15%">Prefix Kode</th>
                    <th width="40%">Deskripsi</th>
                    <th width="15%" class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($categories as $i => $c): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td>
                        <strong class="text-dark"><?= sanitize($c['name']) ?></strong>
                    </td>
                    <td>
                        <span class="badge badge-info" style="font-size: 12px;"><?= sanitize($c['prefix']) ?></span>
                    </td>
                    <td><?= sanitize($c['description'] ?? '-') ?></td>
                    <td class="text-center">
                        <div class="btn-group">
                            <a href="<?= APP_URL ?>/modules/master/categories/edit.php?id=<?= $c['id'] ?>" class="btn btn-info btn-sm" data-toggle="tooltip" title="Ubah">
                                <i class="fas fa-edit"></i>
                            </a>
                            <?php if ($c['item_count'] == 0): ?>
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
                    <h5 class="modal-title">Import Data Kategori</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="type" value="categories">
                    <div class="alert alert-info" style="font-size: 13px;">
                        <i class="fas fa-info-circle mr-1"></i> Gunakan template Excel yang telah disediakan untuk menghindari error saat import.
                    </div>
                    <div class="form-group mb-4">
                        <a href="<?= APP_URL ?>/modules/master/download_template.php?type=categories" class="btn btn-outline-success btn-sm">
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
    initDataTable('#categoriesTable');

    $('.action-btn').on('click', function() {
        const id = $(this).data('id');
        const action = $(this).data('action');
        
        if (action === 'delete') {
            const name = $(this).data('name');
            confirmAction('Hapus Kategori?', `Anda yakin ingin menghapus kategori "${name}"?`, function() {
                $('#formAction').val(action);
                $('#formId').val(id);
                $('#actionForm').submit();
            });
        }
    });
});
</script>
JS;
require_once __DIR__ . '/../../../includes/footer.php';
?>
