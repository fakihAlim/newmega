<?php
/**
 * Proses Import Master Data Excel
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die('Invalid request.');
}

$type = $_POST['type'] ?? '';
$validTypes = ['categories', 'items', 'companies', 'customers', 'vendors', 'projects'];

if (!in_array($type, $validTypes)) {
    setFlash('danger', 'Tipe import tidak valid.');
    header("Location: " . $_SERVER['HTTP_REFERER']);
    exit;
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

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    setFlash('danger', 'Silakan pilih file Excel yang valid.');
    header("Location: " . $_SERVER['HTTP_REFERER']);
    exit;
}

$fileTmp = $_FILES['file']['tmp_name'];
$fileName = $_FILES['file']['name'];
$ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

if (!in_array($ext, ['xls', 'xlsx'])) {
    setFlash('danger', 'Format file tidak didukung. Harus berupa .xls atau .xlsx');
    header("Location: " . $_SERVER['HTTP_REFERER']);
    exit;
}

try {
    $spreadsheet = IOFactory::load($fileTmp);
    $worksheet = $spreadsheet->getActiveSheet();
    $rows = $worksheet->toArray();
    
    // Minimal harus ada header, instruksi, dan 1 data (3 baris)
    if (count($rows) < 3) {
        setFlash('danger', 'File kosong atau tidak berisi data yang valid.');
        header("Location: " . $_SERVER['HTTP_REFERER']);
        exit;
    }
    
    // Remove header and instruction rows (baris 1 dan 2)
    array_shift($rows);
    array_shift($rows);
    
    $successCount = 0;
    $errors = [];
    
    $pdo->beginTransaction();
    
    foreach ($rows as $index => $row) {
        $rowNum = $index + 3; // +3 karena index 0 = baris 3 di Excel
        
        // Skip baris kosong
        if (empty(array_filter($row))) continue;
        
        try {
            if ($type === 'categories') {
                $name = trim($row[0] ?? '');
                $prefix = trim($row[1] ?? '');
                $desc = trim($row[2] ?? '');
                
                if (empty($name) || empty($prefix)) {
                    throw new Exception("Nama dan Prefix wajib diisi.");
                }
                
                // Check exist
                $stmt = $pdo->prepare("SELECT id FROM categories WHERE prefix = ?");
                $stmt->execute([$prefix]);
                $exists = $stmt->fetchColumn();
                
                if ($exists) {
                    $upd = $pdo->prepare("UPDATE categories SET name = ?, description = ? WHERE prefix = ?");
                    $upd->execute([$name, $desc, $prefix]);
                } else {
                    $ins = $pdo->prepare("INSERT INTO categories (name, prefix, description) VALUES (?, ?, ?)");
                    $ins->execute([$name, $prefix, $desc]);
                }
                $successCount++;
                
            } elseif ($type === 'items') {
                $catPrefix = trim($row[0] ?? '');
                $desc      = trim($row[1] ?? '');
                $typeSpec  = trim($row[2] ?? '');
                $uom       = trim($row[3] ?? '');
                $stockType = strtolower(trim($row[4] ?? ''));
                $minStock  = floatval($row[5] ?? 0);
                $whLoc     = trim($row[6] ?? '');
                $remark    = trim($row[7] ?? '');
                
                if (empty($catPrefix) || empty($desc) || empty($uom)) {
                    throw new Exception("Prefix Kategori, Deskripsi, dan UoM wajib diisi.");
                }
                
                if (!in_array($stockType, ['stock', 'direct'])) {
                    $stockType = 'stock';
                }
                
                // Get Category ID
                $stmt = $pdo->prepare("SELECT id, prefix FROM categories WHERE prefix = ?");
                $stmt->execute([$catPrefix]);
                $cat = $stmt->fetch();
                
                if (!$cat) {
                    throw new Exception("Kategori dengan prefix '$catPrefix' tidak ditemukan.");
                }
                
                // Check duplicate item by description and type_specification
                $stmtDup = $pdo->prepare("SELECT id FROM items WHERE description = ? AND type_specification = ?");
                $stmtDup->execute([$desc, $typeSpec]);
                if ($stmtDup->fetch()) {
                    throw new Exception("Barang '$desc' dengan spesifikasi '$typeSpec' sudah ada di database.");
                }
                
                // Generate Item Code
                $stmtLast = $pdo->prepare("
                    SELECT item_code 
                    FROM items 
                    WHERE category_id = ?
                    ORDER BY CAST(SUBSTRING_INDEX(item_code, '-', -1) AS UNSIGNED) DESC 
                    LIMIT 1
                ");
                $stmtLast->execute([$cat['id']]);
                $lastCode = $stmtLast->fetchColumn();
                
                $nextSeq = 1;
                if ($lastCode) {
                    $parts = explode('-', $lastCode);
                    $nextSeq = intval(end($parts)) + 1;
                }
                
                $itemCode = $cat['prefix'] . '-' . str_pad($nextSeq, 4, '0', STR_PAD_LEFT);
                
                // Insert Item
                $ins = $pdo->prepare("
                    INSERT INTO items (category_id, item_code, description, type_specification, uom, minimum_stock, warehouse_location, remark, stock_type, current_stock, is_active) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 1)
                ");
                $ins->execute([$cat['id'], $itemCode, $desc, $typeSpec, strtoupper($uom), $stockType === 'stock' ? $minStock : 0, $whLoc, $remark, $stockType]);
                
                $successCount++;
                
            } elseif ($type === 'companies') {
                $name      = trim($row[0] ?? '');
                $addr      = trim($row[1] ?? '');
                $city      = trim($row[2] ?? '');
                $prov      = trim($row[3] ?? '');
                $postal    = trim($row[4] ?? '');
                $phone     = trim($row[5] ?? '');
                $email     = trim($row[6] ?? '');
                $isDefault = (int)($row[7] ?? 0);
                
                if (empty($name)) throw new Exception("Nama Perusahaan wajib diisi.");
                
                // If this is set as default, unset others first
                if ($isDefault === 1) {
                    $pdo->query("UPDATE companies SET is_default = 0");
                }
                
                // Check exist by name
                $stmt = $pdo->prepare("SELECT id FROM companies WHERE name = ?");
                $stmt->execute([$name]);
                if ($stmt->fetch()) {
                    $upd = $pdo->prepare("UPDATE companies SET address=?, city=?, province=?, postal_code=?, phone=?, email=?, is_default=? WHERE name=?");
                    $upd->execute([$addr, $city, $prov, $postal, $phone, $email, $isDefault, $name]);
                } else {
                    $ins = $pdo->prepare("INSERT INTO companies (name, address, city, province, postal_code, phone, email, is_default) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    $ins->execute([$name, $addr, $city, $prov, $postal, $phone, $email, $isDefault]);
                }
                $successCount++;
                
            } elseif ($type === 'customers') {
                $name   = trim($row[0] ?? '');
                $abbr   = trim($row[1] ?? '');
                $pic    = trim($row[2] ?? '');
                $phone  = trim($row[3] ?? '');
                $email  = trim($row[4] ?? '');
                $addr   = trim($row[5] ?? '');
                $bName  = trim($row[6] ?? '');
                $bAcc   = trim($row[7] ?? '');
                $bHold  = trim($row[8] ?? '');
                $notes  = trim($row[9] ?? '');
                
                if (empty($name) || empty($abbr)) throw new Exception("Nama dan Singkatan wajib diisi.");
                
                $stmt = $pdo->prepare("SELECT id FROM customers WHERE abbreviation = ?");
                $stmt->execute([$abbr]);
                if ($stmt->fetch()) {
                    $upd = $pdo->prepare("UPDATE customers SET company_name=?, pic_name=?, phone=?, email=?, address=?, bank_name=?, bank_account=?, bank_holder=?, notes=? WHERE abbreviation=?");
                    $upd->execute([$name, $pic, $phone, $email, $addr, $bName, $bAcc, $bHold, $notes, $abbr]);
                } else {
                    $ins = $pdo->prepare("INSERT INTO customers (company_name, abbreviation, pic_name, phone, email, address, bank_name, bank_account, bank_holder, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $ins->execute([$name, $abbr, $pic, $phone, $email, $addr, $bName, $bAcc, $bHold, $notes]);
                }
                $successCount++;
                
            } elseif ($type === 'vendors') {
                $name   = trim($row[0] ?? '');
                $abbr   = trim($row[1] ?? '');
                $pic    = trim($row[2] ?? '');
                $phone  = trim($row[3] ?? '');
                $email  = trim($row[4] ?? '');
                $addr   = trim($row[5] ?? '');
                $bName  = trim($row[6] ?? '');
                $bAcc   = trim($row[7] ?? '');
                $bHold  = trim($row[8] ?? '');
                $terms  = trim($row[9] ?? '');
                $notes  = trim($row[10] ?? '');
                
                if (empty($name) || empty($abbr)) throw new Exception("Nama dan Singkatan wajib diisi.");
                
                $stmt = $pdo->prepare("SELECT id FROM vendors WHERE abbreviation = ?");
                $stmt->execute([$abbr]);
                if ($stmt->fetch()) {
                    $upd = $pdo->prepare("UPDATE vendors SET company_name=?, contact_person=?, phone=?, email=?, address=?, bank_name=?, bank_account=?, bank_holder=?, payment_terms=?, notes=? WHERE abbreviation=?");
                    $upd->execute([$name, $pic, $phone, $email, $addr, $bName, $bAcc, $bHold, $terms, $notes, $abbr]);
                } else {
                    $ins = $pdo->prepare("INSERT INTO vendors (company_name, abbreviation, contact_person, phone, email, address, bank_name, bank_account, bank_holder, payment_terms, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $ins->execute([$name, $abbr, $pic, $phone, $email, $addr, $bName, $bAcc, $bHold, $terms, $notes]);
                }
                $successCount++;
                
            } elseif ($type === 'projects') {
                $name     = trim($row[0] ?? '');
                $abbr     = trim($row[1] ?? '');
                $custAbbr = trim($row[2] ?? '');
                $loc      = trim($row[3] ?? '');
                $start    = trim($row[4] ?? '');
                $end      = trim($row[5] ?? '');
                $budget   = floatval($row[6] ?? 0);
                $status   = strtolower(trim($row[7] ?? 'planning'));
                
                if (empty($name) || empty($abbr) || empty($custAbbr)) {
                    throw new Exception("Nama, Singkatan Proyek, dan Singkatan Pelanggan wajib diisi.");
                }
                
                $validStatuses = ['planning', 'active', 'completed', 'cancelled'];
                if (!in_array($status, $validStatuses)) $status = 'planning';
                
                $start = !empty($start) ? date('Y-m-d', strtotime($start)) : null;
                $end = !empty($end) ? date('Y-m-d', strtotime($end)) : null;
                
                // Find Customer
                $stmt = $pdo->prepare("SELECT id, company_name, pic_name, phone FROM customers WHERE abbreviation = ?");
                $stmt->execute([$custAbbr]);
                $cust = $stmt->fetch();
                if (!$cust) {
                    throw new Exception("Pelanggan dengan singkatan '$custAbbr' tidak ditemukan.");
                }
                
                $stmt = $pdo->prepare("SELECT id FROM projects WHERE abbreviation = ?");
                $stmt->execute([$abbr]);
                if ($stmt->fetch()) {
                    $upd = $pdo->prepare("UPDATE projects SET name=?, location=?, start_date=?, end_date=?, budget=?, customer_id=?, customer_name=?, customer_contact=?, status=? WHERE abbreviation=?");
                    $upd->execute([$name, $loc, $start, $end, $budget, $cust['id'], $cust['company_name'], $cust['pic_name'] ?: $cust['phone'], $status, $abbr]);
                } else {
                    $ins = $pdo->prepare("INSERT INTO projects (name, abbreviation, location, start_date, end_date, budget, customer_id, customer_name, customer_contact, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $ins->execute([$name, $abbr, $loc, $start, $end, $budget, $cust['id'], $cust['company_name'], $cust['pic_name'] ?: $cust['phone'], $status]);
                }
                $successCount++;
            }
            
        } catch (Exception $e) {
            $errors[] = "Baris {$rowNum}: " . $e->getMessage();
        }
    }
    
    $pdo->commit();
    
    // Set Flash Message
    if ($successCount > 0 && empty($errors)) {
        setFlash('success', "Import berhasil! {$successCount} data sukses diproses.");
    } elseif ($successCount > 0 && !empty($errors)) {
        $msg = "Import berhasil sebagian. {$successCount} data sukses diproses.<br><strong>Error (" . count($errors) . " baris gagal):</strong><br>";
        $msg .= implode("<br>", array_slice($errors, 0, 10)); // Tampilkan max 10 error
        if (count($errors) > 10) $msg .= "<br>... dan " . (count($errors) - 10) . " error lainnya.";
        setFlash('warning', $msg);
    } elseif ($successCount == 0 && !empty($errors)) {
        $msg = "Import gagal. Tidak ada data yang berhasil diproses.<br><strong>Error (" . count($errors) . " baris gagal):</strong><br>";
        $msg .= implode("<br>", array_slice($errors, 0, 10));
        setFlash('danger', $msg);
    } else {
        setFlash('warning', "Tidak ada data yang diproses.");
    }
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    setFlash('danger', 'Terjadi kesalahan sistem saat membaca file Excel: ' . $e->getMessage());
}

header("Location: " . $_SERVER['HTTP_REFERER']);
exit;
