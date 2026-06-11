<?php
/**
 * Download Template Excel untuk Import Master Data
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

$type = $_GET['type'] ?? '';
$validTypes = ['categories', 'items', 'companies', 'customers', 'vendors', 'projects'];

if (!in_array($type, $validTypes)) {
    die('Tipe template tidak valid.');
}

// Map permission
$permMap = [
    'categories' => 'master_categories',
    'items'      => 'master_items',
    'companies'  => 'master_companies',
    'customers'  => 'master_customers',
    'vendors'    => 'master_vendors',
    'projects'   => 'master_projects',
];
requirePermission($permMap[$type]);

$sp = new Spreadsheet();
$ws = $sp->getActiveSheet();
$filename = 'Template_Import_' . ucfirst($type) . '_' . date('Ymd');
$ws->setTitle('Template ' . ucfirst($type));

// Style Header Function
function applyHeaderStyle($ws, $colCount) {
    $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colCount);
    $range = "A1:{$lastCol}1";
    
    $ws->getStyle($range)->applyFromArray([
        'font'      => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
        'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF4B5563']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF000000']]],
    ]);
    $ws->getRowDimension(1)->setRowHeight(25);
}

// Add Instructions
function addInstructionRow($ws, $colCount, $instructions) {
    $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colCount);
    $ws->fromArray($instructions, null, 'A2');
    $range = "A2:{$lastCol}2";
    $ws->getStyle($range)->applyFromArray([
        'font'      => ['italic' => true, 'color' => ['argb' => 'FF4B5563'], 'size' => 10],
        'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFF3F4F6']],
        'alignment' => ['vertical' => Alignment::VERTICAL_TOP, 'wrapText' => true],
        'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFD1D5DB']]],
    ]);
    $ws->getRowDimension(2)->setRowHeight(40);
}

// Columns definition
$headers = [];
$instructions = [];
$widths = [];

switch ($type) {
    case 'categories':
        $headers = ['Nama Kategori*', 'Prefix*', 'Deskripsi'];
        $instructions = [
            'Wajib diisi. Cth: Construction Material',
            'Wajib diisi, unik, 3-5 huruf. Cth: CMB',
            'Opsional. Penjelasan kategori'
        ];
        $widths = [30, 15, 50];
        break;
        
    case 'items':
        $headers = ['Prefix Kategori*', 'Nama / Deskripsi*', 'Spesifikasi', 'Satuan (UoM)*', 'Tipe Distribusi*', 'Minimum Stok', 'Lokasi Gudang', 'Catatan'];
        $instructions = [
            'Wajib. Prefix kategori yang sudah ada. Cth: CMB',
            'Wajib. Nama barang / material',
            'Opsional. Merek/ukuran/tipe',
            'Wajib. Cth: PCS, ZAK, M2',
            'Wajib diisi "stock" atau "direct"',
            'Opsional. Angka (untuk alert stok)',
            'Opsional. Cth: Rak A1',
            'Opsional.'
        ];
        $widths = [18, 40, 25, 15, 18, 15, 20, 30];
        break;

    case 'companies':
        $headers = ['Nama Perusahaan*', 'Alamat', 'Kota', 'Provinsi', 'Kode Pos', 'No. Telp', 'Email', 'Default (1/0)'];
        $instructions = [
            'Wajib diisi.',
            'Opsional.',
            'Opsional.',
            'Opsional.',
            'Opsional.',
            'Opsional.',
            'Opsional.',
            'Opsional. Isi 1 jika ini perusahaan default, 0 jika bukan.'
        ];
        $widths = [35, 40, 20, 20, 15, 20, 25, 15];
        break;

    case 'customers':
        $headers = ['Nama Pelanggan*', 'Singkatan*', 'Nama PIC', 'No. Telp', 'Email', 'Alamat', 'Nama Bank', 'No. Rekening', 'Atas Nama Rekening', 'Catatan'];
        $instructions = [
            'Wajib diisi.',
            'Wajib diisi. Singkatan 3-5 karakter',
            'Opsional.',
            'Opsional.',
            'Opsional.',
            'Opsional.',
            'Opsional.',
            'Opsional.',
            'Opsional.',
            'Opsional.'
        ];
        $widths = [35, 15, 25, 20, 25, 40, 20, 20, 25, 30];
        break;

    case 'vendors':
        $headers = ['Nama Pemasok*', 'Singkatan*', 'Kontak Person', 'No. Telp', 'Email', 'Alamat', 'Nama Bank', 'No. Rekening', 'Atas Nama Rekening', 'Term Pembayaran', 'Catatan'];
        $instructions = [
            'Wajib diisi.',
            'Wajib diisi. Singkatan 3-5 karakter',
            'Opsional.',
            'Opsional.',
            'Opsional.',
            'Opsional.',
            'Opsional.',
            'Opsional.',
            'Opsional.',
            'Opsional. Cth: NET 30',
            'Opsional.'
        ];
        $widths = [35, 15, 25, 20, 25, 40, 20, 20, 25, 20, 30];
        break;

    case 'projects':
        $headers = ['Nama Proyek*', 'Singkatan*', 'Singkatan Pelanggan*', 'Lokasi', 'Tgl Mulai', 'Tgl Selesai', 'Budget (Rp)', 'Status'];
        $instructions = [
            'Wajib diisi.',
            'Wajib diisi. Singkatan unik',
            'Wajib. Singkatan dari master Pelanggan',
            'Opsional.',
            'Opsional. Format YYYY-MM-DD',
            'Opsional. Format YYYY-MM-DD',
            'Opsional. Angka tanpa titik/koma',
            'Opsional. Isi: planning / active / completed / cancelled (default: planning)'
        ];
        $widths = [35, 15, 25, 30, 15, 15, 20, 20];
        break;
}

// Write headers and instructions
$ws->fromArray($headers, null, 'A1');
applyHeaderStyle($ws, count($headers));

addInstructionRow($ws, count($headers), $instructions);

// Set column widths
foreach ($widths as $i => $w) {
    $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
    $ws->getColumnDimension($col)->setWidth($w);
}

// Output file
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($sp);
$writer->save('php://output');
exit;
