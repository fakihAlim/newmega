<?php
/**
 * Company Management - Create
 */
require_once __DIR__ . '/../../../includes/auth.php';
requirePermission('master_companies');

$pageTitle = 'Tambah Perusahaan Baru';
$breadcrumbs = [
    ['label' => 'Master Data', 'url' => '#'],
    ['label' => 'Perusahaan', 'url' => APP_URL . '/modules/master/companies/index.php'],
    ['label' => 'Tambah']
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name       = trim($_POST['name'] ?? '');
    $address    = trim($_POST['address'] ?? '');
    $city       = trim($_POST['city'] ?? '');
    $province   = trim($_POST['province'] ?? '');
    $postalCode = trim($_POST['postal_code'] ?? '');
    $phone      = trim($_POST['phone'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $isDefault  = isset($_POST['is_default']) ? 1 : 0;
    
    // Validation
    $errors = [];
    if (empty($name)) $errors[] = "Nama perusahaan wajib diisi.";
    
    if (empty($errors)) {
        // Handle Logo Upload
        $logoFilename = null;
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $result = uploadFile($_FILES['logo'], LOGOS_PATH);
            if ($result['success']) {
                $logoFilename = $result['filename'];
            } else {
                $errors[] = $result['message'];
            }
        }
        
        if (empty($errors)) {
            // If setting as default, reset others
            if ($isDefault) {
                $pdo->query("UPDATE companies SET is_default = 0");
            } else {
                // If this is the first company, force default
                $count = $pdo->query("SELECT COUNT(*) FROM companies")->fetchColumn();
                if ($count == 0) $isDefault = 1;
            }
            
            $stmt = $pdo->prepare("INSERT INTO companies (name, address, city, province, postal_code, phone, email, logo, is_default) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            if ($stmt->execute([$name, $address, $city, $province, $postalCode, $phone, $email, $logoFilename, $isDefault])) {
                setFlash('success', 'Perusahaan berhasil ditambahkan.');
                header('Location: ' . APP_URL . '/modules/master/companies/index.php');
                exit;
            } else {
                setFlash('danger', 'Terjadi kesalahan sistem saat menyimpan data.');
            }
        }
    }
    
    if (!empty($errors)) {
        setFlash('danger', implode('<br>', $errors));
    }
}

require_once __DIR__ . '/../../../includes/header.php';
?>

<div class="row">
    <div class="col-md-8 mx-auto">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-building mr-2"></i>Form Tambah Perusahaan</h3>
                <a href="<?= APP_URL ?>/modules/master/companies/index.php" class="btn btn-secondary btn-sm float-right"><i class="fas fa-arrow-left mr-1"></i> Kembali</a>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="card-body">
                    
                    <div class="form-group">
                        <label>Nama Perusahaan <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control check-duplicate" data-type="company" value="<?= sanitize($_POST['name'] ?? '') ?>" placeholder="Contoh: PT. Mega Karya Modern" required>
                        <div class="duplicate-warning text-danger" style="display:none; font-size: 12px; margin-top: 5px;"></div>
                    </div>
                    
                    <div class="form-group">
                        <label>Alamat Lengkap</label>
                        <textarea name="address" class="form-control" rows="3" placeholder="Nama Jalan, Blok, RT/RW..."><?= sanitize($_POST['address'] ?? '') ?></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Kota / Kabupaten</label>
                                <input type="text" name="city" class="form-control" value="<?= sanitize($_POST['city'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Provinsi</label>
                                <input type="text" name="province" class="form-control" value="<?= sanitize($_POST['province'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Kode Pos</label>
                                <input type="text" name="postal_code" class="form-control" value="<?= sanitize($_POST['postal_code'] ?? '') ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>No. Telepon / HP</label>
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
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Logo Perusahaan</label>
                                <div class="custom-file">
                                    <input type="file" name="logo" class="custom-file-input" id="logoInput" accept="image/*">
                                    <label class="custom-file-label" for="logoInput">Pilih logo...</label>
                                </div>
                                <small class="form-text text-muted">Akan ditampilkan pada kop surat / PDF. Maks 2MB.</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Set Sebagai Default?</label>
                                <div class="custom-control custom-switch mt-2">
                                    <input type="checkbox" name="is_default" class="custom-control-input" id="isDefault" value="1" <?= isset($_POST['is_default']) ? 'checked' : '' ?>>
                                    <label class="custom-control-label" for="isDefault">Gunakan sebagai header utama</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                </div>
                <div class="card-footer text-right">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Simpan Perusahaan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$extraJS = <<<'JS'
<script>
$('.custom-file-input').on('change', function() {
    var fileName = $(this).val().split('\\').pop();
    $(this).next('.custom-file-label').text(fileName || 'Pilih logo...');
});
</script>
JS;
require_once __DIR__ . '/../../../includes/footer.php';
?>
