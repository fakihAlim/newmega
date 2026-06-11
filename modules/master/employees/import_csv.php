<?php
/**
 * Master Employees - CSV Import Processor
 */
require_once __DIR__ . '/../../../includes/auth.php';
requirePermission('master_employees');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['csv_file'])) {
    header('Location: ' . APP_URL . '/modules/master/employees/index.php');
    exit;
}

$file = $_FILES['csv_file'];

if ($file['error'] !== UPLOAD_ERR_OK) {
    setFlash('danger', 'Gagal upload file.');
    header('Location: ' . APP_URL . '/modules/master/employees/index.php');
    exit;
}

$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if ($ext !== 'csv') {
    setFlash('danger', 'Format file harus CSV (.csv).');
    header('Location: ' . APP_URL . '/modules/master/employees/index.php');
    exit;
}

$handle = fopen($file['tmp_name'], "r");
if ($handle === FALSE) {
    setFlash('danger', 'Gagal membaca file CSV.');
    header('Location: ' . APP_URL . '/modules/master/employees/index.php');
    exit;
}

// Skip header row
$header = fgetcsv($handle, 1000, ",");

// Fetch available wages for lookup
$wages = $pdo->query("SELECT * FROM master_wages ORDER BY jabatan_name ASC")->fetchAll();
$wageLookup = [];
foreach ($wages as $w) {
    $wageLookup[strtolower(trim($w['jabatan_name']))] = $w['id'];
}

// Get karyawan role_id
$roleStmt = $pdo->prepare("SELECT id FROM roles WHERE role_key = 'karyawan'");
$roleStmt->execute();
$karyawan_role_id = $roleStmt->fetchColumn();

$successCount = 0;
$errorCount = 0;
$errorDetails = [];
$rowNum = 1;

// Get max employee code
$stmtMaxCode = $pdo->query("SELECT MAX(CAST(SUBSTRING(employee_code, 5) AS UNSIGNED)) FROM employees WHERE employee_code LIKE 'KAR-%'");
$currentMax = $stmtMaxCode->fetchColumn() ?: 0;

$pdo->beginTransaction();
try {
    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
        $rowNum++;
        
        if (count($data) < 2) {
            $errorCount++;
            $errorDetails[] = "Baris $rowNum: Data tidak lengkap (minimal: nama, jabatan).";
            continue;
        }
        
        $csvName = trim($data[0]);
        $csvJabatan = trim($data[1]);
        $csvPhone = isset($data[2]) ? trim($data[2]) : '';
        
        if (empty($csvName)) {
            $errorCount++;
            $errorDetails[] = "Baris $rowNum: Nama kosong.";
            continue;
        }
        
        if (empty($csvJabatan)) {
            $errorCount++;
            $errorDetails[] = "Baris $rowNum ($csvName): Jabatan kosong.";
            continue;
        }
        
        // Match jabatan name to wage_id
        $matchedWageId = $wageLookup[strtolower($csvJabatan)] ?? null;
        if (!$matchedWageId) {
            $errorCount++;
            $errorDetails[] = "Baris $rowNum ($csvName): Jabatan \"$csvJabatan\" tidak ditemukan di Master Upah.";
            continue;
        }
        
        // Auto-generate username
        $base_username = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $csvName));
        if (empty($base_username)) $base_username = 'user';
        $username = $base_username;
        
        $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
        $counter = 1;
        while (true) {
            $stmtCheck->execute([$username]);
            if ($stmtCheck->fetchColumn() == 0) break;
            $username = $base_username . $counter;
            $counter++;
        }
        
        $hashedPass = password_hash('123456', PASSWORD_DEFAULT);
        
        // 1. Create User
        $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, phone, is_active) VALUES (?, ?, ?, ?, 1)");
        $stmt->execute([$username, $hashedPass, $csvName, $csvPhone]);
        $newUserId = $pdo->lastInsertId();
        
        // 2. Assign Role
        if ($karyawan_role_id) {
            $pdo->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)")->execute([$newUserId, $karyawan_role_id]);
        }
        
        // 3. Create Employee Profile with generated code
        $currentMax++;
        $employee_code = 'KAR-' . str_pad($currentMax, 3, '0', STR_PAD_LEFT);
        $pdo->prepare("INSERT INTO employees (user_id, wage_id, employee_code, is_active) VALUES (?, ?, ?, 1)")->execute([$newUserId, $matchedWageId, $employee_code]);
        
        $successCount++;
    }
    
    $pdo->commit();
    
    $msg = "Import CSV selesai! <strong>$successCount</strong> karyawan berhasil ditambahkan.";
    if ($errorCount > 0) {
        $msg .= " <strong>$errorCount</strong> baris gagal/dilewati.";
        if (!empty($errorDetails)) {
            $msg .= "<br><small class='text-muted'>Detail:<br>" . implode('<br>', array_slice($errorDetails, 0, 10)) . "</small>";
            if (count($errorDetails) > 10) {
                $msg .= "<br><small class='text-muted'>... dan " . (count($errorDetails) - 10) . " error lainnya.</small>";
            }
        }
    }
    if ($successCount > 0) {
        $msg .= "<br><small>Password default semua karyawan: <b>123456</b></small>";
    }
    setFlash($successCount > 0 ? 'success' : 'danger', $msg);
    
} catch (Exception $e) {
    $pdo->rollBack();
    setFlash('danger', 'Gagal import: ' . $e->getMessage());
}

fclose($handle);
header('Location: ' . APP_URL . '/modules/master/employees/index.php');
exit;
