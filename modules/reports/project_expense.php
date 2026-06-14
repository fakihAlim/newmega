<?php
/**
 * Report - Project Expense (Pengeluaran per Proyek)
 */
require_once __DIR__ . '/../../includes/auth.php';
requirePermission('report_project_expense');

$pageTitle = 'Laporan Pengeluaran Proyek';
$breadcrumbs = [
    ['label' => 'Laporan', 'url' => '#'],
    ['label' => 'Pengeluaran Proyek']
];

// Fetch all projects with their PO expenditure
$sql = "
    SELECT 
        p.id, p.name, p.abbreviation, p.status, p.budget,
        COALESCE(SUM(DISTINCT po_totals.po_total), 0) as total_po,
        COALESCE(SUM(DISTINCT vp_totals.paid_total), 0) as total_paid
    FROM projects p
    LEFT JOIN (
        SELECT pml.mr_id, po.id as po_id, po.total as po_total
        FROM purchase_orders po
        JOIN po_mr_links pml ON pml.po_id = po.id
        WHERE po.status NOT IN ('draft', 'cancelled', 'rejected')
    ) po_sub ON po_sub.mr_id IN (
        SELECT mr.id FROM material_requests mr WHERE mr.project_id = p.id
    )
    LEFT JOIN (
        SELECT po_id, total as po_total FROM purchase_orders WHERE status NOT IN ('draft','cancelled','rejected')
    ) po_totals ON po_totals.po_id = po_sub.po_id
    LEFT JOIN (
        SELECT po_id, SUM(amount) as paid_total FROM vendor_payments GROUP BY po_id
    ) vp_totals ON vp_totals.po_id = po_sub.po_id
    GROUP BY p.id
    ORDER BY p.name
";

// Simpler approach: get project-level PO spending via MR links
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
        ) as total_paid
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
                    <th width="25%">Nama Proyek</th>
                    <th width="10%" class="text-center">Status</th>
                    <th width="10%" class="text-center">Jumlah MR</th>
                    <th width="15%" class="text-right">Budget (Rp)</th>
                    <th width="15%" class="text-right">Nilai PO (Rp)</th>
                    <th width="15%" class="text-right">Terbayar (Rp)</th>
                    <th width="10%" class="text-center">% Terpakai</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $grandBudget = 0; $grandPO = 0; $grandPaid = 0;
                foreach ($projects as $p): 
                    $pctUsed = $p['budget'] > 0 ? round(($p['total_po_value'] / $p['budget']) * 100, 1) : 0;
                    $pctClass = $pctUsed > 90 ? 'text-danger' : ($pctUsed > 70 ? 'text-warning' : 'text-success');
                    $grandBudget += $p['budget'];
                    $grandPO += $p['total_po_value'];
                    $grandPaid += $p['total_paid'];
                ?>
                <tr>
                    <td><strong><?= sanitize($p['name']) ?></strong></td>
                    <td class="text-center"><?= getStatusBadge($p['status']) ?></td>
                    <td class="text-center"><?= $p['total_mr'] ?></td>
                    <td class="text-right"><?= formatRupiah($p['budget']) ?></td>
                    <td class="text-right font-weight-bold"><?= formatRupiah($p['total_po_value']) ?></td>
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