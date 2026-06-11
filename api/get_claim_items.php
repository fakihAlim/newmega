<?php
/**
 * API - Search items for Claim Nota autocomplete
 * Returns JSON list of items matching the search query
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$search = trim($_GET['q'] ?? '');

if (strlen($search) < 1) {
    echo json_encode([]);
    exit;
}

$stmt = $pdo->prepare("
    SELECT id, item_code, description, uom 
    FROM items 
    WHERE is_active = 1 AND (description LIKE ? OR item_code LIKE ?)
    ORDER BY description
    LIMIT 20
");
$searchTerm = "%{$search}%";
$stmt->execute([$searchTerm, $searchTerm]);
$items = $stmt->fetchAll();

echo json_encode($items);
