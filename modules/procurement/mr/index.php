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

<!-- Load Montserrat and Work Sans fonts -->
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&family=Work+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
body {
    font-family: 'Work Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    background-color: #f7f9fb !important;
}

/* Card Redesign */
.mr-card {
    background-color: #ffffff;
    border: 1px solid #e2e8f0 !important;
    border-radius: 4px !important;
    box-shadow: none !important;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    margin-bottom: 2rem;
}
.mr-card:hover {
    transform: translateY(-2px);
    box-shadow: 4px 4px 0px #1e293b !important;
}

/* Header Redesign */
.mr-card-header {
    background-color: #ffffff !important;
    border-bottom: 1px solid #e2e8f0 !important;
    padding: 1.25rem 1.5rem !important;
    border-top-left-radius: 4px !important;
    border-top-right-radius: 4px !important;
}
.mr-card-title {
    font-family: 'Montserrat', sans-serif !important;
    font-weight: 700 !important;
    font-size: 1.25rem !important;
    color: #091426 !important;
    margin: 0 !important;
    letter-spacing: -0.01em;
}

/* Primary CTA Button */
.btn-primary-cta {
    font-family: 'Montserrat', sans-serif !important;
    font-weight: 600 !important;
    font-size: 13px !important;
    background-color: #f28c28 !important;
    color: #ffffff !important;
    border: none !important;
    border-radius: 4px !important;
    padding: 0.5rem 1.25rem !important;
    transition: background-color 0.2s ease;
}
.btn-primary-cta:hover {
    background-color: #d97706 !important;
    color: #ffffff !important;
}

/* Custom Table Styling */
.table-minimalist {
    border-collapse: collapse !important;
    width: 100% !important;
    font-size: 13px !important;
}
.table-minimalist th {
    font-family: 'Montserrat', sans-serif !important;
    font-weight: 600 !important;
    text-transform: uppercase !important;
    font-size: 11px !important;
    letter-spacing: 0.05em !important;
    background-color: #1e293b !important;
    color: #ffffff !important;
    border: 1px solid #334155 !important;
    padding: 12px 14px !important;
}
.table-minimalist td {
    font-family: 'Work Sans', sans-serif !important;
    border: 1px solid #e2e8f0 !important;
    padding: 12px 14px !important;
    vertical-align: middle !important;
}

/* Custom Status Badges */
.badge-flat {
    font-family: 'Work Sans', sans-serif !important;
    font-weight: 600 !important;
    font-size: 11px !important;
    padding: 4px 8px !important;
    border-radius: 4px !important;
    display: inline-block;
}
.badge-flat-draft {
    background-color: #f1f5f9 !important;
    color: #475569 !important;
}
.badge-flat-pending {
    background-color: #ffedd5 !important;
    color: #c2410c !important;
}
.badge-flat-approved {
    background-color: #dcfce7 !important;
    color: #15803d !important;
}
.badge-flat-rejected {
    background-color: #fee2e2 !important;
    color: #b91c1c !important;
}
.badge-flat-completed {
    background-color: #ccfbf1 !important;
    color: #0f766e !important;
}

/* Action Buttons Outline Redesign */
.btn-action-outline {
    background-color: transparent !important;
    border-radius: 4px !important;
    font-size: 12px !important;
    padding: 6px 10px !important;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
    margin: 0 2px;
}
.btn-action-view {
    border: 1px solid #1e293b !important;
    color: #1e293b !important;
}
.btn-action-view:hover {
    background-color: #1e293b !important;
    color: #ffffff !important;
}
.btn-action-edit {
    border: 1px solid #f28c28 !important;
    color: #f28c28 !important;
}
.btn-action-edit:hover {
    background-color: #f28c28 !important;
    color: #ffffff !important;
}
.btn-action-delete {
    border: 1px solid #ba1a1a !important;
    color: #ba1a1a !important;
}
.btn-action-delete:hover {
    background-color: #ba1a1a !important;
    color: #ffffff !important;
}
</style>

<div class="card mr-card">
    <div class="card-header mr-card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title mr-card-title"><i class="fas fa-file-alt mr-2" style="color: #f28c28;"></i> DAFTAR MATERIAL REQUEST</h3>
        <?php if (canAccess('mr_create')): ?>
            <a href="<?= APP_URL ?>/modules/procurement/mr/create.php" class="btn-primary-cta ml-auto">
                <i class="fas fa-plus mr-1"></i> BUAT MR BARU
            </a>
        <?php endif; ?>
    </div>
    <div class="card-body p-4">
        <table id="mrTable" class="table table-minimalist table-bordered table-striped w-100">
            <thead>
                <tr>
                    <th width="15%">No. MR</th>
                    <th width="15%">Tanggal</th>
                    <th width="25%">Proyek</th>
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
                            $badgeClass = 'badge-flat-draft';
                            $label = ucfirst($r['status']);
                            if ($r['status'] === 'draft')
                                $badgeClass = 'badge-flat-draft';
                            if ($r['status'] === 'pending') {
                                $badgeClass = 'badge-flat-pending';
                                $label = 'Menunggu Approval';
                            }
                            if ($r['status'] === 'approved') {
                                $badgeClass = 'badge-flat-approved';
                                $label = 'Disetujui';
                            }
                            if ($r['status'] === 'rejected') {
                                $badgeClass = 'badge-flat-rejected';
                                $label = 'Ditolak';
                            }
                            if ($r['status'] === 'completed') {
                                $badgeClass = 'badge-flat-completed';
                                $label = 'Selesai (PO)';
                            }
                            ?>
                            <span class="badge-flat <?= $badgeClass ?>"><?= $label ?></span>
                        </td>
                        <td class="text-center">
                            <a href="<?= APP_URL ?>/modules/procurement/mr/view.php?id=<?= $r['id'] ?>"
                                class="btn-action-outline btn-action-view" data-toggle="tooltip" title="Lihat Detail">
                                <i class="fas fa-eye"></i>
                            </a>

                            <?php if ($r['status'] === 'draft' && ($user['role'] === 'super_admin' || $user['id'] == $r['requested_by'])): ?>
                                <a href="<?= APP_URL ?>/modules/procurement/mr/edit.php?id=<?= $r['id'] ?>"
                                    class="btn-action-outline btn-action-edit" data-toggle="tooltip" title="Edit Draft">
                                    <i class="fas fa-edit"></i>
                                </a>
                            <?php endif; ?>

                            <?php if (in_array($r['status'], ['draft', 'pending']) && ($user['role'] === 'super_admin' || $user['id'] == $r['requested_by'])): ?>
                                <form method="POST" class="d-inline" id="deleteForm-<?= $r['id'] ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $r['id'] ?>">
                                    <button type="button" class="btn-action-outline btn-action-delete" data-toggle="tooltip" title="Hapus MR"
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