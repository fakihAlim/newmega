<?php
/**
 * Timesheet - Export Report Excel
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../vendor/autoload.php';

requirePermission('report_timesheet');

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

$user = getCurrentUser();
$isAdmin = !in_array('karyawan', array_map('strtolower', $user['roles'] ?? [$user['role']]));

$employee_id = null;
if (!$isAdmin) {
    $stmt = $pdo->prepare("SELECT id FROM employees WHERE user_id = ?");
    $stmt->execute([$user['id']]);
    $employee_id = $stmt->fetchColumn();
}

$month = $_GET['month'] ?? date('n');
$year = $_GET['year'] ?? date('Y');
$company_id = $_GET['company_id'] ?? '';
$project_id = $_GET['project_id'] ?? '';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

$where = ["t.status = 'approved'"];
$params = [];

if (!empty($start_date) && !empty($end_date)) {
    $where[] = "t.work_date BETWEEN ? AND ?";
    $params[] = $start_date;
    $params[] = $end_date;
    $fileSuffix = "{$start_date}_{$end_date}";
} else {
    $where[] = "MONTH(t.work_date) = ?";
    $where[] = "YEAR(t.work_date) = ?";
    $params[] = $month;
    $params[] = $year;
    $fileSuffix = "{$year}_{$month}";
}

if (!empty($company_id)) {
    $where[] = "t.company_id = ?";
    $params[] = $company_id;
}
if (!empty($project_id)) {
    $where[] = "t.project_id = ?";
    $params[] = $project_id;
}
if ($employee_id) {
    $where[] = "t.employee_id = ?";
    $params[] = $employee_id;
}

$whereClause = implode(" AND ", $where);

$sql = "
    SELECT 
        c.name as company_name,
        p.name as project_name,
        u.full_name as employee_name,
        w.jabatan_name,
        MAX(t.daily_wage_at_time) as daily_wage,
        SUM(CASE WHEN t.work_type = 'full' THEN 1 ELSE 0 END) as full_days,
        SUM(CASE WHEN t.work_type = 'half' THEN 1 ELSE 0 END) as half_days,
        SUM(t.overtime_hours) as total_overtime,
        SUM((CASE WHEN t.work_type = 'full' THEN 1 ELSE 0.5 END) * t.daily_wage_at_time) as total_upah,
        SUM((t.daily_wage_at_time / 8) * t.overtime_hours) as total_upah_lembur
    FROM timesheet_entries t
    JOIN employees e ON t.employee_id = e.id
    JOIN users u ON e.user_id = u.id
    JOIN master_wages w ON e.wage_id = w.id
    JOIN projects p ON t.project_id = p.id
    JOIN companies c ON t.company_id = c.id
    WHERE $whereClause
    GROUP BY t.company_id, t.project_id, t.employee_id
    ORDER BY c.name, p.name, u.full_name
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$data = $stmt->fetchAll();

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Rekap Timesheet');

// Header style
$headerStyle = [
    'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF4F46E5']], // Premium Indigo
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical' => Alignment::VERTICAL_CENTER,
        'wrapText' => true
    ],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFCCCCCC']]]
];

$headers = ['Perusahaan', 'Proyek', 'Nama Karyawan', 'Jabatan', 'H.Full', 'H.Half', 'Upah/Hari', 'Total Upah', 'J.Kerja (Jam)', 'J.Lembur', 'Upah/Lembur (Jam)', 'Total Lembur', 'Total Biaya'];
$sheet->fromArray($headers, NULL, 'A1');
$sheet->getStyle('A1:M1')->applyFromArray($headerStyle);
$sheet->getRowDimension(1)->setRowHeight(28);

$rowNum = 2;
foreach ($data as $row) {
    $jamKerjaNormal = ($row['full_days'] * 8) + ($row['half_days'] * 4);
    $upahLemburPerJam = $row['daily_wage'] / 8;
    $totalBiaya = $row['total_upah'] + $row['total_upah_lembur'];

    $sheet->setCellValue('A' . $rowNum, $row['company_name']);
    $sheet->setCellValue('B' . $rowNum, $row['project_name']);
    $sheet->setCellValue('C' . $rowNum, $row['employee_name']);
    $sheet->setCellValue('D' . $rowNum, $row['jabatan_name']);
    $sheet->setCellValue('E' . $rowNum, $row['full_days']);
    $sheet->setCellValue('F' . $rowNum, $row['half_days']);
    $sheet->setCellValue('G' . $rowNum, $row['daily_wage']);
    $sheet->setCellValue('H' . $rowNum, $row['total_upah']);
    $sheet->setCellValue('I' . $rowNum, $jamKerjaNormal);
    $sheet->setCellValue('J' . $rowNum, $row['total_overtime']);
    $sheet->setCellValue('K' . $rowNum, $upahLemburPerJam);
    $sheet->setCellValue('L' . $rowNum, $row['total_upah_lembur']);
    $sheet->setCellValue('M' . $rowNum, $totalBiaya);

    // Style currencies
    $sheet->getStyle('G' . $rowNum . ':H' . $rowNum)->getNumberFormat()->setFormatCode('#,##0');
    $sheet->getStyle('K' . $rowNum . ':M' . $rowNum)->getNumberFormat()->setFormatCode('#,##0');

    $rowNum++;
}

// Auto-size columns
foreach (range('A', 'M') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// Add border to all cells
$sheet->getStyle('A1:M' . ($rowNum - 1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FFCCCCCC');

$filename = 'Laporan_Timesheet_' . $fileSuffix . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
