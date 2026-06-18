<?php
/**
 * Finance - General Ledger (Buku Kas & Buku Besar)
 */
require_once __DIR__ . '/../../../includes/auth.php';
requirePermission('ledger');

$pageTitle = 'Buku Kas (General Ledger)';
$breadcrumbs = [
    ['label' => 'Finance', 'url' => '#'],
    ['label' => 'Buku Kas']
];

// Set default filters
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$companyId = $_GET['company_id'] ?? '';
$paymentMethod = $_GET['payment_method'] ?? '';

// Fetch companies for filter
$companies = $pdo->query("SELECT id, name FROM companies ORDER BY name ASC")->fetchAll();

// Get unique payment methods from customer & vendor payments
$methodsStmt = $pdo->query("
    SELECT DISTINCT payment_method FROM (
        SELECT DISTINCT payment_method FROM customer_payments WHERE payment_method IS NOT NULL AND payment_method != ''
        UNION
        SELECT DISTINCT payment_method FROM vendor_payments WHERE payment_method IS NOT NULL AND payment_method != ''
    ) AS methods ORDER BY payment_method ASC
");
$paymentMethods = $methodsStmt->fetchAll(PDO::FETCH_COLUMN);

// Calculate Saldo Awal (Opening Balance)
$openingSql = "
    SELECT COALESCE(SUM(debit), 0) - COALESCE(SUM(kredit), 0) AS saldo_awal FROM (
        -- Customer Payments (Debit)
        SELECT cp.payment_date AS tanggal, cp.amount AS debit, 0 AS kredit, cp.payment_method, inv.company_id
        FROM customer_payments cp
        JOIN invoices inv ON cp.invoice_id = inv.id
        
        UNION ALL
        
        -- Vendor Payments (Kredit)
        SELECT vp.payment_date AS tanggal, 0 AS debit, vp.amount AS kredit, vp.payment_method, po.company_id
        FROM vendor_payments vp
        JOIN purchase_orders po ON vp.po_id = po.id
        
        UNION ALL
        
        -- Claim Nota (Kredit)
        SELECT nc.claim_date AS tanggal, 0 AS debit, nc.total_amount AS kredit, 'Transfer/Cash' AS payment_method, nc.company_id
        FROM nota_claims nc
        WHERE nc.status = 'paid'
    ) AS temp
    WHERE tanggal < :start_date
      AND (:company_id_filter = '' OR company_id = :company_id_val)
      AND (:payment_method_filter = '' OR payment_method = :payment_method_val)
";

$openingStmt = $pdo->prepare($openingSql);
$openingStmt->execute([
    'start_date' => $startDate,
    'company_id_filter' => $companyId,
    'company_id_val' => $companyId,
    'payment_method_filter' => $paymentMethod,
    'payment_method_val' => $paymentMethod
]);
$openingBalance = (float)$openingStmt->fetchColumn();

// Fetch Chronological Mutasi Kas
$ledgerSql = "
    SELECT * FROM (
        -- Customer Payments (Debit)
        SELECT 
            cp.payment_date AS tanggal,
            cp.reference_no AS no_referensi,
            CONCAT('Penerimaan Invoice ', inv.invoice_no, ' - ', cust.company_name) AS keterangan,
            cp.amount AS debit,
            0 AS kredit,
            cp.payment_method AS metode,
            inv.company_id,
            c.name AS nama_perusahaan
        FROM customer_payments cp
        JOIN invoices inv ON cp.invoice_id = inv.id
        JOIN customers cust ON inv.customer_id = cust.id
        JOIN companies c ON inv.company_id = c.id
        
        UNION ALL
        
        -- Vendor Payments (Kredit)
        SELECT 
            vp.payment_date AS tanggal,
            vp.reference_no AS no_referensi,
            CONCAT('Pembayaran PO ', po.po_number, ' - ', v.company_name) AS keterangan,
            0 AS debit,
            vp.amount AS kredit,
            vp.payment_method AS metode,
            po.company_id,
            c.name AS nama_perusahaan
        FROM vendor_payments vp
        JOIN purchase_orders po ON vp.po_id = po.id
        JOIN vendors v ON po.vendor_id = v.id
        JOIN companies c ON po.company_id = c.id
        
        UNION ALL
        
        -- Claim Nota (Kredit)
        SELECT 
            nc.claim_date AS tanggal,
            nc.claim_number AS no_referensi,
            CONCAT('Reimburse Nota - Karyawan: ', COALESCE(u.full_name, nc.employee_name)) AS keterangan,
            0 AS debit,
            nc.total_amount AS kredit,
            'Transfer/Cash' AS metode,
            nc.company_id,
            c.name AS nama_perusahaan
        FROM nota_claims nc
        LEFT JOIN users u ON nc.employee_id = u.id
        JOIN companies c ON nc.company_id = c.id
        WHERE nc.status = 'paid'
    ) AS cashflow_ledger
    WHERE tanggal BETWEEN :start_date AND :end_date
      AND (:company_id_filter = '' OR company_id = :company_id_val)
      AND (:payment_method_filter = '' OR metode = :payment_method_val)
    ORDER BY tanggal ASC, no_referensi ASC
";

$ledgerStmt = $pdo->prepare($ledgerSql);
$ledgerStmt->execute([
    'start_date' => $startDate,
    'end_date' => $endDate,
    'company_id_filter' => $companyId,
    'company_id_val' => $companyId,
    'payment_method_filter' => $paymentMethod,
    'payment_method_val' => $paymentMethod
]);
$transactions = $ledgerStmt->fetchAll();

// Calculate total Debit and Credit for current period
$totalDebit = 0;
$totalKredit = 0;
foreach ($transactions as $t) {
    $totalDebit += (float)$t['debit'];
    $totalKredit += (float)$t['kredit'];
}
$endingBalance = $openingBalance + $totalDebit - $totalKredit;

require_once __DIR__ . '/../../../includes/header.php';
?>

<!-- Filter Card -->
<div class="card d-print-none mb-3">
    <div class="card-body p-3">
        <form method="GET" action="" class="form-horizontal">
            <div class="row">
                <div class="col-md-2 col-sm-6 mb-2">
                    <label style="font-size:12px;">Tanggal Mulai</label>
                    <input type="date" name="start_date" class="form-control form-control-sm" value="<?= $startDate ?>" required>
                </div>
                <div class="col-md-2 col-sm-6 mb-2">
                    <label style="font-size:12px;">Tanggal Selesai</label>
                    <input type="date" name="end_date" class="form-control form-control-sm" value="<?= $endDate ?>" required>
                </div>
                <div class="col-md-3 col-sm-6 mb-2">
                    <label style="font-size:12px;">Perusahaan (Pemberi Dana)</label>
                    <select name="company_id" class="form-control form-control-sm select2">
                        <option value="">-- Semua Perusahaan --</option>
                        <?php foreach ($companies as $comp): ?>
                            <option value="<?= $comp['id'] ?>" <?= $companyId == $comp['id'] ? 'selected' : '' ?>><?= sanitize($comp['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 col-sm-6 mb-2">
                    <label style="font-size:12px;">Metode Pembayaran</label>
                    <select name="payment_method" class="form-control form-control-sm select2">
                        <option value="">-- Semua Metode --</option>
                        <?php foreach ($paymentMethods as $m): ?>
                            <option value="<?= htmlspecialchars($m) ?>" <?= $paymentMethod == $m ? 'selected' : '' ?>><?= sanitize($m) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 col-sm-12 d-flex align-items-end mb-2">
                    <button type="submit" class="btn btn-primary btn-sm btn-block"><i class="fas fa-search mr-1"></i>Filter</button>
                    <a href="index.php" class="btn btn-default btn-sm ml-2" title="Reset Filters"><i class="fas fa-sync-alt"></i></a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Financial Stats Summary -->
<div class="row">
    <div class="col-lg-3 col-6">
        <div class="small-box bg-gradient-secondary">
            <div class="inner">
                <h3 style="font-size: 1.45rem; font-weight:700;"><?= formatRupiah($openingBalance) ?></h3>
                <p class="mb-1" style="font-size:12px;opacity:0.85;">Saldo Awal (Sebelum <?= date('d/m/Y', strtotime($startDate)) ?>)</p>
            </div>
            <div class="icon"><i class="fas fa-history" style="opacity:0.25;"></i></div>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="small-box bg-gradient-success">
            <div class="inner">
                <h3 style="font-size: 1.45rem; font-weight:700;"><?= formatRupiah($totalDebit) ?></h3>
                <p class="mb-1" style="font-size:12px;opacity:0.85;">Total Uang Masuk (Debit)</p>
            </div>
            <div class="icon"><i class="fas fa-arrow-down" style="opacity:0.25;"></i></div>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="small-box bg-gradient-danger">
            <div class="inner">
                <h3 style="font-size: 1.45rem; font-weight:700;"><?= formatRupiah($totalKredit) ?></h3>
                <p class="mb-1" style="font-size:12px;opacity:0.85;">Total Uang Keluar (Kredit)</p>
            </div>
            <div class="icon"><i class="fas fa-arrow-up" style="opacity:0.25;"></i></div>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="small-box bg-gradient-info">
            <div class="inner">
                <h3 style="font-size: 1.45rem; font-weight:700;"><?= formatRupiah($endingBalance) ?></h3>
                <p class="mb-1" style="font-size:12px;opacity:0.85;">Saldo Akhir Periode</p>
            </div>
            <div class="icon"><i class="fas fa-wallet" style="opacity:0.25;"></i></div>
        </div>
    </div>
</div>

<!-- Ledger Table Card -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center bg-white py-3">
        <h3 class="card-title font-weight-bold text-dark"><i class="fas fa-book mr-2 text-info"></i>Mutasi Kas Rekening Koran</h3>
        <div class="ml-auto d-print-none">
            <button type="button" class="btn btn-info btn-sm mr-2" onclick="window.print();"><i class="fas fa-print mr-1"></i> Cetak PDF</button>
            <a href="export_excel.php?start_date=<?= $startDate ?>&end_date=<?= $endDate ?>&company_id=<?= $companyId ?>&payment_method=<?= urlencode($paymentMethod) ?>" class="btn btn-success btn-sm"><i class="fas fa-file-excel mr-1"></i> Ekspor Excel</a>
        </div>
    </div>
    
    <div class="card-body p-0">
        <!-- Print Header -->
        <div class="report-print-header d-none d-print-block text-center mb-4">
            <h2 style="font-weight: 800; font-size: 18px; margin-bottom: 2px;">LAPORAN BUKU KAS (GENERAL LEDGER)</h2>
            <p style="font-size: 12px; color: #555; margin-bottom: 0;">
                Periode: <strong><?= date('d M Y', strtotime($startDate)) ?></strong> s/d <strong><?= date('d M Y', strtotime($endDate)) ?></strong>
            </p>
            <?php if ($companyId): ?>
                <?php
                    $compName = $pdo->query("SELECT name FROM companies WHERE id = " . (int)$companyId)->fetchColumn();
                ?>
                <p style="font-size: 11px; margin-top:2px;">Perusahaan: <strong><?= sanitize($compName) ?></strong></p>
            <?php endif; ?>
        </div>

        <div class="table-responsive">
            <table id="ledgerTable" class="table table-bordered table-striped table-hover mb-0 w-100" style="font-size:13px;">
                <thead class="bg-light">
                    <tr>
                        <th width="10%">Tanggal</th>
                        <th width="12%">No. Referensi</th>
                        <th width="28%">Keterangan Mutasi</th>
                        <th width="15%">Perusahaan</th>
                        <th width="10%">Metode</th>
                        <th width="12%" class="text-right text-success">Debit (+)</th>
                        <th width="12%" class="text-right text-danger">Kredit (-)</th>
                        <th width="15%" class="text-right font-weight-bold">Saldo</th>
                    </tr>
                    <!-- Row Saldo Awal -->
                    <tr class="bg-light font-weight-bold" style="background-color: #f8f9fa;">
                        <th colspan="5" class="text-right text-secondary" style="font-weight: bold; border: 1px solid #dee2e6;">SALDO AWAL (OPENING BALANCE):</th>
                        <th style="border: 1px solid #dee2e6;"></th>
                        <th style="border: 1px solid #dee2e6;"></th>
                        <th class="text-right text-dark" style="font-weight: bold; border: 1px solid #dee2e6;"><?= number_format($openingBalance, 0, ',', '.') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $currentRunningBalance = $openingBalance;
                    if (!empty($transactions)): 
                        foreach ($transactions as $t): 
                            $debit = (float)$t['debit'];
                            $kredit = (float)$t['kredit'];
                            $currentRunningBalance += ($debit - $kredit);
                    ?>
                        <tr>
                            <td><?= date('d-m-Y', strtotime($t['tanggal'])) ?></td>
                            <td><span class="badge badge-secondary"><?= sanitize($t['no_referensi']) ?></span></td>
                            <td><?= sanitize($t['keterangan']) ?></td>
                            <td><?= sanitize($t['nama_perusahaan']) ?></td>
                            <td><span class="text-muted"><?= sanitize($t['metode']) ?></span></td>
                            <td class="text-right text-success font-weight-bold">
                                <?= $debit > 0 ? number_format($debit, 0, ',', '.') : '-' ?>
                            </td>
                            <td class="text-right text-danger font-weight-bold">
                                <?= $kredit > 0 ? number_format($kredit, 0, ',', '.') : '-' ?>
                            </td>
                            <td class="text-right font-weight-bold">
                                <?= number_format($currentRunningBalance, 0, ',', '.') ?>
                            </td>
                        </tr>
                    <?php 
                        endforeach; 
                    endif; 
                    ?>
                </tbody>
                <tfoot class="bg-light font-weight-bold">
                    <tr>
                        <td colspan="5" class="text-right">TOTAL TRANSAKSI PERIODE INI:</td>
                        <td class="text-right text-success font-weight-bold"><?= number_format($totalDebit, 0, ',', '.') ?></td>
                        <td class="text-right text-danger font-weight-bold"><?= number_format($totalKredit, 0, ',', '.') ?></td>
                        <td class="text-right text-info font-weight-bold"><?= number_format($endingBalance, 0, ',', '.') ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<style>
@media print {
    @page {
        size: A4 landscape !important;
        margin: 8mm 10mm !important;
    }
    body {
        font-size: 11px !important;
        background-color: #fff !important;
    }
    .main-sidebar, .main-header, .main-footer, .d-print-none, .card-header, .small-box {
        display: none !important;
    }
    .content-wrapper {
        margin-left: 0 !important;
        padding: 0 !important;
    }
    .card {
        border: none !important;
        box-shadow: none !important;
    }
    .table td, .table th {
        padding: 5px 6px !important;
        border: 1px solid #000 !important;
    }
}
</style>

<?php
$extraJS = <<<'JS'
<script>
$(document).ready(function() {
    initSelect2('.select2');
    initDataTable('#ledgerTable', {
        ordering: false,
        pageLength: 25,
        lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Semua"]]
    });
});
</script>
JS;

require_once __DIR__ . '/../../../includes/footer.php';
?>
