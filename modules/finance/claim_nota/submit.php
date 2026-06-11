<?php
/**
 * Finance - Submit Claim Nota (draft -> pending)
 */
require_once __DIR__ . '/../../../includes/auth.php';
requirePermission('claim_nota');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$id = (int)($_POST['id'] ?? 0);

$stmt = $pdo->prepare("SELECT id, status, claim_number FROM claim_notas WHERE id = ?");
$stmt->execute([$id]);
$claim = $stmt->fetch();

if (!$claim) {
    setFlash('danger', 'Claim Nota tidak ditemukan.');
} elseif ($claim['status'] !== 'draft') {
    setFlash('warning', 'Hanya claim Draft yang bisa disubmit.');
} else {
    // Check at least 1 item exists
    $itemCount = $pdo->prepare("SELECT COUNT(*) FROM claim_nota_items WHERE claim_id = ?");
    $itemCount->execute([$id]);
    
    if ($itemCount->fetchColumn() == 0) {
        setFlash('danger', 'Claim harus memiliki minimal 1 item sebelum disubmit.');
    } else {
        $pdo->prepare("UPDATE claim_notas SET status = 'pending' WHERE id = ?")->execute([$id]);
        setFlash('success', 'Claim Nota ' . $claim['claim_number'] . ' berhasil disubmit untuk approval.');
    }
}

header('Location: index.php');
exit;
