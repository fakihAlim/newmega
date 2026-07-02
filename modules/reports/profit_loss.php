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
$filterCompany = $_GET['company_id'] ?? '';

$monthCondition = '';
$monthConditionInv = '';
$monthConditionVP = '';
$monthConditionCP = '';

$params = [];

if ($filterMonth) {
    $monthCondition = " AND MONTH(po.po_date) = ? AND YEAR(po.po_date) = ?";
    $monthConditionInv = " AND MONTH(inv.invoice_date) = ? AND YEAR(inv.invoice_date) = ?";
    $monthConditionVP = " AND MONTH(vp.payment_date) = ? AND YEAR(vp.payment_date) = ?";
    $monthConditionCP = " AND MONTH(cp.payment_date) = ? AND YEAR(cp.payment_date) = ?";
    
    $params[] = $filterMonth;
    $params[] = $filterYear;
} else {
    $monthCondition = " AND YEAR(po.po_date) = ?";
    $monthConditionInv = " AND YEAR(inv.invoice_date) = ?";
    $monthConditionVP = " AND YEAR(vp.payment_date) = ?";
    $monthConditionCP = " AND YEAR(cp.payment_date) = ?";
    
    $params[] = $filterYear;
}

$compConditionInv = "";
$compConditionPO = "";
$compConditionCP = "";
$compConditionVP = "";

if ($filterCompany) {
    $compConditionInv = " AND inv.company_id = " . (int)$filterCompany;
    $compConditionPO = " AND po.company_id = " . (int)$filterCompany;
    $compConditionCP = " AND inv.company_id = " . (int)$filterCompany;
    $compConditionVP = " AND po.company_id = " . (int)$filterCompany;
}

$sqlRevenue = "SELECT COALESCE(SUM(inv.total), 0) as total_revenue FROM invoices inv WHERE inv.status NOT IN ('draft', 'rejected') $monthConditionInv $compConditionInv";
$stmtRevenue = $pdo->prepare($sqlRevenue);
$stmtRevenue->execute($params);
$totalRevenue = $stmtRevenue->fetch()['total_revenue'];

$sqlCOGS = "SELECT COALESCE(SUM(po.total), 0) as total_cogs FROM purchase_orders po WHERE po.status NOT IN ('draft', 'cancelled', 'rejected') $monthCondition $compConditionPO";
$stmtCOGS = $pdo->prepare($sqlCOGS);
$stmtCOGS->execute($params);
$totalCOGS = $stmtCOGS->fetch()['total_cogs'];

$sqlCashIn = "SELECT COALESCE(SUM(cp.amount), 0) as total_cash_in FROM customer_payments cp JOIN invoices inv ON cp.invoice_id = inv.id WHERE 1=1 $monthConditionCP $compConditionCP";
$stmtCashIn = $pdo->prepare($sqlCashIn);
$stmtCashIn->execute($params);
$totalCashIn = $stmtCashIn->fetch()['total_cash_in'];

$sqlCashOut = "SELECT COALESCE(SUM(vp.amount), 0) as total_cash_out FROM vendor_payments vp JOIN purchase_orders po ON vp.po_id = po.id WHERE 1=1 $monthConditionVP $compConditionVP";
$stmtCashOut = $pdo->prepare($sqlCashOut);
$stmtCashOut->execute($params);
$totalCashOut = $stmtCashOut->fetch()['total_cash_out'];

$grossProfit = $totalRevenue - $totalCOGS;
$netCashFlow = $totalCashIn - $totalCashOut;

$monthNames = [1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'];
$periodText = 'Periode: ' . ($filterMonth ? $monthNames[$filterMonth] . ' ' : 'Tahun ') . $filterYear;

// Fetch all companies for filter dropdown
$companies = $pdo->query("SELECT id, name FROM companies ORDER BY name ASC")->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/report_print.php';
?>

<?php renderReportPrintHeader('Laporan Laba Rugi (Profit & Loss)', $periodText); ?>

<!-- Filter Card -->
<div class="card card-outline card-primary d-print-none mb-3">
    <div class="card-body p-3">
        <form method="GET" action="" class="row">
            <div class="col-md-3 col-sm-6 mb-2">
                <label style="font-size:12px;">Tahun</label>
                <select name="year" class="form-control form-control-sm select2">
                    <?php for ($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
                        <option value="<?= $y ?>" <?= $filterYear == $y ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-3 col-sm-6 mb-2">
                <label style="font-size:12px;">Bulan</label>
                <select name="month" class="form-control form-control-sm select2">
                    <option value="">-- Semua Bulan --</option>
                    <?php foreach ($monthNames as $num => $name): ?>
                        <option value="<?= $num ?>" <?= $filterMonth == $num ? 'selected' : '' ?>><?= $name ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4 col-sm-6 mb-2">
                <label style="font-size:12px;">Perusahaan</label>
                <select name="company_id" class="form-control form-control-sm select2">
                    <option value="">-- Semua Perusahaan --</option>
                    <?php foreach ($companies as $comp): ?>
                        <option value="<?= $comp['id'] ?>" <?= $filterCompany == $comp['id'] ? 'selected' : '' ?>>
                            <?= sanitize($comp['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 col-sm-12 d-flex align-items-end mb-2">
                <button type="submit" class="btn btn-primary btn-sm btn-block"><i class="fas fa-search mr-1"></i>Filter</button>
                <a href="profit_loss.php" class="btn btn-default btn-sm ml-2" title="Reset Filters"><i class="fas fa-sync-alt"></i></a>
            </div>
        </form>
    </div>
</div>

<!-- Detailed Table -->
<div class="printable-area p-4 bg-white">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="font-weight-bold m-0" style="font-size:24px;">Ringkasan Laba Rugi — <?= $periodText ?></h3>
        <div class="ml-auto d-flex gap-2 d-print-none">
            <a href="export_excel.php?<?= http_build_query(array_merge($_GET, ['type' => 'profit_loss'])) ?>" class="btn btn-success btn-sm"><i class="fas fa-file-excel mr-1"></i> Export Excel</a>
            <a href="export_csv.php?<?= http_build_query(array_merge($_GET, ['type' => 'profit_loss'])) ?>" class="btn btn-info btn-sm ml-1"><i class="fas fa-file-csv mr-1"></i> Export CSV</a>
            <button class="btn btn-default btn-sm ml-1" onclick="window.print()"><i class="fas fa-print mr-1"></i> Cetak</button>
        </div>
    </div>
    
    <table class="table table-bordered table-sm table-hover" style="color:#000000;">
        <thead class="bg-light">
            <tr>
                <th width="60%">Keterangan</th>
                <th width="40%" class="text-right">Jumlah (Rp)</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="pl-4"><strong>Pendapatan (Invoice Terbit)</strong></td>
                <td class="text-right font-weight-bold" style="font-size:16px;"><?= formatRupiah($totalRevenue) ?></td>
            </tr>
            <tr>
                <td class="pl-4"><strong>Harga Pokok / Biaya Pengadaan (PO)</strong></td>
                <td class="text-right font-weight-bold" style="font-size:16px;">(<?= formatRupiah($totalCOGS) ?>)</td>
            </tr>
            <tr class="<?= $grossProfit >= 0 ? 'table-success' : 'table-danger' ?>">
                <td class="pl-3 font-weight-bold" style="font-size:16px;">LABA KOTOR (GROSS PROFIT)</td>
                <td class="text-right font-weight-bold" style="font-size:18px;"><?= formatRupiah($grossProfit) ?></td>
            </tr>
        </tbody>
    </table>
    
    <h5 class="mt-4 mb-3 text-secondary font-weight-bold" style="font-size:18px;">Arus Kas (Cash Flow)</h5>
    <table class="table table-bordered table-sm table-hover" style="color:#000000;">
        <tbody>
            <tr>
                <td width="60%" class="pl-4"><strong>Cash In (Penerimaan dari Customer)</strong></td>
                <td width="40%" class="text-right font-weight-bold" style="font-size:16px;"><?= formatRupiah($totalCashIn) ?></td>
            </tr>
            <tr>
                <td class="pl-4"><strong>Cash Out (Pembayaran ke Vendor)</strong></td>
                <td class="text-right font-weight-bold">(<?= formatRupiah($totalCashOut) ?>)</td>
            </tr>
            <tr class="<?= $netCashFlow >= 0 ? 'table-success' : 'table-danger' ?>">
                <td class="pl-3 font-weight-bold" style="font-size:16px;">NET CASH FLOW</td>
                <td class="text-right font-weight-bold" style="font-size:18px;"><?= formatRupiah($netCashFlow) ?></td>
            </tr>
        </tbody>
    </table>
</div>

<?php renderReportPrintFooter(); ?>

<?php
$extraJS = <<<'JS'
<script>
$(document).ready(function() {
    initSelect2('.select2');
});
</script>
JS;
require_once __DIR__ . '/../../includes/footer.php';
?>
