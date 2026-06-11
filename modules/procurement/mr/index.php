<?php
/**
 * Procurement - Material Request List
 */
require_once __DIR__ . '/../../../includes/auth.php';
requirePermission('mr_list');

$pageTitle = 'Material Request (MR)';
$breadcrumbs = [
    ['label' => 'Procurement', 'url' => '#'],
    ['label' => 'Material Request']
];

$user = getCurrentUser();

// Logic for Role-Based Access
$conditions = [];
$params = [];

if (in_array($user['role'], ['gudang', 'project_manager'])) {
    $conditions[] = "m.requested_by = ?";
    $params[] = $user['id'];
}

$whereClause = "";
if (!empty($conditions)) {
    $whereClause = "WHERE " . implode(" AND ", $conditions);
}

// Fetch MRs
$sql = "
    SELECT m.*, p.name as project_name, u.full_name as requester_name 
    FROM material_requests m
    LEFT JOIN projects p ON m.project_id = p.id
    LEFT JOIN users u ON m.requested_by = u.id
    $whereClause
    ORDER BY m.id DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$requests = $stmt->fetchAll();

// Handle Delete Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $deleteId = $_POST['id'] ?? 0;

    // Check if MR exists and is in deletable status
    $checkStmt = $pdo->prepare("SELECT status, requested_by, mr_number FROM material_requests WHERE id = ?");
    $checkStmt->execute([$deleteId]);
    $mr = $checkStmt->fetch();

    if (!$mr) {
        setFlash('danger', 'MR tidak ditemukan.');
    } elseif (!in_array($mr['status'], ['draft', 'pending'])) {
        setFlash('danger', 'Hanya MR berstatus Draft atau Menunggu Approval yang dapat dihapus.');
    } elseif ($user['role'] !== 'super_admin' && $mr['requested_by'] != $user['id']) {
        setFlash('danger', 'Anda tidak memiliki hak akses untuk menghapus MR ini.');
    } else {
        try {
            $pdo->beginTransaction();

            // Delete items first
            $pdo->prepare("DELETE FROM material_request_items WHERE mr_id = ?")->execute([$deleteId]);
            // Delete header
            $pdo->prepare("DELETE FROM material_requests WHERE id = ?")->execute([$deleteId]);

            $pdo->commit();
            setFlash('success', "MR {$mr['mr_number']} berhasil dihapus.");
        } catch (Exception $e) {
            $pdo->rollBack();
            setFlash('danger', "Terjadi kesalahan sistem: " . $e->getMessage());
        }
    }

    header('Location: ' . APP_URL . '/modules/procurement/mr/index.php');
    exit;
}

require_once __DIR__ . '/../../../includes/header.php';
?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title">Daftar Material Request</h3>
        <?php if (canAccess('mr_create')): ?>
            <a href="<?= APP_URL ?>/modules/procurement/mr/create.php" class="btn btn-primary btn-sm ml-auto">
                <i class="fas fa-plus mr-1"></i> Buat MR Baru
            </a>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <table id="mrTable" class="table table-bordered table-striped w-100" style="font-size: 13px;">
            <thead>
                <tr>
                    <th width="12%">No. MR</th>
                    <th width="12%">Tanggal</th>
                    <th width="20%">Proyek</th>
                    <th width="15%">Pemohon</th>
                    <th width="15%">Status</th>
                    <th width="15%" class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($requests as $r): ?>
                    <tr>
                        <td><strong><?= sanitize($r['mr_number']) ?></strong></td>
                        <td><?= date('d-m-Y', strtotime($r['request_date'])) ?></td>
                        <td><?= sanitize($r['project_name']) ?></td>
                        <td><?= sanitize($r['requester_name']) ?></td>
                        <td>
                            <?php
                            $badge = 'secondary';
                            $label = ucfirst($r['status']);
                            if ($r['status'] === 'draft')
                                $badge = 'secondary';
                            if ($r['status'] === 'pending') {
                                $badge = 'warning';
                                $label = 'Menunggu Approval';
                            }
                            if ($r['status'] === 'approved') {
                                $badge = 'success';
                                $label = 'Disetujui';
                            }
                            if ($r['status'] === 'rejected') {
                                $badge = 'danger';
                                $label = 'Ditolak';
                            }
                            if ($r['status'] === 'completed') {
                                $badge = 'info';
                                $label = 'Selesai (PO)';
                            }
                            ?>
                            <span class="badge badge-<?= $badge ?>"><?= $label ?></span>
                        </td>
                        <td class="text-center">
                            <a href="<?= APP_URL ?>/modules/procurement/mr/view.php?id=<?= $r['id'] ?>"
                                class="btn btn-info btn-sm" data-toggle="tooltip" title="Lihat Detail">
                                <i class="fas fa-eye"></i>
                            </a>

                            <?php if ($r['status'] === 'draft' && ($user['role'] === 'super_admin' || $user['id'] == $r['requested_by'])): ?>
                                <a href="<?= APP_URL ?>/modules/procurement/mr/edit.php?id=<?= $r['id'] ?>"
                                    class="btn btn-warning btn-sm" data-toggle="tooltip" title="Edit Draft">
                                    <i class="fas fa-edit text-white"></i>
                                </a>
                            <?php endif; ?>

                            <?php if (in_array($r['status'], ['draft', 'pending']) && ($user['role'] === 'super_admin' || $user['id'] == $r['requested_by'])): ?>
                                <form method="POST" class="d-inline" id="deleteForm-<?= $r['id'] ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $r['id'] ?>">
                                    <button type="button" class="btn btn-danger btn-sm" data-toggle="tooltip" title="Hapus MR"
                                        onclick="confirmDelete(<?= $r['id'] ?>, '<?= sanitize($r['mr_number']) ?>')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
$extraJS = <<<'JS'
<script>
$(document).ready(function() {
    initDataTable('#mrTable');
});

function confirmDelete(id, number) {
    confirmAction('Hapus Material Request?', 'Anda yakin ingin menghapus ' + number + '? Data yang dihapus tidak dapat dikembalikan.', function() {
        $('#deleteForm-' + id).submit();
    });
}
</script>
JS;
require_once __DIR__ . '/../../../includes/footer.php';
?>