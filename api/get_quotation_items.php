<?php
/**
 * API: Get Quotation Items
 * Returns the list of items for a given quotation ID.
 */
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['error' => 'Invalid request method.']);
    exit;
}

$quotationId = $_GET['quotation_id'] ?? 0;

if (empty($quotationId)) {
    echo json_encode(['error' => 'Quotation ID is required.']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT description, type_specification, qty, uom, material_unit_price, manpower_unit_price, amount
        FROM quotation_items 
        WHERE quotation_id = ?
        ORDER BY id ASC
    ");
    $stmt->execute([$quotationId]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Ensure numeric types for frontend parsing and support both client configurations
    foreach ($items as &$item) {
        $item['qty'] = (float) $item['qty'];
        $item['material_unit_price'] = (float) $item['material_unit_price'];
        $item['manpower_unit_price'] = (float) $item['manpower_unit_price'];
        $item['amount'] = (float) ($item['amount'] ?? 0);
        
        // Backward compatibility for quotations/create.php duplicate feature
        $item['material_price'] = $item['material_unit_price'];
        $item['manpower_price'] = $item['manpower_unit_price'];
    }

    // Fetch quotation summary for invoice creation page
    $qStmt = $pdo->prepare("
        SELECT subtotal, discount, tax, shipping, total 
        FROM quotations 
        WHERE id = ?
    ");
    $qStmt->execute([$quotationId]);
    $quotation = $qStmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $items,
        'quotation' => $quotation
    ]);

} catch (Exception $e) {
    echo json_encode([
        'error' => $e->getMessage()
    ]);
}
?>
