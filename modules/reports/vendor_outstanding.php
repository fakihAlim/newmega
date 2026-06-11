<?php
/**
 * Report - Vendor Outstanding (Hutang ke Vendor)
 */
require_once __DIR__ . '/../../includes/auth.php';
requirePermission('report_vendor_outstanding');

$pageTitle = 'Outstanding Vendor (Hutang)';
$breadcrumbs = [
    ['label' => 'Laporan', 'url' => '#'],
    ['label' => 'Outstanding Vendor']
];

// Fetch all POs with outstanding balance
$sql = "
    SELECT 
        po.id, po.po_number, po.po_date, po.total as po_total, po.status as po_status,
        v.company_name as vendor_name,
        COALESCE(SUM(vp.amount), 0) as total_paid
    FROM purchase_orders po
    JOIN vendors v ON po.vendor_id = v.id
    LEFT JOIN vendor_payments vp ON vp.po_id = po.id
    WHERE po.status NOT IN ('draft', 'cancelled', 'rejected')
    GROUP BY po.id
    ORDER BY (po.total - COALESCE(SUM(vp.amount), 0)) DESC
";
$stmt = $pdo->query($sql);
$pos = $stmt->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/report_print.php';
?>

<?php renderReportPrintHeader('Rekap Hutang ke Vendor (Outstanding)'); ?>

<div class="card card-outline card-warning">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title"><i class="fas fa-file-invoice mr-2"></i> Rekap Hutang ke Vendor (Outstanding)</h3>
        <div class="ml-auto d-flex gap-2">
            <a href="export_excel.php?type=vendor_outstanding" class="btn btn-success btn-sm"><i class="fas fa-file-excel mr-1"></i> Export Excel</a>
            <a href="export_csv.php?type=vendor_outstanding" class="btn btn-info btn-sm ml-1"><i class="fas fa-file-csv mr-1"></i> Export CSV</a>
            <button class="btn btn-default btn-sm ml-1" onclick="window.print()"><i class="fas fa-print mr-1"></i> Cetak</button>
        </div>
    </div>
    <div class="card-body">
        <table id="reportTable" class="table table-bordered table-striped w-100" style="font-size: 13px;">
            <thead class="bg-light">
                <tr>
                    <th width="12%">No. PO</th>
                    <th width="10%">Tanggal</th>
                    <th width="20%">Vendor</th>
                    <th width="10%" class="text-center">Status PO</th>
                    <th width="15%" class="text-right">Nilai PO (Rp)</th>
                    <th width="15%" class="text-right">Terbayar (Rp)</th>
                    <th width="18%" class="text-right">Outstanding (Rp)</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $grandTotal = 0; $grandPaid = 0; $grandOutstanding = 0;
                foreach ($pos as $po): 
                    $outstanding = $po['po_total'] - $po['total_paid'];
                    $grandTotal += $po['po_total'];
                    $grandPaid += $po['total_paid'];
                    $grandOutstanding += $outstanding;
                    $rowClass = $outstanding > 0 ? '' : 'table-success';
                ?>
                <tr class="<?= $rowClass ?>">
                    <td>
                        <a href="<?= APP_URL ?>/modules/procurement/po/view.php?id=<?= $po['id'] ?>" class="font-weight-bold text-info" target="_blank">
                            <?= sanitize($po['po_number']) ?>
                        </a>
                    </td>
                    <td><?= date('d-m-Y', strtotime($po['po_date'])) ?></td>
                    <td><?= sanitize($po['vendor_name']) ?></td>
                    <td class="text-center"><?= getStatusBadge($po['po_status']) ?></td>
                    <td class="text-right"><?= formatRupiah($po['po_total']) ?></td>
                    <td class="text-right text-success"><?= formatRupiah($po['total_paid']) ?></td>
                    <td class="text-right font-weight-bold <?= $outstanding > 0 ? 'text-danger' : 'text-success' ?>" style="font-size:14px;">
                        <?= formatRupiah($outstanding) ?>
                        <?php if ($outstanding <= 0): ?>
                            <i class="fas fa-check-circle text-success ml-1"></i>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr class="font-weight-bold bg-light" style="font-size:14px;">
                    <td colspan="4" class="text-right">GRAND TOTAL:</td>
                    <td class="text-right"><?= formatRupiah($grandTotal) ?></td>
                    <td class="text-right text-success"><?= formatRupiah($grandPaid) ?></td>
                    <td class="text-right text-danger"><?= formatRupiah($grandOutstanding) ?></td>
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