<?php
/**
 * Sales - Quotation List
 */
require_once __DIR__ . '/../../../includes/auth.php';
requirePermission('quotation_list');

$pageTitle = 'Quotation';
$breadcrumbs = [
    ['label' => 'Sales', 'url' => '#'],
    ['label' => 'Quotation']
];

// Handle Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $id = $_POST['id'] ?? 0;
    
    // Check if status is draft
    $stmt = $pdo->prepare("SELECT status FROM quotations WHERE id = ?");
    $stmt->execute([$id]);
    $status = $stmt->fetchColumn();
    
    if ($status === 'draft') {
        $pdo->prepare("DELETE FROM quotation_items WHERE quotation_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM quotations WHERE id = ?")->execute([$id]);
        setFlash('success', 'Quotation berhasil dihapus.');
    } else {
        setFlash('danger', 'Hanya quotation berstatus draft yang dapat dihapus.');
    }
    header('Location: ' . APP_URL . '/modules/sales/quotations/index.php');
    exit;
}

$user = getCurrentUser();

$sql = "
    SELECT q.*, c.name as company_name, cust.company_name as customer_name, 
           p.name as project_name, u.full_name as creator_name
    FROM quotations q
    JOIN companies c ON q.company_id = c.id
    JOIN customers cust ON q.customer_id = cust.id
    LEFT JOIN projects p ON q.project_id = p.id
    LEFT JOIN users u ON q.created_by = u.id
    ORDER BY q.id DESC
";
$stmt = $pdo->query($sql);
$quotations = $stmt->fetchAll();

require_once __DIR__ . '/../../../includes/header.php';
?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title">Daftar Quotation</h3>
        <?php if (canAccess('quotation_create')): ?>
            <a href="<?= APP_URL ?>/modules/sales/quotations/create.php" class="btn btn-primary btn-sm ml-auto">
                <i class="fas fa-plus mr-1"></i> Buat Quotation Baru
            </a>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <table id="qTable" class="table table-bordered table-striped w-100" style="font-size: 13px;">
            <thead>
                <tr>
                    <th width="12%">No. Quotation</th>
                    <th width="10%">Tanggal</th>
                    <th width="18%">Customer</th>
                    <th width="15%">Proyek</th>
                    <th width="15%" class="text-right">Grand Total</th>
                    <th width="10%">Berlaku s/d</th>
                    <th width="10%">Status</th>
                    <th width="10%" class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($quotations as $q): ?>
                <tr>
                    <td><strong><?= sanitize($q['quotation_no']) ?></strong></td>
                    <td><?= date('d-m-Y', strtotime($q['quotation_date'])) ?></td>
                    <td><?= sanitize($q['customer_name']) ?></td>
                    <td><?= sanitize($q['project_name']) ?: '-' ?></td>
                    <td class="text-right"><?= formatRupiah($q['total']) ?></td>
                    <td><?= $q['valid_until'] ? date('d-m-Y', strtotime($q['valid_until'])) : '-' ?></td>
                    <td><?= getStatusBadge($q['status']) ?></td>
                    <td class="text-center">
                        <div class="btn-group">
                            <a href="<?= APP_URL ?>/modules/sales/quotations/view.php?id=<?= $q['id'] ?>" class="btn btn-info btn-sm" data-toggle="tooltip" title="Lihat Detail">
                                <i class="fas fa-eye"></i>
                            </a>
                            <?php if ($q['status'] === 'draft'): ?>
                            <a href="<?= APP_URL ?>/modules/sales/quotations/edit.php?id=<?= $q['id'] ?>" class="btn btn-warning btn-sm" data-toggle="tooltip" title="Edit">
                                <i class="fas fa-edit text-white"></i>
                            </a>
                            <button type="button" class="btn btn-danger btn-sm action-btn" data-id="<?= $q['id'] ?>" data-name="<?= sanitize($q['quotation_no']) ?>" data-toggle="tooltip" title="Hapus">
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
    initDataTable('#qTable');
    
    $('.action-btn').on('click', function() {
        const id = $(this).data('id');
        const name = $(this).data('name');
        
        confirmAction('Hapus Quotation?', `Apakah Anda yakin ingin menghapus quotation "${name}"?`, function() {
            $('#formId').val(id);
            $('#actionForm').submit();
        });
    });
});
</script>
JS;
require_once __DIR__ . '/../../../includes/footer.php';
?>
