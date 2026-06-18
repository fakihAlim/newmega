<?php
/**
 * Export CSV - Handler untuk semua laporan
 * ?type=project_expense|vendor_outstanding|customer_outstanding|profit_loss|stock_report|stock_detail
 */
require_once __DIR__ . '/../../includes/auth.php';

$type = $_GET['type'] ?? '';

$permMap = [
    'project_expense'      => 'report_project_expense',
    'vendor_outstanding'   => 'report_vendor_outstanding',
    'customer_outstanding' => 'report_customer_outstanding',
    'profit_loss'          => 'report_profit_loss',
    'stock_report'         => 'report_stock',
    'stock_detail'         => 'report_stock',
];

if (!isset($permMap[$type])) die('Tipe laporan tidak valid.');
requirePermission($permMap[$type]);

// ── Helper: format angka plain ──
function csvNum($n) { return number_format((float)$n, 0, ',', '.'); }

// ── Helper: output CSV header & start download ──
function startCSV(string $filename) {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    header('Cache-Control: max-age=0');
    // BOM untuk Excel agar UTF-8 terbaca dengan benar
    echo "\xEF\xBB\xBF";
}

// ── Helper: tulis satu baris CSV ──
function csvRow(array $cols) {
    $escaped = array_map(fn($v) => '"' . str_replace('"', '""', (string)$v) . '"', $cols);
    echo implode(',', $escaped) . "\r\n";
}

// Retrieve shared filters
$filterStart = $_GET['start_date'] ?? '';
$filterEnd = $_GET['end_date'] ?? '';

// ══════════════════════════════════════════════════════════
// 1. PENGELUARAN PROYEK
// ══════════════════════════════════════════════════════════
if ($type === 'project_expense') {
    $filterProject = $_GET['project_id'] ?? '';
    $filterStatus = $_GET['status'] ?? '';

    $conditions = [];
    $params = [];

    if ($filterProject) {
        $conditions[] = "p.id = ?";
        $params[] = $filterProject;
    }
    if ($filterStatus) {
        $conditions[] = "p.status = ?";
        $params[] = $filterStatus;
    }

    $whereClause = "";
    if (!empty($conditions)) {
        $whereClause = "WHERE " . implode(" AND ", $conditions);
    }

    $dateCondPO = "";
    $dateCondVP = "";
    $dateCondMR = "";
    if ($filterStart) {
        $dateCondPO .= " AND po.po_date >= " . $pdo->quote($filterStart);
        $dateCondVP .= " AND vp.payment_date >= " . $pdo->quote($filterStart);
        $dateCondMR .= " AND mr.request_date >= " . $pdo->quote($filterStart);
    }
    if ($filterEnd) {
        $dateCondPO .= " AND po.po_date <= " . $pdo->quote($filterEnd);
        $dateCondVP .= " AND vp.payment_date <= " . $pdo->quote($filterEnd);
        $dateCondMR .= " AND mr.request_date <= " . $pdo->quote($filterEnd);
    }

    $sql = "
        SELECT p.name, p.status, p.budget,
            (SELECT COUNT(*) FROM material_requests mr WHERE mr.project_id = p.id AND mr.status != 'draft' $dateCondMR) as total_mr,
            (SELECT COALESCE(SUM(po.total),0) FROM purchase_orders po
             JOIN po_mr_links pml ON pml.po_id = po.id
             JOIN material_requests mr ON pml.mr_id = mr.id
             WHERE mr.project_id = p.id AND po.status NOT IN ('draft','cancelled','rejected') $dateCondPO) as total_po_value,
            (SELECT COALESCE(SUM(vp.amount),0) FROM vendor_payments vp
             JOIN purchase_orders po ON vp.po_id = po.id
             JOIN po_mr_links pml ON pml.po_id = po.id
             JOIN material_requests mr ON pml.mr_id = mr.id
             WHERE mr.project_id = p.id $dateCondVP) as total_paid
        FROM projects p
        $whereClause
        ORDER BY p.name
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    startCSV('Pengeluaran_Proyek_' . date('d-m-Y'));
    csvRow(['Laporan Pengeluaran Proyek']);
    csvRow(['Tanggal Export: ' . date('d-m-Y H:i')]);
    csvRow([]);
    csvRow(['No','Nama Proyek','Status','Jumlah MR','Budget (Rp)','Nilai PO (Rp)','Terbayar (Rp)','% Terpakai']);
    $no = 1;
    foreach ($rows as $p) {
        $pct = $p['budget'] > 0 ? round(($p['total_po_value'] / $p['budget']) * 100, 1) : 0;
        csvRow([
            $no++,
            $p['name'],
            ucfirst($p['status']),
            $p['total_mr'],
            csvNum($p['budget']),
            csvNum($p['total_po_value']),
            csvNum($p['total_paid']),
            $pct . '%',
        ]);
    }
}

// ══════════════════════════════════════════════════════════
// 2. OUTSTANDING VENDOR
// ══════════════════════════════════════════════════════════
elseif ($type === 'vendor_outstanding') {
    $filterVendor = $_GET['vendor_id'] ?? '';
    $filterStatus = $_GET['status'] ?? '';

    $conditions = ["po.status NOT IN ('draft', 'cancelled', 'rejected')"];
    $params = [];

    if ($filterStart) {
        $conditions[] = "po.po_date >= ?";
        $params[] = $filterStart;
    }
    if ($filterEnd) {
        $conditions[] = "po.po_date <= ?";
        $params[] = $filterEnd;
    }
    if ($filterVendor) {
        $conditions[] = "po.vendor_id = ?";
        $params[] = $filterVendor;
    }
    if ($filterStatus) {
        $conditions[] = "po.status = ?";
        $params[] = $filterStatus;
    }

    $whereClause = "";
    if (!empty($conditions)) {
        $whereClause = "WHERE " . implode(" AND ", $conditions);
    }

    $sql = "
        SELECT po.po_number, po.po_date, v.company_name as vendor_name, po.status as po_status,
               po.total as po_total, COALESCE(SUM(vp.amount),0) as total_paid
        FROM purchase_orders po
        JOIN vendors v ON po.vendor_id = v.id
        LEFT JOIN vendor_payments vp ON vp.po_id = po.id
        $whereClause
        GROUP BY po.id
        ORDER BY (po.total - COALESCE(SUM(vp.amount),0)) DESC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    startCSV('Outstanding_Vendor_' . date('d-m-Y'));
    csvRow(['Rekap Hutang ke Vendor (Outstanding)']);
    csvRow(['Tanggal Export: ' . date('d-m-Y H:i')]);
    csvRow([]);
    csvRow(['No','No. PO','Tanggal','Vendor','Status PO','Nilai PO (Rp)','Terbayar (Rp)','Outstanding (Rp)']);
    $no = 1;
    foreach ($rows as $po) {
        $out = $po['po_total'] - $po['total_paid'];
        csvRow([
            $no++,
            $po['po_number'],
            date('d-m-Y', strtotime($po['po_date'])),
            $po['vendor_name'],
            ucfirst(str_replace('_', ' ', $po['po_status'])),
            csvNum($po['po_total']),
            csvNum($po['total_paid']),
            csvNum($out),
        ]);
    }
}

// ══════════════════════════════════════════════════════════
// 3. OUTSTANDING CUSTOMER
// ══════════════════════════════════════════════════════════
elseif ($type === 'customer_outstanding') {
    $filterCustomer = $_GET['customer_id'] ?? '';
    $filterStatus = $_GET['status'] ?? '';

    $conditions = ["inv.status NOT IN ('draft', 'rejected')"];
    $params = [];

    if ($filterStart) {
        $conditions[] = "inv.invoice_date >= ?";
        $params[] = $filterStart;
    }
    if ($filterEnd) {
        $conditions[] = "inv.invoice_date <= ?";
        $params[] = $filterEnd;
    }
    if ($filterCustomer) {
        $conditions[] = "inv.customer_id = ?";
        $params[] = $filterCustomer;
    }
    if ($filterStatus) {
        $conditions[] = "inv.status = ?";
        $params[] = $filterStatus;
    }

    $whereClause = "";
    if (!empty($conditions)) {
        $whereClause = "WHERE " . implode(" AND ", $conditions);
    }

    $sql = "
        SELECT inv.invoice_no, inv.invoice_date, cust.company_name as customer_name,
               inv.termin_no, inv.status as inv_status,
               inv.total as inv_total, COALESCE(SUM(cp.amount),0) as total_received
        FROM invoices inv
        JOIN customers cust ON inv.customer_id = cust.id
        JOIN quotations q ON inv.quotation_id = q.id
        LEFT JOIN customer_payments cp ON cp.invoice_id = inv.id
        $whereClause
        GROUP BY inv.id
        ORDER BY (inv.total - COALESCE(SUM(cp.amount),0)) DESC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    startCSV('Outstanding_Customer_' . date('d-m-Y'));
    csvRow(['Rekap Piutang Customer (Outstanding)']);
    csvRow(['Tanggal Export: ' . date('d-m-Y H:i')]);
    csvRow([]);
    csvRow(['No','No. Invoice','Tanggal','Customer','Termin','Status','Nilai Invoice (Rp)','Diterima (Rp)','Piutang (Rp)']);
    $no = 1;
    foreach ($rows as $inv) {
        $out = $inv['inv_total'] - $inv['total_received'];
        csvRow([
            $no++,
            $inv['invoice_no'],
            date('d-m-Y', strtotime($inv['invoice_date'])),
            $inv['customer_name'],
            'T' . $inv['termin_no'],
            ucfirst(str_replace('_', ' ', $inv['inv_status'])),
            csvNum($inv['inv_total']),
            csvNum($inv['total_received']),
            csvNum($out),
        ]);
    }
}

// ══════════════════════════════════════════════════════════
// 4. PROFIT & LOSS
// ══════════════════════════════════════════════════════════
elseif ($type === 'profit_loss') {
    $filterYear  = (int)($_GET['year']  ?? date('Y'));
    $filterMonth = (isset($_GET['month']) && is_numeric($_GET['month'])) ? (int)$_GET['month'] : '';
    $filterCompany = $_GET['company_id'] ?? '';

    $monthNames  = [1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',
                    7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'];
    $periodLabel = $filterMonth ? $monthNames[$filterMonth] . ' ' . $filterYear : 'Tahun ' . $filterYear;
    $fileMonth   = $filterMonth ? $filterMonth . '_' : '';

    $monthCondition = '';
    $monthConditionInv = '';
    $monthConditionVP = '';
    $monthConditionCP = '';
    $params = [];

    if ($filterMonth) {
        $monthCondition = " AND MONTH(po.po_date) = ? AND YEAR(po.po_date) = ?";
        $monthConditionInv = " AND MONTH(inv.invoice_date) = ? AND YEAR(inv.invoice_date) = ?";
        $monthConditionVP = " AND MONTH(vp.payment_date) = ? AND YEAR(vp.payment_date) = ?";
        $monthConditionCP = " AND MONTH(cp.payment_date) = ? AND YEAR(cp.payment_date) = ?";
        
        $params[] = $filterMonth;
        $params[] = $filterYear;
    } else {
        $monthCondition = " AND YEAR(po.po_date) = ?";
        $monthConditionInv = " AND YEAR(inv.invoice_date) = ?";
        $monthConditionVP = " AND YEAR(vp.payment_date) = ?";
        $monthConditionCP = " AND YEAR(cp.payment_date) = ?";
        
        $params[] = $filterYear;
    }

    $compConditionInv = "";
    $compConditionPO = "";
    $compConditionCP = "";
    $compConditionVP = "";

    if ($filterCompany) {
        $compConditionInv = " AND inv.company_id = " . (int)$filterCompany;
        $compConditionPO = " AND po.company_id = " . (int)$filterCompany;
        $compConditionCP = " AND inv.company_id = " . (int)$filterCompany;
        $compConditionVP = " AND po.company_id = " . (int)$filterCompany;
    }

    // Pendapatan (Invoice)
    $sqlRevenue = "SELECT COALESCE(SUM(inv.total), 0) FROM invoices inv WHERE inv.status NOT IN ('draft', 'rejected') $monthConditionInv $compConditionInv";
    $stmtRevenue = $pdo->prepare($sqlRevenue);
    $stmtRevenue->execute($params);
    $totalRevenue = $stmtRevenue->fetchColumn();

    // Pengeluaran (PO)
    $sqlCOGS = "SELECT COALESCE(SUM(po.total), 0) FROM purchase_orders po WHERE po.status NOT IN ('draft', 'cancelled', 'rejected') $monthCondition $compConditionPO";
    $stmtCOGS = $pdo->prepare($sqlCOGS);
    $stmtCOGS->execute($params);
    $totalCOGS = $stmtCOGS->fetchColumn();

    // Cash In (Customer Payments)
    $sqlCashIn = "SELECT COALESCE(SUM(cp.amount), 0) FROM customer_payments cp JOIN invoices inv ON cp.invoice_id = inv.id WHERE 1=1 $monthConditionCP $compConditionCP";
    $stmtCashIn = $pdo->prepare($sqlCashIn);
    $stmtCashIn->execute($params);
    $totalCashIn = $stmtCashIn->fetchColumn();

    // Cash Out (Vendor Payments)
    $sqlCashOut = "SELECT COALESCE(SUM(vp.amount), 0) FROM vendor_payments vp JOIN purchase_orders po ON vp.po_id = po.id WHERE 1=1 $monthConditionVP $compConditionVP";
    $stmtCashOut = $pdo->prepare($sqlCashOut);
    $stmtCashOut->execute($params);
    $totalCashOut = $stmtCashOut->fetchColumn();

    $grossProfit  = $totalRevenue - $totalCOGS;
    $netCashFlow  = $totalCashIn  - $totalCashOut;

    startCSV('Profit_Loss_' . $fileMonth . $filterYear . '_' . date('d-m-Y'));
    csvRow(['Laporan Laba Rugi (Profit & Loss) — ' . $periodLabel]);
    csvRow(['Tanggal Export: ' . date('d-m-Y H:i')]);
    csvRow([]);
    csvRow(['Keterangan','Jumlah (Rp)']);
    csvRow(['Pendapatan (Invoice Terbit)',        csvNum($totalRevenue)]);
    csvRow(['Harga Pokok / Biaya Pengadaan (PO)', '(' . csvNum($totalCOGS) . ')']);
    csvRow(['LABA KOTOR (GROSS PROFIT)',           csvNum($grossProfit)]);
    csvRow([]);
    csvRow(['Cash In (Penerimaan dari Customer)', csvNum($totalCashIn)]);
    csvRow(['Cash Out (Pembayaran ke Vendor)',     '(' . csvNum($totalCashOut) . ')']);
    csvRow(['NET CASH FLOW',                        csvNum($netCashFlow)]);
}

// ══════════════════════════════════════════════════════════
// 5. LAPORAN STOK
// ══════════════════════════════════════════════════════════
elseif ($type === 'stock_report') {
    $filterCategory = $_GET['category_id'] ?? '';
    $filterLocation = $_GET['warehouse_location'] ?? '';

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
    if ($filterStart) {
        $dateCond .= " AND st.created_at >= " . $pdo->quote($filterStart . ' 00:00:00');
    }
    if ($filterEnd) {
        $dateCond .= " AND st.created_at <= " . $pdo->quote($filterEnd . ' 23:59:59');
    }

    $sql = "
        SELECT i.item_code, c.name as category_name, i.description, i.uom,
               i.current_stock, i.minimum_stock,
               (SELECT COALESCE(SUM(st.qty),0) FROM stock_transactions st WHERE st.item_id=i.id AND st.transaction_type='in' $dateCond) as total_in,
               (SELECT COALESCE(SUM(st.qty),0) FROM stock_transactions st WHERE st.item_id=i.id AND st.transaction_type IN ('out','transfer_out') $dateCond) as total_out,
               (SELECT COUNT(*) FROM stock_transactions st WHERE st.item_id=i.id $dateCond) as tx_count
        FROM items i JOIN categories c ON i.category_id=c.id
        $whereItem
        ORDER BY i.item_code ASC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($queryParams);
    $rows = $stmt->fetchAll();

    startCSV('Laporan_Stok_' . date('d-m-Y'));
    csvRow(['Laporan Rekap Stok & Mutasi']);
    csvRow(['Tanggal Export: ' . date('d-m-Y H:i')]);
    csvRow([]);
    csvRow(['No','Kode','Kategori','Nama Barang','Satuan','Total In','Total Out','Stok Sekarang','Jml Mutasi','Keterangan']);
    $no = 1;
    foreach ($rows as $item) {
        $ket = ($item['current_stock'] <= $item['minimum_stock']) ? 'STOK RENDAH' : '';
        csvRow([
            $no++,
            $item['item_code'],
            $item['category_name'],
            $item['description'],
            $item['uom'],
            (float)$item['total_in'],
            (float)$item['total_out'],
            (float)$item['current_stock'],
            $item['tx_count'],
            $ket,
        ]);
    }
}

// ══════════════════════════════════════════════════════════
// 6. KARTU STOK (detail per item)
// ══════════════════════════════════════════════════════════
elseif ($type === 'stock_detail') {
    $itemId = (int)($_GET['item_id'] ?? 0);
    if (!$itemId) die('item_id tidak valid.');

    $stmtItem = $pdo->prepare("SELECT i.*, c.name as category_name FROM items i JOIN categories c ON i.category_id=c.id WHERE i.id=?");
    $stmtItem->execute([$itemId]);
    $item = $stmtItem->fetch();
    if (!$item) die('Barang tidak ditemukan.');

    $txQuery = "
        SELECT st.created_at, st.transaction_type, st.qty, st.reference_type, st.reference_id, st.notes,
               u.full_name as user_name, p.name as project_name
        FROM stock_transactions st
        LEFT JOIN users u ON st.created_by=u.id
        LEFT JOIN projects p ON st.project_id=p.id
        WHERE st.item_id = :item_id
    ";
    $txParams = ['item_id' => $itemId];
    if ($filterStart) {
        $txQuery .= " AND st.created_at >= :start_date";
        $txParams['start_date'] = $filterStart . ' 00:00:00';
    }
    if ($filterEnd) {
        $txQuery .= " AND st.created_at <= :end_date";
        $txParams['end_date'] = $filterEnd . ' 23:59:59';
    }
    $txQuery .= " ORDER BY st.created_at DESC";

    $stmtTx = $pdo->prepare($txQuery);
    $stmtTx->execute($txParams);
    $transactions = $stmtTx->fetchAll();

    $typeMap = ['in'=>'Masuk (IN)','out'=>'Keluar (OUT)','transfer_out'=>'Transfer Out','transfer_in'=>'Transfer In','adjustment'=>'Penyesuaian'];

    startCSV('Kartu_Stok_' . $item['item_code'] . '_' . date('d-m-Y'));
    csvRow(['KARTU STOK: ' . $item['item_code'] . ' — ' . $item['description']]);
    csvRow(['Kategori: ' . $item['category_name'] . ' | Satuan: ' . $item['uom'] . ' | Stok Saat Ini: ' . (float)$item['current_stock']]);
    csvRow(['Tanggal Export: ' . date('d-m-Y H:i')]);
    csvRow([]);
    csvRow(['No','Waktu','Tipe Transaksi','Qty','Referensi','Proyek','Petugas','Catatan']);
    $no = 1;
    foreach ($transactions as $tx) {
        csvRow([
            $no++,
            date('d-m-Y H:i', strtotime($tx['created_at'])),
            $typeMap[$tx['transaction_type']] ?? $tx['transaction_type'],
            (float)$tx['qty'],
            $tx['reference_type'] . '#' . $tx['reference_id'],
            $tx['project_name'] ?: '-',
            $tx['user_name']    ?: '-',
            $tx['notes']        ?: '-',
        ]);
    }
}

exit;
