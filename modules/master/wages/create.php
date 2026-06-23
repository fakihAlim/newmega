<?php
/**
 * Master Wages - Create
 */
require_once __DIR__ . '/../../../includes/auth.php';
requirePermission('master_wages');

$pageTitle = 'Tambah Master Upah';
$breadcrumbs = [
    ['label' => 'Master Data', 'url' => '#'],
    ['label' => 'Master Upah', 'url' => APP_URL . '/modules/master/wages/index.php'],
    ['label' => 'Tambah']
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $jabatan_name = trim($_POST['jabatan_name'] ?? '');
    $daily_wage = str_replace(['Rp', '.', ',', ' '], '', $_POST['daily_wage'] ?? '0');
    
    $errors = [];
    if (empty($jabatan_name)) $errors[] = 'Nama Jabatan harus diisi.';
    if (!is_numeric($daily_wage) || $daily_wage < 0) $errors[] = 'Upah Harian tidak valid.';
    
    // Check duplicate name
    $stmt = $pdo->prepare("SELECT id FROM master_wages WHERE jabatan_name = ?");
    $stmt->execute([$jabatan_name]);
    if ($stmt->fetch()) {
        $errors[] = 'Nama Jabatan sudah ada di database.';
    }
    
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO master_wages (jabatan_name, daily_wage) 
                VALUES (?, ?)
            ");
            
            $stmt->execute([$jabatan_name, $daily_wage]);
            
            logActivity('create', 'master_wages', "Menambahkan master upah baru: {$jabatan_name}", 'master_wages', $pdo->lastInsertId());
            
            setFlash('success', 'Master upah berhasil ditambahkan.');
            header('Location: ' . APP_URL . '/modules/master/wages/index.php');
            exit;
        } catch (PDOException $e) {
            setFlash('danger', 'Terjadi kesalahan sistem.');
        }
    } else {
        setFlash('danger', implode('<br>', $errors));
    }
}

require_once __DIR__ . '/../../../includes/header.php';
?>

<div class="row">
    <div class="col-md-8 mx-auto">
        <div class="card card-outline card-primary">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-money-bill-wave mr-2"></i>Form Tambah Master Upah</h3>
                <a href="<?= APP_URL ?>/modules/master/wages/index.php" class="btn btn-secondary btn-sm float-right"><i class="fas fa-arrow-left mr-1"></i> Kembali</a>
            </div>
            <form method="POST">
                <div class="card-body">
                    
                    <div class="form-group row">
                        <label class="col-sm-4 col-form-label">Nama Jabatan <span class="text-danger">*</span></label>
                        <div class="col-sm-8">
                            <input type="text" name="jabatan_name" class="form-control" required placeholder="Contoh: Tukang Las, Mandor, Helper" value="<?= htmlspecialchars($_POST['jabatan_name'] ?? '') ?>">
                            <small class="form-text text-muted mt-1">Nama jabatan harus unik, tidak boleh sama.</small>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label class="col-sm-4 col-form-label">Upah Harian (Rp) <span class="text-danger">*</span></label>
                        <div class="col-sm-8">
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><strong>Rp</strong></span>
                                </div>
                                <input type="text" name="daily_wage" class="form-control rupiah-input" required placeholder="0" value="<?= htmlspecialchars($_POST['daily_wage'] ?? '') ?>">
                            </div>
                            <small class="form-text text-muted mt-1">Upah lembur otomatis dihitung: Upah Harian / 8 per jam.</small>
                        </div>
                    </div>
                    
                    <div class="form-group row">
                        <label class="col-sm-4 col-form-label text-muted">Upah Lembur (Per Jam) — Otomatis</label>
                        <div class="col-sm-8">
                            <input type="text" class="form-control" id="overtime_preview" readonly style="background-color: #f4f6f9; font-weight: bold; color: #28a745;">
                            <small class="form-text text-muted mt-1">Dihitung dari Upah Harian ÷ 8.</small>
                        </div>
                    </div>
                    
                </div>
                <div class="card-footer text-right">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Simpan Upah</button>
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
