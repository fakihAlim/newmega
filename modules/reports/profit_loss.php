<?php
/**
 * Report - Profit & Loss (Ringkasan Laba Rugi)
 */
require_once __DIR__ . '/../../includes/auth.php';
requirePermission('report_profit_loss');

$pageTitle = 'Laporan Profit & Loss';
$breadcrumbs = [
    ['label' => 'Laporan', 'url' => '#'],
    ['label' => 'Profit & Loss']
];

$filterYear = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$filterMonth = (isset($_GET['month']) && is_numeric($_GET['month'])) ? (int)$_GET['month'] : '';

$monthCondition = '';
$monthConditionInv = '';
$monthConditionVP = '';
$monthConditionCP = '';

if ($filterMonth) {
    $monthCondition = " AND MONTH(po.po_date) = $filterMonth AND YEAR(po.po_date) = $filterYear";
    $monthConditionInv = " AND MONTH(inv.invoice_date) = $filterMonth AND YEAR(inv.invoice_date) = $filterYear";
    $monthConditionVP = " AND MONTH(vp.payment_date) = $filterMonth AND YEAR(vp.payment_date) = $filterYear";
    $monthConditionCP = " AND MONTH(cp.payment_date) = $filterMonth AND YEAR(cp.payment_date) = $filterYear";
} else {
    $monthCondition = " AND YEAR(po.po_date) = $filterYear";
    $monthConditionInv = " AND YEAR(inv.invoice_date) = $filterYear";
    $monthConditionVP = " AND YEAR(vp.payment_date) = $filterYear";
    $monthConditionCP = " AND YEAR(cp.payment_date) = $filterYear";
}

$stmtRevenue = $pdo->query("SELECT COALESCE(SUM(inv.total), 0) as total_revenue FROM invoices inv WHERE inv.status NOT IN ('draft', 'rejected') $monthConditionInv");
$totalRevenue = $stmtRevenue->fetch()['total_revenue'];

$stmtCOGS = $pdo->query("SELECT COALESCE(SUM(po.total), 0) as total_cogs FROM purchase_orders po WHERE po.status NOT IN ('draft', 'cancelled', 'rejected') $monthCondition");
$totalCOGS = $stmtCOGS->fetch()['total_cogs'];

$stmtCashIn = $pdo->query("SELECT COALESCE(SUM(cp.amount), 0) as total_cash_in FROM customer_payments cp WHERE 1=1 $monthConditionCP");
$totalCashIn = $stmtCashIn->fetch()['total_cash_in'];

$stmtCashOut = $pdo->query("SELECT COALESCE(SUM(vp.amount), 0) as total_cash_out FROM vendor_payments vp WHERE 1=1 $monthConditionVP");
$totalCashOut = $stmtCashOut->fetch()['total_cash_out'];

$grossProfit = $totalRevenue - $totalCOGS;
$netCashFlow = $totalCashIn - $totalCashOut;

$monthNames = [1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'];
$periodText = 'Periode: ' . ($filterMonth ? $monthNames[$filterMonth] . ' ' : 'Tahun ') . $filterYear;

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/report_print.php';
?>

<?php renderReportPrintHeader('Laporan Laba Rugi (Profit & Loss)', $periodText); ?>

<!-- Filter -->
<div class="card card-outline card-secondary mb-3 d-print-none">
    <div class="card-body py-3">
        <form class="form-inline" method="GET">
            <label class="mr-2">Tahun:</label>
            <select name="year" class="form-control form-control-sm mr-3">
                <?php for ($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
                    <option value="<?= $y ?>" <?= $filterYear == $y ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>
            <label class="mr-2">Bulan:</label>
            <select name="month" class="form-control form-control-sm mr-3">
                <option value="">-- Semua Bulan --</option>
                <?php foreach ($monthNames as $num => $name): ?>
                    <option value="<?= $num ?>" <?= $filterMonth == $num ? 'selected' : '' ?>><?= $name ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter mr-1"></i> Filter</button>
        </form>
    </div>
</div>

<!-- Summary Cards -->
<div class="row">
    <div class="col-md-3">
        <div class="small-box bg-info">
            <div class="inner">
                <h4><?= formatRupiah($totalRevenue) ?></h4>
                <p>Pendapatan (Invoice)</p>
            </div>
            <div class="icon"><i class="fas fa-chart-line"></i></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="small-box bg-danger">
            <div class="inner">
                <h4><?= formatRupiah($totalCOGS) ?></h4>
                <p>Pengeluaran (PO)</p>
            </div>
            <div class="icon"><i class="fas fa-shopping-cart"></i></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="small-box <?= $grossProfit >= 0 ? 'bg-success' : 'bg-warning' ?>">
            <div class="inner">
                <h4><?= formatRupiah($grossProfit) ?></h4>
                <p>Gross Profit (Laba Kotor)</p>
            </div>
            <div class="icon"><i class="fas fa-balance-scale"></i></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="small-box <?= $netCashFlow >= 0 ? 'bg-success' : 'bg-warning' ?>">
            <div class="inner">
                <h4><?= formatRupiah($netCashFlow) ?></h4>
                <p>Net Cash Flow</p>
            </div>
            <div class="icon"><i class="fas fa-wallet"></i></div>
        </div>
    </div>
</div>

<!-- Detailed Table -->
<div class="card card-outline card-success">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title"><i class="fas fa-balance-scale mr-2"></i> Ringkasan Laba Rugi — <?= $periodText ?></h3>
        <div class="ml-auto d-flex gap-2">
            <a href="export_excel.php?type=profit_loss&year=<?= $filterYear ?>&month=<?= $filterMonth ?>" class="btn btn-success btn-sm"><i class="fas fa-file-excel mr-1"></i> Export Excel</a>
            <a href="export_csv.php?type=profit_loss&year=<?= $filterYear ?>&month=<?= $filterMonth ?>" class="btn btn-info btn-sm ml-1"><i class="fas fa-file-csv mr-1"></i> Export CSV</a>
            <button class="btn btn-default btn-sm ml-1" onclick="window.print()"><i class="fas fa-print mr-1"></i> Cetak</button>
        </div>
    </div>
    <div class="card-body">
        <table class="table table-bordered" style="font-size:14px;">
            <thead class="bg-light">
                <tr>
                    <th width="60%">Keterangan</th>
                    <th width="40%" class="text-right">Jumlah (Rp)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="pl-4"><i class="fas fa-arrow-down text-info mr-2"></i> <strong>Pendapatan (Invoice Terbit)</strong></td>
                    <td class="text-right font-weight-bold text-info" style="font-size:16px;"><?= formatRupiah($totalRevenue) ?></td>
                </tr>
                <tr>
                    <td class="pl-4"><i class="fas fa-arrow-up text-danger mr-2"></i> <strong>Harga Pokok / Biaya Pengadaan (PO)</strong></td>
                    <td class="text-right font-weight-bold text-danger" style="font-size:16px;">(<?= formatRupiah($totalCOGS) ?>)</td>
                </tr>
                <tr class="<?= $grossProfit >= 0 ? 'table-success' : 'table-danger' ?>">
                    <td class="pl-3 font-weight-bold" style="font-size:16px;">LABA KOTOR (GROSS PROFIT)</td>
                    <td class="text-right font-weight-bold" style="font-size:18px;"><?= formatRupiah($grossProfit) ?></td>
                </tr>
            </tbody>
        </table>
        
        <h5 class="mt-4 mb-3 text-secondary">Arus Kas (Cash Flow)</h5>
        <table class="table table-bordered" style="font-size:14px;">
            <tbody>
                <tr>
                    <td width="60%" class="pl-4"><i class="fas fa-hand-holding-usd text-success mr-2"></i> <strong>Cash In (Penerimaan dari Customer)</strong></td>
                    <td width="40%" class="text-right font-weight-bold text-success" style="font-size:16px;"><?= formatRupiah($totalCashIn) ?></td>
                </tr>
                <tr>
                    <td class="pl-4"><i class="fas fa-money-check-alt text-danger mr-2"></i> <strong>Cash Out (Pembayaran ke Vendor)</strong></td>
                    <td class="text-right font-weight-bold text-danger" style="font-size:16px;">(<?= formatRupiah($totalCashOut) ?>)</td>
                </tr>
                <tr class="<?= $netCashFlow >= 0 ? 'table-success' : 'table-danger' ?>">
                    <td class="pl-3 font-weight-bold" style="font-size:16px;">NET CASH FLOW</td>
                    <td class="text-right font-weight-bold" style="font-size:18px;"><?= formatRupiah($netCashFlow) ?></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<?php renderReportPrintFooter(); ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
