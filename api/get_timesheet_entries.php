<?php
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json');

$date = $_GET['date'] ?? '';
$company_id = $_GET['company_id'] ?? '';
$project_id = $_GET['project_id'] ?? '';

$data = [];

if ($date && $company_id && $project_id) {
    $stmt = $pdo->prepare("
        SELECT 
            t.id as entry_id,
            t.employee_id,
            t.work_type,
            t.overtime_hours,
            t.notes
        FROM timesheet_entries t
        WHERE t.work_date = ? AND t.company_id = ? AND t.project_id = ?
    ");
    $stmt->execute([$date, $company_id, $project_id]);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

echo json_encode($data);
