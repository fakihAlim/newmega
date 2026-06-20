<?php
/**
 * Role Management - Index
 */
require_once __DIR__ . '/../../../includes/auth.php';
// We don't have a specific role_management page key yet, so we use users for now or create a new one.
// The migration mapped users to 'users'. We'll use requirePermission('users') to restrict to admin.
requirePermission('users');

$pageTitle = 'Manajemen Peran & Hak Akses';
$breadcrumbs = [
    ['label' => 'Administrasi', 'url' => '#'],
    ['label' => 'Peran & Hak Akses']
];

// Handle Delete
if (isset($_POST['delete_id'])) {
    $delId = (int)$_POST['delete_id'];
    
    // Check if role is used by users
    $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM user_roles WHERE role_id = ?");
    $stmtCheck->execute([$delId]);
    if ($stmtCheck->fetchColumn() > 0) {
        setFlash('danger', 'Peran tidak dapat dihapus karena sedang digunakan oleh user.');
    } else {
        $stmtDel = $pdo->prepare("DELETE FROM roles WHERE id = ?");
        if ($stmtDel->execute([$delId])) {
            setFlash('success', 'Peran berhasil dihapus.');
        } else {
            setFlash('danger', 'Gagal menghapus peran.');
        }
    }
    header('Location: index.php');
    exit;
}

$stmt = $pdo->query("SELECT * FROM roles ORDER BY role_name ASC");
$roles = $stmt->fetchAll();

require_once __DIR__ . '/../../../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-user-tag mr-2"></i>Daftar Peran</h3>
        <div class="card-tools">
            <a href="create.php" class="btn btn-primary btn-sm"><i class="fas fa-plus mr-1"></i> Tambah Peran</a>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-bordered table-striped table-hover table-sm w-100">
                <thead>
                    <tr>
                        <th width="50">No</th>
                        <th>Nama Peran</th>
                        <th>Key Peran</th>
                        <th>Deskripsi</th>
                        <th width="150" class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($roles)): ?>
                    <tr>
                        <td colspan="5" class="text-center">Tidak ada data.</td>
                    </tr>
                    <?php else: ?>
                        <?php $no = 1; foreach ($roles as $r): ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td><?= sanitize($r['role_name']) ?></td>
                            <td><code><?= sanitize($r['role_key']) ?></code></td>
                            <td><?= sanitize($r['description'] ?? '-') ?></td>
                            <td class="text-center">
                                <a href="edit.php?id=<?= $r['id'] ?>" class="btn btn-sm btn-info" title="Edit Peran & Hak Akses"><i class="fas fa-edit"></i> Akses</a>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus peran ini?');">
                                    <input type="hidden" name="delete_id" value="<?= $r['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-danger" title="Hapus"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>
