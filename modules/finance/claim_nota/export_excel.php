<?php
/**
 * Finance - Claim Nota Excel Export
 */
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../vendor/autoload.php';
requirePermission('claim_nota');

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

$id = $_GET['id'] ?? 0;

// Fetch Claim Header
$stmt = $pdo->prepare("
    SELECT c.*, comp.name as company_name 
    FROM nota_claims c
    LEFT JOIN companies comp ON c.company_id = comp.id
    WHERE c.id = ?
");
$stmt->execute([$id]);
$claim = $stmt->fetch();

if (!$claim) {
    die('Klaim Nota tidak ditemukan.');
}

// Fetch Claim Items
$stmtItems = $pdo->prepare("SELECT * FROM nota_claim_items WHERE claim_id = ? ORDER BY id ASC");
$stmtItems->execute([$id]);
$claimItems = $stmtItems->fetchAll();

// Group items by group_name
$groupedItems = [];
foreach ($claimItems as $item) {
    $group = !empty($item['group_name']) ? trim($item['group_name']) : 'Money change';
    $groupedItems[$group][] = $item;
}

// Create Spreadsheet
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Claim Nota');

// Set default fonts
$spreadsheet->getDefaultStyle()->getFont()->setName('Arial')->setSize(10);

// Document Title (Row 1)
$sheet->setCellValue('A1', 'Nota Developer dan Kantor');
$sheet->mergeCells('A1:F1');
$sheet->getStyle('A1')->applyFromArray([
    'font' => ['bold' => true, 'size' => 14],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
]);
$sheet->getRowDimension(1)->setRowHeight(25);

// Meta Info (Row 3 & 4)
$sheet->setCellValue('A3', 'Karyawan');
$sheet->setCellValue('B3', ': ' . $claim['employee_name']);
$sheet->setCellValue('E3', 'No. Klaim');
$sheet->setCellValue('F3', ': ' . $claim['claim_number']);

// Perusahaan
$sheet->setCellValue('A4', 'Perusahaan');
$sheet->setCellValue('B4', ': ' . $claim['company_name']);
$sheet->setCellValue('E4', 'Tanggal');
$sheet->setCellValue('F4', ': ' . date('d-M-Y', strtotime($claim['claim_date'])));

$sheet->getStyle('A3:A4')->getFont()->setBold(true);
$sheet->getStyle('E3:E4')->getFont()->setBold(true);
$sheet->getStyle('F3')->getFont()->setBold(true); // Red highlighting on form number if needed
$sheet->getStyle('F3')->getFont()->getColor()->setARGB('FFFF0000');

// Table styling helper configurations
$headerStyle = [
    'font' => ['bold' => true],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['argb' => 'FFCFE2F3'] // Light blue background
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical' => Alignment::VERTICAL_CENTER
    ],
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['argb' => 'FF000000']
        ]
    ]
];

$dataBorder = [
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['argb' => 'FF000000']
        ]
    ]
];

$totalStyle = [
    'font' => ['bold' => true],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['argb' => 'FFF2F2F2'] // Light gray
    ],
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['argb' => 'FF000000']
        ]
    ]
];

// Start outputting tables
$currentRow = 6;
$subtotalRows = []; // Keep track of the rows containing subtotals

foreach ($groupedItems as $groupName => $items) {
    // 1. Group Header Row
    $sheet->setCellValue('A' . $currentRow, $groupName);
    $sheet->getStyle('A' . $currentRow)->getFont()->setBold(true)->setSize(11);
    $currentRow++;

    // 2. Table Header Row (Tanggal, Toko, item, Pcs, Harga, Jumlah)
    $sheet->setCellValue('A' . $currentRow, 'Tanggal');
    $sheet->setCellValue('B' . $currentRow, 'Toko');
    $sheet->setCellValue('C' . $currentRow, 'item');
    $sheet->setCellValue('D' . $currentRow, 'Pcs');
    $sheet->setCellValue('E' . $currentRow, 'Harga');
    $sheet->setCellValue('F' . $currentRow, 'Jumlah');
    
    $sheet->getStyle("A{$currentRow}:F{$currentRow}")->applyFromArray($headerStyle);
    $sheet->getRowDimension($currentRow)->setRowHeight(20);
    
    $startItemRow = $currentRow + 1;
    $currentRow++;

    // 3. Item rows
    foreach ($items as $item) {
        $sheet->setCellValue('A' . $currentRow, date('d/m/Y', strtotime($item['item_date'])));
        $sheet->setCellValue('B' . $currentRow, $item['store_name'] ?: '-');
        $sheet->setCellValue('C' . $currentRow, $item['item_name']);
        $sheet->setCellValue('D' . $currentRow, $item['qty']);
        $sheet->setCellValue('E' . $currentRow, $item['price']);
        
        // Excel Formula for Jumlah: =Qty*Harga (D * E)
        $sheet->setCellValue('F' . $currentRow, "=D{$currentRow}*E{$currentRow}");
        
        // Formats
        $sheet->getStyle('A' . $currentRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('D' . $currentRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        
        // Currency formatting for E (Harga) and F (Jumlah)
        $sheet->getStyle('E' . $currentRow)->getNumberFormat()->setFormatCode('Rp#,##0.00');
        $sheet->getStyle('F' . $currentRow)->getNumberFormat()->setFormatCode('Rp#,##0.00');
        
        $sheet->getStyle("A{$currentRow}:F{$currentRow}")->applyFromArray($dataBorder);
        $sheet->getRowDimension($currentRow)->setRowHeight(18);
        $currentRow++;
    }
    
    $endItemRow = $currentRow - 1;

    // 4. Subtotal Row
    $sheet->setCellValue('A' . $currentRow, '');
    $sheet->setCellValue('B' . $currentRow, '');
    $sheet->setCellValue('C' . $currentRow, 'Total');
    $sheet->setCellValue('D' . $currentRow, '');
    $sheet->setCellValue('E' . $currentRow, '');
    
    // Formula: =SUM(F{start}:F{end})
    $sheet->setCellValue('F' . $currentRow, "=SUM(F{$startItemRow}:F{$endItemRow})");
    
    $sheet->getStyle('C' . $currentRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    $sheet->getStyle('F' . $currentRow)->getNumberFormat()->setFormatCode('Rp#,##0.00');
    $sheet->getStyle("A{$currentRow}:F{$currentRow}")->applyFromArray($totalStyle);
    $sheet->getRowDimension($currentRow)->setRowHeight(20);
    
    // Store subtotal row for grand total calculation
    $subtotalRows[] = 'F' . $currentRow;
    
    // Space between groups
    $currentRow += 2; 
}

// 5. Grand Total Row
$currentRow--; // step back to overwrite last extra blank row
$sheet->setCellValue('C' . $currentRow, 'Grand Total');
$sheet->mergeCells("C{$currentRow}:E{$currentRow}");

// Formula for Grand Total: Sum of all group subtotal rows
$grandTotalFormula = '=' . implode('+', $subtotalRows);
$sheet->setCellValue('F' . $currentRow, $grandTotalFormula);

$sheet->getStyle("C{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
$sheet->getStyle("C{$currentRow}:F{$currentRow}")->getFont()->setBold(true)->setSize(11);
$sheet->getStyle('F' . $currentRow)->getNumberFormat()->setFormatCode('Rp#,##0.00');

// Apply borders to Grand Total Row
$sheet->getStyle("C{$currentRow}:F{$currentRow}")->applyFromArray([
    'borders' => [
        'top' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF000000']],
        'bottom' => ['borderStyle' => Border::BORDER_DOUBLE, 'color' => ['argb' => 'FF000000']]
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['argb' => 'FFE2E8F0'] // Slate color for highlight
    ]
]);
$sheet->getRowDimension($currentRow)->setRowHeight(22);

// 6. Signature Section
$currentRow += 3;

$sheet->setCellValue('A' . $currentRow, 'Diajukan Oleh,');
$sheet->setCellValue('D' . $currentRow, 'Disetujui Oleh,');
$sheet->setCellValue('F' . $currentRow, 'Dibayar Oleh,');
$sheet->getStyle("A{$currentRow}:F{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle("A{$currentRow}:F{$currentRow}")->getFont()->setBold(true);

$currentRow += 4;

$sheet->setCellValue('A' . $currentRow, '( ' . $claim['employee_name'] . ' )');
$sheet->setCellValue('D' . $currentRow, '( ____________________ )');
$sheet->setCellValue('F' . $currentRow, '( ____________________ )');
$sheet->getStyle("A{$currentRow}:F{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle("A{$currentRow}:F{$currentRow}")->getFont()->setBold(true);

$currentRow++;

$sheet->setCellValue('A' . $currentRow, 'Karyawan / Penerima');
$sheet->setCellValue('D' . $currentRow, 'Finance / Admin');
$sheet->setCellValue('F' . $currentRow, 'Kasir / Pembayar');
$sheet->getStyle("A{$currentRow}:F{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle("A{$currentRow}:F{$currentRow}")->getFont()->setItalic(true)->setSize(9);

// Set column dimensions
$sheet->getColumnDimension('A')->setWidth(15);
$sheet->getColumnDimension('B')->setWidth(25); // Toko
$sheet->getColumnDimension('C')->setWidth(30); // Item
$sheet->getColumnDimension('D')->setWidth(8);  // Qty
$sheet->getColumnDimension('E')->setWidth(18); // Harga
$sheet->getColumnDimension('F')->setWidth(20); // Jumlah

// Headers for file download
$cleanClaimNo = str_replace(['/', '\\', ' '], '_', $claim['claim_number']);
$filename = 'Claim_Nota_' . $cleanClaimNo . '_' . date('Ymd');

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
