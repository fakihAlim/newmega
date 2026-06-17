<?php
/**
 * User Management - Edit User
 */
require_once __DIR__ . '/../../includes/auth.php';
requirePermission('users');

$id = $_GET['id'] ?? 0;
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$id]);
$userData = $stmt->fetch();

if ($userData) {
    $stmtRoles = $pdo->prepare("SELECT role_id FROM user_roles WHERE user_id = ?");
    $stmtRoles->execute([$id]);
    $currentUserRoles = $stmtRoles->fetchAll(PDO::FETCH_COLUMN);
} else {
    $currentUserRoles = [];
}


if (!$userData) {
    setFlash('danger', 'User tidak ditemukan.');
    header('Location: ' . APP_URL . '/modules/users/index.php');
    exit;
}

$pageTitle = 'Edit User: ' . sanitize($userData['username']);
$breadcrumbs = [
    ['label' => 'Administrasi', 'url' => '#'],
    ['label' => 'User', 'url' => APP_URL . '/modules/users/index.php'],
    ['label' => 'Edit']
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selectedRoles = $_POST['roles'] ?? [];
    $fullName = trim($_POST['full_name'] ?? '');
    $gender   = $_POST['gender'] ?? null;
    $phone    = trim($_POST['phone'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $address  = trim($_POST['address'] ?? '');
    
    // Check if trying to change status from form (optional, since we have shortcut in index)
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    
    // Validation
    $errors = [];
    if (empty($selectedRoles)) $errors[] = "Minimal satu role wajib dipilih.";
    if (empty($fullName)) $errors[] = "Nama lengkap wajib diisi.";
    
    if (empty($errors)) {
        // Handle Photo Upload
        $photoFilename = $userData['photo'];
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $result = uploadFile($_FILES['photo'], PROFILES_PATH);
            if ($result['success']) {
                if ($userData['photo'] && file_exists(PROFILES_PATH . '/' . $userData['photo'])) {
                    unlink(PROFILES_PATH . '/' . $userData['photo']);
                }
                $photoFilename = $result['filename'];
            } else {
                $errors[] = $result['message'];
            }
        }
        
        // Handle Password Reset
        $passwordQuery = "";
        $params = [$fullName, $gender, $phone, $email, $address, $photoFilename, $isActive];
        
        if (!empty($_POST['new_password'])) {
            if (strlen($_POST['new_password']) < 6) {
                $errors[] = "Password baru minimal 6 karakter.";
            } else {
                $passwordQuery = ", password = ?";
                $params[] = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
            }
        }
        
        if (empty($errors)) {
            try {
                $pdo->beginTransaction();
                
                $params[] = $id; // For WHERE id = ?
                $sql = "UPDATE users SET full_name=?, gender=?, phone=?, email=?, address=?, photo=?, is_active=? $passwordQuery WHERE id=?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);

                // Update Roles (only if not editing self, or if we want to allow it - usually admin can't demote self)
                // The UI already handles the "cannot change own role" restriction.
                if ($userData['id'] != $_SESSION['user']['id']) {
                    // Sync Roles
                    $pdo->prepare("DELETE FROM user_roles WHERE user_id = ?")->execute([$id]);
                    $stmtRole = $pdo->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)");
                    foreach ($selectedRoles as $roleId) {
                        $stmtRole->execute([$id, $roleId]);
                    }
                }

                $pdo->commit();
                setFlash('success', 'Data user berhasil diperbarui.');
                header('Location: ' . APP_URL . '/modules/users/index.php');
                exit;
            } catch (Exception $e) {
                $pdo->rollBack();
                error_log('[NEWMEGA] ' . $e->getMessage());
                setFlash('danger', 'Terjadi kesalahan sistem. Silakan coba lagi atau hubungi administrator.');
            }
        }
    }
    
    if (!empty($errors)) {
        setFlash('danger', implode('<br>', $errors));
        // Refresh data to show what was submitted
        $currentUserRoles = $selectedRoles;
        $userData['full_name'] = $fullName;
        $userData['gender'] = $gender;
        $userData['phone'] = $phone;
        $userData['email'] = $email;
        $userData['address'] = $address;
        $userData['is_active'] = $isActive;
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="row">
    <div class="col-md-8 mx-auto">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-user-edit mr-2"></i>Form Edit User</h3>
                <a href="<?= APP_URL ?>/modules/users/index.php" class="btn btn-secondary btn-sm float-right"><i class="fas fa-arrow-left mr-1"></i> Kembali</a>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="card-body">
                    
                    <h5 class="text-primary mb-3">Informasi Akun</h5>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Username <small class="text-muted">(Tidak dapat diubah)</small></label>
                                <input type="text" class="form-control" value="<?= sanitize($userData['username']) ?>" readonly>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Reset Password <small class="text-muted">(Kosongkan jika tidak ingin diubah)</small></label>
                                <input type="password" name="new_password" class="form-control" minlength="6">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Role <span class="text-danger">*</span></label>
                                <?php if ($userData['id'] == $_SESSION['user']['id']): ?>
                                    <?php foreach ($currentUserRoles as $roleId): ?>
                                        <input type="hidden" name="roles[]" value="<?= $roleId ?>">
                                    <?php endforeach; ?>
                                    <input type="text" class="form-control" value="<?= getUserRolesDisplay($userData['id']) ?>" readonly>
                                    <small class="text-muted">Anda tidak dapat mengubah role Anda sendiri.</small>
                                <?php else: ?>
                                    <select name="roles[]" id="roleSelect" class="form-control select2" multiple="multiple" data-placeholder="-- Pilih Role --" required>
                                        <?php
                                        $stmtRoles = $pdo->query("SELECT * FROM roles ORDER BY role_name ASC");
                                        $rolesList = $stmtRoles->fetchAll();
                                        $roleIdToKey = [];
                                        foreach ($rolesList as $r) {
                                            $roleIdToKey[$r['id']] = $r['role_key'];
                                            $selected = in_array($r['id'], $currentUserRoles) ? 'selected' : '';
                                            echo "<option value=\"{$r['id']}\" {$selected}>{$r['role_name']}</option>";
                                        }
                                        ?>
                                    </select>
                                    <small class="form-text text-muted mt-2"><strong>Akses Modul:</strong> <span id="moduleAccessList" class="text-info">Pilih role untuk melihat akses.</span></small>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Status Akun</label>
                                <div class="custom-control custom-switch mt-2">
                                    <?php if ($userData['id'] == $_SESSION['user']['id']): ?>
                                        <input type="checkbox" class="custom-control-input" id="isActive" checked disabled>
                                        <input type="hidden" name="is_active" value="1">
                                    <?php else: ?>
                                        <input type="checkbox" name="is_active" class="custom-control-input" id="isActive" <?= $userData['is_active'] ? 'checked' : '' ?> value="1">
                                    <?php endif; ?>
                                    <label class="custom-control-label" for="isActive">Aktif</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <hr class="my-4">
                    <h5 class="text-primary mb-3">Informasi Pribadi</h5>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Nama Lengkap <span class="text-danger">*</span></label>
                                <input type="text" name="full_name" class="form-control" value="<?= sanitize($userData['full_name']) ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Jenis Kelamin</label>
                                <select name="gender" class="form-control">
                                    <option value="">-- Pilih --</option>
                                    <option value="Laki-laki" <?= ($userData['gender'] === 'Laki-laki') ? 'selected' : '' ?>>Laki-laki</option>
                                    <option value="Perempuan" <?= ($userData['gender'] === 'Perempuan') ? 'selected' : '' ?>>Perempuan</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>No. HP</label>
                                <input type="text" name="phone" class="form-control" value="<?= sanitize($userData['phone'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Email</label>
                                <input type="email" name="email" class="form-control" value="<?= sanitize($userData['email'] ?? '') ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Alamat</label>
                        <textarea name="address" class="form-control" rows="2"><?= sanitize($userData['address'] ?? '') ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Foto Profil</label>
                        <?php if ($userData['photo']): ?>
                            <div class="mb-2">
                                <img src="<?= getProfilePhoto($userData['photo']) ?>" alt="Current Photo" class="img-thumbnail" style="height: 100px;">
                            </div>
                        <?php endif; ?>
                        <div class="custom-file">
                            <input type="file" name="photo" class="custom-file-input" id="photoInput" accept="image/*">
                            <label class="custom-file-label" for="photoInput">Ganti gambar...</label>
                        </div>
                        <small class="form-text text-muted">Format: JPG, PNG, GIF. Maks 2MB.</small>
                    </div>
                    
                </div>
                <div class="card-footer text-right">
                    <button type="submit" class="btn btn-warning"><i class="fas fa-save mr-1"></i> Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$roleAccess = [];
foreach ($PAGE_PERMISSIONS as $page => $roles) {
    foreach ($roles as $role) {
        $roleAccess[$role][] = ucwords(str_replace('_', ' ', $page));
    }
}
$roleAccessJson = json_encode($roleAccess);
$roleIdMapJson = isset($roleIdToKey) ? json_encode($roleIdToKey) : '{}';

$extraJS = <<<JS
<script>
$(document).ready(function() {
    $('.select2').select2({
        theme: 'bootstrap4',
        width: '100%'
    });

    $('.custom-file-input').on('change', function() {
        var fileName = $(this).val().split('\\\\').pop();
        $(this).next('.custom-file-label').text(fileName || 'Pilih gambar...');
    });

    var roleAccess = {$roleAccessJson};
    var roleIdMap = {$roleIdMapJson};

    $('#roleSelect').on('change', function() {
        var selectedIds = $(this).val() || [];
        if (selectedIds.length === 0) {
            $('#moduleAccessList').text('Pilih role untuk melihat akses.');
            return;
        }

        var allModules = new Set();
        selectedIds.forEach(function(id) {
            var roleKey = roleIdMap[id];
            if (roleKey && roleAccess[roleKey]) {
                roleAccess[roleKey].forEach(function(mod) {
                    allModules.add(mod);
                });
            }
        });

        if (allModules.size > 0) {
            $('#moduleAccessList').text(Array.from(allModules).sort().join(', '));
        } else {
            $('#moduleAccessList').text('Tidak ada modul khusus.');
        }
    });

    if ($('#roleSelect').length > 0) {
        $('#roleSelect').trigger('change');
    }
});
</script>
JS;
require_once __DIR__ . '/../../includes/footer.php';
?>
