<?php
/**
 * User Profile Page
 */
require_once __DIR__ . '/../../includes/auth.php';

$pageTitle = 'Profil Saya';
$breadcrumbs = [['label' => 'Profil Saya']];

$userId = $_SESSION['user']['id'];

// Fetch full user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$userData = $stmt->fetch();

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_profile') {
        $fullName = trim($_POST['full_name'] ?? '');
        $gender = $_POST['gender'] ?? null;
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $address = trim($_POST['address'] ?? '');
        
        if (empty($fullName)) {
            setFlash('danger', 'Nama lengkap wajib diisi.');
        } else {
            // Handle photo upload
            $photoFilename = $userData['photo'];
            if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                $result = uploadFile($_FILES['photo'], PROFILES_PATH);
                if ($result['success']) {
                    // Delete old photo
                    if ($userData['photo'] && file_exists(PROFILES_PATH . '/' . $userData['photo'])) {
                        unlink(PROFILES_PATH . '/' . $userData['photo']);
                    }
                    $photoFilename = $result['filename'];
                } else {
                    setFlash('warning', $result['message']);
                }
            }
            
            $stmt = $pdo->prepare("UPDATE users SET full_name=?, gender=?, phone=?, email=?, address=?, photo=? WHERE id=?");
            $stmt->execute([$fullName, $gender, $phone, $email, $address, $photoFilename, $userId]);
            
            // Update session
            $_SESSION['user']['full_name'] = $fullName;
            $_SESSION['user']['email'] = $email;
            $_SESSION['user']['photo'] = $photoFilename;
            
            setFlash('success', 'Profil berhasil diperbarui.');
            header('Location: ' . APP_URL . '/modules/auth/profile.php');
            exit;
        }
    }
    
    if ($action === 'change_password') {
        $currentPass = $_POST['current_password'] ?? '';
        $newPass = $_POST['new_password'] ?? '';
        $confirmPass = $_POST['confirm_password'] ?? '';
        
        if (empty($currentPass) || empty($newPass)) {
            setFlash('danger', 'Semua field password wajib diisi.');
        } elseif ($newPass !== $confirmPass) {
            setFlash('danger', 'Password baru dan konfirmasi tidak cocok.');
        } elseif (strlen($newPass) < 6) {
            setFlash('danger', 'Password baru minimal 6 karakter.');
        } elseif (!password_verify($currentPass, $userData['password'])) {
            setFlash('danger', 'Password lama salah.');
        } else {
            $hashedPass = password_hash($newPass, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hashedPass, $userId]);
            setFlash('success', 'Password berhasil diubah.');
            header('Location: ' . APP_URL . '/modules/auth/profile.php');
            exit;
        }
    }
    
    // Refresh user data
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $userData = $stmt->fetch();
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="row">
    <!-- Profile Card -->
    <div class="col-md-4">
        <div class="card">
            <div class="card-body text-center pt-4">
                <img src="<?= getProfilePhoto($userData['photo']) ?>" 
                     class="img-circle profile-user-img elevation-2 mb-3" 
                     alt="Profile">
                <h4 class="fw-600 mb-1"><?= sanitize($userData['full_name']) ?></h4>
                <p class="text-muted mb-2"><?= getUserRolesDisplay($userData['id']) ?></p>
                <?= getStatusBadge($userData['is_active'] ? 'active' : 'cancelled') ?>
                
                <div class="mt-4 text-left" style="font-size:13.5px;">
                    <?php if ($userData['email']): ?>
                    <p class="mb-2"><i class="fas fa-envelope text-muted mr-2" style="width:16px;"></i> <?= sanitize($userData['email']) ?></p>
                    <?php endif; ?>
                    <?php if ($userData['phone']): ?>
                    <p class="mb-2"><i class="fas fa-phone text-muted mr-2" style="width:16px;"></i> <?= sanitize($userData['phone']) ?></p>
                    <?php endif; ?>
                    <?php if ($userData['gender']): ?>
                    <p class="mb-2"><i class="fas fa-venus-mars text-muted mr-2" style="width:16px;"></i> <?= sanitize($userData['gender']) ?></p>
                    <?php endif; ?>
                    <?php if ($userData['address']): ?>
                    <p class="mb-2"><i class="fas fa-map-marker-alt text-muted mr-2" style="width:16px;"></i> <?= sanitize($userData['address']) ?></p>
                    <?php endif; ?>
                    <p class="mb-0 text-muted" style="font-size:12px;">
                        <i class="fas fa-clock mr-1"></i> Login terakhir: <?= formatDateTime($userData['last_login']) ?>
                    </p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Edit Profile -->
    <div class="col-md-8">
        <!-- Profile Form -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-user-edit mr-2"></i>Edit Profil</h3>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="update_profile">
                <div class="card-body">
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
                                    <option value="Laki-laki" <?= $userData['gender'] === 'Laki-laki' ? 'selected' : '' ?>>Laki-laki</option>
                                    <option value="Perempuan" <?= $userData['gender'] === 'Perempuan' ? 'selected' : '' ?>>Perempuan</option>
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
                        <div class="custom-file">
                            <input type="file" name="photo" class="custom-file-input" id="photoInput" accept="image/*">
                            <label class="custom-file-label" for="photoInput">Pilih gambar...</label>
                        </div>
                        <small class="form-text text-muted">Format: JPG, PNG, GIF. Maks 2MB.</small>
                    </div>
                </div>
                <div class="card-footer text-right">
                    <button type="submit" class="btn btn-warning"><i class="fas fa-save mr-1"></i> Simpan Perubahan</button>
                </div>
            </form>
        </div>
        
        <!-- Change Password -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-key mr-2"></i>Ubah Password</h3>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="change_password">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Password Lama <span class="text-danger">*</span></label>
                                <input type="password" name="current_password" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Password Baru <span class="text-danger">*</span></label>
                                <input type="password" name="new_password" class="form-control" required minlength="6">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Konfirmasi Password <span class="text-danger">*</span></label>
                                <input type="password" name="confirm_password" class="form-control" required>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer text-right">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-lock mr-1"></i> Ubah Password</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$extraJS = <<<'JS'
<script>
// Custom file input label
$('.custom-file-input').on('change', function() {
    var fileName = $(this).val().split('\\').pop();
    $(this).next('.custom-file-label').text(fileName || 'Pilih gambar...');
});
</script>
JS;
require_once __DIR__ . '/../../includes/footer.php';
?>
