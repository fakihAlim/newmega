<?php
/**
 * Master Vendors - Edit
 */
require_once __DIR__ . '/../../../includes/auth.php';
requirePermission('master_vendors');

$id = $_GET['id'] ?? 0;
$stmt = $pdo->prepare("SELECT * FROM vendors WHERE id = ?");
$stmt->execute([$id]);
$vendor = $stmt->fetch();

if (!$vendor) {
    setFlash('danger', 'Vendor tidak ditemukan.');
    header('Location: ' . APP_URL . '/modules/master/vendors/index.php');
    exit;
}

$pageTitle = 'Edit Vendor: ' . sanitize($vendor['company_name']);
$breadcrumbs = [
    ['label' => 'Master Data', 'url' => '#'],
    ['label' => 'Vendor', 'url' => APP_URL . '/modules/master/vendors/index.php'],
    ['label' => 'Edit']
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $companyName  = trim($_POST['company_name'] ?? '');
    $picName      = trim($_POST['pic_name'] ?? '');
    $phone        = trim($_POST['phone'] ?? '');
    $email        = trim($_POST['email'] ?? '');
    $address      = trim($_POST['address'] ?? '');
    $paymentTerms = trim($_POST['payment_terms'] ?? '');
    $notes        = trim($_POST['notes'] ?? '');
    $abbreviation = trim($_POST['abbreviation'] ?? '');
    
    // Auto-generate abbreviation if left blank
    if (empty($abbreviation)) {
        $abbreviation = generateAbbreviation($companyName);
    } else {
        $abbreviation = strtoupper($abbreviation);
    }
    
    // Validation
    $errors = [];
    if (empty($companyName)) $errors[] = "Nama perusahaan wajib diisi.";
    if (empty($picName)) $errors[] = "Nama PIC wajib diisi.";
    
    // Check if abbreviation already exists for OTHER vendors
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM vendors WHERE abbreviation = ? AND id != ?");
    $stmt->execute([$abbreviation, $id]);
    if ($stmt->fetchColumn() > 0) {
        $errors[] = "Singkatan vendor '$abbreviation' sudah digunakan. Silakan input manual singkatan yang berbeda.";
    }
    
    if (empty($errors)) {
        $stmt = $pdo->prepare("
            UPDATE vendors 
            SET company_name=?, abbreviation=?, pic_name=?, phone=?, email=?, address=?, payment_terms=?, notes=? 
            WHERE id=?
        ");
        
        if ($stmt->execute([$companyName, $abbreviation, $picName, $phone, $email, $address, $paymentTerms, $notes, $id])) {
            logActivity('update', 'master_vendors', "Memperbarui Vendor: {$companyName}", 'vendors', $id);
            setFlash('success', "Data vendor berhasil diperbarui.");
            header('Location: ' . APP_URL . '/modules/master/vendors/index.php');
            exit;
        } else {
            setFlash('danger', 'Terjadi kesalahan sistem saat menyimpan data.');
        }
    }
    
    if (!empty($errors)) {
        setFlash('danger', implode('<br>', $errors));
        $vendor['company_name'] = $companyName;
        $vendor['abbreviation'] = $abbreviation;
        $vendor['pic_name'] = $picName;
        $vendor['phone'] = $phone;
        $vendor['email'] = $email;
        $vendor['address'] = $address;
        $vendor['payment_terms'] = $paymentTerms;
        $vendor['notes'] = $notes;
    }
}

require_once __DIR__ . '/../../../includes/header.php';
?>

<div class="row">
    <div class="col-md-8 mx-auto">
        <div class="card card-outline card-primary">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-edit mr-2"></i>Form Edit Vendor</h3>
                <a href="<?= APP_URL ?>/modules/master/vendors/index.php" class="btn btn-secondary btn-sm float-right"><i class="fas fa-arrow-left mr-1"></i> Kembali</a>
            </div>
            <form method="POST">
                <div class="card-body">
                    
                    <div class="form-group row">
                        <label class="col-sm-4 col-form-label">Nama Perusahaan / Toko <span class="text-danger">*</span></label>
                        <div class="col-sm-8">
                            <input type="text" name="company_name" class="form-control check-duplicate" data-type="vendor" data-id="<?= $id ?>" value="<?= sanitize($vendor['company_name']) ?>" required>
                            <div class="duplicate-warning text-danger" style="display:none; font-size: 12px; margin-top: 5px;"></div>
                        </div>
                    </div>
                    
                    <div class="form-group row">
                        <label class="col-sm-4 col-form-label">Singkatan</label>
                        <div class="col-sm-8">
                            <input type="text" name="abbreviation" class="form-control" value="<?= sanitize($vendor['abbreviation']) ?>" maxlength="15" style="text-transform: uppercase;">
                            <small class="text-muted d-block mt-1">Digunakan di No. PO (Max 15 Char).</small>
                        </div>
                    </div>
                    
                    <div class="form-group row">
                        <label class="col-sm-4 col-form-label">Nama PIC (Person In Charge) <span class="text-danger">*</span></label>
                        <div class="col-sm-8">
                            <input type="text" name="pic_name" class="form-control" value="<?= sanitize($vendor['pic_name']) ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-group row">
                        <label class="col-sm-4 col-form-label">Termin Pembayaran <small class="text-muted">(Opsional)</small></label>
                        <div class="col-sm-8">
                            <input type="text" name="payment_terms" class="form-control" value="<?= sanitize($vendor['payment_terms']) ?>">
                        </div>
                    </div>
                    
                    <div class="form-group row">
                        <label class="col-sm-4 col-form-label">No. HP / Telepon</label>
                        <div class="col-sm-8">
                            <input type="text" name="phone" class="form-control" value="<?= sanitize($vendor['phone']) ?>">
                        </div>
                    </div>
                    
                    <div class="form-group row">
                        <label class="col-sm-4 col-form-label">Email</label>
                        <div class="col-sm-8">
                            <input type="email" name="email" class="form-control" value="<?= sanitize($vendor['email']) ?>">
                        </div>
                    </div>
                    
                    <div class="form-group row">
                        <label class="col-sm-4 col-form-label">Alamat Lengkap</label>
                        <div class="col-sm-8">
                            <textarea name="address" class="form-control" rows="2"><?= sanitize($vendor['address']) ?></textarea>
                        </div>
                    </div>
                    
                    <div class="form-group row">
                        <label class="col-sm-4 col-form-label">Catatan Tambahan</label>
                        <div class="col-sm-8">
                            <textarea name="notes" class="form-control" rows="2"><?= sanitize($vendor['notes']) ?></textarea>
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

<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>
