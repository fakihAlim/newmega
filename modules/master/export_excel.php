<?php
/**
 * Export Excel - Handler untuk semua data master
 * ?type=categories|items|vendors|customers|companies|wages|employees|projects
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
    'categories' => 'master_categories',
    'items'      => 'master_items',
    'vendors'    => 'master_vendors',
    'customers'  => 'master_customers',
    'companies'  => 'master_companies',
    'wages'      => 'master_wages',
    'employees'  => 'master_employees',
    'projects'   => 'master_projects',
];

if (!isset($permMap[$type])) {
    die('Tipe export tidak valid.');
}
requirePermission($permMap[$type]);

// Helper: apply header row style
function styleHeader(Spreadsheet $sp, string $range, bool $dark = false) {
    $color = $dark ? 'FF4B5563' : 'FFD1D5DB';
    $sp->getActiveSheet()->getStyle($range)->applyFromArray([
        'font'      => ['bold' => true, 'color' => ['argb' => 'FF000000']],
        'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $color]],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF000000']]],
    ]);
}

// Helper: apply data range style
function styleData(Spreadsheet $sp, string $range) {
    $sp->getActiveSheet()->getStyle($range)->applyFromArray([
        'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF000000']]],
        'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
    ]);
}

// Helper: title row
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
$filename = 'Export_' . ucfirst($type) . '_' . date('d-m-Y');

// 1. KATEGORI
if ($type === 'categories') {
    $ws->setTitle('Master Kategori');
    $categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();
    writeTitle($sp, 'Master Data Kategori', 4, $filename);
    
    $headers = ['No', 'Nama Kategori', 'Prefix', 'Deskripsi'];
    $ws->fromArray($headers, null, 'A3');
    styleHeader($sp, 'A3:D3');
    
    $row = 4; $no = 1;
    foreach ($categories as $c) {
        $ws->fromArray([
            $no++,
            $c['name'],
            $c['prefix'],
            $c['description'] ?: '-'
        ], null, "A{$row}");
        $row++;
    }
    if ($row > 4) styleData($sp, "A4:D" . ($row-1));
    $widths = [5, 30, 15, 50];
    foreach ($widths as $i => $w) $ws->getColumnDimensionByColumn($i+1)->setWidth($w);
}

// 2. BARANG / MATERIAL
elseif ($type === 'items') {
    $ws->setTitle('Master Barang');
    $items = $pdo->query("
        SELECT i.*, c.name as category_name 
        FROM items i 
        LEFT JOIN categories c ON i.category_id = c.id 
        ORDER BY i.item_code ASC
    ")->fetchAll();
    writeTitle($sp, 'Master Data Barang / Material', 10, $filename);
    
    $headers = ['No', 'Kode Barang', 'Kategori', 'Nama / Deskripsi', 'Spesifikasi', 'Satuan (UoM)', 'Stok Min.', 'Lokasi', 'Tipe Stok', 'Stok Saat Ini'];
    $ws->fromArray($headers, null, 'A3');
    styleHeader($sp, 'A3:J3');
    
    $row = 4; $no = 1;
    foreach ($items as $item) {
        $ws->fromArray([
            $no++,
            $item['item_code'],
            $item['category_name'],
            $item['description'],
            $item['type_specification'] ?: '-',
            $item['uom'],
            (float)$item['minimum_stock'],
            $item['warehouse_location'] ?: '-',
            $item['stock_type'] === 'stock' ? 'Stok Gudang' : 'Langsung Proyek',
            (float)$item['current_stock']
        ], null, "A{$row}");
        $row++;
    }
    if ($row > 4) styleData($sp, "A4:J" . ($row-1));
    $widths = [5, 15, 20, 30, 25, 12, 12, 15, 18, 14];
    foreach ($widths as $i => $w) $ws->getColumnDimensionByColumn($i+1)->setWidth($w);
}

// 3. VENDOR / SUPPLIER
elseif ($type === 'vendors') {
    $ws->setTitle('Master Supplier');
    $vendors = $pdo->query("SELECT * FROM vendors ORDER BY company_name ASC")->fetchAll();
    writeTitle($sp, 'Master Data Supplier / Vendor', 12, $filename);
    
    $headers = ['No', 'Kode', 'Nama Perusahaan', 'PIC', 'No. Telp', 'Email', 'Alamat', 'Bank', 'No. Rekening', 'Atas Nama', 'Term Pembayaran', 'Catatan'];
    $ws->fromArray($headers, null, 'A3');
    styleHeader($sp, 'A3:L3');
    
    $row = 4; $no = 1;
    foreach ($vendors as $v) {
        $ws->fromArray([
            $no++,
            $v['abbreviation'],
            $v['company_name'],
            $v['contact_person'] ?: '-',
            $v['phone'] ?: '-',
            $v['email'] ?: '-',
            $v['address'] ?: '-',
            $v['bank_name'] ?: '-',
            $v['bank_account'] ?: '-',
            $v['bank_holder'] ?: '-',
            $v['payment_terms'] ?: '-',
            $v['notes'] ?: '-'
        ], null, "A{$row}");
        $row++;
    }
    if ($row > 4) styleData($sp, "A4:L" . ($row-1));
    $widths = [5, 10, 30, 18, 16, 22, 35, 15, 18, 20, 18, 30];
    foreach ($widths as $i => $w) $ws->getColumnDimensionByColumn($i+1)->setWidth($w);
}

// 4. CUSTOMER / PELANGGAN
elseif ($type === 'customers') {
    $ws->setTitle('Master Pelanggan');
    $customers = $pdo->query("SELECT * FROM customers ORDER BY company_name ASC")->fetchAll();
    writeTitle($sp, 'Master Data Pelanggan / Customer', 11, $filename);
    
    $headers = ['No', 'Kode', 'Nama Customer / Perusahaan', 'Nama PIC', 'No. Telp', 'Email', 'Alamat', 'Bank', 'No. Rekening', 'Atas Nama', 'Catatan'];
    $ws->fromArray($headers, null, 'A3');
    styleHeader($sp, 'A3:K3');
    
    $row = 4; $no = 1;
    foreach ($customers as $c) {
        $ws->fromArray([
            $no++,
            $c['abbreviation'],
            $c['company_name'],
            $c['pic_name'] ?: '-',
            $c['phone'] ?: '-',
            $c['email'] ?: '-',
            $c['address'] ?: '-',
            $c['bank_name'] ?: '-',
            $c['bank_account'] ?: '-',
            $c['bank_holder'] ?: '-',
            $c['notes'] ?: '-'
        ], null, "A{$row}");
        $row++;
    }
    if ($row > 4) styleData($sp, "A4:K" . ($row-1));
    $widths = [5, 10, 30, 18, 16, 22, 35, 15, 18, 20, 30];
    foreach ($widths as $i => $w) $ws->getColumnDimensionByColumn($i+1)->setWidth($w);
}

// 5. PERUSAHAAN
elseif ($type === 'companies') {
    $ws->setTitle('Master Perusahaan');
    $companies = $pdo->query("SELECT * FROM companies ORDER BY name ASC")->fetchAll();
    writeTitle($sp, 'Master Data Perusahaan', 9, $filename);
    
    $headers = ['No', 'Nama Perusahaan', 'Alamat', 'Kota', 'Provinsi', 'Kode Pos', 'No. Telp', 'Email', 'Default'];
    $ws->fromArray($headers, null, 'A3');
    styleHeader($sp, 'A3:I3');
    
    $row = 4; $no = 1;
    foreach ($companies as $c) {
        $ws->fromArray([
            $no++,
            $c['name'],
            $c['address'] ?: '-',
            $c['city'] ?: '-',
            $c['province'] ?: '-',
            $c['postal_code'] ?: '-',
            $c['phone'] ?: '-',
            $c['email'] ?: '-',
            $c['is_default'] ? 'Ya' : 'Tidak'
        ], null, "A{$row}");
        $row++;
    }
    if ($row > 4) styleData($sp, "A4:I" . ($row-1));
    $widths = [5, 30, 35, 15, 18, 12, 16, 22, 12];
    foreach ($widths as $i => $w) $ws->getColumnDimensionByColumn($i+1)->setWidth($w);
}

// 6. MASTER UPAH
elseif ($type === 'wages') {
    $ws->setTitle('Master Upah');
    $wages = $pdo->query("SELECT * FROM master_wages ORDER BY jabatan_name ASC")->fetchAll();
    writeTitle($sp, 'Master Data Upah & Jabatan', 3, $filename);
    
    $headers = ['No', 'Nama Jabatan', 'Upah Harian (Rp)'];
    $ws->fromArray($headers, null, 'A3');
    styleHeader($sp, 'A3:C3');
    
    $row = 4; $no = 1;
    foreach ($wages as $w) {
        $ws->fromArray([
            $no++,
            $w['jabatan_name'],
            (float)$w['daily_wage']
        ], null, "A{$row}");
        $row++;
    }
    if ($row > 4) styleData($sp, "A4:C" . ($row-1));
    $widths = [5, 35, 20];
    foreach ($widths as $i => $w) $ws->getColumnDimensionByColumn($i+1)->setWidth($w);
}

// 7. KARYAWAN
elseif ($type === 'employees') {
    $ws->setTitle('Master Karyawan');
    $employees = $pdo->query("
        SELECT e.*, u.full_name, u.username, u.phone, w.jabatan_name, w.daily_wage 
        FROM employees e 
        JOIN users u ON e.user_id = u.id 
        JOIN master_wages w ON e.wage_id = w.id 
        ORDER BY u.full_name ASC
    ")->fetchAll();
    writeTitle($sp, 'Master Data Karyawan Lapangan', 7, $filename);
    
    $headers = ['No', 'Kode Karyawan', 'Nama Lengkap', 'Username', 'Jabatan', 'Upah Harian (Rp)', 'No. HP'];
    $ws->fromArray($headers, null, 'A3');
    styleHeader($sp, 'A3:G3');
    
    $row = 4; $no = 1;
    foreach ($employees as $e) {
        $ws->fromArray([
            $no++,
            $e['employee_code'],
            $e['full_name'],
            $e['username'],
            $e['jabatan_name'],
            (float)$e['daily_wage'],
            $e['phone'] ?: '-'
        ], null, "A{$row}");
        $row++;
    }
    if ($row > 4) styleData($sp, "A4:G" . ($row-1));
    $widths = [5, 18, 30, 18, 25, 20, 18];
    foreach ($widths as $i => $w) $ws->getColumnDimensionByColumn($i+1)->setWidth($w);
}

// 8. PROYEK
elseif ($type === 'projects') {
    $ws->setTitle('Master Proyek');
    $projects = $pdo->query("
        SELECT p.*, c.company_name as customer_name, u.full_name as pm_name 
        FROM projects p 
        JOIN customers c ON p.customer_id = c.id 
        LEFT JOIN users u ON p.project_manager_id = u.id 
        ORDER BY p.id DESC
    ")->fetchAll();
    writeTitle($sp, 'Master Data Proyek', 9, $filename);
    
    $headers = ['No', 'Nama Proyek', 'Singkatan', 'Customer', 'Project Manager', 'Lokasi', 'Mulai', 'Selesai', 'Budget (Rp)'];
    $ws->fromArray($headers, null, 'A3');
    styleHeader($sp, 'A3:I3');
    
    $row = 4; $no = 1;
    foreach ($projects as $p) {
        $ws->fromArray([
            $no++,
            $p['name'],
            $p['abbreviation'],
            $p['customer_name'],
            $p['pm_name'] ?: '-',
            $p['location'] ?: '-',
            $p['start_date'] ? date('d-m-Y', strtotime($p['start_date'])) : '-',
            $p['end_date'] ? date('d-m-Y', strtotime($p['end_date'])) : '-',
            (float)$p['budget']
        ], null, "A{$row}");
        $row++;
    }
    if ($row > 4) styleData($sp, "A4:I" . ($row-1));
    $widths = [5, 35, 12, 30, 25, 30, 14, 14, 20];
    foreach ($widths as $i => $w) $ws->getColumnDimensionByColumn($i+1)->setWidth($w);
}

// Output file
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($sp);
$writer->save('php://output');
exit;
