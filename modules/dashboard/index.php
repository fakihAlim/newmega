<?php
/**
 * Dashboard
 */
require_once __DIR__ . '/../../includes/auth.php';

$pageTitle = 'Dashboard';
$user = getCurrentUser();

// Fetch dashboard statistics
$stats = [];

// Projects count
$stats['projects_active'] = $pdo->query("SELECT COUNT(*) FROM projects WHERE status = 'active'")->fetchColumn();
$stats['projects_total'] = $pdo->query("SELECT COUNT(*) FROM projects")->fetchColumn();

// MR stats
$stats['mr_pending'] = $pdo->query("SELECT COUNT(*) FROM material_requests WHERE status = 'pending'")->fetchColumn();
$stats['mr_total'] = $pdo->query("SELECT COUNT(*) FROM material_requests")->fetchColumn();

// PO stats
$stats['po_pending'] = $pdo->query("SELECT COUNT(*) FROM purchase_orders WHERE status = 'pending'")->fetchColumn();
$stats['po_active'] = $pdo->query("SELECT COUNT(*) FROM purchase_orders WHERE status IN ('approved','partially_received')")->fetchColumn();
$stats['po_total'] = $pdo->query("SELECT COUNT(*) FROM purchase_orders")->fetchColumn();

// Stock alerts
$stats['low_stock'] = $pdo->query("SELECT COUNT(*) FROM items WHERE current_stock <= minimum_stock AND minimum_stock > 0 AND is_active = 1")->fetchColumn();

// Financial stats
$stats['vendor_total_paid'] = $pdo->query("SELECT COALESCE(SUM(amount),0) FROM vendor_payments")->fetchColumn();

$stats['po_total_value'] = $pdo->query("SELECT COALESCE(SUM(total),0) FROM purchase_orders WHERE status NOT IN ('draft','rejected','cancelled')")->fetchColumn();
$stats['po_total_paid'] = $pdo->query("
    SELECT COALESCE(SUM(vp.amount),0) 
    FROM vendor_payments vp 
    JOIN purchase_orders po ON vp.po_id = po.id 
    WHERE po.status NOT IN ('draft','rejected','cancelled')
")->fetchColumn();
$stats['vendor_outstanding'] = $stats['po_total_value'] - $stats['po_total_paid'];

$stats['invoice_total'] = $pdo->query("SELECT COALESCE(SUM(total),0) FROM invoices WHERE status NOT IN ('draft','rejected')")->fetchColumn();
$stats['customer_paid'] = $pdo->query("SELECT COALESCE(SUM(amount),0) FROM customer_payments")->fetchColumn();
$stats['customer_outstanding'] = $stats['invoice_total'] - $stats['customer_paid'];

// Quotation stats
$stats['quotation_pending'] = $pdo->query("SELECT COUNT(*) FROM quotations WHERE status = 'pending'")->fetchColumn();
$stats['invoice_pending'] = $pdo->query("SELECT COUNT(*) FROM invoices WHERE status = 'pending'")->fetchColumn();

// Items count
$stats['items_total'] = $pdo->query("SELECT COUNT(*) FROM items WHERE is_active = 1")->fetchColumn();
$stats['vendors_total'] = $pdo->query("SELECT COUNT(*) FROM vendors WHERE is_active = 1")->fetchColumn();
$stats['customers_total'] = $pdo->query("SELECT COUNT(*) FROM customers WHERE is_active = 1")->fetchColumn();

// Recent MRs
$recentMR = $pdo->query("
    SELECT mr.*, p.name as project_name, u.full_name as requester_name 
    FROM material_requests mr 
    JOIN projects p ON mr.project_id = p.id 
    JOIN users u ON mr.requested_by = u.id 
    ORDER BY mr.created_at DESC LIMIT 5
")->fetchAll();

// Recent POs
$recentPO = $pdo->query("
    SELECT po.*, v.company_name as vendor_name 
    FROM purchase_orders po 
    JOIN vendors v ON po.vendor_id = v.id 
    ORDER BY po.created_at DESC LIMIT 5
")->fetchAll();

// Low stock items
$lowStockItems = $pdo->query("
    SELECT i.*, c.name as category_name 
    FROM items i 
    JOIN categories c ON i.category_id = c.id 
    WHERE i.current_stock <= i.minimum_stock AND i.minimum_stock > 0 AND i.is_active = 1
    ORDER BY (i.current_stock / i.minimum_stock) ASC 
    LIMIT 5
")->fetchAll();

// === Executive Dashboard Chart Data ===
$cashFlowMonths = [];
$cashFlowIn = [];
$cashFlowOut = [];
$projectNames = [];
$projectBudgets = [];
$projectActuals = [];
$categoryNames = [];
$categorySpent = [];

if (!hasRole(['karyawan'])) {
    // 1. Query for Cash Flow Trend (Last 6 Months)
    $cashFlowSql = "
        SELECT 
            months.month_label,
            COALESCE(SUM(cash_in.amount), 0) AS total_in,
            COALESCE(SUM(cash_out.amount), 0) AS total_out
        FROM (
            SELECT DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 5 MONTH), '%Y-%m') AS month_label UNION ALL
            SELECT DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 4 MONTH), '%Y-%m') UNION ALL
            SELECT DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 3 MONTH), '%Y-%m') UNION ALL
            SELECT DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 2 MONTH), '%Y-%m') UNION ALL
            SELECT DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 MONTH), '%Y-%m') UNION ALL
            SELECT DATE_FORMAT(CURDATE(), '%Y-%m')
        ) months
        LEFT JOIN (
            SELECT DATE_FORMAT(payment_date, '%Y-%m') AS month_label, amount 
            FROM customer_payments
        ) cash_in ON months.month_label = cash_in.month_label
        LEFT JOIN (
            SELECT DATE_FORMAT(payment_date, '%Y-%m') AS month_label, amount 
            FROM vendor_payments
            UNION ALL
            SELECT DATE_FORMAT(claim_date, '%Y-%m') AS month_label, total_amount AS amount
            FROM nota_claims WHERE status = 'paid'
        ) cash_out ON months.month_label = cash_out.month_label
        GROUP BY months.month_label
        ORDER BY months.month_label ASC
    ";
    $cashFlowData = $pdo->query($cashFlowSql)->fetchAll();

    $indonesianMonths = [
        '01' => 'Jan', '02' => 'Feb', '03' => 'Mar', '04' => 'Apr', '05' => 'Mei', '06' => 'Jun',
        '07' => 'Jul', '08' => 'Ags', '09' => 'Sep', '10' => 'Okt', '11' => 'Nov', '12' => 'Des'
    ];
    foreach ($cashFlowData as $row) {
        list($year, $month) = explode('-', $row['month_label']);
        $label = $indonesianMonths[$month] . ' ' . substr($year, 2);
        $cashFlowMonths[] = $label;
        $cashFlowIn[] = (float)$row['total_in'];
        $cashFlowOut[] = (float)$row['total_out'];
    }

    // 2. Query for active projects: Budget vs Actual (PO + Claims)
    $projectSql = "
        SELECT 
            p.name, 
            p.budget,
            -- PO Expenses
            (SELECT COALESCE(SUM(po.total), 0) 
             FROM purchase_orders po 
             JOIN po_mr_links pml ON pml.po_id = po.id 
             JOIN material_requests mr ON pml.mr_id = mr.id 
             WHERE mr.project_id = p.id AND po.status NOT IN ('draft','cancelled','rejected')
            ) as total_po_value,
            -- Claim Nota Expenses
            (SELECT COALESCE(SUM(nci.amount), 0)
             FROM nota_claim_items nci
             JOIN nota_claims nc ON nci.claim_id = nc.id
             WHERE nci.project_id = p.id AND nc.status = 'paid'
            ) as total_claim_value
        FROM projects p
        WHERE p.status = 'active'
        ORDER BY p.name ASC
    ";
    $projectsData = $pdo->query($projectSql)->fetchAll();

    foreach ($projectsData as $row) {
        $projectNames[] = $row['name'];
        $projectBudgets[] = (float)$row['budget'];
        $projectActuals[] = (float)$row['total_po_value'] + (float)$row['total_claim_value'];
    }

    // 3. Query for Top 5 Categories Spent
    $categorySql = "
        SELECT 
            c.name AS category_name, 
            COALESCE(SUM(poi.total), 0) AS total_spent
        FROM purchase_order_items poi
        JOIN items i ON poi.item_id = i.id
        JOIN categories c ON i.category_id = c.id
        JOIN purchase_orders po ON poi.po_id = po.id
        WHERE po.status NOT IN ('draft','cancelled','rejected')
        GROUP BY c.id
        ORDER BY total_spent DESC
        LIMIT 5
    ";
    $categoriesData = $pdo->query($categorySql)->fetchAll();

    foreach ($categoriesData as $row) {
        $categoryNames[] = $row['category_name'];
        $categorySpent[] = (float)$row['total_spent'];
    }
}

// === Karyawan Dashboard Data ===
$karyawanDash = null;
if (hasRole(['karyawan'])) {
    $karyawanDash = [];
    
    // Get employee record
    $stmtEmp = $pdo->prepare("SELECT e.*, w.jabatan_name, w.daily_wage FROM employees e JOIN master_wages w ON e.wage_id = w.id WHERE e.user_id = ?");
    $stmtEmp->execute([$user['id']]);
    $karyawanDash['employee'] = $stmtEmp->fetch();
    
    if ($karyawanDash['employee']) {
        $empId = $karyawanDash['employee']['id'];
        $currentMonth = date('n');
        $currentYear = date('Y');
        
        // Monthly summary for current month
        $stmtMonth = $pdo->prepare("
            SELECT 
                COUNT(*) as total_entries,
                SUM(CASE WHEN work_type = 'full' THEN 1 ELSE 0 END) as full_days,
                SUM(CASE WHEN work_type = 'half' THEN 1 ELSE 0 END) as half_days,
                SUM(overtime_hours) as total_overtime,
                SUM(CASE WHEN status = 'approved' THEN (CASE WHEN work_type = 'full' THEN 1 ELSE 0.5 END) * daily_wage_at_time ELSE 0 END) as total_upah_approved,
                SUM(CASE WHEN status = 'approved' THEN (daily_wage_at_time / 8) * overtime_hours ELSE 0 END) as total_lembur_approved,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_count,
                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_count
            FROM timesheet_entries 
            WHERE employee_id = ? AND MONTH(work_date) = ? AND YEAR(work_date) = ?
        ");
        $stmtMonth->execute([$empId, $currentMonth, $currentYear]);
        $karyawanDash['current_month'] = $stmtMonth->fetch();
        
        // Monthly history (last 6 months)
        $stmtHistory = $pdo->prepare("
            SELECT 
                YEAR(work_date) as tahun,
                MONTH(work_date) as bulan,
                SUM(CASE WHEN work_type = 'full' THEN 1 ELSE 0 END) as full_days,
                SUM(CASE WHEN work_type = 'half' THEN 1 ELSE 0 END) as half_days,
                SUM(overtime_hours) as total_overtime,
                SUM(CASE WHEN status = 'approved' THEN (CASE WHEN work_type = 'full' THEN 1 ELSE 0.5 END) * daily_wage_at_time ELSE 0 END) as total_upah,
                SUM(CASE WHEN status = 'approved' THEN (daily_wage_at_time / 8) * overtime_hours ELSE 0 END) as total_lembur,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_count,
                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_count
            FROM timesheet_entries 
            WHERE employee_id = ? 
            GROUP BY YEAR(work_date), MONTH(work_date) 
            ORDER BY tahun DESC, bulan DESC 
            LIMIT 6
        ");
        $stmtHistory->execute([$empId]);
        $karyawanDash['history'] = $stmtHistory->fetchAll();
        
        // Recent 10 entries
        $stmtRecent = $pdo->prepare("
            SELECT t.*, p.name as project_name, c.name as company_name 
            FROM timesheet_entries t
            JOIN projects p ON t.project_id = p.id
            JOIN companies c ON t.company_id = c.id
            WHERE t.employee_id = ? 
            ORDER BY t.work_date DESC, t.id DESC 
            LIMIT 10
        ");
        $stmtRecent->execute([$empId]);
        $karyawanDash['recent'] = $stmtRecent->fetchAll();
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<?php if ($karyawanDash && $karyawanDash['employee']): ?>
<?php
    $emp = $karyawanDash['employee'];
    $cm = $karyawanDash['current_month'];
    $totalUpahBulanIni = ($cm['total_upah_approved'] ?? 0) + ($cm['total_lembur_approved'] ?? 0);
    $namaBulan = ['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
?>

<!-- Karyawan Info Card -->
<div class="row mb-3">
    <div class="col-12">
        <div class="card bg-gradient-warning mb-0" style="border-radius: 12px;">
            <div class="card-body py-3">
                <div class="d-flex justify-content-between align-items-center flex-wrap">
                    <div>
                        <h4 class="text-white font-weight-bold mb-1">Selamat Datang, <?= sanitize($user['full_name']) ?>!</h4>
                        <span class="text-white" style="opacity:0.85;">
                            <i class="fas fa-hard-hat mr-1"></i> <?= sanitize($emp['jabatan_name']) ?> &bull;
                            Upah Harian: <strong><?= formatRupiah($emp['daily_wage']) ?></strong>
                        </span>
                    </div>
                    <a href="<?= APP_URL ?>/modules/timesheet/input.php" class="btn btn-light btn-lg font-weight-bold mt-2 mt-md-0" style="border-radius:8px;">
                        <i class="fas fa-plus-circle mr-1"></i> Input Absensi
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Karyawan Summary Boxes -->
<div class="row">
    <div class="col-lg-3 col-6">
        <div class="small-box bg-info">
            <div class="inner">
                <h3><?= ($cm['full_days'] ?? 0) + ($cm['half_days'] ?? 0) ?></h3>
                <p>Hari Kerja (<?= $namaBulan[date('n')] ?>)</p>
            </div>
            <div class="icon"><i class="fas fa-calendar-check"></i></div>
            <a href="<?= APP_URL ?>/modules/timesheet/input.php" class="small-box-footer">Lihat Detail <i class="fas fa-arrow-circle-right"></i></a>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="small-box bg-warning">
            <div class="inner">
                <h3><?= $cm['pending_count'] ?? 0 ?></h3>
                <p>Menunggu Approval</p>
            </div>
            <div class="icon"><i class="fas fa-hourglass-half"></i></div>
            <a href="<?= APP_URL ?>/modules/timesheet/input.php" class="small-box-footer">Lihat Detail <i class="fas fa-arrow-circle-right"></i></a>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="small-box bg-success">
            <div class="inner">
                <h3 style="font-size:1.5rem;"><?= formatRupiah($cm['total_upah_approved'] ?? 0) ?></h3>
                <p>Upah Kerja (<?= $namaBulan[date('n')] ?>)</p>
            </div>
            <div class="icon"><i class="fas fa-money-bill-wave"></i></div>
            <a href="<?= APP_URL ?>/modules/reports/timesheet.php" class="small-box-footer">Lihat Laporan <i class="fas fa-arrow-circle-right"></i></a>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="small-box bg-primary">
            <div class="inner">
                <h3 style="font-size:1.5rem;"><?= formatRupiah($totalUpahBulanIni) ?></h3>
                <p>Total + Lembur (<?= $namaBulan[date('n')] ?>)</p>
            </div>
            <div class="icon"><i class="fas fa-coins"></i></div>
            <a href="<?= APP_URL ?>/modules/reports/timesheet.php" class="small-box-footer">Lihat Laporan <i class="fas fa-arrow-circle-right"></i></a>
        </div>
    </div>
</div>

<!-- Riwayat Bulanan & Entri Terbaru -->
<div class="row">
    <!-- Riwayat Bulanan -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header d-flex align-items-center">
                <h3 class="card-title"><i class="fas fa-chart-bar mr-2 text-primary"></i>Riwayat Upah Per Bulan</h3>
            </div>
            <div class="card-body p-0">
                <table class="table table-bordered table-striped table-hover table-sm w-100" >
                    <thead class="bg-light">
                        <tr>
                            <th>Periode</th>
                            <th class="text-center">H.Full</th>
                            <th class="text-center">H.Half</th>
                            <th class="text-center">Lembur</th>
                            <th class="text-right">Upah Kerja</th>
                            <th class="text-right">Upah Lembur</th>
                            <th class="text-right font-weight-bold">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($karyawanDash['history'])): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">Belum ada data timesheet.</td></tr>
                        <?php else: ?>
                        <?php foreach ($karyawanDash['history'] as $h): 
                            $totalBulan = $h['total_upah'] + $h['total_lembur'];
                        ?>
                        <tr>
                            <td class="font-weight-bold"><?= $namaBulan[$h['bulan']] ?> <?= $h['tahun'] ?></td>
                            <td class="text-center"><?= $h['full_days'] ?></td>
                            <td class="text-center"><?= $h['half_days'] ?></td>
                            <td class="text-center"><?= $h['total_overtime'] ?> jam</td>
                            <td class="text-right"><?= number_format($h['total_upah'], 0, ',', '.') ?></td>
                            <td class="text-right"><?= number_format($h['total_lembur'], 0, ',', '.') ?></td>
                            <td class="text-right font-weight-bold text-success"><?= number_format($totalBulan, 0, ',', '.') ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Entri Terbaru -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header d-flex align-items-center">
                <h3 class="card-title"><i class="fas fa-clock mr-2 text-warning"></i>10 Entri Terakhir</h3>
            </div>
            <div class="card-body p-0">
                <table class="table table-bordered table-striped table-hover table-sm w-100" >
                    <thead class="bg-light">
                        <tr>
                            <th>Tanggal</th>
                            <th>Proyek</th>
                            <th class="text-center">Tipe</th>
                            <th class="text-center">Lembur</th>
                            <th class="text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($karyawanDash['recent'])): ?>
                        <tr><td colspan="5" class="text-center text-muted py-4">Belum ada data.</td></tr>
                        <?php else: ?>
                        <?php foreach ($karyawanDash['recent'] as $r): 
                            $statusColor = 'warning'; $statusLabel = 'Pending';
                            if ($r['status'] == 'approved') { $statusColor = 'success'; $statusLabel = 'Approved'; }
                            if ($r['status'] == 'rejected') { $statusColor = 'danger'; $statusLabel = 'Rejected'; }
                        ?>
                        <tr>
                            <td class="font-weight-bold"><?= date('d M Y', strtotime($r['work_date'])) ?></td>
                            <td>
                                <?= sanitize($r['project_name']) ?><br>
                                <small class="text-muted"><?= sanitize($r['company_name']) ?></small>
                            </td>
                            <td class="text-center"><?= $r['work_type'] == 'full' ? 'Full' : 'Half' ?></td>
                            <td class="text-center"><?= $r['overtime_hours'] > 0 ? $r['overtime_hours'] . 'j' : '-' ?></td>
                            <td class="text-center"><span class="badge badge-<?= $statusColor ?>"><?= $statusLabel ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php endif; ?>

<?php if (!hasRole(['karyawan'])): ?>
<!-- Summary Boxes (Row 1) -->
<div class="row">
    <?php if (canAccess('master_projects')): ?>
    <div class="col-lg-3 col-6">
        <div class="small-box bg-info">
            <div class="inner">
                <h3><?= $stats['projects_active'] ?></h3>
                <p>Proyek Aktif</p>
            </div>
            <div class="icon"><i class="fas fa-project-diagram"></i></div>
            <a href="<?= APP_URL ?>/modules/master/projects/index.php" class="small-box-footer">
                Lihat Detail <i class="fas fa-arrow-circle-right"></i>
            </a>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if (canAccess('material_request')): ?>
    <div class="col-lg-3 col-6">
        <div class="small-box bg-warning">
            <div class="inner">
                <h3><?= $stats['mr_pending'] ?></h3>
                <p>MR Menunggu Approval</p>
            </div>
            <div class="icon"><i class="fas fa-clipboard-list"></i></div>
            <a href="<?= APP_URL ?>/modules/procurement/mr/index.php?status=pending" class="small-box-footer">
                Lihat Detail <i class="fas fa-arrow-circle-right"></i>
            </a>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if (canAccess('purchase_order')): ?>
    <div class="col-lg-3 col-6">
        <div class="small-box bg-success">
            <div class="inner">
                <h3><?= $stats['po_active'] ?></h3>
                <p>PO Aktif</p>
            </div>
            <div class="icon"><i class="fas fa-file-invoice"></i></div>
            <a href="<?= APP_URL ?>/modules/procurement/po/index.php" class="small-box-footer">
                Lihat Detail <i class="fas fa-arrow-circle-right"></i>
            </a>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if (canAccess('stock_alerts')): ?>
    <div class="col-lg-3 col-6">
        <div class="small-box <?= $stats['low_stock'] > 0 ? 'bg-danger' : 'bg-secondary' ?>">
            <div class="inner">
                <h3><?= $stats['low_stock'] ?></h3>
                <p>Stok Minimum Alert</p>
            </div>
            <div class="icon"><i class="fas fa-exclamation-triangle"></i></div>
            <a href="<?= APP_URL ?>/modules/warehouse/alerts/index.php" class="small-box-footer">
                Lihat Detail <i class="fas fa-arrow-circle-right"></i>
            </a>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php if (canAccess('ledger')): ?>
<!-- Financial Summary (Row 2) -->
<div class="row">
    <div class="col-lg-3 col-6">
        <div class="info-box">
            <span class="info-box-icon bg-gradient-primary"><i class="fas fa-money-check-alt"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Total PO Value</span>
                <span class="info-box-number" style="font-size:14px;"><?= formatRupiah($stats['po_total_value']) ?></span>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="info-box">
            <span class="info-box-icon bg-gradient-danger"><i class="fas fa-exclamation-circle"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Outstanding Vendor</span>
                <span class="info-box-number" style="font-size:14px;"><?= formatRupiah($stats['vendor_outstanding']) ?></span>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="info-box">
            <span class="info-box-icon bg-gradient-success"><i class="fas fa-file-invoice-dollar"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Total Invoice</span>
                <span class="info-box-number" style="font-size:14px;"><?= formatRupiah($stats['invoice_total']) ?></span>
            </div>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="info-box">
            <span class="info-box-icon bg-gradient-warning"><i class="fas fa-hand-holding-usd"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Outstanding Customer</span>
                <span class="info-box-number" style="font-size:14px;"><?= formatRupiah($stats['customer_outstanding']) ?></span>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (hasRole(['super_admin', 'finance', 'project_manager'])): ?>
<!-- Executive Charts Section -->


<?php endif; ?>

<!-- Quick Stats Footer -->
<div class="row">
    <div class="col-md-3 col-sm-6">
        <div class="info-box bg-light">
            <span class="info-box-icon"><i class="fas fa-boxes text-info"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Total Barang</span>
                <span class="info-box-number"><?= $stats['items_total'] ?></span>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="info-box bg-light">
            <span class="info-box-icon"><i class="fas fa-truck text-success"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Total Vendor</span>
                <span class="info-box-number"><?= $stats['vendors_total'] ?></span>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="info-box bg-light">
            <span class="info-box-icon"><i class="fas fa-building text-warning"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Total Customer</span>
                <span class="info-box-number"><?= $stats['customers_total'] ?></span>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="info-box bg-light">
            <span class="info-box-icon"><i class="fas fa-project-diagram text-danger"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Total Proyek</span>
                <span class="info-box-number"><?= $stats['projects_total'] ?></span>
            </div>
        </div>
    </div>
</div>

<!-- Tables Row -->
<div class="row">
    <!-- Recent Material Requests -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header d-flex align-items-center">
                <h3 class="card-title"><i class="fas fa-clipboard-list mr-2 text-warning"></i>Material Request Terbaru</h3>
                <?php if (canAccess('mr_list')): ?>
                <a href="<?= APP_URL ?>/modules/procurement/mr/index.php" class="btn btn-sm btn-outline-secondary ml-auto">Lihat Semua</a>
                <?php endif; ?>
            </div>
            <div class="card-body p-0">
                <table class="table table-bordered table-striped table-hover table-sm w-100">
                    <thead>
                        <tr>
                            <th>No. MR</th>
                            <th>Proyek</th>
                            <th>Tanggal</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentMR)): ?>
                        <tr><td colspan="4" class="text-center text-muted py-4">Belum ada data</td></tr>
                        <?php else: ?>
                        <?php foreach ($recentMR as $mr): ?>
                        <tr>
                            <td>
                                <a href="<?= APP_URL ?>/modules/procurement/mr/view.php?id=<?= $mr['id'] ?>" class="fw-bold" title="Lihat Detail MR">
                                    <?= sanitize($mr['mr_number']) ?>
                                </a>
                            </td>
                            <td><?= sanitize($mr['project_name']) ?></td>
                            <td><?= formatDate($mr['request_date']) ?></td>
                            <td><?= getStatusBadge($mr['status']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Recent Purchase Orders -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header d-flex align-items-center">
                <h3 class="card-title"><i class="fas fa-file-invoice mr-2 text-primary"></i>Purchase Order Terbaru</h3>
                <?php if (canAccess('po_list')): ?>
                <a href="<?= APP_URL ?>/modules/procurement/po/index.php" class="btn btn-sm btn-outline-secondary ml-auto">Lihat Semua</a>
                <?php endif; ?>
            </div>
            <div class="card-body p-0">
                <table class="table table-bordered table-striped table-hover table-sm w-100">
                    <thead>
                        <tr>
                            <th>No. PO</th>
                            <th>Vendor</th>
                            <th>Total</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentPO)): ?>
                        <tr><td colspan="4" class="text-center text-muted py-4">Belum ada data</td></tr>
                        <?php else: ?>
                        <?php foreach ($recentPO as $po): ?>
                        <tr>
                            <td>
                                <a href="<?= APP_URL ?>/modules/procurement/po/view.php?id=<?= $po['id'] ?>" class="fw-bold" title="Lihat Detail PO">
                                    <?= sanitize($po['po_number']) ?>
                                </a>
                            </td>
                            <td><?= sanitize($po['vendor_name']) ?></td>
                            <td><?= formatRupiah($po['total']) ?></td>
                            <td><?= getStatusBadge($po['status']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php if (canAccess('stock_alerts') && !empty($lowStockItems)): ?>
<!-- Low Stock Alert -->
<div class="row">
    <div class="col-md-12">
        <div class="card card-outline card-danger">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-exclamation-triangle mr-2 text-danger"></i>Peringatan Stok Minimum</h3>
            </div>
            <div class="card-body p-0">
                <table class="table table-bordered table-striped table-hover table-sm w-100">
                    <thead>
                        <tr>
                            <th>Kode</th>
                            <th>Nama Barang</th>
                            <th>Kategori</th>
                            <th>Stok Saat Ini</th>
                            <th>Minimum Stok</th>
                            <th>UOM</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lowStockItems as $item): ?>
                        <tr>
                            <td>
                                <a href="#" class="view-item-history fw-bold" data-id="<?= $item['id'] ?>" title="Lihat Riwayat Barang">
                                    <?= sanitize($item['item_code']) ?>
                                </a>
                            </td>
                            <td><?= sanitize($item['description']) ?></td>
                            <td><?= sanitize($item['category_name']) ?></td>
                            <td class="text-danger fw-600"><?= number_format($item['current_stock'], 0) ?></td>
                            <td><?= number_format($item['minimum_stock'], 0) ?></td>
                            <td><?= sanitize($item['uom']) ?></td>
                            <td>
                                <?php if ($item['current_stock'] <= 0): ?>
                                    <span class="badge badge-danger">Habis</span>
                                <?php else: ?>
                                    <span class="badge badge-warning">Rendah</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Item History Modal -->
<div class="modal fade" id="itemHistoryModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Riwayat Barang</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" id="itemHistoryModalBody">
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="sr-only">Loading...</span>
                    </div>
                    <p class="mt-2 text-muted">Memuat data riwayat...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
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
    $('.view-item-history').on('click', function(e) {
        e.preventDefault();
        const id = $(this).data('id');
        
        $('#itemHistoryModalBody').html(`
            <div class="text-center py-4">
                <div class="spinner-border text-primary" role="status">
                    <span class="sr-only">Loading...</span>
                </div>
                <p class="mt-2 text-muted">Memuat data riwayat...</p>
            </div>
        `);
        $('#itemHistoryModal').modal('show');
        
        $.ajax({
            url: APP_URL + '/modules/dashboard/ajax_item_history.php',
            type: 'GET',
            data: { id: id },
            success: function(response) {
                $('#itemHistoryModalBody').html(response);
            },
            error: function() {
                $('#itemHistoryModalBody').html('<div class="alert alert-danger">Terjadi kesalahan saat memuat data riwayat.</div>');
            }
        });
    });

    <?php if (canAccess('ledger') || canAccess('master_projects')): ?>
    // 1. Chart: Cash Flow Trend
    if ($('#chart-cash-flow').length) {
        var optionsCashFlow = {
            chart: {
                type: 'area',
                height: 350,
                fontFamily: 'inherit',
                toolbar: { show: false },
                zoom: { enabled: false }
            },
            colors: ['#28a745', '#dc3545'],
            fill: {
                type: 'gradient',
                gradient: {
                    shadeIntensity: 1,
                    opacityFrom: 0.35,
                    opacityTo: 0.05,
                    stops: [0, 90, 100]
                }
            },
            dataLabels: { enabled: false },
            stroke: { curve: 'smooth', width: 3 },
            series: [{
                name: 'Uang Masuk (Debit)',
                data: <?php echo json_encode($cashFlowIn); ?>
            }, {
                name: 'Uang Keluar (Kredit)',
                data: <?php echo json_encode($cashFlowOut); ?>
            }],
            xaxis: {
                categories: <?php echo json_encode($cashFlowMonths); ?>,
                labels: { style: { colors: '#64748b' } }
            },
            yaxis: {
                labels: {
                    formatter: function(val) {
                        return "Rp " + new Intl.NumberFormat('id-ID').format(val);
                    },
                    style: { colors: '#64748b' }
                }
            },
            tooltip: {
                y: {
                    formatter: function(val) {
                        return "Rp " + new Intl.NumberFormat('id-ID').format(val);
                    }
                }
            },
            grid: { borderColor: '#f1f5f9' }
        };
        var chartCashFlow = new ApexCharts(document.querySelector("#chart-cash-flow"), optionsCashFlow);
        chartCashFlow.render();
    }

    // 2. Chart: Budget vs Actual
    if ($('#chart-projects').length) {
        var optionsProjects = {
            chart: {
                type: 'bar',
                height: 350,
                fontFamily: 'inherit',
                toolbar: { show: false }
            },
            plotOptions: {
                bar: {
                    horizontal: false,
                    columnWidth: '55%',
                    borderRadius: 4
                },
            },
            colors: ['#007bff', '#28a745'],
            dataLabels: { enabled: false },
            stroke: { show: true, width: 2, colors: ['transparent'] },
            series: [{
                name: 'Anggaran (Budget)',
                data: <?php echo json_encode($projectBudgets); ?>
            }, {
                name: 'Biaya Aktual (PO + Claim)',
                data: <?php echo json_encode($projectActuals); ?>
            }],
            xaxis: {
                categories: <?php echo json_encode($projectNames); ?>,
                labels: { style: { colors: '#64748b' } }
            },
            yaxis: {
                labels: {
                    formatter: function(val) {
                        return "Rp " + new Intl.NumberFormat('id-ID').format(val);
                    },
                    style: { colors: '#64748b' }
                }
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
        var chartProjects = new ApexCharts(document.querySelector("#chart-projects"), optionsProjects);
        chartProjects.render();
    }

    // 3. Chart: Top Categories
    if ($('#chart-categories').length) {
        var optionsCategories = {
            chart: {
                type: 'donut',
                height: 350,
                fontFamily: 'inherit'
            },
            series: <?php echo json_encode($categorySpent); ?>,
            labels: <?php echo json_encode($categoryNames); ?>,
            colors: ['#17a2b8', '#ffc107', '#007bff', '#dc3545', '#6c757d'],
            legend: {
                position: 'bottom',
                horizontalAlign: 'center'
            },
            dataLabels: { enabled: false },
            tooltip: {
                y: {
                    formatter: function(val) {
                        return "Rp " + new Intl.NumberFormat('id-ID').format(val);
                    }
                }
            }
        };
        var chartCategories = new ApexCharts(document.querySelector("#chart-categories"), optionsCategories);
        chartCategories.render();
    }
    <?php endif; ?>
});
</script>
<?php
$extraJS = ob_get_clean();
?>

<?php endif; // end !karyawan ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
