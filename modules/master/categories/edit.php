<?php
/**
 * Master Categories - Edit
 */
require_once __DIR__ . '/../../../includes/auth.php';
requirePermission('master_categories');

$id = $_GET['id'] ?? 0;
$stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
$stmt->execute([$id]);
$category = $stmt->fetch();

if (!$category) {
    setFlash('danger', 'Kategori tidak ditemukan.');
    header('Location: ' . APP_URL . '/modules/master/categories/index.php');
    exit;
}

$pageTitle = 'Edit Kategori';
$breadcrumbs = [
    ['label' => 'Master Data', 'url' => '#'],
    ['label' => 'Kategori', 'url' => APP_URL . '/modules/master/categories/index.php'],
    ['label' => 'Edit']
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
    } elseif (strlen($prefix) > 5) {
        $errors[] = "Prefix maksimal 5 karakter.";
    }
    
    // Check if prefix already exists for OTHER categories
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE prefix = ? AND id != ?");
    $stmt->execute([$prefix, $id]);
    if ($stmt->fetchColumn() > 0) {
        $errors[] = "Prefix kode sudah digunakan oleh kategori lain.";
    }
    
    if (empty($errors)) {
        $stmt = $pdo->prepare("UPDATE categories SET name = ?, prefix = ?, description = ? WHERE id = ?");
        
        if ($stmt->execute([$name, $prefix, $description, $id])) {
            setFlash('success', 'Data kategori berhasil diperbarui.');
            header('Location: ' . APP_URL . '/modules/master/categories/index.php');
            exit;
        } else {
            setFlash('danger', 'Terjadi kesalahan sistem saat menyimpan data.');
        }
    }
    
    if (!empty($errors)) {
        setFlash('danger', implode('<br>', $errors));
        // Refresh local array for standard display
        $category['name'] = $name;
        $category['prefix'] = $prefix;
        $category['description'] = $description;
    }
}

require_once __DIR__ . '/../../../includes/header.php';
?>

<div class="row">
    <div class="col-md-6 mx-auto">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-edit mr-2"></i>Form Edit Kategori</h3>
                <a href="<?= APP_URL ?>/modules/master/categories/index.php" class="btn btn-secondary btn-sm float-right"><i class="fas fa-arrow-left mr-1"></i> Kembali</a>
            </div>
            <form method="POST">
                <div class="card-body">
                    <div class="form-group">
                        <label>Nama Kategori <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control check-duplicate" data-type="category" data-id="<?= $id ?>" value="<?= sanitize($category['name']) ?>" required>
                        <div class="duplicate-warning text-danger" style="display:none; font-size: 12px; margin-top: 5px;"></div>
                    </div>
                    
                    <div class="form-group">
                        <label>Prefix / Awalan Kode <span class="text-danger">*</span></label>
                        <input type="text" name="prefix" class="form-control" value="<?= sanitize($category['prefix']) ?>" maxlength="5" required style="text-transform: uppercase;">
                        <small class="form-text text-muted">Prefix digunakan untuk generate ID otomatis pada item. Maksimal 5 karakter.</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Deskripsi</label>
                        <textarea name="description" class="form-control" rows="3"><?= sanitize($category['description']) ?></textarea>
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
