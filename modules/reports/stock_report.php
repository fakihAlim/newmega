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

// Filters
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$filterCategory = $_GET['category_id'] ?? '';
$filterLocation = $_GET['warehouse_location'] ?? '';

$periodText = '';
if ($startDate && $endDate) {
    $periodText = 'Periode: ' . date('d-m-Y', strtotime($startDate)) . ' s/d ' . date('d-m-Y', strtotime($endDate));
} elseif ($startDate) {
    $periodText = 'Periode Mulai: ' . date('d-m-Y', strtotime($startDate));
} elseif ($endDate) {
    $periodText = 'Periode Selesai: ' . date('d-m-Y', strtotime($endDate));
}

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
    
    $txQuery = "
        SELECT st.*, u.full_name as user_name, p.name as project_name
        FROM stock_transactions st
        LEFT JOIN users u ON st.created_by = u.id
        LEFT JOIN projects p ON st.project_id = p.id
        WHERE st.item_id = :item_id
    ";
    $txParams = ['item_id' => $itemId];
    if ($startDate) {
        $txQuery .= " AND st.created_at >= :start_date";
        $txParams['start_date'] = $startDate . ' 00:00:00';
    }
    if ($endDate) {
        $txQuery .= " AND st.created_at <= :end_date";
        $txParams['end_date'] = $endDate . ' 23:59:59';
    }
    $txQuery .= " ORDER BY st.created_at DESC";
    
    $stmtTx = $pdo->prepare($txQuery);
    $stmtTx->execute($txParams);
    $transactions = $stmtTx->fetchAll();
    
    $pageTitle = 'Kartu Stok: ' . sanitize($item['item_code']);
    $breadcrumbs[] = ['label' => 'Kartu Stok'];
    
    require_once __DIR__ . '/../../includes/header.php';
    require_once __DIR__ . '/../../includes/report_print.php';
    ?>

    <?php renderReportPrintHeader('Kartu Stok: ' . sanitize($item['item_code']) . ' — ' . sanitize($item['description']), $periodText); ?>
    
    <!-- Filter Card for Kartu Stok -->
    <div class="card card-default d-print-none mb-3">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-filter mr-2"></i>Filter Tanggal</h3>
            <div class="card-tools">
                <button type="button" class="btn btn-tool" data-card-widget="collapse">
                    <i class="fas fa-minus"></i>
                </button>
            </div>
        </div>
        <form method="GET" action="">
            <input type="hidden" name="item_id" value="<?= sanitize($itemId) ?>">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 col-sm-6">
                        <div class="form-group mb-2 mb-md-0">
                            <label>Tanggal Mulai</label>
                            <input type="date" name="start_date" class="form-control form-control-sm" value="<?= sanitize($startDate) ?>">
                        </div>
                    </div>
                    <div class="col-md-6 col-sm-6">
                        <div class="form-group mb-2 mb-md-0">
                            <label>Tanggal Selesai</label>
                            <input type="date" name="end_date" class="form-control form-control-sm" value="<?= sanitize($endDate) ?>">
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-footer text-right">
                <a href="stock_report.php?item_id=<?= $itemId ?>" class="btn btn-secondary mr-2"><i class="fas fa-undo mr-1"></i> Reset</a>
                <button type="submit" class="btn btn-primary"><i class="fas fa-search mr-1"></i> Filter</button>
            </div>
        </form>
    </div>

    <div class="card card-outline card-primary">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title">
                <i class="fas fa-clipboard-check mr-2"></i> 
                Kartu Stok: <strong><?= sanitize($item['item_code']) ?></strong> — <?= sanitize($item['description']) ?>
            </h3>
            <div class="ml-auto d-flex gap-2 align-items-center">
                <a href="export_excel.php?<?= http_build_query(array_merge($_GET, ['type' => 'stock_detail', 'item_id' => $item['id']])) ?>" class="btn btn-success btn-sm"><i class="fas fa-file-excel mr-1"></i> Export Excel</a>
                <a href="export_csv.php?<?= http_build_query(array_merge($_GET, ['type' => 'stock_detail', 'item_id' => $item['id']])) ?>" class="btn btn-info btn-sm ml-1"><i class="fas fa-file-csv mr-1"></i> Export CSV</a>
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

// Fetch categories for filter dropdown
$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC")->fetchAll();

$whereItem = "WHERE i.is_active = 1";
$queryParams = [];

if ($filterCategory) {
    $whereItem .= " AND i.category_id = :category_id";
    $queryParams['category_id'] = $filterCategory;
}
if ($filterLocation) {
    $whereItem .= " AND i.warehouse_location LIKE :location";
    $queryParams['location'] = '%' . $filterLocation . '%';
}

$dateCond = "";
if ($startDate) {
    $dateCond .= " AND st.created_at >= " . $pdo->quote($startDate . ' 00:00:00');
}
if ($endDate) {
    $dateCond .= " AND st.created_at <= " . $pdo->quote($endDate . ' 23:59:59');
}

$sql = "
    SELECT i.*, c.name as category_name,
           (SELECT COUNT(*) FROM stock_transactions st WHERE st.item_id = i.id $dateCond) as tx_count,
           (SELECT COALESCE(SUM(st.qty), 0) FROM stock_transactions st WHERE st.item_id = i.id AND st.transaction_type = 'in' $dateCond) as total_in,
           (SELECT COALESCE(SUM(st.qty), 0) FROM stock_transactions st WHERE st.item_id = i.id AND st.transaction_type IN ('out','transfer_out') $dateCond) as total_out
    FROM items i
    JOIN categories c ON i.category_id = c.id
    $whereItem
    ORDER BY i.item_code ASC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($queryParams);
$items = $stmt->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/report_print.php';
?>

<?php renderReportPrintHeader('Laporan Rekap Stok & Mutasi', $periodText); ?>

<!-- Filter Card -->
<div class="card card-default d-print-none mb-3">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-filter mr-2"></i>Filter Data</h3>
        <div class="card-tools">
            <button type="button" class="btn btn-tool" data-card-widget="collapse">
                <i class="fas fa-minus"></i>
            </button>
        </div>
    </div>
    <form method="GET" action="">
        <div class="card-body">
            <div class="row">
                <div class="col-md-3 col-sm-6">
                    <div class="form-group mb-2 mb-md-0">
                        <label>Tanggal Mulai</label>
                        <input type="date" name="start_date" class="form-control form-control-sm" value="<?= sanitize($startDate) ?>">
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="form-group mb-2 mb-md-0">
                        <label>Tanggal Selesai</label>
                        <input type="date" name="end_date" class="form-control form-control-sm" value="<?= sanitize($endDate) ?>">
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="form-group mb-2 mb-md-0">
                        <label>Kategori</label>
                        <select name="category_id" class="form-control form-control-sm select2">
                            <option value="">-- Semua Kategori --</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= $filterCategory == $cat['id'] ? 'selected' : '' ?>>
                                    <?= sanitize($cat['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="form-group mb-2 mb-md-0">
                        <label>Lokasi Gudang</label>
                        <input type="text" name="warehouse_location" class="form-control form-control-sm" placeholder="Contoh: Rak A" value="<?= sanitize($filterLocation) ?>">
                    </div>
                </div>
            </div>
        </div>
        <div class="card-footer text-right">
            <a href="stock_report.php" class="btn btn-secondary mr-2"><i class="fas fa-undo mr-1"></i> Reset</a>
            <button type="submit" class="btn btn-primary"><i class="fas fa-search mr-1"></i> Filter</button>
        </div>
    </form>
</div>

<div class="card card-outline card-primary">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title"><i class="fas fa-clipboard-check mr-2"></i> Rekap Stok & Mutasi</h3>
        <div class="ml-auto d-flex gap-2">
            <a href="export_excel.php?<?= http_build_query(array_merge($_GET, ['type' => 'stock_report'])) ?>" class="btn btn-success btn-sm"><i class="fas fa-file-excel mr-1"></i> Export Excel</a>
            <a href="export_csv.php?<?= http_build_query(array_merge($_GET, ['type' => 'stock_report'])) ?>" class="btn btn-info btn-sm ml-1"><i class="fas fa-file-csv mr-1"></i> Export CSV</a>
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
                        <a href="stock_report.php?<?= http_build_query(array_merge($_GET, ['item_id' => $item['id']])) ?>" class="btn btn-outline-info btn-sm" data-toggle="tooltip" title="Kartu Stok">
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
<script>
$(document).ready(function() {
    initDataTable('#stockReportTable');
    $('.select2').select2({
        theme: 'bootstrap4',
        width: '100%'
    });
});
</script>
JS;
require_once __DIR__ . '/../../includes/footer.php';
?>
