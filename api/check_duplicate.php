<?php
/**
 * API - Check duplicate names for Master Data
 */
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

// Only allow authenticated users
if (!isset($_SESSION['user']['id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$type = $_GET['type'] ?? '';
$query = trim($_GET['q'] ?? '');
$excludeId = $_GET['id'] ?? 0;

if (strlen($query) < 2) {
    echo json_encode(['matches' => []]);
    exit;
}

$matches = [];
$searchTerm = "%$query%";

switch ($type) {
    case 'category':
        $stmt = $pdo->prepare("SELECT name FROM categories WHERE name LIKE ? AND id != ? LIMIT 3");
        $stmt->execute([$searchTerm, $excludeId]);
        $matches = $stmt->fetchAll(PDO::FETCH_COLUMN);
        break;
        
    case 'item':
        $stmt = $pdo->prepare("SELECT description AS name FROM items WHERE description LIKE ? AND id != ? LIMIT 3");
        $stmt->execute([$searchTerm, $excludeId]);
        $matches = $stmt->fetchAll(PDO::FETCH_COLUMN);
        break;
        
    case 'vendor':
        $stmt = $pdo->prepare("SELECT company_name AS name FROM vendors WHERE company_name LIKE ? AND id != ? LIMIT 3");
        $stmt->execute([$searchTerm, $excludeId]);
        $matches = $stmt->fetchAll(PDO::FETCH_COLUMN);
        break;
        
    case 'customer':
        $stmt = $pdo->prepare("SELECT company_name AS name FROM customers WHERE company_name LIKE ? AND id != ? LIMIT 3");
        $stmt->execute([$searchTerm, $excludeId]);
        $matches = $stmt->fetchAll(PDO::FETCH_COLUMN);
        break;
        
    case 'project':
        $stmt = $pdo->prepare("SELECT name FROM projects WHERE name LIKE ? AND id != ? LIMIT 3");
        $stmt->execute([$searchTerm, $excludeId]);
        $matches = $stmt->fetchAll(PDO::FETCH_COLUMN);
        break;
        
    case 'company':
        $stmt = $pdo->prepare("SELECT name FROM companies WHERE name LIKE ? AND id != ? LIMIT 3");
        $stmt->execute([$searchTerm, $excludeId]);
        $matches = $stmt->fetchAll(PDO::FETCH_COLUMN);
        break;
}

echo json_encode(['matches' => $matches]);
