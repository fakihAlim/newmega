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
        $pdo->prepare("DELETE FROM invoice_items WHERE invoice_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM invoices WHERE id = ?")->execute([$id]);
        
        // Revert quotation status if no other invoices exist
        $pdo->prepare("UPDATE quotations SET status = 'approved' WHERE id = ? AND NOT EXISTS (SELECT 1 FROM invoices WHERE quotation_id = ?)")->execute([$invData['quotation_id'], $invData['quotation_id']]);
        
        setFlash('success', 'Invoice berhasil dihapus.');
    } else {
        setFlash('danger', 'Hanya invoice berstatus draft yang dapat dihapus.');
    }
    header('Location: ' . APP_URL . '/modules/sales/invoices/index.php');
    exit;
}

$user = getCurrentUser();

$sql = "
    SELECT inv.*, c.name as company_name, cust.company_name as customer_name, 
           q.quotation_no, u.full_name as creator_name
    FROM invoices inv
    JOIN companies c ON inv.company_id = c.id
    JOIN customers cust ON inv.customer_id = cust.id
    JOIN quotations q ON inv.quotation_id = q.id
    LEFT JOIN users u ON inv.created_by = u.id
    ORDER BY inv.id DESC
";
$stmt = $pdo->query($sql);
$invoices = $stmt->fetchAll();

require_once __DIR__ . '/../../../includes/header.php';
?>

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
        <table id="invTable" class="table table-bordered table-striped w-100" style="font-size: 13px;">
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
                            <a href="<?= APP_URL ?>/modules/sales/invoices/edit.php?id=<?= $inv['id'] ?>" class="btn btn-warning btn-sm" data-toggle="tooltip" title="Edit">
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
