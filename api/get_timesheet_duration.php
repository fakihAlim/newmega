<?php
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json');

$date = $_GET['date'] ?? '';
$data = [];

if ($date) {
    $stmt = $pdo->prepare("
        SELECT 
            employee_id, 
            SUM(CASE WHEN work_type = 'full' THEN 1 ELSE 0.5 END) as total_duration 
        FROM timesheet_entries 
        WHERE work_date = ? 
        GROUP BY employee_id
    ");
    $stmt->execute([$date]);
    foreach ($stmt->fetchAll() as $row) {
        $data[$row['employee_id']] = floatval($row['total_duration']);
    }
}

echo json_encode($data);
