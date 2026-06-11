<?php
/**
 * Report - Project Expense (Pengeluaran per Proyek)
 * Includes PO expenditure + Claim Nota
 */
require_once __DIR__ . '/../../includes/auth.php';
requirePermission('report_project_expense');

$pageTitle = 'Laporan Pengeluaran Proyek';
$breadcrumbs = [
    ['label' => 'Laporan', 'url' => '#'],
    ['label' => 'Pengeluaran Proyek']
];

// Fetch all projects with their PO expenditure + Claim Nota
$sql = "
    SELECT 
        p.id, p.name, p.abbreviation, p.status, p.budget,
        (SELECT COUNT(*) FROM material_requests mr WHERE mr.project_id = p.id AND mr.status != 'draft') as total_mr,
        (SELECT COALESCE(SUM(po.total), 0) 
         FROM purchase_orders po 
         JOIN po_mr_links pml ON pml.po_id = po.id 
         JOIN material_requests mr ON pml.mr_id = mr.id 
         WHERE mr.project_id = p.id AND po.status NOT IN ('draft','cancelled','rejected')
        ) as total_po_value,
        (SELECT COALESCE(SUM(vp.amount), 0) 
         FROM vendor_payments vp 
         JOIN purchase_orders po ON vp.po_id = po.id
         JOIN po_mr_links pml ON pml.po_id = po.id
         JOIN material_requests mr ON pml.mr_id = mr.id
         WHERE mr.project_id = p.id
        ) as total_paid,
        (SELECT COALESCE(SUM(cn.subtotal), 0) 
         FROM claim_notas cn 
         WHERE cn.project_id = p.id AND cn.status = 'approved'
        ) as total_claim
    FROM projects p
    ORDER BY p.name
";
$stmt = $pdo->query($sql);
$projects = $stmt->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/report_print.php';
?>

<?php renderReportPrintHeader('Laporan Pengeluaran Proyek'); ?>

<div class="card card-outline card-primary">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title"><i class="fas fa-chart-bar mr-2"></i> Pengeluaran Per Proyek</h3>
        <div class="ml-auto d-flex gap-2">
            <a href="export_excel.php?type=project_expense" class="btn btn-success btn-sm"><i class="fas fa-file-excel mr-1"></i> Export Excel</a>
            <a href="export_csv.php?type=project_expense" class="btn btn-info btn-sm ml-1"><i class="fas fa-file-csv mr-1"></i> Export CSV</a>
            <button class="btn btn-default btn-sm ml-1" onclick="window.print()"><i class="fas fa-print mr-1"></i> Cetak</button>
        </div>
    </div>
    <div class="card-body">
        <table id="reportTable" class="table table-bordered table-striped w-100" style="font-size: 13px;">
            <thead class="bg-light">
                <tr>
                    <th width="20%">Nama Proyek</th>
                    <th width="8%" class="text-center">Status</th>
                    <th width="7%" class="text-center">MR</th>
                    <th width="13%" class="text-right">Budget (Rp)</th>
                    <th width="13%" class="text-right">Nilai PO (Rp)</th>
                    <th width="13%" class="text-right">Claim Nota (Rp)</th>
                    <th width="13%" class="text-right">Terbayar (Rp)</th>
                    <th width="8%" class="text-center">% Terpakai</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $grandBudget = 0; $grandPO = 0; $grandPaid = 0; $grandClaim = 0;
                foreach ($projects as $p): 
                    $totalExpense = $p['total_po_value'] + $p['total_claim'];
                    $pctUsed = $p['budget'] > 0 ? round(($totalExpense / $p['budget']) * 100, 1) : 0;
                    $pctClass = $pctUsed > 90 ? 'text-danger' : ($pctUsed > 70 ? 'text-warning' : 'text-success');
                    $grandBudget += $p['budget'];
                    $grandPO += $p['total_po_value'];
                    $grandClaim += $p['total_claim'];
                    $grandPaid += $p['total_paid'];
                ?>
                <tr>
                    <td><strong><?= sanitize($p['name']) ?></strong></td>
                    <td class="text-center"><?= getStatusBadge($p['status']) ?></td>
                    <td class="text-center"><?= $p['total_mr'] ?></td>
                    <td class="text-right"><?= formatRupiah($p['budget']) ?></td>
                    <td class="text-right font-weight-bold"><?= formatRupiah($p['total_po_value']) ?></td>
                    <td class="text-right text-info"><?= formatRupiah($p['total_claim']) ?></td>
                    <td class="text-right text-success"><?= formatRupiah($p['total_paid']) ?></td>
                    <td class="text-center font-weight-bold <?= $pctClass ?>">
                        <?= $pctUsed ?>%
                        <?php if ($pctUsed > 90): ?>
                            <i class="fas fa-exclamation-triangle ml-1"></i>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr class="font-weight-bold bg-light">
                    <td colspan="3" class="text-right">TOTAL:</td>
                    <td class="text-right"><?= formatRupiah($grandBudget) ?></td>
                    <td class="text-right"><?= formatRupiah($grandPO) ?></td>
                    <td class="text-right text-info"><?= formatRupiah($grandClaim) ?></td>
                    <td class="text-right text-success"><?= formatRupiah($grandPaid) ?></td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<?php renderReportPrintFooter(); ?>

<?php
$extraJS = <<<'JS'
<script>$(document).ready(function() { initDataTable('#reportTable'); });</script>
JS;
require_once __DIR__ . '/../../includes/footer.php';
?>