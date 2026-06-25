<?php
/**
 * API - Check Item Code & Find Last Sequence
 */
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

// Only allow authenticated users
if (!isset($_SESSION['user']['id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$categoryId = $_GET['category_id'] ?? 0;
$manualCode = $_GET['manual_code'] ?? '';

if (empty($categoryId) || empty($manualCode)) {
    echo json_encode(['exists' => false, 'last_code' => null, 'full_code' => '']);
    exit;
}

// Get category prefix
$stmt = $pdo->prepare("SELECT prefix FROM categories WHERE id = ?");
$stmt->execute([$categoryId]);
$prefix = $stmt->fetchColumn();

if (!$prefix) {
    echo json_encode(['exists' => false, 'last_code' => null, 'full_code' => '']);
    exit;
}

$manualCode = strtoupper(str_replace(' ', '', $manualCode));
$fullCode = $prefix . '-' . $manualCode;

// Check exact match
$stmt = $pdo->prepare("SELECT id FROM items WHERE item_code = ?");
$stmt->execute([$fullCode]);
$exists = (bool) $stmt->fetchColumn();

// Find last code matching [PREFIX]-[MANUAL_CODE]%
$stmt = $pdo->prepare("
    SELECT item_code 
    FROM items 
    WHERE item_code LIKE ? AND item_code != ?
    ORDER BY item_code DESC 
    LIMIT 1
");
$stmt->execute([$prefix . '-' . $manualCode . '%', $fullCode]);
$lastCode = $stmt->fetchColumn();

echo json_encode([
    'exists' => $exists,
    'full_code' => $fullCode,
    'last_code' => $lastCode ?: null
]);
