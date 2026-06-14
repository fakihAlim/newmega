<?php
/**
 * Finance - Claim Nota Combined Excel Export
 */
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../vendor/autoload.php';
requirePermission('claim_nota');

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

// Get filters from GET request
$filterStart = $_GET['start_date'] ?? '';
$filterEnd = $_GET['end_date'] ?? '';
$filterCompany = $_GET['company_id'] ?? '';
$filterEmployee = $_GET['employee_name'] ?? '';
$filterStatus = $_GET['status'] ?? '';

// Build conditions
$conditions = [];
$params = [];

if ($filterStart) {
    $conditions[] = "c.claim_date >= ?";
    $params[] = $filterStart;
}
if ($filterEnd) {
    $conditions[] = "c.claim_date <= ?";
    $params[] = $filterEnd;
}
if ($filterCompany) {
    $conditions[] = "c.company_id = ?";
    $params[] = $filterCompany;
}
if ($filterEmployee) {
    $conditions[] = "c.employee_name LIKE ?";
    $params[] = "%$filterEmployee%";
}
if ($filterStatus) {
    $conditions[] = "c.status = ?";
    $params[] = $filterStatus;
}

$whereClause = "";
if (!empty($conditions)) {
    $whereClause = "WHERE " . implode(" AND ", $conditions);
}

// Fetch all matching items grouped by group_name
$sql = "
    SELECT ci.*, c.claim_number, c.employee_name, comp.name as company_name 
    FROM nota_claim_items ci
    JOIN nota_claims c ON ci.claim_id = c.id
    LEFT JOIN companies comp ON c.company_id = comp.id
    $whereClause
    ORDER BY ci.item_date ASC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$claimItems = $stmt->fetchAll();

// Group items by group_name
$groupedItems = [];
foreach ($claimItems as $item) {
    $group = !empty($item['group_name']) ? trim($item['group_name']) : 'Money change';
    $groupedItems[$group][] = $item;
}

// Fetch filter text details for header display
$selectedCompanyName = 'Semua Perusahaan';
if ($filterCompany) {
    $compStmt = $pdo->prepare("SELECT name FROM companies WHERE id = ?");
    $compStmt->execute([$filterCompany]);
    $selectedCompanyName = $compStmt->fetchColumn() ?: 'Semua Perusahaan';
}

// Create Spreadsheet
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Laporan Gabungan');

// Set default fonts
$spreadsheet->getDefaultStyle()->getFont()->setName('Arial')->setSize(10);

// Document Title (Row 1)
$sheet->setCellValue('A1', 'Nota Developer dan Kantor (Gabungan)');
$sheet->mergeCells('A1:F1');
$sheet->getStyle('A1')->applyFromArray([
    'font' => ['bold' => true, 'size' => 14],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
]);
$sheet->getRowDimension(1)->setRowHeight(25);

// Meta Info (Row 3 & 4)
$sheet->setCellValue('A3', 'Karyawan');
$sheet->setCellValue('B3', ': ' . ($filterEmployee ?: 'Semua Karyawan'));
$sheet->setCellValue('E3', 'Periode');

$periodString = 'Semua Tanggal';
if ($filterStart && $filterEnd) {
    $periodString = date('d/m/Y', strtotime($filterStart)) . ' s/d ' . date('d/m/Y', strtotime($filterEnd));
} elseif ($filterStart) {
    $periodString = 'Sejak ' . date('d/m/Y', strtotime($filterStart));
} elseif ($filterEnd) {
    $periodString = 'Sampai ' . date('d/m/Y', strtotime($filterEnd));
}
$sheet->setCellValue('F3', ': ' . $periodString);

$sheet->setCellValue('A4', 'Perusahaan');
$sheet->setCellValue('B4', ': ' . $selectedCompanyName);
$sheet->setCellValue('E4', 'Status Klaim');

$statusLabels = [
    'pending' => 'Pending Approval',
    'approved' => 'Approved',
    'paid' => 'Lunas (Paid)',
    'rejected' => 'Rejected'
];
$statusString = $filterStatus ? ($statusLabels[$filterStatus] ?? ucfirst($filterStatus)) : 'Semua Status';
$sheet->setCellValue('F4', ': ' . $statusString);

$sheet->getStyle('A3:A4')->getFont()->setBold(true);
$sheet->getStyle('E3:E4')->getFont()->setBold(true);

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

if (empty($groupedItems)) {
    $sheet->setCellValue('A' . $currentRow, 'Tidak ada data klaim nota yang cocok.');
    $sheet->mergeCells("A{$currentRow}:F{$currentRow}");
    $sheet->getStyle('A' . $currentRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
} else {
    foreach ($groupedItems as $groupName => $items) {
        // 1. Group Header Row
        $sheet->setCellValue('A' . $currentRow, $groupName);
        $sheet->getStyle('A' . $currentRow)->getFont()->setBold(true)->setSize(11);
        $currentRow++;

        // 2. Table Header Row (Tanggal, No. Klaim, item, Pcs, Harga, Jumlah)
        $sheet->setCellValue('A' . $currentRow, 'Tanggal');
        $sheet->setCellValue('B' . $currentRow, 'No. Klaim');
        $sheet->setCellValue('C' . $currentRow, 'item (Deskripsi)');
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
            $sheet->setCellValue('B' . $currentRow, $item['claim_number']);
            $sheet->setCellValue('C' . $currentRow, $item['item_name'] . ' (Oleh: ' . $item['employee_name'] . ')');
            $sheet->setCellValue('D' . $currentRow, $item['qty']);
            $sheet->setCellValue('E' . $currentRow, $item['price']);
            
            // Excel Formula for Jumlah: =Qty*Harga (D * E)
            $sheet->setCellValue('F' . $currentRow, "=D{$currentRow}*E{$currentRow}");
            
            // Formats
            $sheet->getStyle('A' . $currentRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('B' . $currentRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('D' . $currentRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            
            // Currency formatting
            $sheet->getStyle('E' . $currentRow)->getNumberFormat()->setFormatCode('Rp#,##0.00');
            $sheet->getStyle('F' . $currentRow)->getNumberFormat()->setFormatCode('Rp#,##0.00');
            
            $sheet->getStyle("A{$currentRow}:F{$currentRow}")->applyFromArray($dataBorder);
            $sheet->getRowDimension($currentRow)->setRowHeight(18);
            $currentRow++;
        }
        
        $endItemRow = $currentRow - 1;

        // 4. Subtotal Row
        $sheet->setCellValue('A' . $currentRow, '');
        $sheet->setCellValue('B' . $currentRow, 'Total');
        $sheet->setCellValue('C' . $currentRow, '');
        $sheet->setCellValue('D' . $currentRow, '');
        $sheet->setCellValue('E' . $currentRow, '');
        
        // Formula: =SUM(F{start}:F{end})
        $sheet->setCellValue('F' . $currentRow, "=SUM(F{$startItemRow}:F{$endItemRow})");
        
        $sheet->getStyle('B' . $currentRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
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
    $sheet->setCellValue('C' . $currentRow, 'Grand Total Laporan');
    $sheet->mergeCells("C{$currentRow}:E{$currentRow}");

    // Formula for Grand Total
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

    $sheet->setCellValue('A' . $currentRow, 'Dibuat Oleh,');
    $sheet->setCellValue('C' . $currentRow, 'Disetujui Oleh,');
    $sheet->setCellValue('E' . $currentRow, 'Dibayar Oleh,');
    
    $sheet->mergeCells("A{$currentRow}:B{$currentRow}");
    $sheet->mergeCells("C{$currentRow}:D{$currentRow}");
    $sheet->mergeCells("E{$currentRow}:F{$currentRow}");
    
    $sheet->getStyle("A{$currentRow}:F{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle("A{$currentRow}:F{$currentRow}")->getFont()->setBold(true);

    $currentRow += 4;

    $sheet->setCellValue('A' . $currentRow, '( ____________________ )');
    $sheet->setCellValue('C' . $currentRow, '( ____________________ )');
    $sheet->setCellValue('E' . $currentRow, '( ____________________ )');
    
    $sheet->mergeCells("A{$currentRow}:B{$currentRow}");
    $sheet->mergeCells("C{$currentRow}:D{$currentRow}");
    $sheet->mergeCells("E{$currentRow}:F{$currentRow}");
    
    $sheet->getStyle("A{$currentRow}:F{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle("A{$currentRow}:F{$currentRow}")->getFont()->setBold(true);

    $currentRow++;

    $sheet->setCellValue('A' . $currentRow, 'Karyawan / Penerima');
    $sheet->setCellValue('C' . $currentRow, 'Finance / Admin');
    $sheet->setCellValue('E' . $currentRow, 'Kasir / Pembayar');
    
    $sheet->mergeCells("A{$currentRow}:B{$currentRow}");
    $sheet->mergeCells("C{$currentRow}:D{$currentRow}");
    $sheet->mergeCells("E{$currentRow}:F{$currentRow}");
    
    $sheet->getStyle("A{$currentRow}:F{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle("A{$currentRow}:F{$currentRow}")->getFont()->setItalic(true)->setSize(9);
}

// Set column dimensions
$sheet->getColumnDimension('A')->setWidth(15);
$sheet->getColumnDimension('B')->setWidth(18);
$sheet->getColumnDimension('C')->setWidth(40);
$sheet->getColumnDimension('D')->setWidth(8);
$sheet->getColumnDimension('E')->setWidth(18);
$sheet->getColumnDimension('F')->setWidth(20);

// Headers for file download
$filename = 'Laporan_Gabungan_Claim_Nota_' . date('Ymd');

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
