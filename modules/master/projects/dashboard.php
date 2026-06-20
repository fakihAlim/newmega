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

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&family=Work+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">

<style>
/* Reset Font Family for the whole page */
.content-wrapper, .content-wrapper * {
    font-family: 'Work Sans', sans-serif;
}
h1, h2, h3, h4, h5, h6, 
.card-title, .metric-value, .btn-custom {
    font-family: 'Montserrat', sans-serif !important;
}

/* Colors & Variables */
:root {
    --primary: #1e293b;
    --primary-dark: #0f172a;
    --accent: #f28c28;
    --accent-hover: #d67417;
    --border-color: #cbd5e1;
    --border-light: #e2e8f0;
    --bg-light: #f8fafc;
    --text-slate: #64748b;
    --text-dark: #0f172a;
}

/* Page Background Overwrite */
.content-wrapper {
    background-color: var(--bg-light) !important;
}

/* Badges / Chips Override */
.badge {
    font-family: 'Work Sans', sans-serif !important;
    font-size: 11px !important;
    font-weight: 600 !important;
    text-transform: uppercase !important;
    letter-spacing: 0.05em !important;
    padding: 6px 12px !important;
    border-radius: 4px !important;
    border: none !important;
    box-shadow: none !important;
}
.badge-secondary { background-color: #e2e8f0 !important; color: #475569 !important; }
.badge-warning { background-color: #fef3c7 !important; color: #b45309 !important; }
.badge-success { background-color: #dcfce7 !important; color: #15803d !important; }
.badge-danger { background-color: #fee2e2 !important; color: #b91c1c !important; }
.badge-info { background-color: #e0f2fe !important; color: #0369a1 !important; }
.badge-dark { background-color: #f1f5f9 !important; color: #1e293b !important; }
.badge-primary { background-color: #dbeafe !important; color: #1d4ed8 !important; }

/* Custom Cards/Panels */
.card {
    background: #ffffff !important;
    border: 1px solid var(--border-light) !important;
    border-radius: 4px !important;
    box-shadow: none !important;
    transition: transform 0.2s ease, box-shadow 0.2s ease !important;
    margin-bottom: 24px !important;
}
.card:hover {
    transform: translate(-4px, -4px) !important;
    box-shadow: 4px 4px 0px var(--primary) !important;
}
.card-header {
    border-bottom: 1px solid var(--border-light) !important;
    background: #ffffff !important;
    border-radius: 4px 4px 0 0 !important;
    padding: 16px 20px !important;
}
.card-title {
    font-weight: 700 !important;
    font-size: 12px !important;
    letter-spacing: 0.08em !important;
    text-transform: uppercase !important;
    color: var(--primary) !important;
    margin: 0 !important;
}
.card-body {
    padding: 20px !important;
}

/* Custom Buttons */
.btn-custom {
    font-size: 11px !important;
    font-weight: 600 !important;
    text-transform: uppercase !important;
    letter-spacing: 0.08em !important;
    padding: 8px 16px !important;
    border-radius: 4px !important;
    transition: all 0.2s ease !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    cursor: pointer !important;
    text-decoration: none !important;
    border: none !important;
}
.btn-custom-primary {
    background-color: var(--accent) !important;
    color: #ffffff !important;
}
.btn-custom-primary:hover {
    background-color: var(--accent-hover) !important;
    color: #ffffff !important;
}
.btn-custom-slate {
    background-color: var(--primary) !important;
    color: #ffffff !important;
}
.btn-custom-slate:hover {
    background-color: var(--primary-dark) !important;
    color: #ffffff !important;
}
.btn-custom-outline {
    background-color: transparent !important;
    border: 2px solid var(--primary) !important;
    color: var(--primary) !important;
    padding: 6px 14px !important;
}
.btn-custom-outline:hover {
    background-color: var(--primary) !important;
    color: #ffffff !important;
}

/* Project Header Area */
.project-meta-item {
    display: inline-flex;
    flex-direction: column;
    margin-right: 32px;
}
.project-meta-label {
    font-size: 10px;
    font-weight: 600;
    letter-spacing: 0.1em;
    color: var(--text-slate);
    text-transform: uppercase;
    margin-bottom: 2px;
}
.project-meta-value {
    font-size: 14px;
    font-weight: 600;
    color: var(--text-dark);
}

/* Financial Metric Cards */
.metric-card {
    background: #ffffff;
    border: 1px solid var(--border-light);
    border-radius: 4px;
    padding: 20px 24px;
    margin-bottom: 24px;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    position: relative;
    overflow: hidden;
}
.metric-card:hover {
    transform: translate(-4px, -4px);
    box-shadow: 4px 4px 0px var(--primary);
}
.metric-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    bottom: 0;
    width: 4px;
}
.border-left-slate::before { background-color: var(--primary); }
.border-left-red::before { background-color: #ba1a1a; }
.border-left-green::before { background-color: #15803d; }
.border-left-orange::before { background-color: var(--accent); }

.metric-label {
    font-size: 10px;
    font-weight: 600;
    letter-spacing: 0.1em;
    color: var(--text-slate);
    text-transform: uppercase;
    margin-bottom: 6px;
}
.metric-value {
    font-size: 18px;
    font-weight: 800;
    color: var(--text-dark);
    margin-bottom: 12px;
}
.metric-meta {
    font-size: 11px;
    color: var(--text-slate);
    border-top: 1px solid #f1f5f9;
    padding-top: 8px;
    white-space: nowrap;
    text-overflow: ellipsis;
    overflow: hidden;
}

/* Progress Bars */
.progress {
    height: 8px !important;
    background-color: #f1f5f9 !important;
    border-radius: 4px !important;
    box-shadow: none !important;
    overflow: hidden !important;
    margin-bottom: 12px !important;
}
.progress-bar {
    border-radius: 4px !important;
    box-shadow: none !important;
    background-color: var(--primary) !important;
}
.progress-bar.bg-info { background-color: var(--primary) !important; }
.progress-bar.bg-warning { background-color: var(--accent) !important; }
.progress-bar.bg-danger { background-color: #ba1a1a !important; }
.progress-bar.bg-success { background-color: #15803d !important; }

/* Dot Indicators */
.indicator-dot {
    display: inline-block;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    margin-right: 8px;
    vertical-align: middle;
}
.indicator-dot.bg-danger { background-color: #ba1a1a !important; }
.indicator-dot.bg-info { background-color: var(--primary) !important; }
.indicator-dot.bg-success { background-color: #15803d !important; }
.indicator-dot.bg-warning { background-color: var(--accent) !important; }

/* Table Overrides */
.table {
    border-collapse: collapse !important;
}
.table thead th {
    background: #f8fafc !important;
    border-bottom: 1px solid var(--border-light) !important;
    border-top: none !important;
    color: var(--text-slate) !important;
    font-weight: 600 !important;
    font-size: 11px !important;
    text-transform: uppercase !important;
    letter-spacing: 0.05em !important;
    padding: 10px 16px !important;
}
.table td {
    border-top: 1px solid var(--border-light) !important;
    padding: 12px 16px !important;
    font-size: 13px !important;
    color: #334155 !important;
}
.table-bordered th, .table-bordered td {
    border: 1px solid var(--border-light) !important;
}
.table-striped tbody tr:nth-of-type(odd) {
    background-color: #f8fafc !important;
}
.table-success, .table-success td {
    background-color: #f0fdf4 !important;
    color: #166534 !important;
}
.table-danger, .table-danger td {
    background-color: #fef2f2 !important;
    color: #991b1b !important;
}
.table-warning, .table-warning td {
    background-color: #fffbeb !important;
    color: #92400e !important;
}

/* Custom Timeline Component */
.custom-timeline {
    position: relative;
    padding: 10px 0;
}
.custom-timeline::before {
    content: '';
    position: absolute;
    top: 0;
    bottom: 0;
    left: 110px;
    width: 1px;
    background-color: var(--border-light);
}
.timeline-row {
    display: flex;
    align-items: center;
    margin-bottom: 16px;
    position: relative;
}
.timeline-date {
    width: 90px;
    text-align: right;
    font-size: 11px;
    color: var(--text-slate);
    padding-right: 10px;
}
.timeline-dot {
    width: 9px;
    height: 9px;
    background-color: #cbd5e1;
    border-radius: 50%;
    margin: 0 11px;
    z-index: 1;
    transition: background-color 0.2s ease;
}
.timeline-row:hover .timeline-dot {
    background-color: var(--accent);
}
.timeline-content {
    flex: 1;
    font-size: 13px;
    color: #334155;
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
    padding-left: 10px;
}
.timeline-type {
    font-weight: 600;
    text-transform: uppercase;
    font-size: 9px;
    color: var(--text-slate);
    background-color: #f1f5f9;
    padding: 2px 6px;
    border-radius: 4px;
}
.timeline-doc {
    font-family: 'Montserrat', sans-serif !important;
    font-weight: 700;
    color: var(--primary);
}
.timeline-actor {
    color: var(--text-slate);
    font-size: 12px;
}
</style>

<!-- Project Header -->
<div class="card mb-4">
    <div class="card-body py-4">
        <div class="row align-items-center">
            <div class="col-md-7">
                <h3 class="mb-3 font-weight-bold text-dark" style="font-size: 20px; font-weight:800; letter-spacing:-0.01em;"><?= sanitize($project['name']) ?></h3>
                <div class="d-flex flex-wrap">
                    <div class="project-meta-item">
                        <span class="project-meta-label">Lokasi</span>
                        <span class="project-meta-value"><?= sanitize($project['location']) ?: '-' ?></span>
                    </div>
                    <div class="project-meta-item">
                        <span class="project-meta-label">Pelanggan</span>
                        <span class="project-meta-value"><?= sanitize($project['customer_name']) ?></span>
                    </div>
                    <div class="project-meta-item">
                        <span class="project-meta-label">Project Manager</span>
                        <span class="project-meta-value"><?= sanitize($project['pm_name']) ?: '-' ?></span>
                    </div>
                </div>
            </div>
            <div class="col-md-5 text-right mt-3 mt-md-0">
                <?= getStatusBadge($project['status']) ?>
                <?php if ($project['start_date']): ?>
                    <span class="ml-3 font-weight-bold" style="font-size:12px; color: var(--text-slate); letter-spacing:0.05em; text-transform:uppercase;">
                        <?= date('d M Y', strtotime($project['start_date'])) ?> — <?= $project['end_date'] ? date('d M Y', strtotime($project['end_date'])) : 'TBD' ?>
                    </span>
                <?php endif; ?>
                <a href="item_usage.php?id=<?= $id ?>" class="btn-custom btn-custom-slate ml-3">Rincian Barang</a>
                <a href="index.php" class="btn-custom btn-custom-outline ml-2">Kembali</a>
            </div>
        </div>
    </div>
</div>

<!-- Summary Cards Row 1: Budget & Financials -->
<div class="row">
    <div class="col-md-3">
        <div class="metric-card border-left-slate">
            <div class="metric-label">Budget Proyek</div>
            <div class="metric-value"><?= formatRupiah($project['budget']) ?></div>
            <div class="metric-meta">
                Terpakai: <strong><?= $budgetUsedPct ?>%</strong>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="metric-card border-left-red">
            <div class="metric-label">Total Pengeluaran</div>
            <div class="metric-value"><?= formatRupiah($totalPengeluaran) ?></div>
            <div class="metric-meta">
                PO: <?= formatRupiah($totalPOValue) ?> | Gudang: <?= formatRupiah($transfers['total_value']) ?>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="metric-card border-left-green">
            <div class="metric-label">Total Pendapatan (Invoice)</div>
            <div class="metric-value"><?= formatRupiah($invoice['total_invoice']) ?></div>
            <div class="metric-meta">
                <?= $invoice['cnt'] ?> Invoice | Diterima: <?= formatRupiah($totalCustReceived) ?>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="metric-card border-left-orange">
            <div class="metric-label">Laba Kotor (Gross Profit)</div>
            <div class="metric-value"><?= formatRupiah($grossProfit) ?></div>
            <div class="metric-meta">
                Cash Flow: <?= formatRupiah($netCashFlow) ?>
            </div>
        </div>
    </div>
</div>

<!-- Charts Row -->
<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Alokasi Anggaran Proyek</h3>
            </div>
            <div class="card-body">
                <div id="chart-budget-allocation" style="min-height: 280px; display: flex; align-items: center; justify-content: center;"></div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Arus Kas & Pembayaran (AR vs AP)</h3>
            </div>
            <div class="card-body">
                <div id="chart-cashflow-comparison" style="min-height: 280px;"></div>
            </div>
        </div>
    </div>
</div>

<!-- Row 2: Budget Progress + MR/PO Status + Receiving -->
<div class="row">
    <!-- Budget Usage -->
    <div class="col-md-4">
        <div class="card">
            <div class="card-header"><h3 class="card-title">Pemakaian Budget</h3></div>
            <div class="card-body">
                <div class="progress-group mb-3" style="font-size:13px;">
                    <span class="progress-text">PO & Gudang vs Budget</span>
                    <span class="float-right"><b><?= $budgetUsedPct ?>%</b></span>
                    <div class="progress mt-1">
                        <div class="progress-bar <?= $budgetUsedPct > 90 ? 'bg-danger' : ($budgetUsedPct > 70 ? 'bg-warning' : 'bg-info') ?>" style="width: <?= min($budgetUsedPct, 100) ?>%"></div>
                    </div>
                </div>
                <table class="table table-sm table-borderless m-0" >
                    <tr>
                        <td><span class="indicator-dot bg-info"></span> Budget Total</td>
                        <td class="text-right font-weight-bold text-dark"><?= formatRupiah($project['budget']) ?></td>
                    </tr>
                    <tr>
                        <td><span class="indicator-dot bg-danger"></span> Nilai PO</td>
                        <td class="text-right font-weight-bold text-danger"><?= formatRupiah($totalPOValue) ?></td>
                    </tr>
                    <tr>
                        <td><span class="indicator-dot bg-warning"></span> Nilai Gudang</td>
                        <td class="text-right font-weight-bold text-warning"><?= formatRupiah($transfers['total_value']) ?></td>
                    </tr>
                    <tr style="border-top:1px solid #e2e8f0;">
                        <td class="pt-2 font-weight-bold">Sisa Budget</td>
                        <td class="pt-2 text-right font-weight-bold <?= ($project['budget'] - $totalPengeluaran) >= 0 ? 'text-success' : 'text-danger' ?>">
                            <?= formatRupiah($project['budget'] - $totalPengeluaran) ?>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
    </div>

    <!-- MR & PO Status -->
    <div class="col-md-4">
        <div class="card">
            <div class="card-header"><h3 class="card-title">Status MR & PO</h3></div>
            <div class="card-body">
                <h6 class="font-weight-bold mb-2 text-uppercase text-xs tracking-wider text-muted">Material Request (<?= $totalMR ?>)</h6>
                <div class="d-flex flex-wrap gap-2 mb-3">
                <?php
                $mrColors = ['draft'=>'secondary','pending'=>'warning','approved'=>'success','rejected'=>'danger','completed'=>'info'];
                foreach ($mrStats as $st => $cnt): ?>
                    <span class="badge badge-<?= $mrColors[$st] ?? 'secondary' ?>">
                        <?= $st ?>: <?= $cnt ?>
                    </span>
                <?php endforeach; ?>
                <?php if ($totalMR === 0): ?><span class="text-muted text-sm">Belum ada MR</span><?php endif; ?>
                </div>
                
                <hr class="my-3">
                
                <h6 class="font-weight-bold mb-2 text-uppercase text-xs tracking-wider text-muted">Purchase Order (<?= $totalPOCount ?>)</h6>
                <div class="d-flex flex-wrap gap-2">
                <?php foreach ($poRows as $r): ?>
                    <span class="badge badge-<?= $mrColors[$r['status']] ?? 'secondary' ?>">
                        <?= $r['status'] ?>: <?= $r['total'] ?>
                    </span>
                <?php endforeach; ?>
                <?php if ($totalPOCount === 0): ?><span class="text-muted text-sm">Belum ada PO</span><?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Receiving Progress -->
    <div class="col-md-4">
        <div class="card">
            <div class="card-header"><h3 class="card-title">Progress Penerimaan</h3></div>
            <div class="card-body">
                <div class="progress-group mb-3" style="font-size:13px;">
                    <span class="progress-text">Barang Diterima</span>
                    <span class="float-right"><b><?= $rcvPct ?>%</b></span>
                    <div class="progress mt-1">
                        <div class="progress-bar bg-success" style="width: <?= $rcvPct ?>%"></div>
                    </div>
                </div>
                <table class="table table-sm table-borderless m-0" >
                    <tr>
                        <td>Kebutuhan Total (PO + Gudang)</td>
                        <td class="text-right font-weight-bold"><?= number_format($combinedTarget, 0) ?></td>
                    </tr>
                    <tr>
                        <td><span class="indicator-dot bg-success"></span> Diterima dari PO</td>
                        <td class="text-right font-weight-bold text-success"><?= number_format($receiving['total_received'], 0) ?></td>
                    </tr>
                    <tr>
                        <td><span class="indicator-dot bg-info"></span> Diterima dari Gudang</td>
                        <td class="text-right font-weight-bold text-info"><?= number_format($transfers['total_items'], 0) ?></td>
                    </tr>
                    <tr>
                        <td><span class="indicator-dot bg-danger"></span> Ditolak/Rusak (PO)</td>
                        <td class="text-right font-weight-bold text-danger"><?= number_format($receiving['total_rejected'], 0) ?></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Row 3: Outstanding & Cash Flow -->
<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><h3 class="card-title">Outstanding Hutang (Vendor)</h3></div>
            <div class="card-body p-0">
                <table class="table table-sm table-bordered m-0" style="border:none;">
                    <tr>
                        <td class="bg-light font-weight-bold" style="width:40%;">Total Nilai PO</td>
                        <td class="text-right font-weight-bold"><?= formatRupiah($totalPOValue) ?></td>
                    </tr>
                    <tr>
                        <td class="table-success font-weight-bold">Sudah Dibayar</td>
                        <td class="text-right font-weight-bold text-success"><?= formatRupiah($totalVendorPaid) ?></td>
                    </tr>
                    <tr class="<?= $vendorOutstanding > 0 ? 'table-danger' : 'table-success' ?>">
                        <td class="font-weight-bold" style="font-size:13px;">Sisa Hutang</td>
                        <td class="text-right font-weight-bold" style="font-size:14px;">
                            <?= formatRupiah($vendorOutstanding) ?>
                            <?php if ($vendorOutstanding <= 0): ?>
                                <span class="badge badge-success ml-2">LUNAS</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><h3 class="card-title">Outstanding Piutang (Customer)</h3></div>
            <div class="card-body p-0">
                <table class="table table-sm table-bordered m-0" style="border:none;">
                    <tr>
                        <td class="bg-light font-weight-bold" style="width:40%;">Total Invoice</td>
                        <td class="text-right font-weight-bold"><?= formatRupiah($invoice['total_invoice']) ?></td>
                    </tr>
                    <tr>
                        <td class="table-success font-weight-bold">Sudah Diterima</td>
                        <td class="text-right font-weight-bold text-success"><?= formatRupiah($totalCustReceived) ?></td>
                    </tr>
                    <tr class="<?= $custOutstanding > 0 ? 'table-warning' : 'table-success' ?>">
                        <td class="font-weight-bold" style="font-size:13px;">Sisa Piutang</td>
                        <td class="text-right font-weight-bold" style="font-size:14px;">
                            <?= formatRupiah($custOutstanding) ?>
                            <?php if ($custOutstanding <= 0): ?>
                                <span class="badge badge-success ml-2">LUNAS</span>
                            <?php endif; ?>
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
        <div class="card">
            <div class="card-header"><h3 class="card-title">MR Terbaru</h3></div>
            <div class="card-body p-0">
                <table class="table table-striped table-hover m-0" >
                    <thead>
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
                            <td><strong class="text-dark"><?= sanitize($mr['mr_number']) ?></strong></td>
                            <td><?= date('d-m-Y', strtotime($mr['request_date'])) ?></td>
                            <td><?= sanitize($mr['requester']) ?></td>
                            <td><?= getStatusBadge($mr['status']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($recentMRs)): ?>
                        <tr><td colspan="4" class="text-center text-muted py-3">Belum ada MR</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <div class="col-md-7">
        <div class="card">
            <div class="card-header"><h3 class="card-title">Timeline Aktivitas</h3></div>
            <div class="card-body" style="max-height:350px; overflow-y:auto; padding: 20px;">
                <div class="custom-timeline">
                    <?php foreach ($timeline as $t): ?>
                    <div class="timeline-row">
                        <div class="timeline-date"><?= date('d M Y H:i', strtotime($t['event_date'])) ?></div>
                        <div class="timeline-dot"></div>
                        <div class="timeline-content">
                            <span class="timeline-type"><?= $t['type'] ?></span>
                            <strong class="timeline-doc"><?= sanitize($t['doc_no']) ?></strong>
                            <span><?= getStatusBadge($t['status']) ?></span>
                            <span class="timeline-actor">oleh <?= sanitize($t['actor']) ?></span>
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

<?php
ob_start();
?>
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<script>
$(document).ready(function() {
    // 1. Chart: Budget Allocation
    if ($('#chart-budget-allocation').length) {
        var optionsBudgetAllocation = {
            chart: {
                type: 'donut',
                height: 280,
                fontFamily: "'Work Sans', sans-serif"
            },
            series: [
                <?php echo (float)$totalPOValue; ?>,
                <?php echo (float)$transfers['total_value']; ?>,
                <?php echo (float)max(0, $project['budget'] - $totalPengeluaran); ?>
            ],
            labels: ['Nilai PO', 'Transfer Gudang', 'Sisa Budget'],
            colors: ['#ba1a1a', '#64748b', '#15803d'],
            dataLabels: { enabled: false },
            legend: {
                position: 'bottom',
                fontSize: '11px',
                fontFamily: "'Work Sans', sans-serif",
                labels: { colors: '#64748b' }
            },
            tooltip: {
                y: {
                    formatter: function(val) {
                        return "Rp " + new Intl.NumberFormat('id-ID').format(val);
                    }
                }
            }
        };
        var chartBudgetAllocation = new ApexCharts(document.querySelector("#chart-budget-allocation"), optionsBudgetAllocation);
        chartBudgetAllocation.render();
    }

    // 2. Chart: Cash Flow & Outstanding Comparison
    if ($('#chart-cashflow-comparison').length) {
        var optionsCashflow = {
            chart: {
                type: 'bar',
                height: 280,
                fontFamily: "'Work Sans', sans-serif",
                toolbar: { show: false }
            },
            plotOptions: {
                bar: {
                    horizontal: false,
                    columnWidth: '50%',
                    borderRadius: 4
                },
            },
            colors: ['#1e293b', '#f28c28'],
            dataLabels: { enabled: false },
            stroke: { show: true, width: 2, colors: ['transparent'] },
            series: [{
                name: 'Total Tagihan / Nilai Kontrak',
                data: [
                    <?php echo (float)$invoice['total_invoice']; ?>,
                    <?php echo (float)$totalPOValue; ?>
                ]
            }, {
                name: 'Jumlah Terbayar',
                data: [
                    <?php echo (float)$totalCustReceived; ?>,
                    <?php echo (float)$totalVendorPaid; ?>
                ]
            }],
            xaxis: {
                categories: ['Piutang Customer', 'Hutang Vendor'],
                labels: {
                    style: {
                        colors: '#64748b',
                        fontSize: '11px'
                    }
                }
            },
            yaxis: {
                labels: {
                    formatter: function(val) {
                        return "Rp " + new Intl.NumberFormat('id-ID').format(val);
                    },
                    style: {
                        colors: '#64748b',
                        fontSize: '11px'
                    }
                }
            },
            legend: {
                position: 'bottom',
                fontSize: '11px',
                fontFamily: "'Work Sans', sans-serif",
                labels: { colors: '#64748b' }
            },
            fill: { opacity: 0.95 },
            tooltip: {
                y: {
                    formatter: function(val) {
                        return "Rp " + new Intl.NumberFormat('id-ID').format(val);
                    }
                }
            },
            grid: { borderColor: '#f1f5f9' }
        };
        var chartCashflow = new ApexCharts(document.querySelector("#chart-cashflow-comparison"), optionsCashflow);
        chartCashflow.render();
    }
});
</script>
<?php
$extraJS = ob_get_clean();
require_once __DIR__ . '/../../../includes/footer.php';
?>
