<?php
/**
 * User Management - Create User
 */
require_once __DIR__ . '/../../includes/auth.php';
requirePermission('users');

$pageTitle = 'Tambah User Baru';
$breadcrumbs = [
    ['label' => 'Administrasi', 'url' => '#'],
    ['label' => 'User', 'url' => APP_URL . '/modules/users/index.php'],
    ['label' => 'Tambah']
];

$selectedRoles = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $selectedRoles = $_POST['roles'] ?? [];
    $fullName = trim($_POST['full_name'] ?? '');
    $gender   = $_POST['gender'] ?? null;
    $phone    = trim($_POST['phone'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $address  = trim($_POST['address'] ?? '');
    
    // Validation
    $errors = [];
    if (empty($username)) $errors[] = "Username wajib diisi.";
    if (empty($password)) $errors[] = "Password wajib diisi.";
    if (empty($selectedRoles)) $errors[] = "Minimal satu role wajib dipilih.";
    if (empty($fullName)) $errors[] = "Nama lengkap wajib diisi.";
    
    if (strlen($password) < 6) {
        $errors[] = "Password minimal 6 karakter.";
    }
    
    // Check if username exists
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetchColumn() > 0) {
        $errors[] = "Username sudah digunakan, silakan pilih yang lain.";
    }
    
    if (empty($errors)) {
        // Handle Photo Upload
        $photoFilename = null;
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $result = uploadFile($_FILES['photo'], PROFILES_PATH);
            if ($result['success']) {
                $photoFilename = $result['filename'];
            } else {
                $errors[] = $result['message'];
            }
        }
        
        if (empty($errors)) {
            try {
                $pdo->beginTransaction();
                
                $hashedPass = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, gender, phone, email, address, photo, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)");
                
                $stmt->execute([$username, $hashedPass, $fullName, $gender, $phone, $email, $address, $photoFilename]);
                $userId = $pdo->lastInsertId();
                
                // Save Roles
                $stmtRole = $pdo->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)");
                foreach ($selectedRoles as $roleId) {
                    $stmtRole->execute([$userId, $roleId]);
                }

                $pdo->commit();
                setFlash('success', 'User berhasil ditambahkan.');
                header('Location: ' . APP_URL . '/modules/users/index.php');
                exit;
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $errors[] = "Terjadi kesalahan sistem: " . $e->getMessage();
            }
        }
    }
    
    if (!empty($errors)) {
        setFlash('danger', implode('<br>', $errors));
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="row">
    <div class="col-md-8 mx-auto">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-user-plus mr-2"></i>Form Tambah User</h3>
                <a href="<?= APP_URL ?>/modules/users/index.php" class="btn btn-secondary btn-sm float-right"><i class="fas fa-arrow-left mr-1"></i> Kembali</a>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="card-body">
                    
                    <h5 class="text-primary mb-3">Informasi Akun</h5>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Username <span class="text-danger">*</span></label>
                                <input type="text" name="username" class="form-control" value="<?= sanitize($_POST['username'] ?? '') ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Password <span class="text-danger">*</span></label>
                                <input type="password" name="password" class="form-control" required minlength="6">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Role <span class="text-danger">*</span></label>
                        <select name="roles[]" id="roleSelect" class="form-control select2" multiple="multiple" data-placeholder="-- Pilih Role --" required>
                            <?php
                            $stmtRoles = $pdo->query("SELECT * FROM roles ORDER BY role_name ASC");
                            $rolesList = $stmtRoles->fetchAll();
                            $roleIdToKey = [];
                            foreach ($rolesList as $r) {
                                $roleIdToKey[$r['id']] = $r['role_key'];
                                $selected = in_array($r['id'], $selectedRoles) ? 'selected' : '';
                                echo "<option value=\"{$r['id']}\" {$selected}>{$r['role_name']}</option>";
                            }
                            ?>
                        </select>
                        <small class="form-text text-muted mt-2"><strong>Akses Modul:</strong> <span id="moduleAccessList" class="text-info">Pilih role untuk melihat akses.</span></small>
                    </div>
                    
                    <hr class="my-4">
                    <h5 class="text-primary mb-3">Informasi Pribadi</h5>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Nama Lengkap <span class="text-danger">*</span></label>
                                <input type="text" name="full_name" class="form-control" value="<?= sanitize($_POST['full_name'] ?? '') ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Jenis Kelamin</label>
                                <select name="gender" class="form-control">
                                    <option value="">-- Pilih --</option>
                                    <option value="Laki-laki" <?= (($_POST['gender'] ?? '') === 'Laki-laki') ? 'selected' : '' ?>>Laki-laki</option>
                                    <option value="Perempuan" <?= (($_POST['gender'] ?? '') === 'Perempuan') ? 'selected' : '' ?>>Perempuan</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>No. HP</label>
                                <input type="text" name="phone" class="form-control" value="<?= sanitize($_POST['phone'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Email</label>
                                <input type="email" name="email" class="form-control" value="<?= sanitize($_POST['email'] ?? '') ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Alamat</label>
                        <textarea name="address" class="form-control" rows="2"><?= sanitize($_POST['address'] ?? '') ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Foto Profil</label>
                        <div class="custom-file">
                            <input type="file" name="photo" class="custom-file-input" id="photoInput" accept="image/*">
                            <label class="custom-file-label" for="photoInput">Pilih gambar...</label>
                        </div>
                        <small class="form-text text-muted">Format: JPG, PNG, GIF. Maks 2MB.</small>
                    </div>
                    
                </div>
                <div class="card-footer text-right">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Simpan User</button>
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
$roleIdMapJson = json_encode($roleIdToKey);

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

    $('#roleSelect').trigger('change');
});
</script>
JS;
require_once __DIR__ . '/../../includes/footer.php';
?>
