<?php
/**
 * Master Employees - Edit
 */
require_once __DIR__ . '/../../../includes/auth.php';
requirePermission('master_employees');

$id = $_GET['id'] ?? 0;

$stmt = $pdo->prepare("
    SELECT e.*, u.full_name, u.phone, u.username 
    FROM employees e 
    JOIN users u ON e.user_id = u.id 
    WHERE e.id = ?
");
$stmt->execute([$id]);
$emp = $stmt->fetch();

if (!$emp) {
    setFlash('danger', 'Karyawan tidak ditemukan.');
    header('Location: ' . APP_URL . '/modules/master/employees/index.php');
    exit;
}

$pageTitle = 'Edit Karyawan: ' . sanitize($emp['full_name']);
$breadcrumbs = [
    ['label' => 'Master Data', 'url' => '#'],
    ['label' => 'Karyawan', 'url' => APP_URL . '/modules/master/employees/index.php'],
    ['label' => 'Edit']
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $wage_id = $_POST['wage_id'] ?? '';
    
    $errors = [];
    if (empty($full_name)) $errors[] = 'Nama Lengkap harus diisi.';
    if (empty($wage_id)) $errors[] = 'Jabatan harus dipilih.';
    
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            // 1. Update User
            $stmt = $pdo->prepare("UPDATE users SET full_name = ?, phone = ? WHERE id = ?");
            $stmt->execute([$full_name, $phone, $emp['user_id']]);
            
            // 2. Update Employee Profile
            $stmt = $pdo->prepare("UPDATE employees SET wage_id = ? WHERE id = ?");
            $stmt->execute([$wage_id, $id]);
            
            $pdo->commit();
            
            setFlash('success', "Data karyawan berhasil diperbarui.");
            header('Location: ' . APP_URL . '/modules/master/employees/index.php');
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            setFlash('danger', 'Terjadi kesalahan sistem: ' . $e->getMessage());
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
                <h3 class="card-title"><i class="fas fa-edit mr-2"></i>Form Edit Karyawan</h3>
                <a href="<?= APP_URL ?>/modules/master/employees/index.php" class="btn btn-secondary btn-sm float-right"><i class="fas fa-arrow-left mr-1"></i> Kembali</a>
            </div>
            <form action="" method="POST">
                <div class="card-body">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Username Login <small class="text-muted">(Tidak dapat diubah)</small></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                                    </div>
                                    <input type="text" class="form-control" readonly value="<?= sanitize($emp['username']) ?>" style="background-color: #f4f6f9;">
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Status Karyawan</label>
                                <div class="mt-1">
                                    <?php if ($emp['is_active']): ?>
                                        <span class="badge badge-success" style="font-size: 14px; padding: 5px 12px;"><i class="fas fa-check-circle mr-1"></i> Aktif</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger" style="font-size: 14px; padding: 5px 12px;"><i class="fas fa-times-circle mr-1"></i> Nonaktif</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Nama Lengkap <span class="text-danger">*</span></label>
                                <input type="text" name="full_name" class="form-control" required value="<?= htmlspecialchars($_POST['full_name'] ?? $emp['full_name']) ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Nomor Telepon/HP</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fas fa-phone"></i></span>
                                    </div>
                                    <input type="text" name="phone" class="form-control" placeholder="08xx-xxxx-xxxx" value="<?= htmlspecialchars($_POST['phone'] ?? $emp['phone']) ?>">
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
                                    <?php
                                    $wages = $pdo->query("SELECT * FROM master_wages ORDER BY jabatan_name ASC")->fetchAll();
                                    foreach ($wages as $w) {
                                        $selected = (($_POST['wage_id'] ?? $emp['wage_id']) == $w['id']) ? 'selected' : '';
                                        echo "<option value=\"{$w['id']}\" {$selected}>" . sanitize($w['jabatan_name']) . " - Rp " . number_format($w['daily_wage'], 0, ',', '.') . "</option>";
                                    }
                                    ?>
                                </select>
                                <small class="form-text text-muted">Perubahan jabatan akan mempengaruhi upah harian pada timesheet <strong>baru</strong>.</small>
                            </div>
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
