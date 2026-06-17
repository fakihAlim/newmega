<?php
/**
 * Master Employees - Create
 */
require_once __DIR__ . '/../../../includes/auth.php';
requirePermission('master_employees');

$pageTitle = 'Tambah Karyawan';
$breadcrumbs = [
    ['label' => 'Master Data', 'url' => '#'],
    ['label' => 'Karyawan', 'url' => APP_URL . '/modules/master/employees/index.php'],
    ['label' => 'Tambah']
];

// Fetch available wages for dropdown
$wages = $pdo->query("SELECT * FROM master_wages ORDER BY jabatan_name ASC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $wage_id = $_POST['wage_id'] ?? '';
    
    // Auto-generate username from full_name (lowercase, remove spaces)
    $base_username = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $full_name));
    if (empty($base_username)) $base_username = 'user';
    $username = $base_username;
    
    // Check duplicate username and append number if exists
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
    $counter = 1;
    while (true) {
        $stmt->execute([$username]);
        if ($stmt->fetchColumn() == 0) break;
        $username = $base_username . $counter;
        $counter++;
    }
    
    // Default password '123456'
    $hashedPass = password_hash('123456', PASSWORD_DEFAULT);
    
    $errors = [];
    if (empty($full_name)) $errors[] = 'Nama Lengkap harus diisi.';
    if (empty($wage_id)) $errors[] = 'Jabatan harus dipilih.';
    
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            // 1. Create User
            $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, phone, is_active) VALUES (?, ?, ?, ?, 1)");
            $stmt->execute([$username, $hashedPass, $full_name, $phone]);
            $user_id = $pdo->lastInsertId();
            
            // 2. Assign Role 'karyawan'
            $roleStmt = $pdo->prepare("SELECT id FROM roles WHERE role_key = 'karyawan'");
            $roleStmt->execute();
            $role_id = $roleStmt->fetchColumn();
            
            if ($role_id) {
                $pdo->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)")->execute([$user_id, $role_id]);
            }
            
            // 3. Create Employee Profile with generated code
            $stmtMaxCode = $pdo->query("SELECT MAX(CAST(SUBSTRING(employee_code, 5) AS UNSIGNED)) FROM employees WHERE employee_code LIKE 'KAR-%'");
            $currentMax = $stmtMaxCode->fetchColumn() ?: 0;
            $employee_code = 'KAR-' . str_pad($currentMax + 1, 3, '0', STR_PAD_LEFT);

            $stmt = $pdo->prepare("INSERT INTO employees (user_id, wage_id, employee_code, is_active) VALUES (?, ?, ?, 1)");
            $stmt->execute([$user_id, $wage_id, $employee_code]);
            
            $pdo->commit();
            
            setFlash('success', "Karyawan berhasil ditambahkan. Kode: <b>$employee_code</b>, Username login: <b>$username</b>, Password default: <b>123456</b>");
            header('Location: ' . APP_URL . '/modules/master/employees/index.php');
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log('[NEWMEGA] ' . $e->getMessage());
            setFlash('danger', 'Terjadi kesalahan sistem. Silakan coba lagi atau hubungi administrator.');
        }
    } else {
        setFlash('danger', implode('<br>', $errors));
    }
}

require_once __DIR__ . '/../../../includes/header.php';
?>

<div class="row">
    <div class="col-md-8 mx-auto">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-hard-hat mr-2"></i>Form Tambah Karyawan</h3>
                <a href="<?= APP_URL ?>/modules/master/employees/index.php" class="btn btn-secondary btn-sm float-right"><i class="fas fa-arrow-left mr-1"></i> Kembali</a>
            </div>
            <form action="" method="POST">
                <div class="card-body">
                    
                    <div class="alert alert-info" style="font-size: 13px;">
                        <i class="fas fa-info-circle mr-1"></i> 
                        Akun login akan dibuat secara otomatis dari nama karyawan. 
                        Password default adalah <strong>123456</strong>.
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Nama Lengkap <span class="text-danger">*</span></label>
                                <input type="text" name="full_name" class="form-control" required placeholder="Contoh: Ahmad Fauzi" value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Nomor Telepon/HP</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fas fa-phone"></i></span>
                                    </div>
                                    <input type="text" name="phone" class="form-control" placeholder="08xx-xxxx-xxxx" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Jabatan & Upah <span class="text-danger">*</span></label>
                                <select name="wage_id" class="form-control select2" required>
                                    <option value="">-- Pilih Jabatan --</option>
                                    <?php foreach ($wages as $w): 
                                        $selected = (($_POST['wage_id'] ?? '') == $w['id']) ? 'selected' : '';
                                    ?>
                                        <option value="<?= $w['id'] ?>" <?= $selected ?>><?= sanitize($w['jabatan_name']) ?> - Rp <?= number_format($w['daily_wage'], 0, ',', '.') ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="form-text text-muted">Jabatan harus sudah tersedia di <a href="<?= APP_URL ?>/modules/master/wages/index.php">Master Upah</a>.</small>
                            </div>
                        </div>
                    </div>
                    
                </div>
                <div class="card-footer text-right">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Simpan Karyawan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$extraJS = <<<'JS'
<script>
$(document).ready(function() {
    $('.select2').select2({
        theme: 'bootstrap4'
    });
});
</script>
JS;
require_once __DIR__ . '/../../../includes/footer.php';
?>
