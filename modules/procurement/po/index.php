<?php
/**
 * Procurement - Purchase Order List
 */
require_once __DIR__ . '/../../../includes/auth.php';
requirePermission('po_list');

$pageTitle = 'Purchase Order (PO)';
$breadcrumbs = [
    ['label' => 'Procurement', 'url' => '#'],
    ['label' => 'Purchase Order']
];

$user = getCurrentUser();

// Set default filters
$status = isset($_GET['status']) ? $_GET['status'] : 'pending';
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : (in_array($status, ['pending', '']) ? '' : date('Y-m-01'));
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : (in_array($status, ['pending', '']) ? '' : date('Y-m-d'));
$vendorId = $_GET['vendor_id'] ?? '';

// Fetch vendors for filter
$vendors = $pdo->query("SELECT id, company_name FROM vendors ORDER BY company_name ASC")->fetchAll();

$conditions = [];
$params = [];

if ($startDate) {
    $conditions[] = "po.po_date >= ?";
    $params[] = $startDate;
}
if ($endDate) {
    $conditions[] = "po.po_date <= ?";
    $params[] = $endDate;
}
if ($vendorId) {
    $conditions[] = "po.vendor_id = ?";
    $params[] = $vendorId;
}
if ($status) {
    $conditions[] = "po.status = ?";
    $params[] = $status;
}

$whereClause = "";
if (!empty($conditions)) {
    $whereClause = "WHERE " . implode(" AND ", $conditions);
}

// Fetch POs
$sql = "
    SELECT po.*, v.company_name as vendor_name, c.name as company_name, u.full_name as creator_name,
           (SELECT COUNT(*) FROM goods_receivings WHERE po_id = po.id) as gr_count
    FROM purchase_orders po
    JOIN vendors v ON po.vendor_id = v.id
    JOIN companies c ON po.company_id = c.id
    LEFT JOIN users u ON po.created_by = u.id
    $whereClause
    ORDER BY po.id DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

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
                    <label style="font-size:12px;">Vendor</label>
                    <select name="vendor_id" class="form-control form-control-sm select2">
                        <option value="">-- Semua Vendor --</option>
                        <?php foreach ($vendors as $v): ?>
                            <option value="<?= $v['id'] ?>" <?= $vendorId == $v['id'] ? 'selected' : '' ?>><?= sanitize($v['company_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 col-sm-6 mb-2">
                    <label style="font-size:12px;">Status</label>
                    <select name="status" class="form-control form-control-sm" style="height: 31px !important; padding-top: 0 !important; padding-bottom: 0 !important;">
                        <option value="">Semua Status</option>
                        <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>Draft</option>
                        <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="approved" <?= $status === 'approved' ? 'selected' : '' ?>>Approved</option>
                        <option value="partially_received" <?= $status === 'partially_received' ? 'selected' : '' ?>>Partially Received</option>
                        <option value="completed" <?= $status === 'completed' ? 'selected' : '' ?>>Completed</option>
                        <option value="rejected" <?= $status === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                        <option value="cancelled" <?= $status === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
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
        <h3 class="card-title">Daftar Purchase Order</h3>
        <?php if (canAccess('po_create')): ?>
            <a href="<?= APP_URL ?>/modules/procurement/po/create.php" class="btn btn-primary btn-sm ml-auto">
                <i class="fas fa-plus mr-1"></i> Buat PO Baru
            </a>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <table id="poTable" class="table table-bordered table-striped table-hover table-sm w-100" >
            <thead>
                <tr>
                    <th width="12%">No. PO</th>
                    <th width="10%">Tanggal</th>
                    <th width="20%">Vendor</th>
                    <th width="18%">Perusahaan (Header)</th>
                    <th width="15%" class="text-right">Grand Total</th>
                    <th width="12%">Status</th>
                    <th width="13%" class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $o): ?>
                <tr>
                    <td>
                        <?php
                        $colorClass = 'text-dark';
                        if ($o['status'] === 'approved' || $o['status'] === 'completed') {
                            $colorClass = 'text-success';
                        } elseif ($o['status'] === 'pending') {
                            $colorClass = 'text-warning';
                        } elseif ($o['status'] === 'rejected') {
                            $colorClass = 'text-danger';
                        } elseif ($o['status'] === 'draft') {
                            $colorClass = 'text-secondary';
                        }
                        ?>
                        <strong class="<?= $colorClass ?>" <?= $o['status'] === 'pending' ? 'style="color: #d97706 !important;"' : '' ?>>
                            <?= sanitize($o['po_number']) ?>
                        </strong>
                    </td>
                    <td><?= date('d-m-Y', strtotime($o['po_date'])) ?></td>
                    <td><?= sanitize($o['vendor_name']) ?></td>
                    <td><?= sanitize($o['company_name']) ?></td>
                    <td class="text-right"><?= formatRupiah($o['total']) ?></td>
                    <td>
                        <?= getStatusBadge($o['status']) ?>
                    </td>
                    <td class="text-center">
                        <a href="<?= APP_URL ?>/modules/procurement/po/view.php?id=<?= $o['id'] ?>" class="btn btn-info btn-sm" data-toggle="tooltip" title="Lihat Detail">
                            <i class="fas fa-eye"></i>
                        </a>
                        
                        <?php 
                        $canEdit = (in_array($o['status'], ['draft', 'pending']) && ($user['id'] == $o['created_by'] || canAccess('purchase_order', 'edit')));
                        if (!$canEdit && $o['status'] === 'approved' && hasRole('super_admin') && $o['gr_count'] == 0) {
                            $canEdit = true;
                        }
                        if ($canEdit): 
                        ?>
                        <a href="<?= APP_URL ?>/modules/procurement/po/edit.php?id=<?= $o['id'] ?>" class="btn btn-warning btn-sm" data-toggle="tooltip" title="Ubah">
                            <i class="fas fa-edit text-white"></i>
                        </a>
                        <button type="button" class="btn btn-danger btn-sm btn-delete-po" data-id="<?= $o['id'] ?>" data-number="<?= sanitize($o['po_number']) ?>" data-toggle="tooltip" title="Hapus">
                            <i class="fas fa-trash"></i>
                        </button>
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
    initDataTable('#poTable', {
        columnDefs: [
            { responsivePriority: 1, targets: 0 }, // No. PO (Selalu tampil di HP)
            { responsivePriority: 2, targets: 5 }, // Status (Selalu tampil di HP)
            { responsivePriority: 3, targets: 6 }, // Aksi (Selalu tampil di HP)
            { responsivePriority: 4, targets: 4 }  // Grand Total
        ]
    });
    
    // Delete PO handler
    $('.btn-delete-po').on('click', function() {
        var poId = $(this).data('id');
        var poNumber = $(this).data('number');
        confirmAction(
            'Hapus PO ' + poNumber + '?',
            'Data PO akan dihapus permanen dan qty MR yang terkait akan dikembalikan. Tindakan ini tidak dapat dibatalkan.',
            function() {
                window.location.href = APP_URL + '/modules/procurement/po/delete.php?id=' + poId;
            }
        );
    });
});
</script>
JS;
require_once __DIR__ . '/../../../includes/footer.php';
?>
