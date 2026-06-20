<?php
/**
 * Procurement - Goods Receiving List
 */
require_once __DIR__ . '/../../../includes/auth.php';
requirePermission('receiving_list');

$pageTitle = 'Penerimaan Barang (Goods Receipt)';
$breadcrumbs = [
    ['label' => 'Procurement', 'url' => '#'],
    ['label' => 'Penerimaan Barang']
];

$user = getCurrentUser();

// Set default filters
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$vendorId = $_GET['vendor_id'] ?? '';
$location = $_GET['location'] ?? ''; // 'warehouse' or 'project'

// Fetch vendors for filter
$vendors = $pdo->query("SELECT id, company_name FROM vendors ORDER BY company_name ASC")->fetchAll();

$conditions = [];
$params = [];

if ($startDate) {
    $conditions[] = "gr.receive_date >= ?";
    $params[] = $startDate;
}
if ($endDate) {
    $conditions[] = "gr.receive_date <= ?";
    $params[] = $endDate;
}
if ($vendorId) {
    $conditions[] = "po.vendor_id = ?";
    $params[] = $vendorId;
}
if ($location) {
    if ($location === 'warehouse') {
        $conditions[] = "gr.received_at = 'warehouse'";
    } elseif ($location === 'project') {
        $conditions[] = "gr.received_at != 'warehouse'";
    }
}

$whereClause = "";
if (!empty($conditions)) {
    $whereClause = "WHERE " . implode(" AND ", $conditions);
}

// Fetch Goods Receivings
$sql = "
    SELECT gr.*, po.po_number, v.company_name as vendor_name, u.full_name as receiver_name, p.name as project_name
    FROM goods_receivings gr
    JOIN purchase_orders po ON gr.po_id = po.id
    JOIN vendors v ON po.vendor_id = v.id
    LEFT JOIN users u ON gr.received_by = u.id
    LEFT JOIN projects p ON gr.project_id = p.id
    $whereClause
    ORDER BY gr.id DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$receivings = $stmt->fetchAll();

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
                    <label style="font-size:12px;">Vendor</label>
                    <select name="vendor_id" class="form-control form-control-sm select2">
                        <option value="">-- Semua Vendor --</option>
                        <?php foreach ($vendors as $v): ?>
                            <option value="<?= $v['id'] ?>" <?= $vendorId == $v['id'] ? 'selected' : '' ?>><?= sanitize($v['company_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 col-sm-6 mb-2">
                    <label style="font-size:12px;">Lokasi Terima</label>
                    <select name="location" class="form-control form-control-sm select2">
                        <option value="">-- Semua Lokasi --</option>
                        <option value="warehouse" <?= $location === 'warehouse' ? 'selected' : '' ?>>Gudang Utama</option>
                        <option value="project" <?= $location === 'project' ? 'selected' : '' ?>>Proyek</option>
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
        <h3 class="card-title">Daftar Penerimaan Barang</h3>
        <?php if (canAccess('receiving_list')): // Assuming if they can list, they can create for now, or add specific permission ?>
            <a href="<?= APP_URL ?>/modules/procurement/receiving/create.php" class="btn btn-primary btn-sm ml-auto">
                <i class="fas fa-plus mr-1"></i> Terima Barang Baru
            </a>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <table id="grTable" class="table table-bordered table-striped table-hover table-sm w-100" >
            <thead>
                <tr>
                    <th width="12%">Tgl Terima</th>
                    <th width="15%">No. Surat Jalan</th>
                    <th width="15%">No. PO Ref.</th>
                    <th width="20%">Vendor</th>
                    <th width="15%">Lokasi Terima</th>
                    <th width="13%">Penerima</th>
                    <th width="10%" class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($receivings as $gr): ?>
                <tr>
                    <td><?= date('d-m-Y', strtotime($gr['receive_date'])) ?></td>
                    <td><strong><?= sanitize($gr['surat_jalan_no']) ?></strong></td>
                    <td>
                        <a href="<?= APP_URL ?>/modules/procurement/po/view.php?id=<?= $gr['po_id'] ?>" target="_blank" class="text-info font-weight-bold">
                            <?= sanitize($gr['po_number']) ?>
                        </a>
                    </td>
                    <td><?= sanitize($gr['vendor_name']) ?></td>
                    <td>
                        <?php if ($gr['received_at'] === 'warehouse'): ?>
                            <span class="badge badge-primary">Gudang Utama</span>
                        <?php else: ?>
                            <span class="badge badge-success">Proyek</span><br>
                            <small class="text-muted"><?= sanitize($gr['project_name']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td><?= sanitize($gr['receiver_name']) ?></td>
                    <td class="text-center">
                        <a href="<?= APP_URL ?>/modules/procurement/receiving/view.php?id=<?= $gr['id'] ?>" class="btn btn-info btn-sm" data-toggle="tooltip" title="Lihat Detail SJ">
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
    initDataTable('#grTable');
});
</script>
JS;
require_once __DIR__ . '/../../../includes/footer.php';
?>
