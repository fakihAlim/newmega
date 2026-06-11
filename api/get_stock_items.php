<?php
/**
 * API: Get Items with Available Stock > 0
 */
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

$term = $_GET['q'] ?? '';

try {
    $sql = "
        SELECT id, item_code, description, uom, current_stock 
        FROM items 
        WHERE is_active = 1 AND current_stock > 0 
        AND (item_code LIKE ? OR description LIKE ?)
        ORDER BY description ASC
        LIMIT 50
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute(["%$term%", "%$term%"]);
    $items = $stmt->fetchAll();
    
    $results = [];
    foreach ($items as $item) {
        $results[] = [
            'id' => $item['id'],
            'text' => $item['item_code'] . ' - ' . $item['description'] . ' (Stok: ' . (float)$item['current_stock'] . ' ' . $item['uom'] . ')',
            'item_code' => $item['item_code'],
            'description' => $item['description'],
            'uom' => $item['uom'],
            'current_stock' => $item['current_stock']
        ];
    }
    
    echo json_encode(['results' => $results]);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
