<?php
/**
 * User Management - List Users
 */
require_once __DIR__ . '/../../includes/auth.php';
requirePermission('users');

$pageTitle = 'Manajemen Pengguna';
$breadcrumbs = [
    ['label' => 'Administrasi', 'url' => '#'],
    ['label' => 'Pengguna']
];

// Handle Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $id = $_POST['id'] ?? 0;
    if ($id == $_SESSION['user']['id']) {
        setFlash('danger', 'Anda tidak dapat menghapus akun Anda sendiri.');
    } else {
        // We will just deactivate instead of hard delete to maintain data integrity
        $stmt = $pdo->prepare("UPDATE users SET is_active = 0 WHERE id = ?");
        if ($stmt->execute([$id])) {
            logActivity('update', 'users', "Menonaktifkan pengguna ID: {$id}", 'users', $id);
            setFlash('success', 'Pengguna berhasil dinonaktifkan.');
        } else {
            setFlash('danger', 'Gagal menonaktifkan pengguna.');
        }
    }
    header('Location: ' . APP_URL . '/modules/users/index.php');
    exit;
}

// Handle Activate
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'activate') {
    $id = $_POST['id'] ?? 0;
    $stmt = $pdo->prepare("UPDATE users SET is_active = 1 WHERE id = ?");
    if ($stmt->execute([$id])) {
        logActivity('update', 'users', "Mengaktifkan pengguna ID: {$id}", 'users', $id);
        setFlash('success', 'Pengguna berhasil diaktifkan.');
    } else {
        setFlash('danger', 'Gagal mengaktifkan pengguna.');
    }
    header('Location: ' . APP_URL . '/modules/users/index.php');
    exit;
}

// Fetch Users
$stmt = $pdo->query("SELECT * FROM users ORDER BY id DESC");
$users = $stmt->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title">Daftar Pengguna</h3>
        <a href="<?= APP_URL ?>/modules/users/create.php" class="btn btn-primary btn-sm ml-auto">
            <i class="fas fa-plus mr-1"></i> Tambah Pengguna Baru
        </a>
    </div>
    <div class="card-body">
        <table id="usersTable" class="table table-bordered table-striped table-hover table-sm w-100" >
            <thead>
                <tr>
                    <th width="5%">No</th>
                    <th width="20%">Nama / Username</th>
                    <th width="15%">Peran</th>
                    <th width="20%">Kontak</th>
                    <th width="15%">Login Terakhir</th>
                    <th width="10%">Status</th>
                    <th width="15%" class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $i => $u): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td>
                        <div class="d-flex align-items-center">
                            <img src="<?= getProfilePhoto($u['photo']) ?>" class="img-circle mr-2" style="width:32px;height:32px;">
                            <div>
                                <strong class="d-block text-dark"><?= sanitize($u['full_name']) ?></strong>
                                <small class="text-muted">@<?= sanitize($u['username']) ?></small>
                            </div>
                        </div>
                    </td>
                    <td><?= getUserRolesDisplay($u['id']) ?></td>
                    <td>
                        <?php if ($u['email']): ?>
                        <div class="mb-1"><i class="fas fa-envelope text-muted" style="width:15px;"></i> <small><?= sanitize($u['email']) ?></small></div>
                        <?php endif; ?>
                        <?php if ($u['phone']): ?>
                        <div><i class="fas fa-phone text-muted" style="width:15px;"></i> <small><?= sanitize($u['phone']) ?></small></div>
                        <?php endif; ?>
                    </td>
                    <td><small><?= formatDateTime($u['last_login']) ?></small></td>
                    <td><?= getStatusBadge($u['is_active'] ? 'active' : 'cancelled') ?></td>
                    <td class="text-center">
                        <a href="<?= APP_URL ?>/modules/users/edit.php?id=<?= $u['id'] ?>" class="btn btn-info btn-sm" data-toggle="tooltip" title="Ubah">
                            <i class="fas fa-edit"></i>
                        </a>
                        
                        <?php if ($u['id'] != $_SESSION['user']['id']): ?>
                            <?php if ($u['is_active']): ?>
                                <button type="button" class="btn btn-warning btn-sm action-btn" 
                                    data-id="<?= $u['id'] ?>" 
                                    data-name="<?= sanitize($u['full_name']) ?>" 
                                    data-action="delete"
                                    data-toggle="tooltip" title="Nonaktifkan">
                                    <i class="fas fa-ban text-white"></i>
                                </button>
                            <?php else: ?>
                                <button type="button" class="btn btn-success btn-sm action-btn" 
                                    data-id="<?= $u['id'] ?>" 
                                    data-name="<?= sanitize($u['full_name']) ?>" 
                                    data-action="activate"
                                    data-toggle="tooltip" title="Aktifkan">
                                    <i class="fas fa-check"></i>
                                </button>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Hidden Form for Actions -->
<form id="actionForm" method="POST" style="display: none;">
    <input type="hidden" name="action" id="formAction">
    <input type="hidden" name="id" id="formId">
</form>

<?php
$extraJS = <<<'JS'
<script>
$(document).ready(function() {
    // Initialize DataTable using our custom helper
    initDataTable('#usersTable');

    // Handle Delete/Activate action via SweetAlert2
    $('.action-btn').on('click', function() {
        const id = $(this).data('id');
        const name = $(this).data('name');
        const action = $(this).data('action');
        
        let title = action === 'delete' ? 'Nonaktifkan Pengguna?' : 'Aktifkan Pengguna?';
        let text = action === 'delete' ? 
            `Anda yakin ingin menonaktifkan pengguna "${name}"? Pengguna tidak akan bisa login.` : 
            `Anda yakin ingin mengaktifkan pengguna "${name}"?`;
        
        confirmAction(title, text, function() {
            $('#formAction').val(action);
            $('#formId').val(id);
            $('#actionForm').submit();
        });
    });
});
</script>
JS;
require_once __DIR__ . '/../../includes/footer.php';
?>
