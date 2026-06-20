<?php
/**
 * Master Customers - Create
 */
require_once __DIR__ . '/../../../includes/auth.php';
requirePermission('master_customers', 'create');

$pageTitle = 'Tambah Customer';
$breadcrumbs = [
    ['label' => 'Master Data', 'url' => '#'],
    ['label' => 'Customer', 'url' => APP_URL . '/modules/master/customers/index.php'],
    ['label' => 'Tambah']
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $companyName  = trim($_POST['company_name'] ?? '');
    $picName      = trim($_POST['pic_name'] ?? '');
    $phone        = trim($_POST['phone'] ?? '');
    $email        = trim($_POST['email'] ?? '');
    $address      = trim($_POST['address'] ?? '');
    $notes        = trim($_POST['notes'] ?? '');
    
    // Auto-generate base abbreviation if not provided manually
    $baseAbbr = trim($_POST['abbreviation'] ?? '');
    if (empty($baseAbbr)) {
        $baseAbbr = generateAbbreviation($companyName);
    } else {
        $baseAbbr = strtoupper(substr($baseAbbr, 0, 3));
    }
    
    // Find the next sequence for this base abbreviation
    $stmtSeq = $pdo->prepare("SELECT abbreviation FROM customers WHERE abbreviation LIKE ? ORDER BY abbreviation DESC LIMIT 1");
    $stmtSeq->execute([$baseAbbr . '-%']);
    $lastSeq = $stmtSeq->fetchColumn();

    if ($lastSeq) {
        $parts = explode('-', $lastSeq);
        $num = (int)end($parts);
        $newNum = $num + 1;
        $abbreviation = $baseAbbr . '-' . str_pad($newNum, 3, '0', STR_PAD_LEFT);
    } else {
        $abbreviation = $baseAbbr . '-001';
    }
    
    // Validation
    $errors = [];
    if (empty($companyName)) $errors[] = "Nama customer wajib diisi.";
    if (empty($abbreviation)) $errors[] = "Singkatan gagal digenerate.";
    
    if (empty($errors)) {
        $stmt = $pdo->prepare("
            INSERT INTO customers (company_name, abbreviation, pic_name, phone, email, address, notes, is_active) 
            VALUES (?, ?, ?, ?, ?, ?, ?, 1)
        ");
        
        if ($stmt->execute([$companyName, $abbreviation, $picName, $phone, $email, $address, $notes])) {
            setFlash('success', "Customer berhasil ditambahkan dengan kode: $abbreviation");
            header('Location: ' . APP_URL . '/modules/master/customers/index.php');
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
                <h3 class="card-title"><i class="fas fa-building mr-2"></i>Form Tambah Customer</h3>
                <a href="<?= APP_URL ?>/modules/master/customers/index.php" class="btn btn-secondary btn-sm float-right"><i class="fas fa-arrow-left mr-1"></i> Kembali</a>
            </div>
            <form method="POST">
                <div class="card-body">
                    
                    <div class="form-group row">
                        <label class="col-sm-4 col-form-label">Nama Customer / Perusahaan <span class="text-danger">*</span></label>
                        <div class="col-sm-8">
                            <input type="text" name="company_name" class="form-control check-duplicate" data-type="customer" value="<?= sanitize($_POST['company_name'] ?? '') ?>" required>
                            <div class="duplicate-warning text-danger" style="display:none; font-size: 12px; margin-top: 5px;"></div>
                        </div>
                    </div>
                    
                    <div class="form-group row">
                        <label class="col-sm-4 col-form-label">Singkatan</label>
                        <div class="col-sm-8">
                            <input type="text" name="abbreviation" class="form-control" value="<?= sanitize($_POST['abbreviation'] ?? '') ?>" maxlength="10" style="text-transform: uppercase;" placeholder="Cth: MKM">
                            <small class="text-muted d-block mt-1">Kosongkan agar otomatis digenerate.</small>
                        </div>
                    </div>
                    
                    <div class="form-group row">
                        <label class="col-sm-4 col-form-label">Nama PIC (Opsional)</label>
                        <div class="col-sm-8">
                            <input type="text" name="pic_name" class="form-control" value="<?= sanitize($_POST['pic_name'] ?? '') ?>">
                            <small class="text-muted d-block mt-1">Orang yang dapat dihubungi dari pihak customer.</small>
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
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Simpan Customer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>
