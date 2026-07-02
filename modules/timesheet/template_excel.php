<?php
/**
 * Timesheet - Excel Template Download
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../vendor/autoload.php';

requirePermission('timesheet_input');

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Template Import');

$headerStyle = [
    'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF10B981']], // Emerald Green
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical' => Alignment::VERTICAL_CENTER,
    ],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFCCCCCC']]]
];

$headers = ['Username_Karyawan', 'ID_Company', 'ID_Proyek', 'Tanggal (YYYY-MM-DD)', 'Tipe_Kerja (full/half)', 'Jam_Lembur', 'Catatan'];
$sheet->fromArray($headers, NULL, 'A1');
$sheet->getStyle('A1:G1')->applyFromArray($headerStyle);
$sheet->getRowDimension(1)->setRowHeight(25);

// Example data row
$sheet->setCellValue('A2', 'budi123');
$sheet->setCellValue('B2', '1');
$sheet->setCellValue('C2', '1');
$sheet->setCellValue('D2', date('Y-m-d'));
$sheet->setCellValue('E2', 'full');
$sheet->setCellValue('F2', '2');
$sheet->setCellValue('G2', 'Pengecoran lantai 2');

// Text formatting for example row
$sheet->getStyle('A2:G2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

// Auto-size columns
foreach (range('A', 'G') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

$sheet->getStyle('A1:G2')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FFCCCCCC');

$filename = 'template_import_timesheet.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
