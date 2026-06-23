<?php
/**
 * Master Categories - Create
 */
require_once __DIR__ . '/../../../includes/auth.php';
requirePermission('master_categories');

$pageTitle = 'Tambah Kategori';
$breadcrumbs = [
    ['label' => 'Master Data', 'url' => '#'],
    ['label' => 'Kategori', 'url' => APP_URL . '/modules/master/categories/index.php'],
    ['label' => 'Tambah']
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name        = trim($_POST['name'] ?? '');
    $prefix      = strtoupper(trim($_POST['prefix'] ?? ''));
    $description = trim($_POST['description'] ?? '');
    
    // Validation
    $errors = [];
    if (empty($name)) $errors[] = "Nama kategori wajib diisi.";
    if (empty($prefix)) {
        $errors[] = "Prefix kode wajib diisi.";
    } elseif (strlen($prefix) > 10) {
        $errors[] = "Prefix maksimal 10 karakter.";
    }
    
    // Check if prefix already exists
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE prefix = ?");
    $stmt->execute([$prefix]);
    if ($stmt->fetchColumn() > 0) {
        $errors[] = "Prefix kode sudah digunakan oleh kategori lain.";
    }
    
    if (empty($errors)) {
        $stmt = $pdo->prepare("INSERT INTO categories (name, prefix, description) VALUES (?, ?, ?)");
        
        if ($stmt->execute([$name, $prefix, $description])) {
            $newId = $pdo->lastInsertId();
            logActivity('create', 'master_categories', "Menambah Kategori: {$name}", 'categories', $newId);
            setFlash('success', 'Kategori berhasil ditambahkan.');
            header('Location: ' . APP_URL . '/modules/master/categories/index.php');
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
                <h3 class="card-title"><i class="fas fa-tags mr-2"></i>Form Tambah Kategori</h3>
                <a href="<?= APP_URL ?>/modules/master/categories/index.php" class="btn btn-secondary btn-sm float-right"><i class="fas fa-arrow-left mr-1"></i> Kembali</a>
            </div>
            <form method="POST">
                <div class="card-body">
                    <div class="form-group row">
                        <label class="col-sm-4 col-form-label">Nama Kategori <span class="text-danger">*</span></label>
                        <div class="col-sm-8">
                            <input type="text" name="name" class="form-control check-duplicate" data-type="category" value="<?= sanitize($_POST['name'] ?? '') ?>" placeholder="Misal: Material Bangunan" required>
                            <div class="duplicate-warning text-danger" style="display:none; font-size: 12px; margin-top: 5px;"></div>
                        </div>
                    </div>
                    
                    <div class="form-group row">
                        <label class="col-sm-4 col-form-label">Prefix / Awalan Kode <span class="text-danger">*</span></label>
                        <div class="col-sm-8">
                            <input type="text" name="prefix" class="form-control" value="<?= sanitize($_POST['prefix'] ?? '') ?>" placeholder="Misal: CN-SEM" maxlength="10" required style="text-transform: uppercase;">
                        </div>
                    </div>
                    
                    <div class="form-group row">
                        <label class="col-sm-4 col-form-label">Deskripsi</label>
                        <div class="col-sm-8">
                            <textarea name="description" class="form-control" rows="3"><?= sanitize($_POST['description'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>
                <div class="card-footer text-right">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Simpan Kategori</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>
