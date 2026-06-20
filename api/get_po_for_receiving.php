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

    // 3. Determine if PO belongs/refers to a project
    $stmtProj = $pdo->prepare("
        SELECT DISTINCT mr.project_id 
        FROM po_mr_links pml
        JOIN material_requests mr ON pml.mr_id = mr.id
        WHERE pml.po_id = ?
    ");
    $stmtProj->execute([$poId]);
    $projectIds = $stmtProj->fetchAll(PDO::FETCH_COLUMN);

    if (empty($projectIds)) {
        // Fallback: Try via items
        $stmtProjItems = $pdo->prepare("
            SELECT DISTINCT mr.project_id 
            FROM purchase_order_items poi
            JOIN material_request_items mri ON poi.mr_item_id = mri.id
            JOIN material_requests mr ON mri.mr_id = mr.id
            WHERE poi.po_id = ? AND poi.mr_item_id IS NOT NULL
        ");
        $stmtProjItems->execute([$poId]);
        $projectIds = $stmtProjItems->fetchAll(PDO::FETCH_COLUMN);
    }
    $projectId = !empty($projectIds) ? (int)$projectIds[0] : null;

    echo json_encode([
        'success' => true,
        'po' => $po,
        'items' => $items,
        'project_id' => $projectId
    ]);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}

