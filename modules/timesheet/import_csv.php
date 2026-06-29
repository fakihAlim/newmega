<?php
/**
 * Timesheet - CSV Import Processor
 */
require_once __DIR__ . '/../../includes/auth.php';
requirePermission('timesheet_input');

$user = getCurrentUser();
$isAdmin = !in_array('karyawan', array_map('strtolower', $user['roles'] ?? [$user['role']]));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    $file = $_FILES['file'];
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        setFlash('danger', 'Gagal upload file.');
        header('Location: ' . APP_URL . '/modules/reports/timesheet.php');
        exit;
    }
    
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    if (strtolower($ext) !== 'csv') {
        setFlash('danger', 'Format file harus CSV.');
        header('Location: ' . APP_URL . '/modules/reports/timesheet.php');
        exit;
    }
    
    if ($file['size'] > 5242880) {
        setFlash('danger', 'Ukuran file terlalu besar (maks 5MB).');
        header('Location: ' . APP_URL . '/modules/reports/timesheet.php');
        exit;
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    $allowedCsvMimes = ['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'];
    if (!in_array($mimeType, $allowedCsvMimes)) {
        setFlash('danger', 'Tipe MIME tidak valid untuk CSV.');
        header('Location: ' . APP_URL . '/modules/reports/timesheet.php');
        exit;
    }
    
    $handle = fopen($file['tmp_name'], "r");
    if ($handle !== FALSE) {
        $header = fgetcsv($handle, 1000, ","); // Skip header
        
        $successCount = 0;
        $errorCount = 0;
        
        $pdo->beginTransaction();
        try {
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                if (count($data) < 6) continue; // Skip invalid rows
                
                $username = trim($data[0]);
                $company_id = intval($data[1]);
                $project_id = intval($data[2]);
                $work_date = trim($data[3]);
                $work_type = strtolower(trim($data[4]));
                $overtime_hours = floatval($data[5]);
                $notes = isset($data[6]) ? trim($data[6]) : '';
                
                if (!in_array($work_type, ['full', 'half'])) {
                    $work_type = 'full';
                }
                
                // Get employee ID and daily wage by username
                $stmtEmp = $pdo->prepare("SELECT e.id, w.daily_wage FROM employees e JOIN users u ON e.user_id = u.id JOIN master_wages w ON e.wage_id = w.id WHERE u.username = ?");
                $stmtEmp->execute([$username]);
                $emp = $stmtEmp->fetch();
                
                if (!$emp) {
                    $errorCount++;
                    continue; // Skip if user not found
                }
                
                // Check if already exists
                $stmtCheck = $pdo->prepare("SELECT id FROM timesheet_entries WHERE employee_id = ? AND work_date = ? AND project_id = ?");
                $stmtCheck->execute([$emp['id'], $work_date, $project_id]);
                if ($stmtCheck->fetch()) {
                    $errorCount++;
                    continue; // Skip duplicate
                }
                
                $status = $isAdmin ? 'approved' : 'pending';
                $approved_by = $isAdmin ? $user['id'] : null;
                $approved_at = $isAdmin ? date('Y-m-d H:i:s') : null;
                
                $stmtIns = $pdo->prepare("
                    INSERT INTO timesheet_entries 
                    (employee_id, company_id, project_id, work_date, work_type, overtime_hours, daily_wage_at_time, notes, status, approved_by, approved_at, created_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $stmtIns->execute([
                    $emp['id'], $company_id, $project_id, $work_date, $work_type, $overtime_hours, $emp['daily_wage'], $notes, $status, $approved_by, $approved_at, $user['id']
                ]);
                
                $successCount++;
            }
            $pdo->commit();
            
            if ($successCount > 0) {
                logActivity('create', 'timesheet', "Berhasil mengimpor $successCount baris timesheet dari file CSV", 'timesheet_entries');
            }
            
            $msg = "Import selesai! Berhasil: $successCount baris.";
            if ($errorCount > 0) {
                $msg .= " Gagal/Dilewati: $errorCount baris (User tidak ditemukan / Duplikat).";
            }
            setFlash('success', $msg);
            
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log('[NEWMEGA] ' . $e->getMessage());
            setFlash('danger', 'Gagal mengimpor data timesheet. Terjadi kesalahan sistem.');
        }
        
        fclose($handle);
    }
}

header('Location: ' . APP_URL . '/modules/reports/timesheet.php');
exit;
