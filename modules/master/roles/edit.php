<?php
/**
 * Role Management - Edit
 */
require_once __DIR__ . '/../../../includes/auth.php';
requirePermission('users');

$id = $_GET['id'] ?? 0;
$stmt = $pdo->prepare("SELECT * FROM roles WHERE id = ?");
$stmt->execute([$id]);
$role = $stmt->fetch();

if (!$role) {
    setFlash('danger', 'Peran tidak ditemukan.');
    header('Location: index.php');
    exit;
}

// Fetch existing permissions
$stmtPerm = $pdo->prepare("SELECT * FROM role_permissions WHERE role_id = ?");
$stmtPerm->execute([$id]);
$existingPerms = [];
while ($row = $stmtPerm->fetch()) {
    $existingPerms[$row['module_key']] = $row;
}

$pageTitle = 'Edit Peran: ' . sanitize($role['role_name']);
$breadcrumbs = [
    ['label' => 'Administrasi', 'url' => '#'],
    ['label' => 'Peran & Hak Akses', 'url' => 'index.php'],
    ['label' => 'Edit']
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $roleName = trim($_POST['role_name'] ?? '');
    $roleKey  = trim($_POST['role_key'] ?? '');
    $description = trim($_POST['description'] ?? '');
    
    if (empty($roleKey)) {
        $roleKey = strtolower(str_replace(' ', '_', $roleName));
        $roleKey = preg_replace('/[^a-z0-9_]/', '', $roleKey);
    }
    
    $errors = [];
    if (empty($roleName)) $errors[] = "Nama Peran wajib diisi.";
    if (empty($roleKey)) $errors[] = "Key Peran gagal digenerate.";
    
    // Check if key exists for other roles
    $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM roles WHERE role_key = ? AND id != ?");
    $stmtCheck->execute([$roleKey, $id]);
    if ($stmtCheck->fetchColumn() > 0) {
        $errors[] = "Key Peran '$roleKey' sudah digunakan.";
    }
    
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            $stmtUpd = $pdo->prepare("UPDATE roles SET role_name=?, role_key=?, description=? WHERE id=?");
            $stmtUpd->execute([$roleName, $roleKey, $description, $id]);
            
            // Delete old permissions
            $pdo->prepare("DELETE FROM role_permissions WHERE role_id=?")->execute([$id]);
            
            // Insert new permissions
            $permissions = $_POST['permissions'] ?? [];
            $stmtPermIns = $pdo->prepare("INSERT INTO role_permissions (role_id, module_key, can_view, can_create, can_edit, can_delete) VALUES (?, ?, ?, ?, ?, ?)");
            
            foreach ($permissions as $moduleKey => $flags) {
                $canView = isset($flags['view']) ? 1 : 0;
                $canCreate = isset($flags['create']) ? 1 : 0;
                $canEdit = isset($flags['edit']) ? 1 : 0;
                $canDelete = isset($flags['delete']) ? 1 : 0;
                
                if ($canCreate || $canEdit || $canDelete) {
                    $canView = 1;
                }
                
                if ($canView || $canCreate || $canEdit || $canDelete) {
                    $stmtPermIns->execute([$id, $moduleKey, $canView, $canCreate, $canEdit, $canDelete]);
                }
            }
            
            // If user edited their own role's permissions, or to be safe, we could invalidate sessions. 
            // But they will naturally update on next request if we didn't cache it in session forever.
            // Wait, we cached it in `static $userPermissions = null;` which lives only for the current request.
            // So it's fine! It will query DB on every request for permissions!
            
            $pdo->commit();
            setFlash('success', 'Data peran berhasil diperbarui.');
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
        $role['role_name'] = $roleName;
        $role['role_key'] = $roleKey;
        $role['description'] = $description;
    }
}

require_once __DIR__ . '/../../../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-edit mr-2"></i>Form Edit Peran</h3>
        <a href="index.php" class="btn btn-secondary btn-sm float-right"><i class="fas fa-arrow-left mr-1"></i> Kembali</a>
    </div>
    <form method="POST">
        <div class="card-body">
            
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Nama Peran <span class="text-danger">*</span></label>
                        <input type="text" name="role_name" class="form-control" value="<?= sanitize($role['role_name']) ?>" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Key Peran</label>
                        <input type="text" name="role_key" class="form-control" value="<?= sanitize($role['role_key']) ?>">
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label>Deskripsi</label>
                <textarea name="description" class="form-control" rows="2"><?= sanitize($role['description'] ?? '') ?></textarea>
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
                            <tr class="bg-secondary text-white">
                                <td colspan="5"><strong><?= $groupName ?></strong></td>
                            </tr>
                            <?php foreach ($modules as $modKey => $modName): 
                                $v = isset($existingPerms[$modKey]) && $existingPerms[$modKey]['can_view'] ? 'checked' : '';
                                $c = isset($existingPerms[$modKey]) && $existingPerms[$modKey]['can_create'] ? 'checked' : '';
                                $e = isset($existingPerms[$modKey]) && $existingPerms[$modKey]['can_edit'] ? 'checked' : '';
                                $d = isset($existingPerms[$modKey]) && $existingPerms[$modKey]['can_delete'] ? 'checked' : '';
                            ?>
                            <tr>
                                <td><?= $modName ?></td>
                                <td class="text-center">
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input perm-view" id="v_<?= $modKey ?>" name="permissions[<?= $modKey ?>][view]" value="1" <?= $v ?>>
                                        <label class="custom-control-label" for="v_<?= $modKey ?>"></label>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input perm-create" id="c_<?= $modKey ?>" name="permissions[<?= $modKey ?>][create]" value="1" <?= $c ?>>
                                        <label class="custom-control-label" for="c_<?= $modKey ?>"></label>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input perm-edit" id="e_<?= $modKey ?>" name="permissions[<?= $modKey ?>][edit]" value="1" <?= $e ?>>
                                        <label class="custom-control-label" for="e_<?= $modKey ?>"></label>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input perm-delete" id="d_<?= $modKey ?>" name="permissions[<?= $modKey ?>][delete]" value="1" <?= $d ?>>
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
            <button type="submit" class="btn btn-warning"><i class="fas fa-save mr-1"></i> Simpan Perubahan</button>
        </div>
    </form>
</div>

<?php
$extraJS = <<<'JS'
<script>
$(document).ready(function() {
    $('.perm-create, .perm-edit, .perm-delete').on('change', function() {
        if ($(this).is(':checked')) {
            var tr = $(this).closest('tr');
            tr.find('.perm-view').prop('checked', true);
        }
    });
    
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
