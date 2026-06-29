<?php
/**
 * Timesheet - Export Report CSV
 */
require_once __DIR__ . '/../../includes/auth.php';
requirePermission('report_timesheet');

$user = getCurrentUser();
$isAdmin = !in_array('karyawan', array_map('strtolower', $user['roles'] ?? [$user['role']]));

$employee_id = null;
if (!$isAdmin) {
    $stmt = $pdo->prepare("SELECT id FROM employees WHERE user_id = ?");
    $stmt->execute([$user['id']]);
    $employee_id = $stmt->fetchColumn();
}

$month = $_GET['month'] ?? date('n');
$year = $_GET['year'] ?? date('Y');
$company_id = $_GET['company_id'] ?? '';
$project_id = $_GET['project_id'] ?? '';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

$where = ["t.status = 'approved'"];
$params = [];

if (!empty($start_date) && !empty($end_date)) {
    $where[] = "t.work_date BETWEEN ? AND ?";
    $params[] = $start_date;
    $params[] = $end_date;
    $fileSuffix = "{$start_date}_{$end_date}";
} else {
    $where[] = "MONTH(t.work_date) = ?";
    $where[] = "YEAR(t.work_date) = ?";
    $params[] = $month;
    $params[] = $year;
    $fileSuffix = "{$year}_{$month}";
}

if (!empty($company_id)) {
    $where[] = "t.company_id = ?";
    $params[] = $company_id;
}
if (!empty($project_id)) {
    $where[] = "t.project_id = ?";
    $params[] = $project_id;
}
if ($employee_id) {
    $where[] = "t.employee_id = ?";
    $params[] = $employee_id;
}

$whereClause = implode(" AND ", $where);

$sql = "
    SELECT 
        c.name as company_name,
        p.name as project_name,
        u.full_name as employee_name,
        w.jabatan_name,
        MAX(t.daily_wage_at_time) as daily_wage,
        SUM(CASE WHEN t.work_type = 'full' THEN 1 ELSE 0 END) as full_days,
        SUM(CASE WHEN t.work_type = 'half' THEN 1 ELSE 0 END) as half_days,
        SUM(t.overtime_hours) as total_overtime,
        SUM((CASE WHEN t.work_type = 'full' THEN 1 ELSE 0.5 END) * t.daily_wage_at_time) as total_upah,
        SUM((t.daily_wage_at_time / 8) * t.overtime_hours) as total_upah_lembur
    FROM timesheet_entries t
    JOIN employees e ON t.employee_id = e.id
    JOIN users u ON e.user_id = u.id
    JOIN master_wages w ON e.wage_id = w.id
    JOIN projects p ON t.project_id = p.id
    JOIN companies c ON t.company_id = c.id
    WHERE $whereClause
    GROUP BY t.company_id, t.project_id, t.employee_id
    ORDER BY c.name, p.name, u.full_name
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$data = $stmt->fetchAll();

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="Laporan_Timesheet_'.$fileSuffix.'.csv"');

$output = fopen('php://output', 'w');
fputcsv($output, ['Perusahaan', 'Proyek', 'Nama Karyawan', 'Jabatan', 'H.Full', 'H.Half', 'Upah/Hari', 'Total Upah', 'J.Kerja', 'J.Lembur', 'Upah/Lembur', 'Total Lembur', 'Total Biaya']);

foreach ($data as $row) {
    $jamKerjaNormal = ($row['full_days'] * 8) + ($row['half_days'] * 4);
    $upahLemburPerJam = $row['daily_wage'] / 8;
    $totalBiaya = $row['total_upah'] + $row['total_upah_lembur'];
    
    fputcsv($output, [
        $row['company_name'],
        $row['project_name'],
        $row['employee_name'],
        $row['jabatan_name'],
        $row['full_days'],
        $row['half_days'],
        $row['daily_wage'],
        $row['total_upah'],
        $jamKerjaNormal,
        $row['total_overtime'],
        $upahLemburPerJam,
        $row['total_upah_lembur'],
        $totalBiaya
    ]);
}

fclose($output);
exit;
