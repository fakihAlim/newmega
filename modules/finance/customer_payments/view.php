<?php
/**
 * Finance - View Customer Payment / Kwitansi
 */
require_once __DIR__ . '/../../../includes/auth.php';
requirePermission('customer_payments');

$id = $_GET['id'] ?? 0;

$sql = "
    SELECT cp.*, 
           inv.invoice_no, inv.total as invoice_total, inv.invoice_date,
           cust.company_name as customer_name, cust.address as customer_address, cust.phone as customer_phone, cust.pic_name as customer_pic,
           c.name as company_name, c.address as company_address, c.phone as company_phone, c.logo as company_logo,
           u.full_name as receiver_name
    FROM customer_payments cp
    JOIN invoices inv ON cp.invoice_id = inv.id
    JOIN customers cust ON inv.customer_id = cust.id
    JOIN companies c ON inv.company_id = c.id
    LEFT JOIN users u ON cp.received_by = u.id
    WHERE cp.id = ?
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);
$cp = $stmt->fetch();

if (!$cp) {
    setFlash('danger', 'Data penerimaan tidak ditemukan.');
    header('Location: index.php');
    exit;
}

// Get all payments for this Invoice
$stmtHistory = $pdo->prepare("SELECT * FROM customer_payments WHERE invoice_id = ? ORDER BY payment_date ASC");
$stmtHistory->execute([$cp['invoice_id']]);
$history = $stmtHistory->fetchAll();

$totalReceived = 0;
foreach ($history as $h) { $totalReceived += $h['amount']; }
$outstanding = $cp['invoice_total'] - $totalReceived;

$pageTitle = 'Kwitansi Penerimaan Customer';
$breadcrumbs = [
    ['label' => 'Finance', 'url' => '#'],
    ['label' => 'Penerimaan Customer', 'url' => 'index.php'],
    ['label' => 'Detail']
];

require_once __DIR__ . '/../../../includes/header.php';
?>

<div class="card card-outline card-info">
    <div class="card-header d-flex justify-content-between align-items-center d-print-none">
        <h3 class="card-title text-info"><i class="fas fa-receipt mr-2"></i> Kwitansi Penerimaan</h3>
        <div class="ml-auto">
            <button class="btn btn-default btn-sm" onclick="window.print()"><i class="fas fa-print mr-1"></i> Cetak Kwitansi</button>
            <a href="index.php" class="btn btn-secondary btn-sm ml-1"><i class="fas fa-arrow-left mr-1"></i> Kembali</a>
        </div>
    </div>
    
    <div class="card-body printable-area p-5">
        
        <!-- Header -->
        <div class="row mb-4 pb-3" style="border-bottom: 2px solid #333;">
            <div class="col-sm-7">
                <div class="d-flex align-items-center">
                    <?php if ($cp['company_logo']): ?>
                        <img src="<?= getCompanyLogo($cp['company_logo']) ?>" alt="Logo" style="height:55px; margin-right:15px;">
                    <?php endif; ?>
                    <div>
                        <h4 class="mb-0 font-weight-bold" style="color:#000;"><?= sanitize($cp['company_name']) ?></h4>
                        <div style="font-size:13px; color:#000;">
                            <?= sanitize($cp['company_address']) ?><br>
                            Telp: <?= sanitize($cp['company_phone']) ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-5 text-right">
                <h3 class="text-uppercase font-weight-bold" style="color:#666; letter-spacing:2px;">Kwitansi</h3>
                <div style="font-size:14px;">
                    <strong>Tanggal:</strong> <?= date('d F Y', strtotime($cp['payment_date'])) ?><br>
                    <strong>Ref Invoice:</strong> <?= sanitize($cp['invoice_no']) ?>
                </div>
            </div>
        </div>
        
        <!-- Customer Info -->
        <div class="row mb-4">
            <div class="col-sm-6">
                <h6 class="text-uppercase font-weight-bold border-bottom pb-1 mb-2" style="font-size:12px; color:#555;">Diterima Dari</h6>
                <div style="font-size:14px;">
                    <strong style="font-size:15px;"><?= sanitize($cp['customer_name']) ?></strong><br>
                    UP: <?= sanitize($cp['customer_pic']) ?: '-' ?><br>
                    <?= nl2br(sanitize($cp['customer_address'])) ?><br>
                    Telp: <?= sanitize($cp['customer_phone']) ?: '-' ?>
                </div>
            </div>
        </div>
        
        <!-- Payment Detail -->
        <div class="table-responsive mb-4">
            <table class="table table-bordered" style="font-size:14px;">
                <thead class="bg-light">
                    <tr>
                        <th width="30%">Keterangan</th>
                        <th width="20%">Metode</th>
                        <th width="20%">No. Referensi</th>
                        <th width="30%" class="text-right">Jumlah (Rp)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Pembayaran Invoice <strong><?= sanitize($cp['invoice_no']) ?></strong></td>
                        <td><?= sanitize($cp['payment_method']) ?: '-' ?></td>
                        <td><?= sanitize($cp['reference_no']) ?: '-' ?></td>
                        <td class="text-right font-weight-bold" style="font-size:18px; color:#000;"><?= formatRupiah($cp['amount']) ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <!-- Summary & Notes Section -->
        <div class="row no-gutters mb-4">
            <!-- Notes (Left) -->
            <div class="col-sm-7 pt-2 pr-3 d-flex flex-column">
                <div class="p-1 px-2 text-white font-weight-bold"
                    style="background-color: #666 !important; border: 1px solid #000; font-size: 12px;">
                    Catatan Penerimaan :</div>
                <div class="p-2 flex-grow-1"
                    style="border: 1px solid #000 !important; font-size: 11px; border-top: none !important; color: #333;">
                    <?= $cp['notes'] ? nl2br(sanitize($cp['notes'])) : '<span class="text-muted" style="font-style: italic;">-</span>' ?>
                </div>
            </div>
            
            <!-- Invoice Payment Summary (Right) -->
            <div class="col-sm-5 pt-2">
                <table class="table table-sm table-bordered text-right font-weight-bold mb-0" style="font-size:13px; border: 1px solid #000;">
                    <tr>
                        <td width="60%" class="bg-light px-2" style="border: 1px solid #000;">TOTAL NILAI INVOICE</td>
                        <td width="40%" class="px-2" style="border: 1px solid #000; color: #000;">
                            <?= formatRupiah($cp['invoice_total']) ?>
                        </td>
                    </tr>
                    <tr>
                        <td class="bg-light px-2" style="border: 1px solid #000; color: #000;">TOTAL TERBAYAR</td>
                        <td class="px-2" style="border: 1px solid #000; color: #000;">
                            <?= formatRupiah($totalReceived) ?>
                        </td>
                    </tr>
                    <tr style="background-color: #f2f2f2;">
                        <td class="px-2" style="border: 1px solid #000; font-size: 14px; color: #000;">SISA OUTSTANDING</td>
                        <td class="px-2" style="border: 1px solid #000; font-size: 15px; color: #000;">
                            <?= formatRupiah($outstanding) ?>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        
        <!-- Signatures -->
        <div class="row mt-5 pt-3 text-center" style="font-size:14px;">
            <div class="col-sm-4">
                <p class="mb-5">Diterima Oleh,</p>
                <strong><?= sanitize($cp['receiver_name']) ?></strong>
                <p class="text-muted">Finance</p>
            </div>
            <div class="col-sm-4 offset-sm-4">
                <p class="mb-5">Penyetor,</p>
                <strong>( ................................... )</strong>
                <p class="text-muted"><?= sanitize($cp['customer_name']) ?></p>
            </div>
        </div>
        
    </div>
</div>



<style>
@media print {
    @page {
        size: A4 portrait;
        margin: 10mm;
    }
    body { background-color: white !important; }
    .main-sidebar, .main-header, .d-print-none, .breadcrumb, .content-header { display: none !important; }
    .content-wrapper { margin-left: 0 !important; padding: 0 !important; }
    .card { border: none !important; box-shadow: none !important; }
    .card-header { display: none !important; }
    .printable-area { width: 100% !important; }
}
</style>

<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>
