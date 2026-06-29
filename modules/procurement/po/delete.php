<?php
/**
 * Procurement - Purchase Order Delete (Draft/Pending Only)
 */
require_once __DIR__ . '/../../../includes/auth.php';
requirePermission('po_create');

$id = $_GET['id'] ?? 0;

$stmt = $pdo->prepare("SELECT * FROM purchase_orders WHERE id = ?");
$stmt->execute([$id]);
$po = $stmt->fetch();

if (!$po) {
    setFlash('danger', 'PO tidak ditemukan.');
    header('Location: ' . APP_URL . '/modules/procurement/po/index.php');
    exit;
}

$user = getCurrentUser();

// Authorization: Only creator or super admin
if (!canAccess('purchase_order', 'delete') && $po['created_by'] != $user['id']) {
    setFlash('danger', 'Anda tidak memiliki akses untuk menghapus PO ini.');
    header('Location: ' . APP_URL . '/modules/procurement/po/index.php');
    exit;
}

// Only Draft or Pending can be deleted
if (!in_array($po['status'], ['draft', 'pending'])) {
    setFlash('danger', 'Hanya PO berstatus Draft atau Pending yang dapat dihapus.');
    header('Location: ' . APP_URL . '/modules/procurement/po/index.php');
    exit;
}

try {
    $pdo->beginTransaction();
    
    // Revert MR qty_ordered for all linked MR items
    $poItems = $pdo->prepare("SELECT mr_item_id, qty FROM purchase_order_items WHERE po_id = ?");
    $poItems->execute([$id]);
    foreach ($poItems->fetchAll() as $item) {
        if ($item['mr_item_id']) {
            $pdo->prepare("UPDATE material_request_items SET qty_ordered = GREATEST(0, qty_ordered - ?) WHERE id = ?")
                ->execute([$item['qty'], $item['mr_item_id']]);
        }
    }
    
    // Delete PO (purchase_order_items and po_mr_links cascade via ON DELETE CASCADE)
    $pdo->prepare("DELETE FROM purchase_orders WHERE id = ?")->execute([$id]);
    
    $pdo->commit();
    
    logActivity('delete', 'purchase_order', "Menghapus Purchase Order: {$po['po_number']}", 'purchase_orders', $id);
    
    setFlash('success', "PO {$po['po_number']} berhasil dihapus.");
} catch (Exception $e) {
    $pdo->rollBack();
    error_log('[NEWMEGA] ' . $e->getMessage());
    setFlash('danger', 'Terjadi kesalahan sistem saat menghapus PO.');
}

header('Location: ' . APP_URL . '/modules/procurement/po/index.php');
exit;
