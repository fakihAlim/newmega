<?php
/**
 * Download CSV Template for Employee Import
 */
require_once __DIR__ . '/../../../includes/auth.php';
requirePermission('master_employees');

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="template_import_karyawan.csv"');

// UTF-8 BOM for Excel compatibility
echo "\xEF\xBB\xBF";

$output = fopen('php://output', 'w');

// Header row
fputcsv($output, ['nama_lengkap', 'jabatan', 'no_telepon']);

// Example rows
fputcsv($output, ['Ahmad Fauzi', 'Tukang Las', '081234567890']);
fputcsv($output, ['Budi Santoso', 'Helper', '']);
fputcsv($output, ['Candra Wijaya', 'Mandor', '089876543210']);

fclose($output);
exit;
