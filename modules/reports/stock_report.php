<?php
/**
 * Report - Stock Report (Laporan Stok & Mutasi)
 */
require_once __DIR__ . '/../../includes/auth.php';
requirePermission('report_stock');

$pageTitle = 'Laporan Stok & Mutasi';
$breadcrumbs = [
    ['label' => 'Laporan', 'url' => '#'],
    ['label' => 'Laporan Stok']
];

// Show item detail if requested
$itemId = $_GET['item_id'] ?? null;

if ($itemId) {
    $stmtItem = $pdo->prepare("SELECT i.*, c.name as category_name FROM items i JOIN categories c ON i.category_id = c.id WHERE i.id = ?");
    $stmtItem->execute([$itemId]);
    $item = $stmtItem->fetch();
    
    if (!$item) {
        setFlash('danger', 'Barang tidak ditemukan.');
        header('Location: stock_report.php');
        exit;
    }
    
    $stmtTx = $pdo->prepare("
        SELECT st.*, u.full_name as user_name, p.name as project_name
        FROM stock_transactions st
        LEFT JOIN users u ON st.created_by = u.id
        LEFT JOIN projects p ON st.project_id = p.id
        WHERE st.item_id = ?
        ORDER BY st.created_at DESC
    ");
    $stmtTx->execute([$itemId]);
    $transactions = $stmtTx->fetchAll();
    
    $pageTitle = 'Kartu Stok: ' . sanitize($item['item_code']);
    $breadcrumbs[] = ['label' => 'Kartu Stok'];
    
    require_once __DIR__ . '/../../includes/header.php';
    require_once __DIR__ . '/../../includes/report_print.php';
    ?>

    <?php renderReportPrintHeader('Kartu Stok: ' . sanitize($item['item_code']) . ' — ' . sanitize($item['description'])); ?>
    
    <div class="card card-outline card-primary">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title">
                <i class="fas fa-clipboard-check mr-2"></i> 
                Kartu Stok: <strong><?= sanitize($item['item_code']) ?></strong> — <?= sanitize($item['description']) ?>
            </h3>
            <div class="ml-auto d-flex gap-2 align-items-center">
                <a href="export_excel.php?type=stock_detail&item_id=<?= $item['id'] ?>" class="btn btn-success btn-sm"><i class="fas fa-file-excel mr-1"></i> Export Excel</a>
                <a href="export_csv.php?type=stock_detail&item_id=<?= $item['id'] ?>" class="btn btn-info btn-sm ml-1"><i class="fas fa-file-csv mr-1"></i> Export CSV</a>
                <button class="btn btn-default btn-sm ml-1" onclick="window.print()"><i class="fas fa-print mr-1"></i> Cetak</button>
                <a href="stock_report.php" class="btn btn-secondary btn-sm ml-1"><i class="fas fa-arrow-left mr-1"></i> Kembali</a>
            </div>
        </div>
        <div class="card-body">
            <!-- Item Summary -->
            <div class="row mb-4">
                <div class="col-md-3"><strong>Kode:</strong> <?= sanitize($item['item_code']) ?></div>
                <div class="col-md-3"><strong>Kategori:</strong> <?= sanitize($item['category_name']) ?></div>
                <div class="col-md-3"><strong>Satuan:</strong> <?= sanitize($item['uom']) ?></div>
                <div class="col-md-3"><strong>Stok Saat Ini:</strong> <span class="font-weight-bold text-primary" style="font-size:18px;"><?= (float)$item['current_stock'] ?></span></div>
            </div>
            
            <table id="txTable" class="table table-bordered table-striped w-100" style="font-size: 13px;">
                <thead class="bg-light">
                    <tr>
                        <th width="15%">Waktu</th>
                        <th width="12%" class="text-center">Tipe</th>
                        <th width="12%" class="text-center">Qty</th>
                        <th width="12%">Referensi</th>
                        <th width="15%">Proyek</th>
                        <th width="15%">Petugas</th>
                        <th width="19%">Catatan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transactions as $tx): ?>
                    <?php
                        $typeLabels = [
                            'in' => '<span class="badge badge-success">Masuk (IN)</span>',
                            'out' => '<span class="badge badge-danger">Keluar (OUT)</span>',
                            'transfer_out' => '<span class="badge badge-warning">Transfer Out</span>',
                            'transfer_in' => '<span class="badge badge-info">Transfer In</span>',
                            'adjustment' => '<span class="badge badge-secondary">Penyesuaian</span>',
                        ];
                    ?>
                    <tr>
                        <td><?= date('d-m-Y H:i', strtotime($tx['created_at'])) ?></td>
                        <td class="text-center"><?= $typeLabels[$tx['transaction_type']] ?? $tx['transaction_type'] ?></td>
                        <td class="text-center font-weight-bold"><?= (float)$tx['qty'] ?></td>
                        <td><?= sanitize($tx['reference_type']) ?>#<?= $tx['reference_id'] ?></td>
                        <td><?= sanitize($tx['project_name']) ?: '-' ?></td>
                        <td><?= sanitize($tx['user_name']) ?: '-' ?></td>
                        <td><small><?= sanitize($tx['notes']) ?: '-' ?></small></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php renderReportPrintFooter(); ?>
    
    <?php
    $extraJS = <<<'JS'
    <script>$(document).ready(function() { initDataTable('#txTable'); });</script>
JS;
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

// ===== MAIN VIEW: All Items Summary =====
$sql = "
    SELECT i.*, c.name as category_name,
           (SELECT COUNT(*) FROM stock_transactions st WHERE st.item_id = i.id) as tx_count,
           (SELECT COALESCE(SUM(st.qty), 0) FROM stock_transactions st WHERE st.item_id = i.id AND st.transaction_type = 'in') as total_in,
           (SELECT COALESCE(SUM(st.qty), 0) FROM stock_transactions st WHERE st.item_id = i.id AND st.transaction_type IN ('out','transfer_out')) as total_out
    FROM items i
    JOIN categories c ON i.category_id = c.id
    WHERE i.is_active = 1
    ORDER BY i.item_code ASC
";
$stmt = $pdo->query($sql);
$items = $stmt->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/report_print.php';
?>

<?php renderReportPrintHeader('Laporan Rekap Stok & Mutasi'); ?>

<div class="card card-outline card-primary">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title"><i class="fas fa-clipboard-check mr-2"></i> Rekap Stok & Mutasi</h3>
        <div class="ml-auto d-flex gap-2">
            <a href="export_excel.php?type=stock_report" class="btn btn-success btn-sm"><i class="fas fa-file-excel mr-1"></i> Export Excel</a>
            <a href="export_csv.php?type=stock_report" class="btn btn-info btn-sm ml-1"><i class="fas fa-file-csv mr-1"></i> Export CSV</a>
            <button class="btn btn-default btn-sm ml-1" onclick="window.print()"><i class="fas fa-print mr-1"></i> Cetak</button>
        </div>
    </div>
    <div class="card-body">
        <table id="stockReportTable" class="table table-bordered table-striped w-100" style="font-size: 13px;">
            <thead class="bg-light">
                <tr>
                    <th width="10%">Kode</th>
                    <th width="12%">Kategori</th>
                    <th width="28%">Nama Barang</th>
                    <th width="8%" class="text-center">Satuan</th>
                    <th width="10%" class="text-center text-success">Total In</th>
                    <th width="10%" class="text-center text-danger">Total Out</th>
                    <th width="12%" class="text-center">Stok Sekarang</th>
                    <th width="5%" class="text-center">Mutasi</th>
                    <th width="5%" class="text-center col-detail-print">Detail</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                <?php $isDanger = ($item['current_stock'] <= $item['minimum_stock']); ?>
                <tr>
                    <td><strong><?= sanitize($item['item_code']) ?></strong></td>
                    <td><?= sanitize($item['category_name']) ?></td>
                    <td>
                        <?= sanitize($item['description']) ?>
                        <?php if ($isDanger): ?><span class="badge badge-danger ml-1">Low</span><?php endif; ?>
                    </td>
                    <td class="text-center"><?= sanitize($item['uom']) ?></td>
                    <td class="text-center text-success font-weight-bold"><?= (float)$item['total_in'] ?></td>
                    <td class="text-center text-danger font-weight-bold"><?= (float)$item['total_out'] ?></td>
                    <td class="text-center font-weight-bold <?= $isDanger ? 'text-danger' : 'text-primary' ?>" style="font-size:15px;">
                        <?= (float)$item['current_stock'] ?>
                    </td>
                    <td class="text-center"><?= $item['tx_count'] ?> trx</td>
                    <td class="text-center col-detail-print">
                        <a href="stock_report.php?item_id=<?= $item['id'] ?>" class="btn btn-outline-info btn-sm" data-toggle="tooltip" title="Kartu Stok">
                            <i class="fas fa-search"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php renderReportPrintFooter(); ?>

<?php
$extraJS = <<<'JS'
<script>$(document).ready(function() { initDataTable('#stockReportTable'); });</script>
JS;
require_once __DIR__ . '/../../includes/footer.php';
?>
