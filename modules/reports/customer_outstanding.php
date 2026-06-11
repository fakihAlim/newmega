<?php
/**
 * Report - Customer Outstanding (Piutang)
 */
require_once __DIR__ . '/../../includes/auth.php';
requirePermission('report_customer_outstanding');

$pageTitle = 'Outstanding Customer (Piutang)';
$breadcrumbs = [
    ['label' => 'Laporan', 'url' => '#'],
    ['label' => 'Outstanding Customer']
];

$sql = "
    SELECT 
        inv.id, inv.invoice_no, inv.invoice_date, inv.total as inv_total, inv.status as inv_status,
        inv.termin_no, inv.termin_description,
        cust.company_name as customer_name,
        q.quotation_no,
        COALESCE(SUM(cp.amount), 0) as total_received
    FROM invoices inv
    JOIN customers cust ON inv.customer_id = cust.id
    JOIN quotations q ON inv.quotation_id = q.id
    LEFT JOIN customer_payments cp ON cp.invoice_id = inv.id
    WHERE inv.status NOT IN ('draft', 'rejected')
    GROUP BY inv.id
    ORDER BY (inv.total - COALESCE(SUM(cp.amount), 0)) DESC
";
$stmt = $pdo->query($sql);
$invoices = $stmt->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/report_print.php';
?>

<?php renderReportPrintHeader('Rekap Piutang Customer (Outstanding)'); ?>

<div class="card card-outline card-info">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title"><i class="fas fa-file-invoice-dollar mr-2"></i> Rekap Piutang Customer (Outstanding)</h3>
        <div class="ml-auto d-flex gap-2">
            <a href="export_excel.php?type=customer_outstanding" class="btn btn-success btn-sm"><i class="fas fa-file-excel mr-1"></i> Export Excel</a>
            <a href="export_csv.php?type=customer_outstanding" class="btn btn-info btn-sm ml-1"><i class="fas fa-file-csv mr-1"></i> Export CSV</a>
            <button class="btn btn-default btn-sm ml-1" onclick="window.print()"><i class="fas fa-print mr-1"></i> Cetak</button>
        </div>
    </div>
    <div class="card-body">
        <table id="reportTable" class="table table-bordered table-striped w-100" style="font-size: 13px;">
            <thead class="bg-light">
                <tr>
                    <th width="12%">No. Invoice</th>
                    <th width="10%">Tanggal</th>
                    <th width="18%">Customer</th>
                    <th width="8%">Termin</th>
                    <th width="10%" class="text-center">Status</th>
                    <th width="14%" class="text-right">Nilai Invoice</th>
                    <th width="14%" class="text-right">Diterima</th>
                    <th width="14%" class="text-right">Piutang</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $grandTotal = 0; $grandReceived = 0; $grandOutstanding = 0;
                foreach ($invoices as $inv): 
                    $outstanding = $inv['inv_total'] - $inv['total_received'];
                    $grandTotal += $inv['inv_total'];
                    $grandReceived += $inv['total_received'];
                    $grandOutstanding += $outstanding;
                    $rowClass = $outstanding <= 0 ? 'table-success' : '';
                ?>
                <tr class="<?= $rowClass ?>">
                    <td>
                        <a href="<?= APP_URL ?>/modules/sales/invoices/view.php?id=<?= $inv['id'] ?>" class="font-weight-bold text-info" target="_blank">
                            <?= sanitize($inv['invoice_no']) ?>
                        </a>
                    </td>
                    <td><?= date('d-m-Y', strtotime($inv['invoice_date'])) ?></td>
                    <td><?= sanitize($inv['customer_name']) ?></td>
                    <td>T<?= $inv['termin_no'] ?></td>
                    <td class="text-center"><?= getStatusBadge($inv['inv_status']) ?></td>
                    <td class="text-right"><?= formatRupiah($inv['inv_total']) ?></td>
                    <td class="text-right text-success"><?= formatRupiah($inv['total_received']) ?></td>
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
                    <td colspan="5" class="text-right">GRAND TOTAL:</td>
                    <td class="text-right"><?= formatRupiah($grandTotal) ?></td>
                    <td class="text-right text-success"><?= formatRupiah($grandReceived) ?></td>
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
