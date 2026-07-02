<?php
/**
 * Warehouse - Goods Transfer List
 */
require_once __DIR__ . '/../../../includes/auth.php';
requirePermission('transfer_list');

$pageTitle = 'Transfer Barang (Gudang ke Proyek)';
$breadcrumbs = [
    ['label' => 'Warehouse', 'url' => '#'],
    ['label' => 'Transfer Barang']
];

$user = getCurrentUser();

// Set default filters
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$projectId = $_GET['project_id'] ?? '';
$status = $_GET['status'] ?? '';

// Fetch projects for filter
$projects = $pdo->query("SELECT id, name FROM projects ORDER BY name ASC")->fetchAll();

$conditions = [];
$params = [];

if ($startDate) {
    $conditions[] = "wt.transfer_date >= ?";
    $params[] = $startDate;
}
if ($endDate) {
    $conditions[] = "wt.transfer_date <= ?";
    $params[] = $endDate;
}
if ($projectId) {
    $conditions[] = "wt.to_project_id = ?";
    $params[] = $projectId;
}
if ($status) {
    $conditions[] = "wt.status = ?";
    $params[] = $status;
}

$whereClause = "";
if (!empty($conditions)) {
    $whereClause = "WHERE " . implode(" AND ", $conditions);
}

$sql = "
    SELECT wt.*, p.name as project_name, u.full_name as transfer_user
    FROM warehouse_transfers wt
    JOIN projects p ON wt.to_project_id = p.id
    LEFT JOIN users u ON wt.transferred_by = u.id
    $whereClause
    ORDER BY wt.id DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$transfers = $stmt->fetchAll();

require_once __DIR__ . '/../../../includes/header.php';
?>

<!-- Filter Card -->
<div class="card card-outline card-primary d-print-none mb-3">
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
                    <label style="font-size:12px;">Proyek Tujuan</label>
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
                        <option value="in_transit" <?= $status === 'in_transit' ? 'selected' : '' ?>>Dalam Perjalanan</option>
                        <option value="received" <?= $status === 'received' ? 'selected' : '' ?>>Diterima Proyek</option>
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
        <h3 class="card-title">Histori Transfer Barang</h3>
        <?php if (canAccess('transfer_list')): // Create implicitly given via list for now?>
            <a href="<?= APP_URL ?>/modules/warehouse/transfer/create.php" class="btn btn-primary btn-sm ml-auto">
                <i class="fas fa-truck-loading mr-1"></i> Buat Surat Jalan Keluar
            </a>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <table id="transferTable" class="table table-bordered table-striped table-hover table-sm w-100" >
            <thead>
                <tr>
                    <th width="15%">No. Transfer</th>
                    <th width="15%">Tanggal</th>
                    <th width="30%">Proyek Tujuan</th>
                    <th width="15%">Petugas (Admin)</th>
                    <th width="15%" class="text-center">Status</th>
                    <th width="10%" class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($transfers as $tr): ?>
                <tr>
                    <td><strong><?= sanitize($tr['transfer_number']) ?></strong></td>
                    <td><?= date('d-m-Y', strtotime($tr['transfer_date'])) ?></td>
                    <td><?= sanitize($tr['project_name']) ?></td>
                    <td><?= sanitize($tr['transfer_user']) ?></td>
                    <td class="text-center">
                        <?= getStatusBadge($tr['status']) ?>
                    </td>
                    <td class="text-center">
                        <a href="<?= APP_URL ?>/modules/warehouse/transfer/view.php?id=<?= $tr['id'] ?>" class="btn btn-info btn-sm" data-toggle="tooltip" title="Lihat/Selesaikan">
                            <i class="fas fa-eye"></i>
                        </a>
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
    initDataTable('#transferTable');
});
</script>
JS;
require_once __DIR__ . '/../../../includes/footer.php';
?>
