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
    setFlash('danger', 'Pengguna tidak ditemukan.');
    header('Location: ' . APP_URL . '/modules/users/index.php');
    exit;
}

$pageTitle = 'Edit Pengguna: ' . sanitize($userData['username']);
$breadcrumbs = [
    ['label' => 'Administrasi', 'url' => '#'],
    ['label' => 'Pengguna', 'url' => APP_URL . '/modules/users/index.php'],
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
    if (empty($selectedRoles)) $errors[] = "Minimal satu peran wajib dipilih.";
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

                logActivity('update', 'users', "Memperbarui data pengguna: " . sanitize($userData['username']), 'users', $id);

                setFlash('success', 'Data pengguna berhasil diperbarui.');
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
        <div class="card card-outline card-primary">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-user-edit mr-2"></i>Form Edit Pengguna</h3>
                <a href="<?= APP_URL ?>/modules/users/index.php" class="btn btn-secondary btn-sm float-right"><i class="fas fa-arrow-left mr-1"></i> Kembali</a>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="card-body">
 
                    <div class="form-group row">
                        <label class="col-sm-4 col-form-label">Username <small class="text-muted">(Tidak dapat diubah)</small></label>
                        <div class="col-sm-8">
                            <input type="text" class="form-control" value="<?= sanitize($userData['username']) ?>" readonly>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label class="col-sm-4 col-form-label">Reset Password <small class="text-muted">(Kosongkan jika tidak ingin diubah)</small></label>
                        <div class="col-sm-8">
                            <input type="password" name="new_password" class="form-control" minlength="6">
                        </div>
                    </div>
                    
                    <div class="form-group row">
                        <label class="col-sm-4 col-form-label">Peran <span class="text-danger">*</span></label>
                        <div class="col-sm-8">
                            <?php if ($userData['id'] == $_SESSION['user']['id']): ?>
                                <?php foreach ($currentUserRoles as $roleId): ?>
                                    <input type="hidden" name="roles[]" value="<?= $roleId ?>">
                                <?php endforeach; ?>
                                <input type="text" class="form-control" value="<?= getUserRolesDisplay($userData['id']) ?>" readonly>
                                <small class="text-muted d-block mt-1">Anda tidak dapat mengubah peran Anda sendiri.</small>
                            <?php else: ?>
                                <select name="roles[]" id="roleSelect" class="form-control select2" multiple="multiple" data-placeholder="-- Pilih Peran --" required>
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
                                <small class="form-text text-muted mt-2 d-block"><strong>Akses Modul:</strong> <span id="moduleAccessList" class="text-info">Pilih peran untuk melihat akses.</span></small>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label class="col-sm-4 col-form-label">Status Akun</label>
                        <div class="col-sm-8 pt-1">
                            <div class="custom-control custom-switch">
                                <?php if ($userData['id'] == $_SESSION['user']['id']): ?>
                                    <input type="checkbox" class="custom-control-input" id="isActive" checked disabled>
                                    <input type="hidden" name="is_active" value="1">
                                <?php else: ?>
                                    <input type="checkbox" name="is_active" class="custom-control-input" id="isActive" <?= $userData['is_active'] ? 'checked' : '' ?> value="1">
                                <?php endif; ?>
                                <label class="custom-control-label font-weight-normal" for="isActive">Aktif</label>
                            </div>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label class="col-sm-4 col-form-label">Nama Lengkap <span class="text-danger">*</span></label>
                        <div class="col-sm-8">
                            <input type="text" name="full_name" class="form-control" value="<?= sanitize($userData['full_name']) ?>" required>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label class="col-sm-4 col-form-label">Jenis Kelamin</label>
                        <div class="col-sm-8">
                            <select name="gender" class="form-control">
                                <option value="">-- Pilih --</option>
                                <option value="Laki-laki" <?= ($userData['gender'] === 'Laki-laki') ? 'selected' : '' ?>>Laki-laki</option>
                                <option value="Perempuan" <?= ($userData['gender'] === 'Perempuan') ? 'selected' : '' ?>>Perempuan</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group row">
                        <label class="col-sm-4 col-form-label">No. HP</label>
                        <div class="col-sm-8">
                            <input type="text" name="phone" class="form-control" value="<?= sanitize($userData['phone'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="form-group row">
                        <label class="col-sm-4 col-form-label">Email</label>
                        <div class="col-sm-8">
                            <input type="email" name="email" class="form-control" value="<?= sanitize($userData['email'] ?? '') ?>">
                        </div>
                    </div>
                    
                    <div class="form-group row">
                        <label class="col-sm-4 col-form-label">Alamat</label>
                        <div class="col-sm-8">
                            <textarea name="address" class="form-control" rows="2"><?= sanitize($userData['address'] ?? '') ?></textarea>
                        </div>
                    </div>
                    
                    <div class="form-group row">
                        <label class="col-sm-4 col-form-label">Foto Profil</label>
                        <div class="col-sm-8">
                            <?php if ($userData['photo']): ?>
                                <div class="mb-2">
                                    <img src="<?= getProfilePhoto($userData['photo']) ?>" alt="Current Photo" class="img-thumbnail" style="height: 100px;">
                                </div>
                            <?php endif; ?>
                            <div class="custom-file">
                                <input type="file" name="photo" class="custom-file-input" id="photoInput" accept="image/*">
                                <label class="custom-file-label" for="photoInput">Ganti gambar...</label>
                            </div>
                            <small class="form-text text-muted mt-1">Format: JPG, PNG, GIF. Maks 2MB.</small>
                        </div>
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
    initSelect2('.select2');

    $('.custom-file-input').on('change', function() {
        var fileName = $(this).val().split('\\\\').pop();
        $(this).next('.custom-file-label').text(fileName || 'Pilih gambar...');
    });

    var roleAccess = {$roleAccessJson};
    var roleIdMap = {$roleIdMapJson};

    $('#roleSelect').on('change', function() {
        var selectedIds = $(this).val() || [];
        if (selectedIds.length === 0) {
            $('#moduleAccessList').text('Pilih peran untuk melihat akses.');
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
