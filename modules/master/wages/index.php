<?php
/**
 * Master Wages - List
 */
require_once __DIR__ . '/../../../includes/auth.php';
requirePermission('master_wages');

$pageTitle = 'Master Upah & Jabatan';
$breadcrumbs = [
    ['label' => 'Master Data', 'url' => '#'],
    ['label' => 'Master Upah']
];

// Handle Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $id = $_POST['id'] ?? 0;
    
    // Check if used in employees
    $check = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE wage_id = ?");
    $check->execute([$id]);
    $usedInEmployees = $check->fetchColumn();
    
    if ($usedInEmployees > 0) {
        setFlash('danger', 'Jabatan tidak dapat dihapus karena sedang digunakan oleh karyawan.');
    } else {
        $stmt = $pdo->prepare("DELETE FROM master_wages WHERE id = ?");
        if ($stmt->execute([$id])) {
            setFlash('success', 'Master upah berhasil dihapus.');
        } else {
            setFlash('danger', 'Gagal menghapus master upah.');
        }
    }
    header('Location: ' . APP_URL . '/modules/master/wages/index.php');
    exit;
}

// Fetch Wages
$wages = $pdo->query("
    SELECT w.*, 
    (SELECT COUNT(*) FROM employees WHERE wage_id = w.id) as employee_count 
    FROM master_wages w 
    ORDER BY w.jabatan_name ASC
")->fetchAll();

require_once __DIR__ . '/../../../includes/header.php';
?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title">Daftar Master Upah</h3>
        <div class="ml-auto">
            <a href="<?= APP_URL ?>/modules/master/wages/create.php" class="btn btn-primary btn-sm">
                <i class="fas fa-plus mr-1"></i> Tambah Upah
            </a>
        </div>
    </div>
    <div class="card-body">
        <table id="wagesTable" class="table table-bordered table-striped w-100" style="font-size: 13.5px;">
            <thead>
                <tr>
                    <th width="5%">No</th>
                    <th width="35%">Nama Jabatan</th>
                    <th width="25%">Upah Harian (Rp)</th>
                    <th width="15%">Jml Karyawan</th>
                    <th width="20%" class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($wages as $i => $w): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td>
                        <strong class="text-dark"><?= sanitize($w['jabatan_name']) ?></strong>
                    </td>
                    <td><?= formatRupiah($w['daily_wage']) ?></td>
                    <td>
                        <span class="badge badge-info"><?= $w['employee_count'] ?> orang</span>
                    </td>
                    <td class="text-center">
                        <div class="btn-group">
                            <a href="<?= APP_URL ?>/modules/master/wages/edit.php?id=<?= $w['id'] ?>" class="btn btn-info btn-sm" data-toggle="tooltip" title="Ubah">
                                <i class="fas fa-edit"></i>
                            </a>
                            <?php if ($w['employee_count'] == 0): ?>
                            <button type="button" class="btn btn-danger btn-sm action-btn" 
                                data-id="<?= $w['id'] ?>" 
                                data-name="<?= sanitize($w['jabatan_name']) ?>" 
                                data-action="delete"
                                data-toggle="tooltip" title="Hapus Permanen">
                                <i class="fas fa-trash"></i>
                            </button>
                            <?php endif; ?>
                        </div>
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
    initDataTable('#wagesTable');

    $('.action-btn').on('click', function() {
        const id = $(this).data('id');
        const action = $(this).data('action');
        
        if (action === 'delete') {
            const name = $(this).data('name');
            confirmAction('Hapus Master Upah?', `Anda yakin ingin menghapus jabatan "${name}"?`, function() {
                $('#formAction').val(action);
                $('#formId').val(id);
                $('#actionForm').submit();
            });
        }
    });
});
</script>
JS;
require_once __DIR__ . '/../../../includes/footer.php';
?>
