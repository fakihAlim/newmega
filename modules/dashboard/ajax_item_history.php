<?php
require_once __DIR__ . '/../../includes/auth.php';

$id = (int)($_GET['id'] ?? 0);

if (!$id) {
    echo '<div class="alert alert-danger">ID barang tidak valid.</div>';
    exit;
}

$stmt = $pdo->prepare("SELECT item_code, description, uom FROM items WHERE id = ?");
$stmt->execute([$id]);
$item = $stmt->fetch();

if (!$item) {
    echo '<div class="alert alert-danger">Barang tidak ditemukan.</div>';
    exit;
}

// 1. Fetch Purchase History from POs
$stmtPo = $pdo->prepare("
    SELECT po.po_number, po.po_date, po.status as po_status, v.company_name as vendor_name, poi.qty as quantity, poi.unit_price as price, poi.total 
    FROM purchase_order_items poi 
    JOIN purchase_orders po ON poi.po_id = po.id 
    JOIN vendors v ON po.vendor_id = v.id 
    WHERE poi.item_id = ? 
    ORDER BY po.po_date DESC
");
$stmtPo->execute([$id]);
$purchaseHistory = $stmtPo->fetchAll();

// 2. Fetch Usage History (Warehouse Transfers / Stock Out)
$stmtUsage = $pdo->prepare("
    SELECT wt.transfer_number, wt.transfer_date, p.name as project_name, wti.qty as quantity, wt.status
    FROM warehouse_transfer_items wti 
    JOIN warehouse_transfers wt ON wti.transfer_id = wt.id 
    JOIN projects p ON wt.to_project_id = p.id 
    WHERE wti.item_id = ? AND wt.status = 'completed'
    ORDER BY wt.transfer_date DESC
");
$stmtUsage->execute([$id]);
$usageHistory = $stmtUsage->fetchAll();

?>
<div class="mb-4">
    <h5 class="border-bottom pb-2 text-primary">Informasi Barang</h5>
    <div class="row">
        <div class="col-sm-4"><strong>Kode Barang:</strong> <?= sanitize($item['item_code']) ?></div>
        <div class="col-sm-5"><strong>Nama:</strong> <?= sanitize($item['description']) ?></div>
        <div class="col-sm-3"><strong>Satuan:</strong> <?= sanitize($item['uom']) ?></div>
    </div>
</div>

<h5 class="text-success">Riwayat Pembelian</h5>
<div class="table-responsive mb-4">
    <table class="table table-sm table-bordered table-striped" >
        <thead class="bg-light">
            <tr>
                <th>No. PO</th>
                <th>Tanggal</th>
                <th>Vendor</th>
                <th class="text-center">Qty</th>
                <th class="text-right">Harga Satuan</th>
                <th class="text-right">Total</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($purchaseHistory)): ?>
                <tr><td colspan="6" class="text-center text-muted">Belum ada riwayat pembelian.</td></tr>
            <?php else: ?>
                <?php foreach ($purchaseHistory as $ph): ?>
                    <tr>
                        <td><strong><?= sanitize($ph['po_number']) ?></strong></td>
                        <td><?= formatDate($ph['po_date']) ?></td>
                        <td><?= sanitize($ph['vendor_name']) ?></td>
                        <td class="text-center"><?= $ph['quantity'] ?></td>
                        <td class="text-right"><?= formatRupiah($ph['price']) ?></td>
                        <td class="text-right"><?= formatRupiah($ph['total']) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<h5 class="text-info">Riwayat Pemakaian (Transfer ke Proyek)</h5>
<div class="table-responsive">
    <table class="table table-sm table-bordered table-striped" >
        <thead class="bg-light">
            <tr>
                <th>No. Transfer</th>
                <th>Tanggal Transfer</th>
                <th>Tujuan Proyek</th>
                <th class="text-center">Qty Terpakai</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($usageHistory)): ?>
                <tr><td colspan="4" class="text-center text-muted">Belum ada riwayat pemakaian/transfer.</td></tr>
            <?php else: ?>
                <?php foreach ($usageHistory as $uh): ?>
                    <tr>
                        <td><strong><?= sanitize($uh['transfer_number']) ?></strong></td>
                        <td><?= formatDate($uh['transfer_date']) ?></td>
                        <td><?= sanitize($uh['project_name']) ?></td>
                        <td class="text-center font-weight-bold text-danger">-<?= $uh['quantity'] ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
