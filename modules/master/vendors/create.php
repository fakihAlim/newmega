<?php
/**
 * Master Vendors - Create
 */
require_once __DIR__ . '/../../../includes/auth.php';
requirePermission('master_vendors');

$pageTitle = 'Tambah Supplier';
$breadcrumbs = [
    ['label' => 'Master Data', 'url' => '#'],
    ['label' => 'Vendor', 'url' => APP_URL . '/modules/master/vendors/index.php'],
    ['label' => 'Tambah']
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $companyName  = trim($_POST['company_name'] ?? '');
    $picName      = trim($_POST['pic_name'] ?? '');
    $phone        = trim($_POST['phone'] ?? '');
    $email        = trim($_POST['email'] ?? '');
    $address      = trim($_POST['address'] ?? '');
    $paymentTerms = trim($_POST['payment_terms'] ?? '');
    $notes        = trim($_POST['notes'] ?? '');
    
    // Auto-generate abbreviation if not provided manually
    $abbreviation = trim($_POST['abbreviation'] ?? '');
    if (empty($abbreviation)) {
        $abbreviation = generateAbbreviation($companyName);
    } else {
        $abbreviation = strtoupper(substr($abbreviation, 0, 3));
    }
    
    // Validation
    $errors = [];
    if (empty($companyName)) $errors[] = "Nama perusahaan wajib diisi.";
    if (empty($picName)) $errors[] = "Nama PIC wajib diisi.";
    if (empty($abbreviation)) $errors[] = "Singkatan vendor gagal digenerate.";
    
    // Check if abbreviation already exists
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM vendors WHERE abbreviation = ?");
    $stmt->execute([$abbreviation]);
    if ($stmt->fetchColumn() > 0) {
        $errors[] = "Singkatan vendor '$abbreviation' sudah digunakan. Silakan input manual singkatan yang berbeda.";
    }
    
    if (empty($errors)) {
        $stmt = $pdo->prepare("
            INSERT INTO vendors (company_name, abbreviation, pic_name, phone, email, address, payment_terms, notes, is_active) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)
        ");
        
        if ($stmt->execute([$companyName, $abbreviation, $picName, $phone, $email, $address, $paymentTerms, $notes])) {
            $newId = $pdo->lastInsertId();
            logActivity('create', 'master_vendors', "Menambah Vendor: {$companyName} ({$abbreviation})", 'vendors', $newId);
            setFlash('success', "Vendor berhasil ditambahkan dengan kode: $abbreviation");
            header('Location: ' . APP_URL . '/modules/master/vendors/index.php');
            exit;
        } else {
            setFlash('danger', 'Terjadi kesalahan sistem saat menyimpan data.');
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
        <div class="card card-outline card-primary">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-truck mr-2"></i>Form Tambah Supplier</h3>
                <a href="<?= APP_URL ?>/modules/master/vendors/index.php" class="btn btn-secondary btn-sm float-right"><i class="fas fa-arrow-left mr-1"></i> Kembali</a>
            </div>
            <form method="POST">
                <div class="card-body">
                    
                    <div class="form-group row">
                        <label class="col-sm-4 col-form-label">Nama Perusahaan / Toko <span class="text-danger">*</span></label>
                        <div class="col-sm-8">
                            <input type="text" name="company_name" class="form-control check-duplicate" data-type="vendor" value="<?= sanitize($_POST['company_name'] ?? '') ?>" required>
                            <div class="duplicate-warning text-danger" style="display:none; font-size: 12px; margin-top: 5px;"></div>
                        </div>
                    </div>
                    
                    <div class="form-group row">
                        <label class="col-sm-4 col-form-label">Singkatan (Akan digenerate otomatis)</label>
                        <div class="col-sm-8">
                            <input type="text" name="abbreviation" class="form-control" value="<?= sanitize($_POST['abbreviation'] ?? '') ?>" maxlength="3" style="text-transform: uppercase;" placeholder="Cth: MKM">
                            <small class="text-muted d-block mt-1">Kosongkan agar OTOMATIS digenerate.</small>
                        </div>
                    </div>
                    
                    <div class="form-group row">
                        <label class="col-sm-4 col-form-label">Nama PIC (Person In Charge) <span class="text-danger">*</span></label>
                        <div class="col-sm-8">
                            <input type="text" name="pic_name" class="form-control" value="<?= sanitize($_POST['pic_name'] ?? '') ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-group row">
                        <label class="col-sm-4 col-form-label">Termin Pembayaran <small class="text-muted">(Opsional)</small></label>
                        <div class="col-sm-8">
                            <input type="text" name="payment_terms" class="form-control" value="<?= sanitize($_POST['payment_terms'] ?? '') ?>" placeholder="Cth: Net 30, Cash On Delivery">
                        </div>
                    </div>
                    
                    <div class="form-group row">
                        <label class="col-sm-4 col-form-label">No. HP / Telepon</label>
                        <div class="col-sm-8">
                            <input type="text" name="phone" class="form-control" value="<?= sanitize($_POST['phone'] ?? '') ?>">
                        </div>
                    </div>
                    
                    <div class="form-group row">
                        <label class="col-sm-4 col-form-label">Email</label>
                        <div class="col-sm-8">
                            <input type="email" name="email" class="form-control" value="<?= sanitize($_POST['email'] ?? '') ?>">
                        </div>
                    </div>
                    
                    <div class="form-group row">
                        <label class="col-sm-4 col-form-label">Alamat Lengkap</label>
                        <div class="col-sm-8">
                            <textarea name="address" class="form-control" rows="2"><?= sanitize($_POST['address'] ?? '') ?></textarea>
                        </div>
                    </div>
                    
                    <div class="form-group row">
                        <label class="col-sm-4 col-form-label">Catatan Tambahan</label>
                        <div class="col-sm-8">
                            <textarea name="notes" class="form-control" rows="2"><?= sanitize($_POST['notes'] ?? '') ?></textarea>
                        </div>
                    </div>
                    
                </div>
                <div class="card-footer text-right">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Simpan Vendor</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>
