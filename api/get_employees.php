<?php
/**
 * API - Get active employees as JSON
 */
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json');
$employees = $pdo->query("
    SELECT e.id, e.employee_code, u.username, u.full_name, w.jabatan_name, w.daily_wage
    FROM employees e
    JOIN users u ON e.user_id = u.id
    JOIN master_wages w ON e.wage_id = w.id
    WHERE e.is_active = 1
    ORDER BY u.full_name ASC
")->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($employees);
