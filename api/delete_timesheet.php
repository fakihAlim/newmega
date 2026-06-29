<?php
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json');

$user = getCurrentUser();
$isAdmin = !in_array('karyawan', array_map('strtolower', $user['roles'] ?? [$user['role']]));

if (!$isAdmin) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Read raw JSON or POST data
$input = json_decode(file_get_contents('php://input'), true);
$id = $input['id'] ?? ($_POST['id'] ?? null);

if ($id) {
    $stmt = $pdo->prepare("DELETE FROM timesheet_entries WHERE id = ?");
    if ($stmt->execute([$id])) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal menghapus data dari database.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'ID tidak ditemukan.']);
}
