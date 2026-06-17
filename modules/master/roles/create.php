<?php
/**
 * Role Management - Create
 */
require_once __DIR__ . '/../../../includes/auth.php';
requirePermission('users');

$pageTitle = 'Tambah Role';
$breadcrumbs = [
    ['label' => 'Administrasi', 'url' => '#'],
    ['label' => 'Role & Akses', 'url' => 'index.php'],
    ['label' => 'Tambah']
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $roleName = trim($_POST['role_name'] ?? '');
    $roleKey  = trim($_POST['role_key'] ?? '');
    $description = trim($_POST['description'] ?? '');
    
    // Auto generate role_key if empty
    if (empty($roleKey)) {
        $roleKey = strtolower(str_replace(' ', '_', $roleName));
        $roleKey = preg_replace('/[^a-z0-9_]/', '', $roleKey);
    }
    
    $errors = [];
    if (empty($roleName)) $errors[] = "Nama Role wajib diisi.";
    if (empty($roleKey)) $errors[] = "Key Role gagal digenerate.";
    
    // Check if key exists
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM roles WHERE role_key = ?");
    $stmt->execute([$roleKey]);
    if ($stmt->fetchColumn() > 0) {
        $errors[] = "Key Role '$roleKey' sudah digunakan.";
    }
    
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            $stmtIns = $pdo->prepare("INSERT INTO roles (role_name, role_key, description) VALUES (?, ?, ?)");
            $stmtIns->execute([$roleName, $roleKey, $description]);
            $newRoleId = $pdo->lastInsertId();
            
            // Insert permissions based on matrix
            $permissions = $_POST['permissions'] ?? [];
            $stmtPerm = $pdo->prepare("INSERT INTO role_permissions (role_id, module_key, can_view, can_create, can_edit, can_delete) VALUES (?, ?, ?, ?, ?, ?)");
            
            foreach ($permissions as $moduleKey => $flags) {
                $canView = isset($flags['view']) ? 1 : 0;
                $canCreate = isset($flags['create']) ? 1 : 0;
                $canEdit = isset($flags['edit']) ? 1 : 0;
                $canDelete = isset($flags['delete']) ? 1 : 0;
                
                // If they can create/edit/delete, they must be able to view
                if ($canCreate || $canEdit || $canDelete) {
                    $canView = 1;
                }
                
                if ($canView || $canCreate || $canEdit || $canDelete) {
                    $stmtPerm->execute([$newRoleId, $moduleKey, $canView, $canCreate, $canEdit, $canDelete]);
                }
            }
            
            $pdo->commit();
            setFlash('success', 'Role berhasil ditambahkan.');
            header('Location: index.php');
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log('[NEWMEGA] ' . $e->getMessage());
            $errors[] = 'Terjadi kesalahan sistem. Silakan coba lagi atau hubungi administrator.';
        }
    }
    
    if (!empty($errors)) {
        setFlash('danger', implode('<br>', $errors));
    }
}

require_once __DIR__ . '/../../../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-plus mr-2"></i>Form Tambah Role</h3>
        <a href="index.php" class="btn btn-secondary btn-sm float-right"><i class="fas fa-arrow-left mr-1"></i> Kembali</a>
    </div>
    <form method="POST">
        <div class="card-body">
            
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Nama Role <span class="text-danger">*</span></label>
                        <input type="text" name="role_name" class="form-control" value="<?= sanitize($_POST['role_name'] ?? '') ?>" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Key Role</label>
                        <input type="text" name="role_key" class="form-control" value="<?= sanitize($_POST['role_key'] ?? '') ?>" placeholder="Cth: finance_manager">
                        <small class="text-muted">Kosongkan agar digenerate otomatis dari Nama Role.</small>
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label>Deskripsi</label>
                <textarea name="description" class="form-control" rows="2"><?= sanitize($_POST['description'] ?? '') ?></textarea>
            </div>
            
            <hr>
            <h5 class="mb-3">Matriks Hak Akses</h5>
            
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead class="bg-light">
                        <tr>
                            <th>Modul</th>
                            <th width="100" class="text-center">Lihat</th>
                            <th width="100" class="text-center">Tambah</th>
                            <th width="100" class="text-center">Ubah</th>
                            <th width="100" class="text-center">Hapus</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($AVAILABLE_MODULES as $groupName => $modules): ?>
                            <tr class="bg-light text-white">
                                <td colspan="5"><strong><?= $groupName ?></strong></td>
                            </tr>
                            <?php foreach ($modules as $modKey => $modName): ?>
                            <tr>
                                <td><?= $modName ?></td>
                                <td class="text-center">
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input perm-view" id="v_<?= $modKey ?>" name="permissions[<?= $modKey ?>][view]" value="1">
                                        <label class="custom-control-label" for="v_<?= $modKey ?>"></label>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input perm-create" id="c_<?= $modKey ?>" name="permissions[<?= $modKey ?>][create]" value="1">
                                        <label class="custom-control-label" for="c_<?= $modKey ?>"></label>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input perm-edit" id="e_<?= $modKey ?>" name="permissions[<?= $modKey ?>][edit]" value="1">
                                        <label class="custom-control-label" for="e_<?= $modKey ?>"></label>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input perm-delete" id="d_<?= $modKey ?>" name="permissions[<?= $modKey ?>][delete]" value="1">
                                        <label class="custom-control-label" for="d_<?= $modKey ?>"></label>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
        </div>
        <div class="card-footer text-right">
            <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Simpan Role</button>
        </div>
    </form>
</div>

<?php
$extraJS = <<<'JS'
<script>
$(document).ready(function() {
    // If any CRUD action checked, auto check view
    $('.perm-create, .perm-edit, .perm-delete').on('change', function() {
        if ($(this).is(':checked')) {
            var tr = $(this).closest('tr');
            tr.find('.perm-view').prop('checked', true);
        }
    });
    
    // If view unchecked, uncheck all others
    $('.perm-view').on('change', function() {
        if (!$(this).is(':checked')) {
            var tr = $(this).closest('tr');
            tr.find('.perm-create, .perm-edit, .perm-delete').prop('checked', false);
        }
    });
});
</script>
JS;
require_once __DIR__ . '/../../../includes/footer.php';
?>
