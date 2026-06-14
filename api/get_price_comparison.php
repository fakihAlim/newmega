<?php
/**
 * API - Get Item Price Comparison
 */
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user']['id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$itemId = $_GET['item_id'] ?? 0;

if (!$itemId) {
    echo json_encode(['error' => 'Invalid Item ID']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT 
            poi.unit_price, 
            po.po_number, 
            po.po_date, 
            v.company_name as vendor_name
        FROM purchase_order_items poi
        JOIN purchase_orders po ON poi.po_id = po.id
        JOIN vendors v ON po.vendor_id = v.id
        WHERE poi.item_id = ? AND po.status IN ('approved', 'partially_received', 'completed')
        ORDER BY poi.unit_price ASC, po.po_date DESC
        LIMIT 5
    ");
    $stmt->execute([$itemId]);
    $history = $stmt->fetchAll();

    echo json_encode($history);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
exit;
