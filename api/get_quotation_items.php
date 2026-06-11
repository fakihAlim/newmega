<?php
/**
 * API - Get Quotation Items by Quotation ID
 * Used by Invoice creation to load items when a quotation is selected
 */
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user']['id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$quotationId = $_GET['quotation_id'] ?? 0;

if (empty($quotationId)) {
    echo json_encode(['data' => [], 'quotation' => null]);
    exit;
}

// Get quotation header info
$stmtQ = $pdo->prepare("SELECT * FROM quotations WHERE id = ?");
$stmtQ->execute([$quotationId]);
$quotation = $stmtQ->fetch(PDO::FETCH_ASSOC);

if (!$quotation) {
    echo json_encode(['error' => 'Quotation not found', 'data' => [], 'quotation' => null]);
    exit;
}

// Get quotation items
$stmtItems = $pdo->prepare("SELECT * FROM quotation_items WHERE quotation_id = ? ORDER BY id ASC");
$stmtItems->execute([$quotationId]);
$items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'data' => $items,
    'quotation' => $quotation
]);
