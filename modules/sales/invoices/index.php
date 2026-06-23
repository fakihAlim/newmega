<?php
/**
 * Sales - Invoice List
 */
require_once __DIR__ . '/../../../includes/auth.php';
requirePermission('invoice_list');

$pageTitle = 'Invoice';
$breadcrumbs = [
    ['label' => 'Sales', 'url' => '#'],
    ['label' => 'Invoice']
];

// Handle Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $id = $_POST['id'] ?? 0;
    
    // Check if status is draft
    $stmt = $pdo->prepare("SELECT status, quotation_id FROM invoices WHERE id = ?");
    $stmt->execute([$id]);
    $invData = $stmt->fetch();
    
    if ($invData && $invData['status'] === 'draft') {
        $stmtNo = $pdo->prepare("SELECT invoice_no FROM invoices WHERE id = ?");
        $stmtNo->execute([$id]);
        $invNo = $stmtNo->fetchColumn();
        
        $pdo->prepare("DELETE FROM invoice_items WHERE invoice_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM invoices WHERE id = ?")->execute([$id]);
        
        // Revert quotation status if no other invoices exist
        $pdo->prepare("UPDATE quotations SET status = 'approved' WHERE id = ? AND NOT EXISTS (SELECT 1 FROM invoices WHERE quotation_id = ?)")->execute([$invData['quotation_id'], $invData['quotation_id']]);
        
        logActivity('delete', 'invoice', "Menghapus Invoice: {$invNo}");
        
        setFlash('success', 'Invoice berhasil dihapus.');
    } else {
        setFlash('danger', 'Hanya invoice berstatus draft yang dapat dihapus.');
    }
    header('Location: ' . APP_URL . '/modules/sales/invoices/index.php');
    exit;
}

$user = getCurrentUser();

// Set default filters
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$customerId = $_GET['customer_id'] ?? '';
$status = $_GET['status'] ?? '';

// Fetch customers for filter dropdown
$customers = $pdo->query("SELECT id, company_name FROM customers ORDER BY company_name ASC")->fetchAll();

// Build conditions
$conditions = [];
$params = [];

if ($startDate) {
    $conditions[] = "inv.invoice_date >= ?";
    $params[] = $startDate;
}
if ($endDate) {
    $conditions[] = "inv.invoice_date <= ?";
    $params[] = $endDate;
}
if ($customerId) {
    $conditions[] = "inv.customer_id = ?";
    $params[] = $customerId;
}
if ($status) {
    $conditions[] = "inv.status = ?";
    $params[] = $status;
}

$whereClause = "";
if (!empty($conditions)) {
    $whereClause = "WHERE " . implode(" AND ", $conditions);
}

$sql = "
    SELECT inv.*, c.name as company_name, cust.company_name as customer_name, 
           q.quotation_no, u.full_name as creator_name
    FROM invoices inv
    JOIN companies c ON inv.company_id = c.id
    JOIN customers cust ON inv.customer_id = cust.id
    JOIN quotations q ON inv.quotation_id = q.id
    LEFT JOIN users u ON inv.created_by = u.id
    $whereClause
    ORDER BY inv.id DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$invoices = $stmt->fetchAll();

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
                    <label style="font-size:12px;">Customer</label>
                    <select name="customer_id" class="form-control form-control-sm select2">
                        <option value="">-- Semua Customer --</option>
                        <?php foreach ($customers as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= $customerId == $c['id'] ? 'selected' : '' ?>><?= sanitize($c['company_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 col-sm-6 mb-2">
                    <label style="font-size:12px;">Status</label>
                    <select name="status" class="form-control form-control-sm select2">
                        <option value="">-- Semua Status --</option>
                        <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>Draft</option>
                        <option value="approved" <?= $status === 'approved' ? 'selected' : '' ?>>Approved</option>
                        <option value="sent" <?= $status === 'sent' ? 'selected' : '' ?>>Sent</option>
                        <option value="partial_paid" <?= $status === 'partial_paid' ? 'selected' : '' ?>>Partial Paid</option>
                        <option value="paid" <?= $status === 'paid' ? 'selected' : '' ?>>Paid</option>
                        <option value="rejected" <?= $status === 'rejected' ? 'selected' : '' ?>>Rejected</option>
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
        <h3 class="card-title">Daftar Invoice</h3>
        <?php if (canAccess('invoice_create')): ?>
            <a href="<?= APP_URL ?>/modules/sales/invoices/create.php" class="btn btn-primary btn-sm ml-auto">
                <i class="fas fa-plus mr-1"></i> Buat Invoice Baru
            </a>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <table id="invTable" class="table table-bordered table-striped table-hover table-sm w-100" >
            <thead>
                <tr>
                    <th width="12%">No. Invoice</th>
                    <th width="10%">Tanggal</th>
                    <th width="12%">Ref. Quotation</th>
                    <th width="18%">Customer</th>
                    <th width="10%">Termin</th>
                    <th width="13%" class="text-right">Total</th>
                    <th width="10%">Status</th>
                    <th width="10%" class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($invoices as $inv): ?>
                <tr>
                    <td><strong><?= sanitize($inv['invoice_no']) ?></strong></td>
                    <td><?= date('d-m-Y', strtotime($inv['invoice_date'])) ?></td>
                    <td><span class="text-info"><?= sanitize($inv['quotation_no']) ?></span></td>
                    <td><?= sanitize($inv['customer_name']) ?></td>
                    <td>Termin <?= $inv['termin_no'] ?></td>
                    <td class="text-right"><?= formatRupiah($inv['total']) ?></td>
                    <td><?= getStatusBadge($inv['status']) ?></td>
                    <td class="text-center">
                        <div class="btn-group">
                            <a href="<?= APP_URL ?>/modules/sales/invoices/view.php?id=<?= $inv['id'] ?>" class="btn btn-info btn-sm" data-toggle="tooltip" title="Lihat Detail">
                                <i class="fas fa-eye"></i>
                            </a>
                            <?php if ($inv['status'] === 'draft'): ?>
                            <a href="<?= APP_URL ?>/modules/sales/invoices/edit.php?id=<?= $inv['id'] ?>" class="btn btn-warning btn-sm" data-toggle="tooltip" title="Ubah">
                                <i class="fas fa-edit text-white"></i>
                            </a>
                            <button type="button" class="btn btn-danger btn-sm action-btn" data-id="<?= $inv['id'] ?>" data-name="<?= sanitize($inv['invoice_no']) ?>" data-toggle="tooltip" title="Hapus">
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
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="formId">
</form>

<?php
$extraJS = <<<'JS'
<script>
$(document).ready(function() {
    initSelect2('.select2');
    initDataTable('#invTable');
    
    $('.action-btn').on('click', function() {
        const id = $(this).data('id');
        const name = $(this).data('name');
        
        confirmAction('Hapus Invoice?', `Apakah Anda yakin ingin menghapus invoice "${name}"?`, function() {
            $('#formId').val(id);
            $('#actionForm').submit();
        });
    });
});
</script>
JS;
require_once __DIR__ . '/../../../includes/footer.php';
?>
