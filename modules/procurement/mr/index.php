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

// Set default filters
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$projectId = $_GET['project_id'] ?? '';
$status = $_GET['status'] ?? '';

// Fetch projects for filter
$projects = $pdo->query("SELECT id, name FROM projects ORDER BY name ASC")->fetchAll();

// Logic for Role-Based Access
$conditions = [];
$params = [];

$canSeeAllMR = canAccess('purchase_order', 'view') || canAccess('users', 'view');
if (!$canSeeAllMR) {
    $conditions[] = "m.requested_by = ?";
    $params[] = $user['id'];
}

if ($startDate) {
    $conditions[] = "m.request_date >= ?";
    $params[] = $startDate;
}
if ($endDate) {
    $conditions[] = "m.request_date <= ?";
    $params[] = $endDate;
}
if ($projectId) {
    $conditions[] = "m.project_id = ?";
    $params[] = $projectId;
}
if ($status) {
    $conditions[] = "m.status = ?";
    $params[] = $status;
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
    } elseif (!canAccess('material_request', 'delete') && $mr['requested_by'] != $user['id']) {
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
            error_log('[NEWMEGA] ' . $e->getMessage());
            setFlash('danger', 'Terjadi kesalahan sistem. Silakan coba lagi atau hubungi administrator.');
        }
    }

    header('Location: ' . APP_URL . '/modules/procurement/mr/index.php');
    exit;
}

require_once __DIR__ . '/../../../includes/header.php';
?>

<!-- Filter Card -->
<div class="card d-print-none mb-3">
    <div class="card-body p-3">
        <form method="GET" action="" class="form-horizontal">
            <div class="row">
                <div class="col-md-2 col-sm-6 mb-2">
                    <label style="font-size:12px;">Tanggal Mulai</label>
                    <input type="date" name="start_date" class="form-control form-control-sm" value="<?= htmlspecialchars($startDate) ?>">
                </div>
                <div class="col-md-2 col-sm-6 mb-2">
                    <label style="font-size:12px;">Tanggal Selesai</label>
                    <input type="date" name="end_date" class="form-control form-control-sm" value="<?= htmlspecialchars($endDate) ?>">
                </div>
                <div class="col-md-4 col-sm-6 mb-2">
                    <label style="font-size:12px;">Proyek</label>
                    <select name="project_id" class="form-control form-control-sm select2">
                        <option value="">-- Semua Proyek --</option>
                        <?php foreach ($projects as $p): ?>
                            <option value="<?= $p['id'] ?>" <?= $projectId == $p['id'] ? 'selected' : '' ?>><?= sanitize($p['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 col-sm-6 mb-2">
                    <label style="font-size:12px;">Status</label>
                    <select name="status" class="form-control form-control-sm select2">
                        <option value="">-- Semua Status --</option>
                        <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>Draft</option>
                        <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="approved" <?= $status === 'approved' ? 'selected' : '' ?>>Approved</option>
                        <option value="rejected" <?= $status === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                        <option value="completed" <?= $status === 'completed' ? 'selected' : '' ?>>Completed</option>
                    </select>
                </div>
                <div class="col-md-2 col-sm-12 d-flex align-items-end mb-2">
                    <button type="submit" class="btn btn-primary btn-sm btn-block"><i class="fas fa-search mr-1"></i>Filter</button>
                    <a href="index.php" class="btn btn-default btn-sm ml-2" title="Reset Filters"><i class="fas fa-sync-alt"></i></a>
                </div>
            </div>
        </form>
    </div>
</div>

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
        <table id="mrTable" class="table table-bordered table-striped table-hover table-sm w-100" >
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

                            <?php if ($r['status'] === 'draft' && ($user['id'] == $r['requested_by'] || canAccess('material_request', 'edit'))): ?>
                                <a href="<?= APP_URL ?>/modules/procurement/mr/edit.php?id=<?= $r['id'] ?>"
                                    class="btn btn-warning btn-sm" data-toggle="tooltip" title="Ubah Draft">
                                    <i class="fas fa-edit text-white"></i>
                                </a>
                            <?php endif; ?>

                            <?php if (in_array($r['status'], ['draft', 'pending']) && ($user['id'] == $r['requested_by'] || canAccess('material_request', 'delete'))): ?>
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
    initSelect2('.select2');
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