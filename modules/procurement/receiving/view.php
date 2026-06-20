<?php
/**
 * Procurement - View Goods Receiving
 */
require_once __DIR__ . '/../../../includes/auth.php';
requirePermission('receiving_list');

$id = $_GET['id'] ?? 0;

$sql = "
    SELECT gr.*, 
           po.po_number, po.po_date, po.status as po_status,
           v.company_name as vendor_name, v.address as vendor_address, v.phone as vendor_phone,
           u.full_name as receiver_name,
           p.name as project_name
    FROM goods_receivings gr
    JOIN purchase_orders po ON gr.po_id = po.id
    JOIN vendors v ON po.vendor_id = v.id
    LEFT JOIN users u ON gr.received_by = u.id
    LEFT JOIN projects p ON gr.project_id = p.id
    WHERE gr.id = ?
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);
$gr = $stmt->fetch();

if (!$gr) {
    setFlash('danger', 'Surat Jalan / Penerimaan tidak ditemukan.');
    header('Location: index.php');
    exit;
}

// Fetch GR items
$stmtItems = $pdo->prepare("
    SELECT gri.*, poi.item_name, poi.uom, poi.qty as qty_ordered
    FROM goods_receiving_items gri
    JOIN purchase_order_items poi ON gri.po_item_id = poi.id
    WHERE gri.receiving_id = ?
");
$stmtItems->execute([$id]);
$items = $stmtItems->fetchAll();

$pageTitle = 'Detail Surat Jalan: ' . sanitize($gr['surat_jalan_no']);
$breadcrumbs = [
    ['label' => 'Procurement', 'url' => '#'],
    ['label' => 'Penerimaan', 'url' => 'index.php'],
    ['label' => 'Detail']
];

require_once __DIR__ . '/../../../includes/header.php';
?>

<div class="card card-outline card-primary">
    <div class="card-header d-flex justify-content-between align-items-center d-print-none">
        <h3 class="card-title text-primary"><i class="fas fa-file-invoice mr-2"></i> Laporan Penerimaan Barang / Surat Jalan</h3>
        <div class="ml-auto">
            <button class="btn btn-default btn-sm ml-3" onclick="window.print()"><i class="fas fa-print mr-1"></i> Cetak Tanda Terima</button>
            <a href="index.php" class="btn btn-secondary btn-sm ml-1"><i class="fas fa-arrow-left mr-1"></i> Kembali</a>
        </div>
    </div>
    
    <div class="card-body printable-area p-4 bg-white">
        
        <!-- Print Header -->
        <div class="row mb-4 pb-3" style="border-bottom: 2px solid #ccc;">
            <div class="col-sm-6">
                <h3 class="font-weight-bold text-uppercase" style="letter-spacing: 1px;">Penerimaan Barang</h3>
                <h4 class="font-weight-bold">
                    <?= sanitize($gr['surat_jalan_no']) ?>
                    <?php if (!empty($gr['surat_jalan_file'])): ?>
                        <a href="<?= APP_URL ?>/uploads/receiving/<?= $gr['surat_jalan_file'] ?>" target="_blank" class="btn btn-xs btn-outline-primary ml-2 d-print-none">
                            <i class="fas fa-paperclip mr-1"></i> Lihat Lampiran
                        </a>
                    <?php endif; ?>
                </h4>
            </div>
            <div class="col-sm-6 text-right">
                <div style="font-size: 14px;">
                    <strong>Tanggal Terima:</strong> <?= date('d F Y', strtotime($gr['receive_date'])) ?><br>
                    <strong>Referensi PO:</strong> <?= sanitize($gr['po_number']) ?><br>
                    <strong>Lokasi Terima:</strong> 
                    <?php if ($gr['received_at'] === 'warehouse'): ?>
                        Gudang Utama
                    <?php else: ?>
                        Proyek (<?= sanitize($gr['project_name']) ?>)
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Vendor & Penerima -->
        <div class="row mb-4">
            <div class="col-sm-6">
                <h6 class="text-uppercase font-weight-bold pb-1" style="border-bottom:1px solid #eee;">Diterima Dari (Vendor)</h6>
                <div style="font-size: 14px;">
                    <strong><?= sanitize($gr['vendor_name']) ?></strong><br>
                    <?= nl2br(sanitize($gr['vendor_address'])) ?><br>
                    Telp: <?= sanitize($gr['vendor_phone']) ?: '-' ?>
                </div>
            </div>
            <div class="col-sm-6 text-right">
                <h6 class="text-uppercase font-weight-bold pb-1" style="border-bottom:1px solid #eee;">Penerima (Internal)</h6>
                <div style="font-size: 14px;">
                    <strong><?= sanitize($gr['receiver_name']) ?></strong><br>
                    (Staf Gudang / Logistik Proyek)
                </div>
            </div>
        </div>
        
        <!-- Items Table -->
        <div class="table-responsive mb-4">
            <table class="table table-bordered table-sm print-table" >
                <thead class="bg-light">
                    <tr>
                        <th width="5%" class="text-center">No</th>
                        <th width="40%">Deskripsi Barang</th>
                        <th width="12%" class="text-center">Di Pesan (PO)</th>
                        <th width="15%" class="text-center">Diterima (Qty)</th>
                        <th width="15%" class="text-center">Ditolak / Rusak</th>
                        <th width="13%" class="text-center">Satuan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $no = 1; foreach ($items as $item): ?>
                    <tr>
                        <td class="text-center"><?= $no++ ?></td>
                        <td>
                            <strong><?= sanitize($item['item_name']) ?></strong>
                            <?php if ($item['reject_reason']): ?>
                                <br><small>Alesan Ditolak: <?= sanitize($item['reject_reason']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td class="text-center"><?= number_format($item['qty_ordered'], 2, ',', '.') ?></td>
                        <td class="text-center font-weight-bold"><?= number_format($item['qty_received'], 2, ',', '.') ?></td>
                        <td class="text-center font-weight-bold"><?= $item['qty_rejected'] > 0 ? number_format($item['qty_rejected'], 2, ',', '.') : '-' ?></td>
                        <td class="text-center"><?= sanitize($item['uom']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Footer / Notes -->
        <div class="row mt-4 pt-3">
            <div class="col-sm-8">
                <strong>Catatan Penerimaan:</strong>
                <p style="font-size:14px; min-height:60px; color: #000;">
                    <?= nl2br(sanitize($gr['notes'])) ?: 'Tidak ada catatan.' ?>
                </p>
            </div>
        </div>

        <!-- Signatures -->
        <div class="row mt-5 text-center" style="font-size:14px;">
            <div class="col-sm-4">
                <p class="mb-5">Pengirim (Sopir Vendor),</p>
                <strong>( ......................................... )</strong>
            </div>
            <div class="col-sm-4 offset-sm-4">
                <p class="mb-5">Penerima Barang,</p>
                <strong><?= sanitize($gr['receiver_name']) ?></strong>
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
    body { background-color: white !important; color: #000 !important; }
    .main-sidebar, .main-header, .d-print-none, .breadcrumb, .content-header { display: none !important; }
    .content-wrapper { margin-left: 0 !important; padding: 0 !important; }
    .card { border: none !important; box-shadow: none !important; }
    .card-header { display: none !important; }
    .printable-area { width: 100% !important; border: none !important; color: #000 !important; }
    .printable-area * { color: #000 !important; }
    .print-table th { background-color: #eee !important; -webkit-print-color-adjust: exact; color: #000 !important; }
}
.printable-area { color: #000 !important; }
.printable-area * { color: #000 !important; }

</style>

<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>
