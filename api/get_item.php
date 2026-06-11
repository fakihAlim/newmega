<?php
/**
 * API - Get Item Details
 */
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user']['id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$id = $_GET['id'] ?? 0;

if (!$id) {
    echo json_encode(['error' => 'Invalid ID']);
    exit;
}

$stmt = $pdo->prepare("SELECT item_code, description, type_specification, uom, stock_type, current_stock FROM items WHERE id = ?");
$stmt->execute([$id]);
$item = $stmt->fetch();

if ($item) {
    echo json_encode($item);
} else {
    echo json_encode(['error' => 'Item not found']);
}
