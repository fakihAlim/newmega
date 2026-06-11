<?php
/**
 * API: Get PO Details and Items for Receiving
 */
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

if (!isset($_GET['po_id'])) {
    echo json_encode(['error' => 'No PO ID provided']);
    exit;
}

$poId = (int)$_GET['po_id'];

try {
    // 1. Fetch PO details
    $stmt = $pdo->prepare("
        SELECT po.id, po.po_number, po.po_date, po.status, v.company_name as vendor_name 
        FROM purchase_orders po
        JOIN vendors v ON po.vendor_id = v.id
        WHERE po.id = ? 
    ");
    $stmt->execute([$poId]);
    $po = $stmt->fetch();

    if (!$po) {
        echo json_encode(['error' => 'PO tidak ditemukan']);
        exit;
    }

    // 2. Fetch Items
    $stmtItems = $pdo->prepare("
        SELECT id, item_id, item_name, qty, uom, qty_received 
        FROM purchase_order_items 
        WHERE po_id = ?
    ");
    $stmtItems->execute([$poId]);
    $items = $stmtItems->fetchAll();

    // Determine pending qty
    foreach ($items as &$item) {
        $item['pending_qty'] = $item['qty'] - $item['qty_received'];
    }

    echo json_encode([
        'success' => true,
        'po' => $po,
        'items' => $items
    ]);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
