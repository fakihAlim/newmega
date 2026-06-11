<?php
/**
 * Master Customers - Edit
 */
require_once __DIR__ . '/../../../includes/auth.php';
requirePermission('master_customers', 'edit');

$id = $_GET['id'] ?? 0;
$stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
$stmt->execute([$id]);
$customer = $stmt->fetch();

if (!$customer) {
    setFlash('danger', 'Customer tidak ditemukan.');
    header('Location: ' . APP_URL . '/modules/master/customers/index.php');
    exit;
}

$pageTitle = 'Edit Customer: ' . sanitize($customer['company_name']);
$breadcrumbs = [
    ['label' => 'Master Data', 'url' => '#'],
    ['label' => 'Customer', 'url' => APP_URL . '/modules/master/customers/index.php'],
    ['label' => 'Edit']
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $companyName  = trim($_POST['company_name'] ?? '');
    $picName      = trim($_POST['pic_name'] ?? '');
    $phone        = trim($_POST['phone'] ?? '');
    $email        = trim($_POST['email'] ?? '');
    $address      = trim($_POST['address'] ?? '');
    $notes        = trim($_POST['notes'] ?? '');
    $abbreviation = trim($_POST['abbreviation'] ?? '');
    
    if (empty($abbreviation)) {
        $abbreviation = generateAbbreviation($companyName);
    } else {
        $abbreviation = strtoupper(substr($abbreviation, 0, 3));
    }
    
    // Validation
    $errors = [];
    if (empty($companyName)) $errors[] = "Nama customer wajib diisi.";
    
    // Check if abbreviation already exists for OTHER customers
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM customers WHERE abbreviation = ? AND id != ?");
    $stmt->execute([$abbreviation, $id]);
    if ($stmt->fetchColumn() > 0) {
        $errors[] = "Singkatan customer '$abbreviation' sudah digunakan. Silakan input manual singkatan yang berbeda.";
    }
    
    if (empty($errors)) {
        $stmt = $pdo->prepare("
            UPDATE customers 
            SET company_name=?, abbreviation=?, pic_name=?, phone=?, email=?, address=?, notes=? 
            WHERE id=?
        ");
        
        if ($stmt->execute([$companyName, $abbreviation, $picName, $phone, $email, $address, $notes, $id])) {
            setFlash('success', "Data customer berhasil diperbarui.");
            header('Location: ' . APP_URL . '/modules/master/customers/index.php');
            exit;
        } else {
            setFlash('danger', 'Terjadi kesalahan sistem saat menyimpan data.');
        }
    }
    
    if (!empty($errors)) {
        setFlash('danger', implode('<br>', $errors));
        $customer['company_name'] = $companyName;
        $customer['abbreviation'] = $abbreviation;
        $customer['pic_name'] = $picName;
        $customer['phone'] = $phone;
        $customer['email'] = $email;
        $customer['address'] = $address;
        $customer['notes'] = $notes;
    }
}

require_once __DIR__ . '/../../../includes/header.php';
?>

<div class="row">
    <div class="col-md-8 mx-auto">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-edit mr-2"></i>Form Edit Customer</h3>
                <a href="<?= APP_URL ?>/modules/master/customers/index.php" class="btn btn-secondary btn-sm float-right"><i class="fas fa-arrow-left mr-1"></i> Kembali</a>
            </div>
            <form method="POST">
                <div class="card-body">
                    
                    <div class="row">
                        <div class="col-md-8">
                            <div class="form-group">
                                <label>Nama Customer / Perusahaan <span class="text-danger">*</span></label>
                                <input type="text" name="company_name" class="form-control check-duplicate" data-type="customer" data-id="<?= $id ?>" value="<?= sanitize($customer['company_name']) ?>" required>
                                <div class="duplicate-warning text-danger" style="display:none; font-size: 12px; margin-top: 5px;"></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Singkatan</label>
                                <input type="text" name="abbreviation" class="form-control" value="<?= sanitize($customer['abbreviation']) ?>" maxlength="10" style="text-transform: uppercase;">
                                <small class="text-muted">Digunakan di No. INV/Q (Max 10 Char).</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Nama PIC (Opsional)</label>
                        <input type="text" name="pic_name" class="form-control" value="<?= sanitize($customer['pic_name']) ?>">
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>No. HP / Telepon</label>
                                <input type="text" name="phone" class="form-control" value="<?= sanitize($customer['phone']) ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Email</label>
                                <input type="email" name="email" class="form-control" value="<?= sanitize($customer['email']) ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Alamat Lengkap</label>
                        <textarea name="address" class="form-control" rows="2"><?= sanitize($customer['address']) ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Catatan Tambahan</label>
                        <textarea name="notes" class="form-control" rows="2"><?= sanitize($customer['notes']) ?></textarea>
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
