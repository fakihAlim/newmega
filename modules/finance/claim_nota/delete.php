<?php
/**
 * Finance - Delete Claim Nota (draft only)
 */
require_once __DIR__ . '/../../../includes/auth.php';
requirePermission('claim_nota', 'delete');

$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT id, status FROM claim_notas WHERE id = ?");
$stmt->execute([$id]);
$claim = $stmt->fetch();

if (!$claim) {
    setFlash('danger', 'Claim Nota tidak ditemukan.');
} elseif ($claim['status'] !== 'draft') {
    setFlash('warning', 'Hanya claim Draft yang bisa dihapus.');
} else {
    try {
        $pdo->beginTransaction();
        $pdo->prepare("DELETE FROM claim_nota_items WHERE claim_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM claim_notas WHERE id = ?")->execute([$id]);
        $pdo->commit();
        setFlash('success', 'Claim Nota berhasil dihapus.');
    } catch (Exception $e) {
        $pdo->rollBack();
        setFlash('danger', 'Gagal menghapus: ' . $e->getMessage());
    }
}

header('Location: index.php');
exit;
