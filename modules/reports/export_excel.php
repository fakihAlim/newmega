<?php
/**
 * Export Excel - Handler untuk semua laporan
 * ?type=project_expense|vendor_outstanding|customer_outstanding|profit_loss|stock_report|stock_detail
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

$type = $_GET['type'] ?? '';

// Permission map
$permMap = [
    'project_expense'      => 'report_project_expense',
    'vendor_outstanding'   => 'report_vendor_outstanding',
    'customer_outstanding' => 'report_customer_outstanding',
    'profit_loss'          => 'report_profit_loss',
    'stock_report'         => 'report_stock',
    'stock_detail'         => 'report_stock',
    'claim_nota'           => 'report_claim_nota',
];

if (!isset($permMap[$type])) {
    die('Tipe laporan tidak valid.');
}
requirePermission($permMap[$type]);

// ── Helper: format rupiah plain (no Rp prefix, just number) ──
function excelRupiah($n) { return number_format((float)$n, 0, ',', '.'); }

// ── Helper: apply header row style ──
function styleHeader(Spreadsheet $sp, string $range, bool $dark = false) {
    $color = $dark ? 'FF4B5563' : 'FFD1D5DB';
    $sp->getActiveSheet()->getStyle($range)->applyFromArray([
        'font'      => ['bold' => true, 'color' => ['argb' => 'FF000000']],
        'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $color]],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF000000']]],
    ]);
}

// ── Helper: apply data range style ──
function styleData(Spreadsheet $sp, string $range) {
    $sp->getActiveSheet()->getStyle($range)->applyFromArray([
        'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF000000']]],
        'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
    ]);
}

// ── Helper: title row ──
function writeTitle(Spreadsheet $sp, string $title, int $colCount, string &$filename) {
    $ws = $sp->getActiveSheet();
    $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colCount);
    $ws->mergeCells("A1:{$lastCol}1");
    $ws->setCellValue('A1', strtoupper($title));
    $ws->getStyle('A1')->applyFromArray([
        'font'      => ['bold' => true, 'size' => 14],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
    ]);
    $ws->mergeCells("A2:{$lastCol}2");
    $ws->setCellValue('A2', 'Tanggal Export: ' . date('d-m-Y H:i'));
    $ws->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $ws->getRowDimension(1)->setRowHeight(22);
}

$sp = new Spreadsheet();
$ws = $sp->getActiveSheet();
$filename = 'Laporan_' . date('d-m-Y');

// ═══════════════════════════════════════════════════════════
// 1. PENGELUARAN PROYEK
// ═══════════════════════════════════════════════════════════
if ($type === 'project_expense') {
    requirePermission('report_project_expense');
    $filename = 'Pengeluaran_Proyek_' . date('d-m-Y');
    $ws->setTitle('Pengeluaran Proyek');

    $sql = "
        SELECT p.name, p.status, p.budget,
            (SELECT COUNT(*) FROM material_requests mr WHERE mr.project_id = p.id AND mr.status != 'draft') as total_mr,
            (SELECT COALESCE(SUM(po.total),0) FROM purchase_orders po
             JOIN po_mr_links pml ON pml.po_id = po.id
             JOIN material_requests mr ON pml.mr_id = mr.id
             WHERE mr.project_id = p.id AND po.status NOT IN ('draft','cancelled','rejected')) as total_po_value,
            (SELECT COALESCE(SUM(vp.amount),0) FROM vendor_payments vp
             JOIN purchase_orders po ON vp.po_id = po.id
             JOIN po_mr_links pml ON pml.po_id = po.id
             JOIN material_requests mr ON pml.mr_id = mr.id
             WHERE mr.project_id = p.id) as total_paid,
            (SELECT COALESCE(SUM(cn.subtotal),0) FROM claim_notas cn
             WHERE cn.project_id = p.id AND cn.status = 'approved') as total_claim
        FROM projects p ORDER BY p.name
    ";
    $rows = $pdo->query($sql)->fetchAll();

    writeTitle($sp, 'Laporan Pengeluaran Proyek', 8, $filename);

    $headers = ['No','Nama Proyek','Status','Jumlah MR','Budget (Rp)','Nilai PO (Rp)','Claim Nota (Rp)','Terbayar (Rp)'];
    $ws->fromArray($headers, null, 'A3');
    styleHeader($sp, 'A3:H3');

    $row = 4; $no = 1;
    foreach ($rows as $p) {
        $ws->fromArray([
            $no++,
            $p['name'],
            ucfirst($p['status']),
            $p['total_mr'],
            excelRupiah($p['budget']),
            excelRupiah($p['total_po_value']),
            excelRupiah($p['total_claim']),
            excelRupiah($p['total_paid']),
        ], null, "A{$row}");
        $row++;
    }
    if ($row > 4) styleData($sp, "A4:H" . ($row-1));

    $widths = [5,35,15,12,20,20,20,20];
    foreach ($widths as $i => $w) $ws->getColumnDimensionByColumn($i+1)->setWidth($w);
}

// ═══════════════════════════════════════════════════════════
// 2. OUTSTANDING VENDOR
// ═══════════════════════════════════════════════════════════
elseif ($type === 'vendor_outstanding') {
    $filename = 'Outstanding_Vendor_' . date('d-m-Y');
    $ws->setTitle('Outstanding Vendor');

    $sql = "
        SELECT po.po_number, po.po_date, v.company_name as vendor_name, po.status as po_status,
               po.total as po_total, COALESCE(SUM(vp.amount),0) as total_paid
        FROM purchase_orders po
        JOIN vendors v ON po.vendor_id = v.id
        LEFT JOIN vendor_payments vp ON vp.po_id = po.id
        WHERE po.status NOT IN ('draft','cancelled','rejected')
        GROUP BY po.id
        ORDER BY (po.total - COALESCE(SUM(vp.amount),0)) DESC
    ";
    $rows = $pdo->query($sql)->fetchAll();

    writeTitle($sp, 'Rekap Hutang ke Vendor (Outstanding)', 7, $filename);
    $headers = ['No','No. PO','Tanggal','Vendor','Status PO','Nilai PO (Rp)','Terbayar (Rp)','Outstanding (Rp)'];
    $ws->fromArray($headers, null, 'A3');
    styleHeader($sp, 'A3:H3');

    $row = 4; $no = 1;
    foreach ($rows as $po) {
        $out = $po['po_total'] - $po['total_paid'];
        $ws->fromArray([
            $no++,
            $po['po_number'],
            date('d-m-Y', strtotime($po['po_date'])),
            $po['vendor_name'],
            ucfirst(str_replace('_',' ',$po['po_status'])),
            excelRupiah($po['po_total']),
            excelRupiah($po['total_paid']),
            excelRupiah($out),
        ], null, "A{$row}");
        $row++;
    }
    if ($row > 4) styleData($sp, "A4:H" . ($row-1));

    $widths = [5,18,12,30,18,20,20,22];
    foreach ($widths as $i => $w) $ws->getColumnDimensionByColumn($i+1)->setWidth($w);
}

// ═══════════════════════════════════════════════════════════
// 3. OUTSTANDING CUSTOMER
// ═══════════════════════════════════════════════════════════
elseif ($type === 'customer_outstanding') {
    $filename = 'Outstanding_Customer_' . date('d-m-Y');
    $ws->setTitle('Outstanding Customer');

    $sql = "
        SELECT inv.invoice_no, inv.invoice_date, cust.company_name as customer_name,
               inv.termin_no, inv.status as inv_status,
               inv.total as inv_total, COALESCE(SUM(cp.amount),0) as total_received
        FROM invoices inv
        JOIN customers cust ON inv.customer_id = cust.id
        JOIN quotations q ON inv.quotation_id = q.id
        LEFT JOIN customer_payments cp ON cp.invoice_id = inv.id
        WHERE inv.status NOT IN ('draft','rejected')
        GROUP BY inv.id
        ORDER BY (inv.total - COALESCE(SUM(cp.amount),0)) DESC
    ";
    $rows = $pdo->query($sql)->fetchAll();

    writeTitle($sp, 'Rekap Piutang Customer (Outstanding)', 8, $filename);
    $headers = ['No','No. Invoice','Tanggal','Customer','Termin','Status','Nilai Invoice (Rp)','Diterima (Rp)','Piutang (Rp)'];
    $ws->fromArray($headers, null, 'A3');
    styleHeader($sp, 'A3:I3');

    $row = 4; $no = 1;
    foreach ($rows as $inv) {
        $out = $inv['inv_total'] - $inv['total_received'];
        $ws->fromArray([
            $no++,
            $inv['invoice_no'],
            date('d-m-Y', strtotime($inv['invoice_date'])),
            $inv['customer_name'],
            'T' . $inv['termin_no'],
            ucfirst(str_replace('_',' ',$inv['inv_status'])),
            excelRupiah($inv['inv_total']),
            excelRupiah($inv['total_received']),
            excelRupiah($out),
        ], null, "A{$row}");
        $row++;
    }
    if ($row > 4) styleData($sp, "A4:I" . ($row-1));

    $widths = [5,18,12,30,8,15,22,20,22];
    foreach ($widths as $i => $w) $ws->getColumnDimensionByColumn($i+1)->setWidth($w);
}

// ═══════════════════════════════════════════════════════════
// 4. PROFIT & LOSS
// ═══════════════════════════════════════════════════════════
elseif ($type === 'profit_loss') {
    $filterYear  = (int)($_GET['year']  ?? date('Y'));
    $filterMonth = (int)($_GET['month'] ?? 0);
    $monthNames  = [1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',
                    7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'];
    $periodLabel = $filterMonth ? $monthNames[$filterMonth] . ' ' . $filterYear : 'Tahun ' . $filterYear;
    $filename    = 'Profit_Loss_' . ($filterMonth ? $filterMonth . '_' : '') . $filterYear . '_' . date('d-m-Y');
    $ws->setTitle('Profit Loss');

    $mc  = $filterMonth ? " AND MONTH(po.po_date)=$filterMonth AND YEAR(po.po_date)=$filterYear"       : " AND YEAR(po.po_date)=$filterYear";
    $mi  = $filterMonth ? " AND MONTH(inv.invoice_date)=$filterMonth AND YEAR(inv.invoice_date)=$filterYear" : " AND YEAR(inv.invoice_date)=$filterYear";
    $mvp = $filterMonth ? " AND MONTH(vp.payment_date)=$filterMonth AND YEAR(vp.payment_date)=$filterYear"   : " AND YEAR(vp.payment_date)=$filterYear";
    $mcp = $filterMonth ? " AND MONTH(cp.payment_date)=$filterMonth AND YEAR(cp.payment_date)=$filterYear"   : " AND YEAR(cp.payment_date)=$filterYear";

    $totalRevenue = $pdo->query("SELECT COALESCE(SUM(inv.total),0) FROM invoices inv WHERE inv.status NOT IN ('draft','rejected') $mi")->fetchColumn();
    $totalCOGS    = $pdo->query("SELECT COALESCE(SUM(po.total),0) FROM purchase_orders po WHERE po.status NOT IN ('draft','cancelled','rejected') $mc")->fetchColumn();
    $totalCashIn  = $pdo->query("SELECT COALESCE(SUM(cp.amount),0) FROM customer_payments cp WHERE 1=1 $mcp")->fetchColumn();
    $totalCashOut = $pdo->query("SELECT COALESCE(SUM(vp.amount),0) FROM vendor_payments vp WHERE 1=1 $mvp")->fetchColumn();

    $grossProfit = $totalRevenue - $totalCOGS;
    $netCashFlow = $totalCashIn  - $totalCashOut;

    writeTitle($sp, 'Laporan Laba Rugi (Profit & Loss) — ' . $periodLabel, 2, $filename);
    $ws->fromArray(['Keterangan','Jumlah (Rp)'], null, 'A3');
    styleHeader($sp, 'A3:B3');

    $dataRows = [
        ['Pendapatan (Invoice Terbit)',          excelRupiah($totalRevenue)],
        ['Harga Pokok / Biaya Pengadaan (PO)',   '(' . excelRupiah($totalCOGS) . ')'],
        ['LABA KOTOR (GROSS PROFIT)',             excelRupiah($grossProfit)],
        ['',''],
        ['Cash In (Penerimaan dari Customer)',   excelRupiah($totalCashIn)],
        ['Cash Out (Pembayaran ke Vendor)',       '(' . excelRupiah($totalCashOut) . ')'],
        ['NET CASH FLOW',                         excelRupiah($netCashFlow)],
    ];
    $row = 4;
    foreach ($dataRows as $dr) {
        $ws->fromArray($dr, null, "A{$row}");
        if (in_array($row - 4, [2, 6])) { // bold total rows
            $ws->getStyle("A{$row}:B{$row}")->getFont()->setBold(true);
            $ws->getStyle("A{$row}:B{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE5E7EB');
        }
        $row++;
    }
    styleData($sp, 'A4:B' . ($row-1));
    $ws->getColumnDimension('A')->setWidth(45);
    $ws->getColumnDimension('B')->setWidth(25);
}

// ═══════════════════════════════════════════════════════════
// 5. LAPORAN STOK
// ═══════════════════════════════════════════════════════════
elseif ($type === 'stock_report') {
    $filename = 'Laporan_Stok_' . date('d-m-Y');
    $ws->setTitle('Rekap Stok');

    $sql = "
        SELECT i.item_code, c.name as category_name, i.description, i.uom, i.current_stock, i.minimum_stock,
               (SELECT COALESCE(SUM(st.qty),0) FROM stock_transactions st WHERE st.item_id=i.id AND st.transaction_type='in') as total_in,
               (SELECT COALESCE(SUM(st.qty),0) FROM stock_transactions st WHERE st.item_id=i.id AND st.transaction_type IN ('out','transfer_out')) as total_out,
               (SELECT COUNT(*) FROM stock_transactions st WHERE st.item_id=i.id) as tx_count
        FROM items i JOIN categories c ON i.category_id=c.id
        WHERE i.is_active=1 ORDER BY i.item_code ASC
    ";
    $rows = $pdo->query($sql)->fetchAll();

    writeTitle($sp, 'Laporan Rekap Stok & Mutasi', 8, $filename);
    $headers = ['No','Kode','Kategori','Nama Barang','Satuan','Total In','Total Out','Stok Sekarang','Jml Mutasi'];
    $ws->fromArray($headers, null, 'A3');
    styleHeader($sp, 'A3:I3');

    $row = 4; $no = 1;
    foreach ($rows as $item) {
        $ws->fromArray([
            $no++,
            $item['item_code'],
            $item['category_name'],
            $item['description'],
            $item['uom'],
            (float)$item['total_in'],
            (float)$item['total_out'],
            (float)$item['current_stock'],
            $item['tx_count'],
        ], null, "A{$row}");
        if ($item['current_stock'] <= $item['minimum_stock']) {
            $ws->getStyle("A{$row}:I{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFEE2E2');
        }
        $row++;
    }
    if ($row > 4) styleData($sp, "A4:I" . ($row-1));

    $widths = [5,12,18,35,8,10,10,14,10];
    foreach ($widths as $i => $w) $ws->getColumnDimensionByColumn($i+1)->setWidth($w);
}

// ═══════════════════════════════════════════════════════════
// 6. KARTU STOK (detail per item)
// ═══════════════════════════════════════════════════════════
elseif ($type === 'stock_detail') {
    $itemId = (int)($_GET['item_id'] ?? 0);
    if (!$itemId) die('item_id tidak valid.');

    $item = $pdo->prepare("SELECT i.*, c.name as category_name FROM items i JOIN categories c ON i.category_id=c.id WHERE i.id=?");
    $item->execute([$itemId]);
    $item = $item->fetch();
    if (!$item) die('Barang tidak ditemukan.');

    $filename = 'Kartu_Stok_' . $item['item_code'] . '_' . date('d-m-Y');
    $ws->setTitle('Kartu Stok');

    $txStmt = $pdo->prepare("
        SELECT st.created_at, st.transaction_type, st.qty, st.reference_type, st.reference_id, st.notes,
               u.full_name as user_name, p.name as project_name
        FROM stock_transactions st
        LEFT JOIN users u ON st.created_by=u.id
        LEFT JOIN projects p ON st.project_id=p.id
        WHERE st.item_id=? ORDER BY st.created_at DESC
    ");
    $txStmt->execute([$itemId]);
    $transactions = $txStmt->fetchAll();

    // Item info rows
    $ws->setCellValue('A1', 'KARTU STOK: ' . $item['item_code'] . ' — ' . $item['description']);
    $ws->mergeCells('A1:G1');
    $ws->getStyle('A1')->applyFromArray(['font'=>['bold'=>true,'size'=>13],'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER]]);
    $ws->setCellValue('A2', 'Kategori: ' . $item['category_name'] . ' | Satuan: ' . $item['uom'] . ' | Stok Saat Ini: ' . (float)$item['current_stock']);
    $ws->mergeCells('A2:G2');
    $ws->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $ws->setCellValue('A3', 'Tanggal Export: ' . date('d-m-Y H:i'));
    $ws->mergeCells('A3:G3');
    $ws->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    $headers = ['No','Waktu','Tipe Transaksi','Qty','Referensi','Proyek','Petugas','Catatan'];
    $ws->fromArray($headers, null, 'A4');
    styleHeader($sp, 'A4:H4');

    $typeMap = ['in'=>'Masuk (IN)','out'=>'Keluar (OUT)','transfer_out'=>'Transfer Out','transfer_in'=>'Transfer In','adjustment'=>'Penyesuaian'];
    $row = 5; $no = 1;
    foreach ($transactions as $tx) {
        $ws->fromArray([
            $no++,
            date('d-m-Y H:i', strtotime($tx['created_at'])),
            $typeMap[$tx['transaction_type']] ?? $tx['transaction_type'],
            (float)$tx['qty'],
            $tx['reference_type'] . '#' . $tx['reference_id'],
            $tx['project_name'] ?: '-',
            $tx['user_name'] ?: '-',
            $tx['notes'] ?: '-',
        ], null, "A{$row}");
        $row++;
    }
    if ($row > 5) styleData($sp, "A5:H" . ($row-1));

    $widths = [5,18,18,8,18,25,20,30];
    foreach ($widths as $i => $w) $ws->getColumnDimensionByColumn($i+1)->setWidth($w);
}

// ═══════════════════════════════════════════════════════════
// 7. CLAIM NOTA
// ═══════════════════════════════════════════════════════════
elseif ($type === 'claim_nota') {
    $filename = 'Laporan_Claim_Nota_' . date('d-m-Y');
    $ws->setTitle('Claim Nota');

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

    writeTitle($sp, 'Laporan Claim Nota', 9, $filename);
    $headers = ['No','No. Claim','Tanggal','Karyawan','Proyek','Perusahaan','Toko','Total (Rp)','Status'];
    $ws->fromArray($headers, null, 'A3');
    styleHeader($sp, 'A3:I3');

    $row = 4; $no = 1;
    foreach ($rows as $r) {
        $statusText = $r['is_reimbursed'] ? 'Reimbursed' : ucfirst($r['status']);
        $ws->fromArray([
            $no++,
            $r['claim_number'],
            date('d-m-Y', strtotime($r['claim_date'])),
            $r['employee_name'],
            $r['project_name'],
            $r['company_name'],
            $r['store_name'] ?: '-',
            excelRupiah($r['subtotal']),
            $statusText,
        ], null, "A{$row}");
        $row++;
    }
    if ($row > 4) styleData($sp, "A4:I" . ($row-1));

    $widths = [5,18,12,20,25,20,15,18,12];
    foreach ($widths as $i => $w) $ws->getColumnDimensionByColumn($i+1)->setWidth($w);
}

// ── Output file ──
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($sp);
$writer->save('php://output');
exit;
