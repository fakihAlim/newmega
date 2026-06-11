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
    'claim_nota'           => 'report_claim_nota',
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
    $escaped = array_map(fn($v) => '"' . str_replace('"', '""', $v) . '"', $cols);
    echo implode(',', $escaped) . "\r\n";
}

// ══════════════════════════════════════════════════════════
// 1. PENGELUARAN PROYEK
// ══════════════════════════════════════════════════════════
if ($type === 'project_expense') {
    $sql = "
        SELECT p.name, p.status, p.budget,
            (SELECT COUNT(*) FROM material_requests mr WHERE mr.project_id=p.id AND mr.status!='draft') as total_mr,
            (SELECT COALESCE(SUM(po.total),0) FROM purchase_orders po
             JOIN po_mr_links pml ON pml.po_id=po.id
             JOIN material_requests mr ON pml.mr_id=mr.id
             WHERE mr.project_id=p.id AND po.status NOT IN ('draft','cancelled','rejected')) as total_po_value,
            (SELECT COALESCE(SUM(vp.amount),0) FROM vendor_payments vp
             JOIN purchase_orders po ON vp.po_id=po.id
             JOIN po_mr_links pml ON pml.po_id=po.id
             JOIN material_requests mr ON pml.mr_id=mr.id
             WHERE mr.project_id=p.id) as total_paid,
            (SELECT COALESCE(SUM(cn.subtotal),0) FROM claim_notas cn
             WHERE cn.project_id=p.id AND cn.status='approved') as total_claim
        FROM projects p ORDER BY p.name
    ";
    $rows = $pdo->query($sql)->fetchAll();

    startCSV('Pengeluaran_Proyek_' . date('d-m-Y'));
    csvRow(['Laporan Pengeluaran Proyek']);
    csvRow(['Tanggal Export: ' . date('d-m-Y H:i')]);
    csvRow([]);
    csvRow(['No','Nama Proyek','Status','Jumlah MR','Budget (Rp)','Nilai PO (Rp)','Claim Nota (Rp)','Terbayar (Rp)','% Terpakai']);
    $no = 1;
    foreach ($rows as $p) {
        $totalExpense = $p['total_po_value'] + $p['total_claim'];
        $pct = $p['budget'] > 0 ? round(($totalExpense / $p['budget']) * 100, 1) : 0;
        csvRow([
            $no++,
            $p['name'],
            ucfirst($p['status']),
            $p['total_mr'],
            csvNum($p['budget']),
            csvNum($p['total_po_value']),
            csvNum($p['total_claim']),
            csvNum($p['total_paid']),
            $pct . '%',
        ]);
    }
}

// ══════════════════════════════════════════════════════════
// 2. OUTSTANDING VENDOR
// ══════════════════════════════════════════════════════════
elseif ($type === 'vendor_outstanding') {
    $sql = "
        SELECT po.po_number, po.po_date, v.company_name as vendor_name, po.status as po_status,
               po.total as po_total, COALESCE(SUM(vp.amount),0) as total_paid
        FROM purchase_orders po
        JOIN vendors v ON po.vendor_id=v.id
        LEFT JOIN vendor_payments vp ON vp.po_id=po.id
        WHERE po.status NOT IN ('draft','cancelled','rejected')
        GROUP BY po.id
        ORDER BY (po.total - COALESCE(SUM(vp.amount),0)) DESC
    ";
    $rows = $pdo->query($sql)->fetchAll();

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
    $sql = "
        SELECT inv.invoice_no, inv.invoice_date, cust.company_name as customer_name,
               inv.termin_no, inv.status as inv_status,
               inv.total as inv_total, COALESCE(SUM(cp.amount),0) as total_received
        FROM invoices inv
        JOIN customers cust ON inv.customer_id=cust.id
        JOIN quotations q ON inv.quotation_id=q.id
        LEFT JOIN customer_payments cp ON cp.invoice_id=inv.id
        WHERE inv.status NOT IN ('draft','rejected')
        GROUP BY inv.id
        ORDER BY (inv.total - COALESCE(SUM(cp.amount),0)) DESC
    ";
    $rows = $pdo->query($sql)->fetchAll();

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
    $filterMonth = (int)($_GET['month'] ?? 0);
    $monthNames  = [1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',
                    7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'];
    $periodLabel = $filterMonth ? $monthNames[$filterMonth] . ' ' . $filterYear : 'Tahun ' . $filterYear;
    $fileMonth   = $filterMonth ? $filterMonth . '_' : '';

    $mc  = $filterMonth ? " AND MONTH(po.po_date)=$filterMonth AND YEAR(po.po_date)=$filterYear"              : " AND YEAR(po.po_date)=$filterYear";
    $mi  = $filterMonth ? " AND MONTH(inv.invoice_date)=$filterMonth AND YEAR(inv.invoice_date)=$filterYear"  : " AND YEAR(inv.invoice_date)=$filterYear";
    $mvp = $filterMonth ? " AND MONTH(vp.payment_date)=$filterMonth AND YEAR(vp.payment_date)=$filterYear"    : " AND YEAR(vp.payment_date)=$filterYear";
    $mcp = $filterMonth ? " AND MONTH(cp.payment_date)=$filterMonth AND YEAR(cp.payment_date)=$filterYear"    : " AND YEAR(cp.payment_date)=$filterYear";

    $totalRevenue = $pdo->query("SELECT COALESCE(SUM(inv.total),0) FROM invoices inv WHERE inv.status NOT IN ('draft','rejected') $mi")->fetchColumn();
    $totalCOGS    = $pdo->query("SELECT COALESCE(SUM(po.total),0) FROM purchase_orders po WHERE po.status NOT IN ('draft','cancelled','rejected') $mc")->fetchColumn();
    $totalCashIn  = $pdo->query("SELECT COALESCE(SUM(cp.amount),0) FROM customer_payments cp WHERE 1=1 $mcp")->fetchColumn();
    $totalCashOut = $pdo->query("SELECT COALESCE(SUM(vp.amount),0) FROM vendor_payments vp WHERE 1=1 $mvp")->fetchColumn();
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
    $sql = "
        SELECT i.item_code, c.name as category_name, i.description, i.uom,
               i.current_stock, i.minimum_stock,
               (SELECT COALESCE(SUM(st.qty),0) FROM stock_transactions st WHERE st.item_id=i.id AND st.transaction_type='in') as total_in,
               (SELECT COALESCE(SUM(st.qty),0) FROM stock_transactions st WHERE st.item_id=i.id AND st.transaction_type IN ('out','transfer_out')) as total_out,
               (SELECT COUNT(*) FROM stock_transactions st WHERE st.item_id=i.id) as tx_count
        FROM items i JOIN categories c ON i.category_id=c.id
        WHERE i.is_active=1 ORDER BY i.item_code ASC
    ";
    $rows = $pdo->query($sql)->fetchAll();

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

    $stmtTx = $pdo->prepare("
        SELECT st.created_at, st.transaction_type, st.qty, st.reference_type, st.reference_id, st.notes,
               u.full_name as user_name, p.name as project_name
        FROM stock_transactions st
        LEFT JOIN users u ON st.created_by=u.id
        LEFT JOIN projects p ON st.project_id=p.id
        WHERE st.item_id=? ORDER BY st.created_at DESC
    ");
    $stmtTx->execute([$itemId]);
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

// ════════════════════════════════════════════════════════
// 7. CLAIM NOTA
// ════════════════════════════════════════════════════════
elseif ($type === 'claim_nota') {
    $sql = "
        SELECT cn.claim_number, cn.claim_date, cn.employee_name, cn.store_name, cn.subtotal, cn.status, cn.is_reimbursed,
               p.name as project_name, c.name as company_name
        FROM claim_notas cn
        JOIN projects p ON cn.project_id = p.id
        JOIN companies c ON cn.company_id = c.id
        WHERE cn.status != 'draft'
        ORDER BY cn.claim_date DESC
    ";
    $rows = $pdo->query($sql)->fetchAll();

    startCSV('Laporan_Claim_Nota_' . date('d-m-Y'));
    csvRow(['Laporan Claim Nota']);
    csvRow(['Tanggal Export: ' . date('d-m-Y H:i')]);
    csvRow([]);
    csvRow(['No','No. Claim','Tanggal','Karyawan','Proyek','Perusahaan','Toko','Total (Rp)','Status']);
    $no = 1;
    foreach ($rows as $r) {
        $statusText = $r['is_reimbursed'] ? 'Reimbursed' : ucfirst($r['status']);
        csvRow([
            $no++,
            $r['claim_number'],
            date('d-m-Y', strtotime($r['claim_date'])),
            $r['employee_name'],
            $r['project_name'],
            $r['company_name'],
            $r['store_name'] ?: '-',
            csvNum($r['subtotal']),
            $statusText,
        ]);
    }
}

exit;
