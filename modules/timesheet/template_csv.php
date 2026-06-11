<?php
/**
 * Timesheet - CSV Template Download
 */
require_once __DIR__ . '/../../includes/auth.php';
requirePermission('timesheet_input');

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="template_import_timesheet.csv"');

$output = fopen('php://output', 'w');
fputcsv($output, ['Username_Karyawan', 'ID_Company', 'ID_Proyek', 'Tanggal (YYYY-MM-DD)', 'Tipe_Kerja (full/half)', 'Jam_Lembur', 'Catatan']);

// Example data
fputcsv($output, ['budi123', '1', '1', date('Y-m-d'), 'full', '2', 'Pengecoran lantai 2']);

fclose($output);
exit;
