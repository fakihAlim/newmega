<?php
/**
 * Master Wages - Edit
 */
require_once __DIR__ . '/../../../includes/auth.php';
requirePermission('master_wages');

$id = $_GET['id'] ?? 0;

$stmt = $pdo->prepare("SELECT * FROM master_wages WHERE id = ?");
$stmt->execute([$id]);
$wage = $stmt->fetch();

if (!$wage) {
    setFlash('danger', 'Master upah tidak ditemukan.');
    header('Location: ' . APP_URL . '/modules/master/wages/index.php');
    exit;
}

// Count employees using this wage
$stmtCount = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE wage_id = ?");
$stmtCount->execute([$id]);
$empCount = $stmtCount->fetchColumn();

$pageTitle = 'Edit Master Upah: ' . sanitize($wage['jabatan_name']);
$breadcrumbs = [
    ['label' => 'Master Data', 'url' => '#'],
    ['label' => 'Master Upah', 'url' => APP_URL . '/modules/master/wages/index.php'],
    ['label' => 'Edit']
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $jabatan_name = trim($_POST['jabatan_name'] ?? '');
    $daily_wage = str_replace(['Rp', '.', ',', ' '], '', $_POST['daily_wage'] ?? '0');
    
    $errors = [];
    if (empty($jabatan_name)) $errors[] = 'Nama Jabatan harus diisi.';
    if (!is_numeric($daily_wage) || $daily_wage < 0) $errors[] = 'Upah Harian tidak valid.';
    
    // Check duplicate name
    $stmt = $pdo->prepare("SELECT id FROM master_wages WHERE jabatan_name = ? AND id != ?");
    $stmt->execute([$jabatan_name, $id]);
    if ($stmt->fetch()) {
        $errors[] = 'Nama Jabatan sudah ada di database.';
    }
    
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                UPDATE master_wages 
                SET jabatan_name = ?, daily_wage = ?
                WHERE id = ?
            ");
            
            $stmt->execute([$jabatan_name, $daily_wage, $id]);
            
            setFlash('success', 'Master upah berhasil diperbarui.');
            header('Location: ' . APP_URL . '/modules/master/wages/index.php');
            exit;
        } catch (PDOException $e) {
            setFlash('danger', 'Terjadi kesalahan sistem.');
        }
    } else {
        setFlash('danger', implode('<br>', $errors));
        $wage['jabatan_name'] = $jabatan_name;
        $wage['daily_wage'] = $daily_wage;
    }
}

require_once __DIR__ . '/../../../includes/header.php';
?>

<div class="row">
    <div class="col-md-8 mx-auto">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-edit mr-2"></i>Form Edit Master Upah</h3>
                <a href="<?= APP_URL ?>/modules/master/wages/index.php" class="btn btn-secondary btn-sm float-right"><i class="fas fa-arrow-left mr-1"></i> Kembali</a>
            </div>
            <form action="" method="POST">
                <div class="card-body">
                    
                    <?php if ($empCount > 0): ?>
                    <div class="alert alert-warning" style="font-size: 13px;">
                        <i class="fas fa-exclamation-triangle mr-1"></i> 
                        Jabatan ini sedang digunakan oleh <strong><?= $empCount ?></strong> karyawan. 
                        Perubahan upah <strong>tidak</strong> akan mengubah data timesheet yang sudah ada.
                    </div>
                    <?php endif; ?>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Nama Jabatan <span class="text-danger">*</span></label>
                                <input type="text" name="jabatan_name" class="form-control" required placeholder="Contoh: Tukang Las, Mandor, Helper" value="<?= htmlspecialchars($wage['jabatan_name']) ?>">
                                <small class="form-text text-muted">Nama jabatan harus unik, tidak boleh sama.</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Upah Harian (Rp) <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><strong>Rp</strong></span>
                                    </div>
                                    <input type="text" name="daily_wage" class="form-control rupiah-input" required placeholder="0" value="<?= htmlspecialchars($wage['daily_wage']) ?>">
                                </div>
                                <small class="form-text text-muted">Upah lembur otomatis dihitung: Upah Harian / 8 per jam.</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-0">
                                <label class="text-muted">Upah Lembur (Per Jam) — Otomatis</label>
                                <input type="text" class="form-control" id="overtime_preview" readonly style="background-color: #f4f6f9; font-weight: bold; color: #28a745;">
                                <small class="form-text text-muted">Dihitung dari Upah Harian ÷ 8.</small>
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
    initRupiahInput('.rupiah-input');
    
    // Live preview overtime wage
    function updateOvertimePreview() {
        let raw = $('.rupiah-input').val().replace(/[^0-9]/g, '');
        let daily = parseInt(raw) || 0;
        let overtime = Math.round(daily / 8);
        $('#overtime_preview').val('Rp ' + overtime.toLocaleString('id-ID'));
    }
    
    $('.rupiah-input').on('input change keyup', updateOvertimePreview);
    updateOvertimePreview();
});
</script>
JS;
require_once __DIR__ . '/../../../includes/footer.php';
?>
