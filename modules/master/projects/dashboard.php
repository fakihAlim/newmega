<?php
/**
 * Project Dashboard - Comprehensive per-project overview
 */
require_once __DIR__ . '/../../../includes/auth.php';
requirePermission('project_dashboard');

$id = $_GET['id'] ?? 0;

$stmtProject = $pdo->prepare("
    SELECT p.*, c.company_name as customer_name, c.abbreviation as customer_code, u.full_name as pm_name
    FROM projects p
    JOIN customers c ON p.customer_id = c.id
    LEFT JOIN users u ON p.project_manager_id = u.id
    WHERE p.id = ?
");
$stmtProject->execute([$id]);
$project = $stmtProject->fetch();

if (!$project) {
    setFlash('danger', 'Proyek tidak ditemukan.');
    header('Location: index.php');
    exit;
}

// ======== DATA AGGREGATION ========

// 1. Material Requests
$stmtMR = $pdo->prepare("SELECT status, COUNT(*) as total FROM material_requests WHERE project_id = ? GROUP BY status");
$stmtMR->execute([$id]);
$mrStats = $stmtMR->fetchAll(PDO::FETCH_KEY_PAIR);
$totalMR = array_sum($mrStats);

// Recent MRs
$stmtMRRecent = $pdo->prepare("
    SELECT mr.mr_number, mr.status, mr.request_date, u.full_name as requester
    FROM material_requests mr LEFT JOIN users u ON mr.requested_by = u.id
    WHERE mr.project_id = ? ORDER BY mr.id DESC LIMIT 5
");
$stmtMRRecent->execute([$id]);
$recentMRs = $stmtMRRecent->fetchAll();

// 2. Purchase Orders (via MR links)
$stmtPO = $pdo->prepare("
    SELECT po.status, COUNT(DISTINCT po.id) as total, COALESCE(SUM(po.total), 0) as value
    FROM purchase_orders po
    JOIN po_mr_links pml ON pml.po_id = po.id
    JOIN material_requests mr ON pml.mr_id = mr.id
    WHERE mr.project_id = ? AND po.status NOT IN ('draft','cancelled','rejected')
    GROUP BY po.status
");
$stmtPO->execute([$id]);
$poRows = $stmtPO->fetchAll();
$totalPOCount = 0; $totalPOValue = 0;
foreach ($poRows as $r) { $totalPOCount += $r['total']; $totalPOValue += $r['value']; }

// 3. Goods Receiving Progress (PO)
$stmtRcv = $pdo->prepare("
    SELECT 
        COALESCE(SUM(gri.qty_received), 0) as total_received,
        COALESCE(SUM(gri.qty_rejected), 0) as total_rejected
    FROM goods_receiving_items gri
    JOIN goods_receivings gr ON gri.receiving_id = gr.id
    JOIN purchase_orders po ON gr.po_id = po.id
    JOIN po_mr_links pml ON pml.po_id = po.id
    JOIN material_requests mr ON pml.mr_id = mr.id
    WHERE mr.project_id = ?
");
$stmtRcv->execute([$id]);
$receiving = $stmtRcv->fetch();

$stmtPOItems = $pdo->prepare("
    SELECT COALESCE(SUM(poi.qty), 0) as total_ordered
    FROM purchase_order_items poi
    JOIN purchase_orders po ON poi.po_id = po.id
    JOIN po_mr_links pml ON pml.po_id = po.id
    JOIN material_requests mr ON pml.mr_id = mr.id
    WHERE mr.project_id = ? AND po.status NOT IN ('draft','cancelled','rejected')
");
$stmtPOItems->execute([$id]);
$totalOrdered = $stmtPOItems->fetch()['total_ordered'];

// 7. Warehouse Transfers to this project
$stmtWT = $pdo->prepare("
    SELECT wt.id as transfer_id, wti.item_id, wti.qty
    FROM warehouse_transfers wt
    JOIN warehouse_transfer_items wti ON wti.transfer_id = wt.id
    WHERE wt.to_project_id = ? AND wt.status = 'completed'
");
$stmtWT->execute([$id]);
$wtRows = $stmtWT->fetchAll();

$total_transfers_count = count(array_unique(array_column($wtRows, 'transfer_id')));
$total_transfers_items = 0;
$total_transfers_value = 0;

$latestPricesCache = [];
foreach ($wtRows as $wtRow) {
    $total_transfers_items += $wtRow['qty'];
    $iId = $wtRow['item_id'];
    
    if (!isset($latestPricesCache[$iId])) {
        $stmtP = $pdo->prepare("
            SELECT poi.unit_price
            FROM purchase_order_items poi
            JOIN purchase_orders po ON poi.po_id = po.id
            WHERE poi.item_id = ? AND po.status NOT IN ('draft', 'cancelled', 'rejected')
            ORDER BY po.po_date DESC, po.id DESC
            LIMIT 1
        ");
        $stmtP->execute([$iId]);
        $price = $stmtP->fetchColumn();
        $latestPricesCache[$iId] = $price ? (float)$price : 0;
    }
    
    $total_transfers_value += ($wtRow['qty'] * $latestPricesCache[$iId]);
}

$transfers = [
    'total_transfers' => $total_transfers_count,
    'total_items' => $total_transfers_items,
    'total_value' => $total_transfers_value
];

// Calculate Combined Progress
$combinedTarget = $totalOrdered + $transfers['total_items'];
$combinedReceived = $receiving['total_received'] + $transfers['total_items'];
$rcvPct = $combinedTarget > 0 ? round(($combinedReceived / $combinedTarget) * 100, 1) : 0;

// 4. Vendor Payments (Cash Out)
$stmtVP = $pdo->prepare("
    SELECT COALESCE(SUM(vp.amount), 0) as total_paid
    FROM vendor_payments vp
    JOIN purchase_orders po ON vp.po_id = po.id
    JOIN po_mr_links pml ON pml.po_id = po.id
    JOIN material_requests mr ON pml.mr_id = mr.id
    WHERE mr.project_id = ?
");
$stmtVP->execute([$id]);
$totalVendorPaid = $stmtVP->fetch()['total_paid'];
$vendorOutstanding = $totalPOValue - $totalVendorPaid;

// 5. Quotations & Invoices (Revenue)
$stmtQ = $pdo->prepare("
    SELECT COALESCE(SUM(total), 0) as total_quotation, COUNT(*) as cnt
    FROM quotations WHERE project_id = ? AND status NOT IN ('draft','rejected')
");
$stmtQ->execute([$id]);
$quotation = $stmtQ->fetch();

$stmtInv = $pdo->prepare("
    SELECT COALESCE(SUM(inv.total), 0) as total_invoice, COUNT(*) as cnt
    FROM invoices inv
    JOIN quotations q ON inv.quotation_id = q.id
    WHERE q.project_id = ? AND inv.status NOT IN ('draft','rejected')
");
$stmtInv->execute([$id]);
$invoice = $stmtInv->fetch();

// 6. Customer Payments (Cash In)
$stmtCP = $pdo->prepare("
    SELECT COALESCE(SUM(cp.amount), 0) as total_received
    FROM customer_payments cp
    JOIN invoices inv ON cp.invoice_id = inv.id
    JOIN quotations q ON inv.quotation_id = q.id
    WHERE q.project_id = ?
");
$stmtCP->execute([$id]);
$totalCustReceived = $stmtCP->fetch()['total_received'];
$custOutstanding = $invoice['total_invoice'] - $totalCustReceived;

// 8. Profit/Loss & Total Expenditure
$totalPengeluaran = $totalPOValue + $transfers['total_value'];
$grossProfit = $invoice['total_invoice'] - $totalPengeluaran;
$netCashFlow = $totalCustReceived - $totalVendorPaid;
$budgetUsedPct = $project['budget'] > 0 ? round(($totalPengeluaran / $project['budget']) * 100, 1) : 0;

// 9. Timeline / Activity Log
$stmtTimeline = $pdo->prepare("
    (SELECT 'MR' as type, mr.mr_number as doc_no, mr.status, mr.created_at as event_date, u.full_name as actor
     FROM material_requests mr LEFT JOIN users u ON mr.requested_by = u.id WHERE mr.project_id = ?)
    UNION ALL
    (SELECT 'PO' as type, po.po_number as doc_no, po.status, po.created_at as event_date, u.full_name as actor
     FROM purchase_orders po JOIN po_mr_links pml ON pml.po_id = po.id JOIN material_requests mr ON pml.mr_id = mr.id
     LEFT JOIN users u ON po.created_by = u.id WHERE mr.project_id = ?)
    UNION ALL
    (SELECT 'Quotation' as type, q.quotation_no as doc_no, q.status, q.created_at as event_date, u.full_name as actor
     FROM quotations q LEFT JOIN users u ON q.created_by = u.id WHERE q.project_id = ?)
    UNION ALL
    (SELECT 'Invoice' as type, inv.invoice_no as doc_no, inv.status, inv.created_at as event_date, u.full_name as actor
     FROM invoices inv JOIN quotations q ON inv.quotation_id = q.id LEFT JOIN users u ON inv.created_by = u.id WHERE q.project_id = ?)
    ORDER BY event_date DESC
    LIMIT 15
");
$stmtTimeline->execute([$id, $id, $id, $id]);
$timeline = $stmtTimeline->fetchAll();

$pageTitle = 'Dashboard Proyek: ' . sanitize($project['name']);
$breadcrumbs = [
    ['label' => 'Master Data', 'url' => '#'],
    ['label' => 'Proyek', 'url' => 'index.php'],
    ['label' => 'Dashboard']
];

require_once __DIR__ . '/../../../includes/header.php';
?>

<!-- Project Header -->
<div class="card card-outline card-warning mb-3">
    <div class="card-body py-3">
        <div class="row align-items-center">
            <div class="col-md-6">
                <h4 class="mb-1 font-weight-bold"><?= sanitize($project['name']) ?></h4>
                <div style="font-size:14px;">
                    <i class="fas fa-map-marker-alt text-danger mr-1"></i> <?= sanitize($project['location']) ?: '-' ?>
                    <span class="mx-2">|</span>
                    <i class="fas fa-building text-primary mr-1"></i> <?= sanitize($project['customer_name']) ?>
                    <span class="mx-2">|</span>
                    <i class="fas fa-hard-hat text-warning mr-1"></i> PM: <?= sanitize($project['pm_name']) ?: '-' ?>
                </div>
            </div>
            <div class="col-md-6 text-right">
                <?= getStatusBadge($project['status']) ?>
                <span class="ml-3" style="font-size:13px;">
                    <?php if ($project['start_date']): ?>
                        <i class="far fa-calendar mr-1"></i>
                        <?= date('d M Y', strtotime($project['start_date'])) ?> — <?= $project['end_date'] ? date('d M Y', strtotime($project['end_date'])) : 'TBD' ?>
                    <?php endif; ?>
                </span>
                <a href="item_usage.php?id=<?= $id ?>" class="btn btn-sm btn-info ml-3"><i class="fas fa-box-open mr-1"></i> Rincian Barang</a>
                <a href="index.php" class="btn btn-sm btn-secondary ml-2"><i class="fas fa-arrow-left mr-1"></i> Kembali</a>
            </div>
        </div>
    </div>
</div>

<!-- Summary Cards Row 1: Budget & Financials -->
<div class="row">
    <div class="col-md-3">
        <div class="small-box bg-info">
            <div class="inner">
                <h4><?= formatRupiah($project['budget']) ?></h4>
                <p>Budget Proyek</p>
            </div>
            <div class="icon"><i class="fas fa-wallet"></i></div>
            <div class="small-box-footer" style="font-size:12px;">
                Terpakai: <strong><?= $budgetUsedPct ?>%</strong>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="small-box bg-danger">
            <div class="inner">
                <h4><?= formatRupiah($totalPengeluaran) ?></h4>
                <p>Total Pengeluaran (PO+Gudang)</p>
            </div>
            <div class="icon"><i class="fas fa-shopping-cart"></i></div>
            <div class="small-box-footer" style="font-size:12px;">
                PO: <?= formatRupiah($totalPOValue) ?> | Gudang: <?= formatRupiah($transfers['total_value']) ?>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="small-box bg-success">
            <div class="inner">
                <h4><?= formatRupiah($invoice['total_invoice']) ?></h4>
                <p>Total Pendapatan (Invoice)</p>
            </div>
            <div class="icon"><i class="fas fa-file-invoice-dollar"></i></div>
            <div class="small-box-footer" style="font-size:12px;">
                <?= $invoice['cnt'] ?> Invoice | Diterima: <?= formatRupiah($totalCustReceived) ?>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="small-box <?= $grossProfit >= 0 ? 'bg-primary' : 'bg-warning' ?>">
            <div class="inner">
                <h4><?= formatRupiah($grossProfit) ?></h4>
                <p>Laba Kotor (Gross Profit)</p>
            </div>
            <div class="icon"><i class="fas fa-balance-scale"></i></div>
            <div class="small-box-footer" style="font-size:12px;">
                Cash Flow: <?= formatRupiah($netCashFlow) ?>
            </div>
        </div>
    </div>
</div>

<!-- Row 2: Budget Progress + MR/PO Status + Receiving -->
<div class="row">
    <!-- Budget Usage -->
    <div class="col-md-4">
        <div class="card card-outline card-info">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-chart-pie mr-2"></i> Pemakaian Budget</h3></div>
            <div class="card-body">
                <div class="progress-group mb-3">
                    <span class="progress-text">PO & Gudang vs Budget</span>
                    <span class="float-right"><b><?= $budgetUsedPct ?>%</b></span>
                    <div class="progress">
                        <div class="progress-bar <?= $budgetUsedPct > 90 ? 'bg-danger' : ($budgetUsedPct > 70 ? 'bg-warning' : 'bg-info') ?>" style="width: <?= min($budgetUsedPct, 100) ?>%"></div>
                    </div>
                </div>
                <table class="table table-sm table-borderless" style="font-size:13px;">
                    <tr><td>Budget</td><td class="text-right font-weight-bold"><?= formatRupiah($project['budget']) ?></td></tr>
                    <tr><td><i class="fas fa-shopping-cart text-danger mr-1"></i> Nilai PO</td><td class="text-right font-weight-bold text-danger"><?= formatRupiah($totalPOValue) ?></td></tr>
                    <tr><td><i class="fas fa-exchange-alt text-info mr-1"></i> Nilai Gudang</td><td class="text-right font-weight-bold text-info"><?= formatRupiah($transfers['total_value']) ?></td></tr>
                    <tr style="border-top:1px solid #eee;">
                        <td>Sisa Budget</td>
                        <td class="text-right font-weight-bold <?= ($project['budget'] - $totalPengeluaran) >= 0 ? 'text-success' : 'text-danger' ?>">
                            <?= formatRupiah($project['budget'] - $totalPengeluaran) ?>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
    </div>

    <!-- MR & PO Status -->
    <div class="col-md-4">
        <div class="card card-outline card-primary">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-clipboard-list mr-2"></i> Status MR & PO</h3></div>
            <div class="card-body">
                <h6 class="font-weight-bold mb-2">Material Request (<?= $totalMR ?>)</h6>
                <?php
                $mrColors = ['draft'=>'secondary','pending'=>'warning','approved'=>'success','rejected'=>'danger','completed'=>'info'];
                foreach ($mrStats as $st => $cnt): ?>
                    <span class="badge badge-<?= $mrColors[$st] ?? 'secondary' ?> mr-1" style="font-size:12px;">
                        <?= ucfirst($st) ?>: <?= $cnt ?>
                    </span>
                <?php endforeach; ?>
                <?php if ($totalMR === 0): ?><span class="text-muted">Belum ada MR</span><?php endif; ?>
                
                <hr class="my-3">
                
                <h6 class="font-weight-bold mb-2">Purchase Order (<?= $totalPOCount ?>)</h6>
                <?php foreach ($poRows as $r): ?>
                    <span class="badge badge-<?= $mrColors[$r['status']] ?? 'secondary' ?> mr-1" style="font-size:12px;">
                        <?= ucfirst($r['status']) ?>: <?= $r['total'] ?>
                    </span>
                <?php endforeach; ?>
                <?php if ($totalPOCount === 0): ?><span class="text-muted">Belum ada PO</span><?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Receiving Progress -->
    <div class="col-md-4">
        <div class="card card-outline card-success">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-dolly mr-2"></i> Progress Penerimaan</h3></div>
            <div class="card-body">
                <div class="progress-group mb-3">
                    <span class="progress-text">Barang Diterima</span>
                    <span class="float-right"><b><?= $rcvPct ?>%</b></span>
                    <div class="progress">
                        <div class="progress-bar bg-success" style="width: <?= $rcvPct ?>%"></div>
                    </div>
                </div>
                <table class="table table-sm table-borderless" style="font-size:13px;">
                    <tr><td>Total Kebutuhan (PO + Gudang)</td><td class="text-right font-weight-bold"><?= number_format($combinedTarget, 0) ?></td></tr>
                    <tr><td><i class="fas fa-truck text-success mr-1"></i> Diterima dari PO</td><td class="text-right font-weight-bold text-success"><?= number_format($receiving['total_received'], 0) ?></td></tr>
                    <tr><td><i class="fas fa-exchange-alt text-info mr-1"></i> Diterima dari Gudang</td><td class="text-right font-weight-bold text-info"><?= number_format($transfers['total_items'], 0) ?></td></tr>
                    <tr><td><i class="fas fa-times-circle text-danger mr-1"></i> Ditolak/Rusak (PO)</td><td class="text-right font-weight-bold text-danger"><?= number_format($receiving['total_rejected'], 0) ?></td></tr>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Row 3: Outstanding & Cash Flow -->
<div class="row">
    <div class="col-md-6">
        <div class="card card-outline card-danger">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-money-check-alt mr-2"></i> Outstanding Hutang (Vendor)</h3></div>
            <div class="card-body">
                <table class="table table-sm table-bordered" style="font-size:13px;">
                    <tr><td>Total Nilai PO</td><td class="text-right font-weight-bold"><?= formatRupiah($totalPOValue) ?></td></tr>
                    <tr class="table-success"><td>Sudah Dibayar</td><td class="text-right font-weight-bold text-success"><?= formatRupiah($totalVendorPaid) ?></td></tr>
                    <tr class="<?= $vendorOutstanding > 0 ? 'table-danger' : 'table-success' ?>">
                        <td class="font-weight-bold" style="font-size:14px;">Sisa Hutang</td>
                        <td class="text-right font-weight-bold" style="font-size:16px;"><?= formatRupiah($vendorOutstanding) ?>
                            <?= $vendorOutstanding <= 0 ? ' <i class="fas fa-check-circle text-success"></i>' : '' ?>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card card-outline card-success">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-hand-holding-usd mr-2"></i> Outstanding Piutang (Customer)</h3></div>
            <div class="card-body">
                <table class="table table-sm table-bordered" style="font-size:13px;">
                    <tr><td>Total Invoice</td><td class="text-right font-weight-bold"><?= formatRupiah($invoice['total_invoice']) ?></td></tr>
                    <tr class="table-success"><td>Sudah Diterima</td><td class="text-right font-weight-bold text-success"><?= formatRupiah($totalCustReceived) ?></td></tr>
                    <tr class="<?= $custOutstanding > 0 ? 'table-warning' : 'table-success' ?>">
                        <td class="font-weight-bold" style="font-size:14px;">Sisa Piutang</td>
                        <td class="text-right font-weight-bold" style="font-size:16px;"><?= formatRupiah($custOutstanding) ?>
                            <?= $custOutstanding <= 0 ? ' <i class="fas fa-check-circle text-success"></i>' : '' ?>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Row 4: Recent MRs & Timeline -->
<div class="row">
    <div class="col-md-5">
        <div class="card card-outline card-secondary">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-clipboard-list mr-2"></i> MR Terbaru</h3></div>
            <div class="card-body p-0">
                <table class="table table-sm table-striped m-0" style="font-size:13px;">
                    <thead class="bg-light">
                        <tr>
                            <th>No. MR</th>
                            <th>Tanggal</th>
                            <th>Pemohon</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentMRs as $mr): ?>
                        <tr>
                            <td><strong><?= sanitize($mr['mr_number']) ?></strong></td>
                            <td><?= date('d-m-Y', strtotime($mr['request_date'])) ?></td>
                            <td><?= sanitize($mr['requester']) ?></td>
                            <td><?= getStatusBadge($mr['status']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($recentMRs)): ?>
                        <tr><td colspan="4" class="text-center text-muted">Belum ada MR</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <div class="col-md-7">
        <div class="card card-outline card-secondary">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-stream mr-2"></i> Timeline Aktivitas</h3></div>
            <div class="card-body p-0" style="max-height:350px; overflow-y:auto;">
                <div class="timeline timeline-inverse px-3 pt-3">
                    <?php foreach ($timeline as $t): 
                        $typeIcons = [
                            'MR' => 'fas fa-clipboard-list bg-primary',
                            'PO' => 'fas fa-file-invoice bg-danger',
                            'Quotation' => 'fas fa-file-alt bg-info',
                            'Invoice' => 'fas fa-file-invoice-dollar bg-success',
                        ];
                    ?>
                    <div>
                        <i class="<?= $typeIcons[$t['type']] ?? 'fas fa-circle bg-secondary' ?> timeline-item-icon" style="width:28px;height:28px;line-height:28px;font-size:12px;border-radius:50%;text-align:center;color:#fff;position:absolute;left:-14px;"></i>
                        <div class="timeline-item" style="margin-left:20px;padding:8px 12px;margin-bottom:8px;border:1px solid #e9ecef;border-radius:5px;font-size:13px;">
                            <span class="time" style="font-size:11px;color:#999;"><i class="far fa-clock mr-1"></i><?= date('d M Y H:i', strtotime($t['event_date'])) ?></span>
                            <div>
                                <span class="badge badge-light mr-1"><?= $t['type'] ?></span>
                                <strong><?= sanitize($t['doc_no']) ?></strong>
                                <?= getStatusBadge($t['status']) ?>
                                <span class="text-muted ml-1">oleh <?= sanitize($t['actor']) ?></span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php if (empty($timeline)): ?>
                    <p class="text-center text-muted py-3">Belum ada aktivitas.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.timeline { position: relative; padding-left: 20px; }
.timeline::before { content: ''; position: absolute; left: 5px; top: 0; bottom: 0; width: 2px; background: #dee2e6; }
.timeline > div { position: relative; margin-bottom: 5px; }
.timeline-item-icon { position: absolute; left: -14px; z-index: 1; }
.small-box .small-box-footer { padding: 3px 10px; background: rgba(0,0,0,.1); color: rgba(255,255,255,.8); display: block; text-decoration: none; }
</style>

<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>
