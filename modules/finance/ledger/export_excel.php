<?php
/**
 * Finance - General Ledger Excel Export
 */
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../vendor/autoload.php';
requirePermission('ledger');

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

// Read filters
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$companyId = $_GET['company_id'] ?? '';
$paymentMethod = $_GET['payment_method'] ?? '';

// Helper: format rupiah
function excelRupiah($n) { 
    return $n != 0 ? number_format((float)$n, 0, ',', '.') : '-'; 
}

// Helper: header style
function styleHeader(Spreadsheet $sp, string $range) {
    $sp->getActiveSheet()->getStyle($range)->applyFromArray([
        'font'      => ['bold' => true, 'color' => ['argb' => 'FF000000']],
        'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFD1D5DB']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF000000']]],
    ]);
}

// Helper: data style
function styleData(Spreadsheet $sp, string $range) {
    $sp->getActiveSheet()->getStyle($range)->applyFromArray([
        'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF000000']]],
        'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
    ]);
}

// Calculate Saldo Awal (Opening Balance)
$openingSql = "
    SELECT COALESCE(SUM(debit), 0) - COALESCE(SUM(kredit), 0) AS saldo_awal FROM (
        -- Customer Payments (Debit)
        SELECT cp.payment_date AS tanggal, cp.amount AS debit, 0 AS kredit, cp.payment_method, inv.company_id
        FROM customer_payments cp
        JOIN invoices inv ON cp.invoice_id = inv.id
        
        UNION ALL
        
        -- Vendor Payments (Kredit)
        SELECT vp.payment_date AS tanggal, 0 AS debit, vp.amount AS kredit, vp.payment_method, po.company_id
        FROM vendor_payments vp
        JOIN purchase_orders po ON vp.po_id = po.id
        
        UNION ALL
        
        -- Claim Nota (Kredit)
        SELECT nc.claim_date AS tanggal, 0 AS debit, nc.total_amount AS kredit, 'Transfer/Cash' AS payment_method, nc.company_id
        FROM nota_claims nc
        WHERE nc.status = 'paid'
    ) AS temp
    WHERE tanggal < :start_date
      AND (:company_id = '' OR company_id = :company_id)
      AND (:payment_method = '' OR payment_method = :payment_method)
";

$openingStmt = $pdo->prepare($openingSql);
$openingStmt->execute([
    'start_date' => $startDate,
    'company_id' => $companyId,
    'payment_method' => $paymentMethod
]);
$openingBalance = (float)$openingStmt->fetchColumn();

// Fetch Chronological Mutasi Kas
$ledgerSql = "
    SELECT * FROM (
        -- Customer Payments (Debit)
        SELECT 
            cp.payment_date AS tanggal,
            cp.reference_no AS no_referensi,
            CONCAT('Penerimaan Invoice ', inv.invoice_no, ' - ', cust.company_name) AS keterangan,
            cp.amount AS debit,
            0 AS kredit,
            cp.payment_method AS metode,
            inv.company_id,
            c.name AS nama_perusahaan
        FROM customer_payments cp
        JOIN invoices inv ON cp.invoice_id = inv.id
        JOIN customers cust ON inv.customer_id = cust.id
        JOIN companies c ON inv.company_id = c.id
        
        UNION ALL
        
        -- Vendor Payments (Kredit)
        SELECT 
            vp.payment_date AS tanggal,
            vp.reference_no AS no_referensi,
            CONCAT('Pembayaran PO ', po.po_number, ' - ', v.company_name) AS keterangan,
            0 AS debit,
            vp.amount AS kredit,
            vp.payment_method AS metode,
            po.company_id,
            c.name AS nama_perusahaan
        FROM vendor_payments vp
        JOIN purchase_orders po ON vp.po_id = po.id
        JOIN vendors v ON po.vendor_id = v.id
        JOIN companies c ON po.company_id = c.id
        
        UNION ALL
        
        -- Claim Nota (Kredit)
        SELECT 
            nc.claim_date AS tanggal,
            nc.claim_number AS no_referensi,
            CONCAT('Reimburse Nota - Karyawan: ', COALESCE(u.full_name, nc.employee_name_manual)) AS keterangan,
            0 AS debit,
            nc.total_amount AS kredit,
            'Transfer/Cash' AS metode,
            nc.company_id,
            c.name AS nama_perusahaan
        FROM nota_claims nc
        LEFT JOIN users u ON nc.employee_id = u.id
        JOIN companies c ON nc.company_id = c.id
        WHERE nc.status = 'paid'
    ) AS cashflow_ledger
    WHERE tanggal BETWEEN :start_date AND :end_date
      AND (:company_id = '' OR company_id = :company_id)
      AND (:payment_method = '' OR payment_method = :payment_method)
    ORDER BY tanggal ASC, no_referensi ASC
";

$ledgerStmt = $pdo->prepare($ledgerSql);
$ledgerStmt->execute([
    'start_date' => $startDate,
    'end_date' => $endDate,
    'company_id' => $companyId,
    'payment_method' => $paymentMethod
]);
$transactions = $ledgerStmt->fetchAll();

// Excel Setup
$sp = new Spreadsheet();
$ws = $sp->getActiveSheet();
$ws->setTitle('Buku Kas Ledger');
$filename = 'Buku_Kas_Ledger_' . date('d-m-Y', strtotime($startDate)) . '_to_' . date('d-m-Y', strtotime($endDate));

// Title section
$ws->mergeCells("A1:I1");
$ws->setCellValue('A1', 'LAPORAN BUKU KAS (GENERAL LEDGER)');
$ws->getStyle('A1')->applyFromArray([
    'font' => ['bold' => true, 'size' => 14],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
]);

$periodString = 'Periode: ' . date('d-m-Y', strtotime($startDate)) . ' s/d ' . date('d-m-Y', strtotime($endDate));
if ($companyId) {
    $compName = $pdo->query("SELECT name FROM companies WHERE id = " . (int)$companyId)->fetchColumn();
    $periodString .= ' | Perusahaan: ' . $compName;
}
if ($paymentMethod) {
    $periodString .= ' | Metode: ' . $paymentMethod;
}

$ws->mergeCells("A2:I2");
$ws->setCellValue('A2', $periodString);
$ws->getStyle('A2')->applyFromArray([
    'font' => ['italic' => true, 'size' => 10],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
]);

// Headers
$headers = ['No', 'Tanggal', 'No. Referensi', 'Keterangan Mutasi', 'Perusahaan', 'Metode', 'Debit (+)', 'Kredit (-)', 'Saldo'];
$ws->fromArray($headers, null, 'A4');
styleHeader($sp, 'A4:I4');

$ws->getRowDimension(1)->setRowHeight(25);
$ws->getRowDimension(2)->setRowHeight(18);
$ws->getRowDimension(4)->setRowHeight(22);

// Saldo Awal Row
$ws->setCellValue('A5', '1');
$ws->setCellValue('B5', date('d-m-Y', strtotime($startDate)));
$ws->setCellValue('D5', 'SALDO AWAL (OPENING BALANCE)');
$ws->setCellValue('I5', excelRupiah($openingBalance));
$ws->getStyle('A5:I5')->getFont()->setBold(true);
$ws->getStyle('A5:I5')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF2F2F2');
$ws->getStyle('G5:I5')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

$row = 6;
$no = 2;
$currentRunningBalance = $openingBalance;
$totalDebit = 0;
$totalKredit = 0;

foreach ($transactions as $t) {
    $debit = (float)$t['debit'];
    $kredit = (float)$t['kredit'];
    $currentRunningBalance += ($debit - $kredit);
    $totalDebit += $debit;
    $totalKredit += $kredit;
    
    $ws->fromArray([
        $no++,
        date('d-m-Y', strtotime($t['tanggal'])),
        $t['no_referensi'],
        $t['keterangan'],
        $t['nama_perusahaan'],
        $t['metode'],
        excelRupiah($debit),
        excelRupiah($kredit),
        excelRupiah($currentRunningBalance)
    ], null, "A{$row}");
    
    // Align values
    $ws->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $ws->getStyle("B{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $ws->getStyle("C{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $ws->getStyle("E{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
    $ws->getStyle("F{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $ws->getStyle("G{$row}:I{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    
    // Style text color of debit/credit
    if ($debit > 0) {
        $ws->getStyle("G{$row}")->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FF16A34A'));
    }
    if ($kredit > 0) {
        $ws->getStyle("H{$row}")->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FFDC2626'));
    }
    
    $ws->getRowDimension($row)->setRowHeight(20);
    $row++;
}

// Summary Footer Row
$ws->fromArray([
    '',
    '',
    '',
    'TOTAL TRANSAKSI PERIODE INI',
    '',
    '',
    excelRupiah($totalDebit),
    excelRupiah($totalKredit),
    excelRupiah($currentRunningBalance)
], null, "A{$row}");

$ws->mergeCells("D{$row}:F{$row}");
$ws->getStyle("D{$row}:I{$row}")->getFont()->setBold(true);
$ws->getStyle("D{$row}:I{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE5E7EB');
$ws->getStyle("G{$row}:I{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
$ws->getRowDimension($row)->setRowHeight(22);

// Borders
styleData($sp, 'A5:I' . $row);

// Columns width
$widths = [6, 13, 18, 45, 25, 12, 16, 16, 18];
foreach ($widths as $i => $w) {
    $ws->getColumnDimensionByColumn($i + 1)->setWidth($w);
}

// Output headers
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($sp);
$writer->save('php://output');
exit;
