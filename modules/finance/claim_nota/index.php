<?php
/**
 * Finance - Claim Nota List
 */
require_once __DIR__ . '/../../../includes/auth.php';
requirePermission('claim_nota');

$pageTitle = 'Claim Nota (Reimbursement)';
$breadcrumbs = [
    ['label' => 'Finance', 'url' => '#'],
    ['label' => 'Claim Nota']
];

$user = getCurrentUser();

// Get filters
$filterStart = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$filterEnd = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$filterCompany = $_GET['company_id'] ?? '';
$filterEmployee = $_GET['employee_name'] ?? '';
$filterStatus = $_GET['status'] ?? '';

// Build conditions
$conditions = [];
$params = [];

if ($filterStart) {
    $conditions[] = "c.claim_date >= ?";
    $params[] = $filterStart;
}
if ($filterEnd) {
    $conditions[] = "c.claim_date <= ?";
    $params[] = $filterEnd;
}
if ($filterCompany) {
    $conditions[] = "c.company_id = ?";
    $params[] = $filterCompany;
}
if ($filterEmployee) {
    $conditions[] = "c.employee_name LIKE ?";
    $params[] = "%$filterEmployee%";
}
if ($filterStatus) {
    $conditions[] = "c.status = ?";
    $params[] = $filterStatus;
}

$whereClause = "";
if (!empty($conditions)) {
    $whereClause = "WHERE " . implode(" AND ", $conditions);
}

// Fetch claims
$sql = "
    SELECT c.*, comp.name as company_name, u.full_name as creator_name 
    FROM nota_claims c
    LEFT JOIN companies comp ON c.company_id = comp.id
    LEFT JOIN users u ON c.created_by = u.id
    $whereClause
    ORDER BY c.id DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$claims = $stmt->fetchAll();

// Fetch all companies for filter dropdown
$companies = $pdo->query("SELECT id, name FROM companies ORDER BY name ASC")->fetchAll();

require_once __DIR__ . '/../../../includes/header.php';
?>

<!-- Filter Card -->
<div class="card card-outline card-primary d-print-none mb-3">
    <div class="card-body p-3">
        <form method="GET" action="" class="row">
            <div class="col-md-2 col-sm-6 mb-2">
                <label style="font-size:12px;">Tanggal Mulai</label>
                <input type="date" name="start_date" class="form-control form-control-sm" value="<?= htmlspecialchars($filterStart) ?>">
            </div>
            <div class="col-md-2 col-sm-6 mb-2">
                <label style="font-size:12px;">Tanggal Selesai</label>
                <input type="date" name="end_date" class="form-control form-control-sm" value="<?= htmlspecialchars($filterEnd) ?>">
            </div>
            <div class="col-md-2 col-sm-6 mb-2">
                <label style="font-size:12px;">Perusahaan</label>
                <select name="company_id" class="form-control form-control-sm select2">
                    <option value="">-- Semua Perusahaan --</option>
                    <?php foreach ($companies as $comp): ?>
                        <option value="<?= $comp['id'] ?>" <?= $filterCompany == $comp['id'] ? 'selected' : '' ?>>
                            <?= sanitize($comp['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 col-sm-6 mb-2">
                <label style="font-size:12px;">Nama Karyawan</label>
                <input type="text" name="employee_name" class="form-control form-control-sm" placeholder="Nama Karyawan..." value="<?= htmlspecialchars($filterEmployee) ?>">
            </div>
            <div class="col-md-2 col-sm-6 mb-2">
                <label style="font-size:12px;">Status</label>
                <select name="status" class="form-control form-control-sm select2">
                    <option value="">-- Semua Status --</option>
                    <option value="pending" <?= $filterStatus === 'pending' ? 'selected' : '' ?>>Pending Approval</option>
                    <option value="approved" <?= $filterStatus === 'approved' ? 'selected' : '' ?>>Approved</option>
                    <option value="paid" <?= $filterStatus === 'paid' ? 'selected' : '' ?>>Paid (Lunas)</option>
                    <option value="rejected" <?= $filterStatus === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                </select>
            </div>
            <div class="col-md-2 col-sm-12 d-flex align-items-end mb-2">
                <button type="submit" class="btn btn-primary btn-sm btn-block"><i class="fas fa-search mr-1"></i>Filter</button>
                <a href="index.php" class="btn btn-default btn-sm ml-2" title="Reset Filters"><i class="fas fa-sync-alt"></i></a>
            </div>
        </form>
    </div>
</div>

<!-- Claims Table Card -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title">Daftar Klaim Nota</h3>
        <div class="ml-auto">
            <a href="view_combined.php?<?= http_build_query($_GET) ?>" class="btn btn-default btn-sm mr-1">
                <i class="fas fa-print mr-1"></i> Cetak Laporan Gabungan
            </a>
            <a href="export_combined.php?<?= http_build_query($_GET) ?>" class="btn btn-success btn-sm mr-1">
                <i class="fas fa-file-excel mr-1"></i> Excel Laporan Gabungan
            </a>
            <?php if (canAccess('claim_nota', 'create')): ?>
                <a href="create.php" class="btn btn-primary btn-sm">
                    <i class="fas fa-plus mr-1"></i> Input Klaim Baru
                </a>
            <?php endif; ?>
        </div>
    </div>
    <div class="card-body">
        
        <table id="claimsTable" class="table table-bordered table-striped table-hover table-sm w-100" >
            <thead>
                <tr>
                    <th width="12%">No. Klaim</th>
                    <th width="12%">Tanggal</th>
                    <th width="20%">Karyawan</th>
                    <th width="20%">Perusahaan</th>
                    <th width="12%">Total Nominal</th>
                    <th width="12%">Status</th>
                    <th width="12%" class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($claims as $c): ?>
                    <tr>
                        <td><strong><?= sanitize($c['claim_number']) ?></strong></td>
                        <td><?= date('d-m-Y', strtotime($c['claim_date'])) ?></td>
                        <td><?= sanitize($c['employee_name']) ?></td>
                        <td><?= sanitize($c['company_name']) ?></td>
                        <td class="text-right"><?= formatRupiah($c['total_amount']) ?></td>
                        <td>
                            <?php
                            $badge = 'secondary';
                            $label = ucfirst($c['status']);
                            if ($c['status'] === 'pending') { $badge = 'warning'; $label = 'Pending Approval'; }
                            if ($c['status'] === 'approved') { $badge = 'success'; $label = 'Approved'; }
                            if ($c['status'] === 'paid') { $badge = 'info'; $label = 'Paid'; }
                            if ($c['status'] === 'rejected') { $badge = 'danger'; $label = 'Rejected'; }
                            ?>
                            <span class="badge badge-<?= $badge ?>"><?= $label ?></span>
                        </td>
                        <td class="text-center">
                            <a href="view.php?id=<?= $c['id'] ?>" class="btn btn-info btn-sm" data-toggle="tooltip" title="Lihat Detail">
                                <i class="fas fa-eye"></i>
                            </a>

                            <?php if (canAccess('claim_nota', 'edit') && in_array($c['status'], ['pending', 'rejected'])): ?>
                                <a href="edit.php?id=<?= $c['id'] ?>" class="btn btn-warning btn-sm" data-toggle="tooltip" title="Edit Klaim">
                                    <i class="fas fa-edit text-white"></i>
                                </a>
                            <?php endif; ?>

                            <?php if (canAccess('claim_nota', 'delete') && in_array($c['status'], ['pending', 'rejected'])): ?>
                                <form method="POST" action="delete.php" class="d-inline" id="deleteForm-<?= $c['id'] ?>">
                                    <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                    <button type="button" class="btn btn-danger btn-sm" data-toggle="tooltip" title="Hapus Klaim"
                                        onclick="confirmDelete(<?= $c['id'] ?>, '<?= sanitize($c['claim_number']) ?>')">
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
    initDataTable('#claimsTable');
    initSelect2('.select2');
});

function confirmDelete(id, number) {
    confirmAction('Hapus Klaim Nota?', 'Anda yakin ingin menghapus klaim ' + number + '? Data detail dan file bukti nota yang terunggah juga akan dihapus permanen.', function() {
        $('#deleteForm-' + id).submit();
    });
}
</script>
JS;
require_once __DIR__ . '/../../../includes/footer.php';
?>
