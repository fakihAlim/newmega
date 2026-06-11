<?php
/**
 * Finance - Claim Nota List
 */
require_once __DIR__ . '/../../../includes/auth.php';
requirePermission('claim_nota');

$pageTitle = 'Claim Nota';
$breadcrumbs = [
    ['label' => 'Finance', 'url' => '#'],
    ['label' => 'Claim Nota']
];

$user = getCurrentUser();

// Fetch all claim notas with related data
$sql = "
    SELECT cn.*, 
           p.name as project_name, p.abbreviation as project_abbr,
           c.name as company_name,
           u.full_name as claimer_name,
           ua.full_name as approver_name,
           (SELECT COUNT(*) FROM claim_nota_items ci WHERE ci.claim_id = cn.id) as item_count
    FROM claim_notas cn
    JOIN projects p ON cn.project_id = p.id
    JOIN companies c ON cn.company_id = c.id
    LEFT JOIN users u ON cn.claimed_by = u.id
    LEFT JOIN users ua ON cn.approved_by = ua.id
    ORDER BY cn.id DESC
";
$stmt = $pdo->query($sql);
$claims = $stmt->fetchAll();

require_once __DIR__ . '/../../../includes/header.php';
?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title"><i class="fas fa-receipt mr-2"></i> Daftar Claim Nota</h3>
        <?php if (canAccess('claim_nota', 'create')): ?>
            <a href="<?= APP_URL ?>/modules/finance/claim_nota/create.php" class="btn btn-primary btn-sm ml-auto">
                <i class="fas fa-plus mr-1"></i> Buat Claim Baru
            </a>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <table id="claimTable" class="table table-bordered table-striped w-100" style="font-size: 13px;">
            <thead>
                <tr>
                    <th width="12%">No. Claim</th>
                    <th width="9%">Tanggal</th>
                    <th width="14%">Karyawan</th>
                    <th width="15%">Proyek</th>
                    <th width="12%">Perusahaan</th>
                    <th width="10%">Toko</th>
                    <th width="10%" class="text-right">Total (Rp)</th>
                    <th width="8%" class="text-center">Status</th>
                    <th width="10%" class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($claims as $cl): ?>
                <tr>
                    <td>
                        <a href="view.php?id=<?= $cl['id'] ?>" class="text-primary font-weight-bold">
                            <?= sanitize($cl['claim_number']) ?>
                        </a>
                    </td>
                    <td><?= date('d-m-Y', strtotime($cl['claim_date'])) ?></td>
                    <td><?= sanitize($cl['employee_name']) ?></td>
                    <td>
                        <small class="badge badge-light"><?= sanitize($cl['project_abbr']) ?></small>
                        <?= sanitize($cl['project_name']) ?>
                    </td>
                    <td><?= sanitize($cl['company_name']) ?></td>
                    <td><?= sanitize($cl['store_name']) ?: '-' ?></td>
                    <td class="text-right font-weight-bold"><?= formatRupiah($cl['subtotal']) ?></td>
                    <td class="text-center">
                        <?php if ($cl['is_reimbursed']): ?>
                            <?= getStatusBadge('reimbursed') ?>
                        <?php else: ?>
                            <?= getStatusBadge($cl['status']) ?>
                        <?php endif; ?>
                    </td>
                    <td class="text-center">
                        <a href="view.php?id=<?= $cl['id'] ?>" class="btn btn-info btn-xs" data-toggle="tooltip" title="Lihat Detail">
                            <i class="fas fa-eye"></i>
                        </a>
                        <?php if ($cl['status'] === 'draft'): ?>
                            <?php if (canAccess('claim_nota', 'edit')): ?>
                            <a href="edit.php?id=<?= $cl['id'] ?>" class="btn btn-warning btn-xs" data-toggle="tooltip" title="Edit">
                                <i class="fas fa-edit"></i>
                            </a>
                            <?php endif; ?>
                            <?php if (canAccess('claim_nota', 'delete')): ?>
                            <a href="delete.php?id=<?= $cl['id'] ?>" class="btn btn-danger btn-xs btn-delete" data-toggle="tooltip" title="Hapus"
                               onclick="return confirm('Yakin hapus claim ini?')">
                                <i class="fas fa-trash"></i>
                            </a>
                            <?php endif; ?>
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
    initDataTable('#claimTable');
});
</script>
JS;
require_once __DIR__ . '/../../../includes/footer.php';
?>
