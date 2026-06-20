<?php
/**
 * Finance - View Vendor Payment / Bukti Bayar
 */
require_once __DIR__ . '/../../../includes/auth.php';
requirePermission('vendor_payments');

$id = $_GET['id'] ?? 0;

$sql = "
    SELECT vp.*, 
           po.po_number, po.total as po_total, po.po_date,
           v.company_name as vendor_name, v.address as vendor_address, v.phone as vendor_phone,
           v.bank_name as vendor_bank_name, v.bank_account as vendor_bank_account, v.bank_holder as vendor_bank_holder,
           c.name as company_name, c.address as company_address, c.phone as company_phone, c.logo as company_logo,
           u.full_name as payer_name
    FROM vendor_payments vp
    JOIN purchase_orders po ON vp.po_id = po.id
    JOIN vendors v ON po.vendor_id = v.id
    JOIN companies c ON po.company_id = c.id
    LEFT JOIN users u ON vp.paid_by = u.id
    WHERE vp.id = ?
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);
$vp = $stmt->fetch();

if (!$vp) {
    setFlash('danger', 'Data pembayaran tidak ditemukan.');
    header('Location: index.php');
    exit;
}

// Get all payments for this PO to show history
$stmtHistory = $pdo->prepare("SELECT * FROM vendor_payments WHERE po_id = ? ORDER BY payment_date ASC");
$stmtHistory->execute([$vp['po_id']]);
$history = $stmtHistory->fetchAll();

$totalPaid = 0;
foreach ($history as $h) { $totalPaid += $h['amount']; }
$outstanding = $vp['po_total'] - $totalPaid;

$pageTitle = 'Bukti Pembayaran Vendor';
$breadcrumbs = [
    ['label' => 'Finance', 'url' => '#'],
    ['label' => 'Pembayaran Vendor', 'url' => 'index.php'],
    ['label' => 'Detail']
];

require_once __DIR__ . '/../../../includes/header.php';
?>

<div class="card card-outline card-primary">
    <div class="card-header d-flex justify-content-between align-items-center d-print-none">
        <h3 class="card-title text-primary"><i class="fas fa-receipt mr-2"></i> Bukti Pembayaran Vendor</h3>
        <div class="ml-auto">
            <button class="btn btn-default btn-sm" onclick="window.print()"><i class="fas fa-print mr-1"></i> Cetak Bukti Bayar</button>
            <a href="index.php" class="btn btn-secondary btn-sm ml-1"><i class="fas fa-arrow-left mr-1"></i> Kembali</a>
        </div>
    </div>
    
    <div class="card-body printable-area p-4 bg-white">
        
        <!-- Header -->
        <div class="row mb-4 pb-3" style="border-bottom: 2px solid #333;">
            <div class="col-sm-7">
                <div class="d-flex align-items-center">
                    <?php if ($vp['company_logo']): ?>
                        <img src="<?= getCompanyLogo($vp['company_logo']) ?>" alt="Logo" style="height:55px; margin-right:15px;">
                    <?php endif; ?>
                    <div>
                        <h4 class="mb-0 font-weight-bold" style="color:#000;"><?= sanitize($vp['company_name']) ?></h4>
                        <div style="font-size:13px; color:#000;">
                            <?= sanitize($vp['company_address']) ?><br>
                            Telp: <?= sanitize($vp['company_phone']) ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-5 text-right">
                <h3 class="text-uppercase font-weight-bold" style="color:#666; letter-spacing:2px;">Bukti Bayar</h3>
                <div style="font-size:14px;">
                    <strong>Tanggal:</strong> <?= date('d F Y', strtotime($vp['payment_date'])) ?><br>
                    <strong>Ref PO:</strong> <?= sanitize($vp['po_number']) ?>
                </div>
            </div>
        </div>
        
        <!-- Vendor Info -->
        <div class="row mb-4">
            <div class="col-sm-6">
                <h6 class="text-uppercase font-weight-bold border-bottom pb-1 mb-2" style="font-size:12px; color:#555;">Dibayarkan Kepada</h6>
                <div style="font-size:14px;">
                    <strong style="font-size:15px;"><?= sanitize($vp['vendor_name']) ?></strong><br>
                    <?= nl2br(sanitize($vp['vendor_address'])) ?><br>
                    Telp: <?= sanitize($vp['vendor_phone']) ?: '-' ?>
                </div>
            </div>
            <div class="col-sm-6">
                <h6 class="text-uppercase font-weight-bold border-bottom pb-1 mb-2" style="font-size:12px; color:#555;">Informasi Bank Vendor</h6>
                <table class="table-sm table-borderless" >
                    <tr><td width="40%"><strong>Nama Bank:</strong></td><td><?= sanitize($vp['bank_name']) ?: sanitize($vp['vendor_bank_name']) ?: '-' ?></td></tr>
                    <tr><td><strong>No. Rekening:</strong></td><td><?= sanitize($vp['bank_account']) ?: sanitize($vp['vendor_bank_account']) ?: '-' ?></td></tr>
                    <tr><td><strong>Atas Nama:</strong></td><td><?= sanitize($vp['vendor_bank_holder']) ?: '-' ?></td></tr>
                </table>
            </div>
        </div>
        
        <!-- Payment Detail -->
        <div class="table-responsive mb-4">
            <table class="table table-bordered" >
                <thead class="bg-light">
                    <tr>
                        <th width="25%">Keterangan</th>
                        <th width="20%">Metode</th>
                        <th width="20%">No. Referensi</th>
                        <th width="15%">Termin</th>
                        <th width="20%" class="text-right">Jumlah (Rp)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Pembayaran untuk PO <strong><?= sanitize($vp['po_number']) ?></strong></td>
                        <td><?= sanitize($vp['payment_method']) ?: '-' ?></td>
                        <td><?= sanitize($vp['reference_no']) ?: '-' ?></td>
                        <td><?= sanitize($vp['payment_term']) ?: '-' ?></td>
                        <td class="text-right font-weight-bold" style="font-size:18px; color: #000;"><?= formatRupiah($vp['amount']) ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <!-- Summary & Notes Section -->
        <div class="row no-gutters mb-4">
            <!-- Notes (Left) -->
            <div class="col-sm-7 pt-2 pr-3 d-flex flex-column">
                <div class="p-1 px-2 bg-light text-dark font-weight-bold"
                    style="border: 1px solid #000; font-size: 12px;">
                    Catatan Pembayaran :</div>
                <div class="p-2 flex-grow-1"
                    style="border: 1px solid #000 !important; font-size: 11px; border-top: none !important; color: #333;">
                    <?= $vp['notes'] ? nl2br(sanitize($vp['notes'])) : '<span class="text-muted" style="font-style: italic;">-</span>' ?>
                </div>
            </div>
            
            <!-- PO Payment Summary (Right) -->
            <div class="col-sm-5 pt-2">
                <table class="table table-sm table-bordered text-right font-weight-bold mb-0" style="border: 1px solid #000;">
                    <tr>
                        <td width="60%" class="bg-light px-2" style="border: 1px solid #000;">TOTAL NILAI PO</td>
                        <td width="40%" class="px-2" style="border: 1px solid #000; color: #000;">
                            <?= formatRupiah($vp['po_total']) ?>
                        </td>
                    </tr>
                    <tr>
                        <td class="bg-light px-2" style="border: 1px solid #000; color: #000;">TOTAL TERBAYAR</td>
                        <td class="px-2" style="border: 1px solid #000; color: #000;">
                            <?= formatRupiah($totalPaid) ?>
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
                <p class="mb-5">Dibayar Oleh,</p>
                <strong><?= sanitize($vp['payer_name']) ?></strong>
                <p class="text-muted">Finance</p>
            </div>
            <div class="col-sm-4 offset-sm-4">
                <p class="mb-5">Mengetahui,</p>
                <strong>( ................................... )</strong>
                <p class="text-muted">Direktur</p>
            </div>
        </div>
        
    </div>
</div>

<!-- Payment History for this PO -->
<?php if (count($history) > 1): ?>
<div class="card d-print-none">
    <div class="card-header">
        <h3 class="card-title">Riwayat Pembayaran PO <?= sanitize($vp['po_number']) ?></h3>
    </div>
    <div class="card-body p-0">
        <table class="table table-sm table-bordered m-0" >
            <thead class="bg-light">
                <tr>
                    <th>Tanggal</th>
                    <th>Metode</th>
                    <th>Referensi</th>
                    <th class="text-right">Jumlah (Rp)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($history as $h): ?>
                <tr class="<?= $h['id'] == $id ? 'table-success' : '' ?>">
                    <td><?= date('d-m-Y', strtotime($h['payment_date'])) ?></td>
                    <td><?= sanitize($h['payment_method']) ?: '-' ?></td>
                    <td><?= sanitize($h['reference_no']) ?: '-' ?></td>
                    <td class="text-right font-weight-bold"><?= formatRupiah($h['amount']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr class="font-weight-bold">
                    <td colspan="3" class="text-right">Total Terbayar:</td>
                    <td class="text-right text-success"><?= formatRupiah($totalPaid) ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
<?php endif; ?>

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
