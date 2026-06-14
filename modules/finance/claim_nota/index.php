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
$filterStart = $_GET['start_date'] ?? '';
$filterEnd = $_GET['end_date'] ?? '';
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

// Calculate total of filtered claims
$totalSum = 0;
foreach ($claims as $cl) {
    $totalSum += $cl['total_amount'];
}

// Fetch all companies for filter dropdown
$companies = $pdo->query("SELECT id, name FROM companies ORDER BY name ASC")->fetchAll();

require_once __DIR__ . '/../../../includes/header.php';
?>

<!-- Filter Card -->
<div class="card card-default">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-filter mr-2"></i>Filter Data</h3>
        <div class="card-tools">
            <button type="button" class="btn btn-tool" data-card-widget="collapse">
                <i class="fas fa-minus"></i>
            </button>
        </div>
    </div>
    <form method="GET" action="">
        <div class="card-body">
            <div class="row">
                <div class="col-md col-sm-6">
                    <div class="form-group mb-2 mb-md-0">
                        <label>Tanggal Mulai</label>
                        <input type="date" name="start_date" class="form-control form-control-sm" value="<?= htmlspecialchars($filterStart) ?>">
                    </div>
                </div>
                <div class="col-md col-sm-6">
                    <div class="form-group mb-2 mb-md-0">
                        <label>Tanggal Selesai</label>
                        <input type="date" name="end_date" class="form-control form-control-sm" value="<?= htmlspecialchars($filterEnd) ?>">
                    </div>
                </div>
                <div class="col-md col-sm-6">
                    <div class="form-group mb-2 mb-md-0">
                        <label>Perusahaan</label>
                        <select name="company_id" class="form-control form-control-sm select2">
                            <option value="">-- Semua Perusahaan --</option>
                            <?php foreach ($companies as $comp): ?>
                                <option value="<?= $comp['id'] ?>" <?= $filterCompany == $comp['id'] ? 'selected' : '' ?>>
                                    <?= sanitize($comp['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md col-sm-6">
                    <div class="form-group mb-2 mb-md-0">
                        <label>Nama Karyawan</label>
                        <input type="text" name="employee_name" class="form-control form-control-sm" placeholder="Nama Karyawan..." value="<?= htmlspecialchars($filterEmployee) ?>">
                    </div>
                </div>
                <div class="col-md col-sm-6">
                    <div class="form-group mb-2 mb-md-0">
                        <label>Status</label>
                        <select name="status" class="form-control form-control-sm">
                            <option value="">-- Semua Status --</option>
                            <option value="pending" <?= $filterStatus === 'pending' ? 'selected' : '' ?>>Pending Approval</option>
                            <option value="approved" <?= $filterStatus === 'approved' ? 'selected' : '' ?>>Approved</option>
                            <option value="paid" <?= $filterStatus === 'paid' ? 'selected' : '' ?>>Paid (Lunas)</option>
                            <option value="rejected" <?= $filterStatus === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
        <div class="card-footer text-right">
            <a href="index.php" class="btn btn-secondary mr-2"><i class="fas fa-undo mr-1"></i> Reset</a>
            <button type="submit" class="btn btn-primary"><i class="fas fa-search mr-1"></i> Filter</button>
        </div>
    </form>
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
        
        <!-- Total Filtered summary alert -->
        <div class="alert alert-info py-2 px-3 mb-3 d-flex justify-content-between align-items-center">
            <span><i class="fas fa-info-circle mr-1"></i> Menampilkan hasil pencarian berdasarkan filter di atas.</span>
            <strong class="text-lg">Total Klaim: <?= formatRupiah($totalSum) ?></strong>
        </div>

        <table id="claimsTable" class="table table-bordered table-striped w-100" style="font-size: 13px;">
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
    $('.select2').select2({
        theme: 'bootstrap4',
        width: '100%'
    });
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
