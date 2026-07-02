<?php
/**
 * Timesheet - Excel Import Processor
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../vendor/autoload.php';

requirePermission('timesheet_input');

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;

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
    if (!in_array(strtolower($ext), ['xlsx', 'xls'])) {
        setFlash('danger', 'Format file harus Excel (.xlsx atau .xls).');
        header('Location: ' . APP_URL . '/modules/reports/timesheet.php');
        exit;
    }
    
    if ($file['size'] > 5242880) {
        setFlash('danger', 'Ukuran file terlalu besar (maks 5MB).');
        header('Location: ' . APP_URL . '/modules/reports/timesheet.php');
        exit;
    }

    try {
        $spreadsheet = IOFactory::load($file['tmp_name']);
        $sheet = $spreadsheet->getActiveSheet();
        $highestRow = $sheet->getHighestRow();
        
        $successCount = 0;
        $errorCount = 0;
        
        $pdo->beginTransaction();
        
        for ($row = 2; $row <= $highestRow; $row++) {
            $username = trim($sheet->getCell('A' . $row)->getValue() ?? '');
            
            // Skip empty rows
            if ($username === '') continue;
            
            $company_id = intval($sheet->getCell('B' . $row)->getValue() ?? 0);
            $project_id = intval($sheet->getCell('C' . $row)->getValue() ?? 0);
            
            // Handle Excel Date cell
            $cellDate = $sheet->getCell('D' . $row);
            $dateVal = $cellDate->getValue();
            if (Date::isDateTime($cellDate)) {
                $dateTime = Date::excelToDateTimeObject($dateVal);
                $work_date = $dateTime->format('Y-m-d');
            } else {
                $work_date = trim($dateVal ?? '');
                if (is_numeric($work_date)) {
                    $dateTime = Date::excelToDateTimeObject((float)$work_date);
                    $work_date = $dateTime->format('Y-m-d');
                }
            }
            
            $work_type = strtolower(trim($sheet->getCell('E' . $row)->getValue() ?? ''));
            $overtime_hours = floatval($sheet->getCell('F' . $row)->getValue() ?? 0);
            $notes = trim($sheet->getCell('G' . $row)->getValue() ?? '');
            
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
            logActivity('create', 'timesheet', "Berhasil mengimpor $successCount baris timesheet dari file Excel", 'timesheet_entries');
        }
        
        $msg = "Import selesai! Berhasil: $successCount baris.";
        if ($errorCount > 0) {
            $msg .= " Gagal/Dilewati: $errorCount baris (User tidak ditemukan / Duplikat).";
        }
        setFlash('success', $msg);
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('[NEWMEGA] ' . $e->getMessage());
        setFlash('danger', 'Gagal mengimpor data timesheet. Format Excel salah atau terjadi kesalahan sistem.');
    }
}

header('Location: ' . APP_URL . '/modules/reports/timesheet.php');
exit;
