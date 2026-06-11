<?php
/**
 * Warehouse - View Transfer (SJ Keluar)
 */
require_once __DIR__ . '/../../../includes/auth.php';
requirePermission('transfer_list');

$id = $_GET['id'] ?? 0;

$sql = "
    SELECT wt.*, 
           p.name as project_name, p.location as project_location,
           u.full_name as transfer_user,
           c.name as company_name, c.logo as company_logo, c.address as company_address, 
           c.phone as company_phone, c.email as company_email
    FROM warehouse_transfers wt
    JOIN projects p ON wt.to_project_id = p.id
    LEFT JOIN users u ON wt.transferred_by = u.id
    LEFT JOIN companies c ON wt.company_id = c.id
    WHERE wt.id = ?
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);
$tr = $stmt->fetch();

if (!$tr) {
    setFlash('danger', 'Transfer Barang tidak ditemukan.');
    header('Location: index.php');
    exit;
}

// Fetch items for display
$stmtItems = $pdo->prepare("
    SELECT wti.*, i.item_code, i.description as item_name, i.uom 
    FROM warehouse_transfer_items wti
    JOIN items i ON wti.item_id = i.id
    WHERE wti.transfer_id = ?
");
$stmtItems->execute([$id]);
$items = $stmtItems->fetchAll();

$pageTitle = 'Detail Surat Jalan Keluar: ' . sanitize($tr['transfer_number']);
$breadcrumbs = [
    ['label' => 'Warehouse', 'url' => '#'],
    ['label' => 'Transfer', 'url' => 'index.php'],
    ['label' => 'Detail']
];

require_once __DIR__ . '/../../../includes/header.php';
?>

<div class="row">
    <div class="col-md-12">
        <div class="card card-outline card-warning">
            <div class="card-header d-flex justify-content-between align-items-center d-print-none">
                <h3 class="card-title text-warning font-weight-bold"><i class="fas fa-truck-loading mr-2"></i> Pengeluaran Barang: <strong><?= sanitize($tr['transfer_number']) ?></strong></h3>
                <div class="ml-auto">
                    <?= getStatusBadge($tr['status']) ?>
                    <button class="btn btn-default btn-sm ml-3" onclick="window.print()"><i class="fas fa-print mr-1"></i> Cetak SJ Keluar</button>
                    <a href="index.php" class="btn btn-secondary btn-sm ml-1"><i class="fas fa-arrow-left mr-1"></i> Kembali</a>
                </div>
            </div>
            
            <div class="card-body printable-area p-5">
                
                <!-- Company Header (Standard like PO) -->
                <div class="row mb-4">
                    <div class="col-sm-2 text-center">
                        <?php if ($tr['company_logo']): ?>
                            <img src="<?= getCompanyLogo($tr['company_logo']) ?>" alt="Logo" style="max-height: 100px; max-width: 100%;">
                        <?php else: ?>
                            <i class="fas fa-building fa-4x text-muted"></i>
                        <?php endif; ?>
                    </div>
                    <div class="col-sm-7">
                        <h2 class="font-weight-bold mb-0"><?= sanitize($tr['company_name']) ?></h2>
                        <p class="mb-0"><?= nl2br(sanitize($tr['company_address'])) ?></p>
                        <p class="mb-0">
                            <?php if ($tr['company_phone']): ?> <i class="fas fa-phone mr-1"></i> <?= sanitize($tr['company_phone']) ?> <?php endif; ?>
                            <?php if ($tr['company_email']): ?> <i class="fas fa-envelope ml-3 mr-1"></i> <?= sanitize($tr['company_email']) ?> <?php endif; ?>
                        </p>
                    </div>
                    <div class="col-sm-3 text-right">
                        <h3 class="text-uppercase font-weight-bold text-secondary">Surat Jalan</h3>
                        <h5 class="font-weight-bold"><?= sanitize($tr['transfer_number']) ?></h5>
                        <p>Tanggal: <?= date('d/m/Y', strtotime($tr['transfer_date'])) ?></p>
                    </div>
                </div>

                <div style="border-top: 2px solid #333; margin-bottom: 20px;"></div>

                <!-- Info Section -->
                <div class="row mb-4">
                    <div class="col-sm-6">
                        <h6 class="text-secondary text-uppercase font-weight-bold" style="font-size:12px;">Tujuan Pengiriman (Proyek):</h6>
                        <strong style="font-size: 16px;"><?= sanitize($tr['project_name']) ?></strong><br>
                        <p class="text-muted"><?= nl2br(sanitize($tr['project_location'])) ?></p>
                    </div>
                    <div class="col-sm-6 text-right">
                        <h6 class="text-secondary text-uppercase font-weight-bold" style="font-size:12px;">Informasi Transportasi:</h6>
                        <strong>Sopir:</strong> <?= sanitize($tr['driver_name']) ?: '-' ?><br>
                        <strong>Admin Gudang:</strong> <?= sanitize($tr['transfer_user']) ?>
                    </div>
                </div>
                
                <!-- Items Table -->
                <div class="table-responsive mb-4">
                    <table class="table table-bordered table-sm print-table" style="font-size:14px;">
                        <thead class="bg-light">
                            <tr>
                                <th width="5%" class="text-center">No</th>
                                <th width="15%" class="text-center">Kode Barang</th>
                                <th width="50%">Deskripsi / Nama Barang</th>
                                <th width="15%" class="text-center">Qty Dikirim</th>
                                <th width="15%" class="text-center">Satuan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $no = 1; foreach ($items as $item): ?>
                            <tr>
                                <td class="text-center"><?= $no++ ?></td>
                                <td class="text-center font-weight-bold"><?= sanitize($item['item_code']) ?></td>
                                <td><?= sanitize($item['item_name']) ?></td>
                                <td class="text-center font-weight-bold" style="font-size:15px;"><?= number_format($item['qty'], 2, ',', '.') ?></td>
                                <td class="text-center"><?= sanitize($item['uom']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Additional Notes Box (PO Style) -->
                <div class="row mt-4">
                    <div class="col-sm-12">
                        <div style="border: 1px solid #ccc; padding: 10px; border-radius: 5px; min-height: 80px; background-color: #fcfcfc;">
                            <h6 class="text-secondary text-uppercase font-weight-bold mb-1" style="font-size:11px;">Keterangan Pengiriman / Additional Notes:</h6>
                            <p class="mb-0" style="font-size:13px; color: #444;">
                                <?= nl2br(sanitize($tr['notes'])) ?: 'Tidak ada keterangan tambahan.' ?>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Signatures -->
                <div class="row mt-5 pt-3 text-center" style="font-size:14px;">
                    <div class="col-sm-4">
                        <p class="mb-5">Dikirim Oleh (Gudang),</p>
                        <div style="margin-top: 50px;">
                            <strong>( <?= sanitize($tr['transfer_user']) ?> )</strong>
                        </div>
                    </div>
                    <div class="col-sm-4">
                        <p class="mb-5">Membawa (Sopir),</p>
                        <div style="margin-top: 50px;">
                            <strong>( <?= sanitize($tr['driver_name']) ?: '...................................' ?> )</strong>
                        </div>
                    </div>
                    <div class="col-sm-4">
                        <p class="mb-5">Diterima Oleh (Proyek),</p>
                        <div style="margin-top: 50px;">
                            <strong>( ................................... )</strong>
                        </div>
                    </div>
                </div>
                
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
    body { background-color: white !important; color: black !important; }
    .main-sidebar, .main-header, .d-print-none, .breadcrumb, .content-header { display: none !important; }
    .content-wrapper { margin-left: 0 !important; padding: 0 !important; }
    .card { border: none !important; box-shadow: none !important; }
    .card-header { display: none !important; }
    .printable-area { width: 100% !important; border: none !important; padding: 0 !important; }
    .print-table th { background-color: #eee !important; -webkit-print-color-adjust: exact; }
    .card-body { padding: 0 !important; }
}
</style>

<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>
