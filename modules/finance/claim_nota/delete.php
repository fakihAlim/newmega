<?php
/**
 * Finance - Claim Nota Delete
 */
require_once __DIR__ . '/../../../includes/auth.php';
requirePermission('claim_nota', 'delete');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? 0;

    // Fetch Claim to check existence and status
    $stmt = $pdo->prepare("SELECT claim_number, status FROM nota_claims WHERE id = ?");
    $stmt->execute([$id]);
    $claim = $stmt->fetch();

    if (!$claim) {
        setFlash('danger', 'Klaim Nota tidak ditemukan.');
    } elseif (!in_array($claim['status'], ['pending', 'rejected'])) {
        setFlash('danger', 'Hanya klaim berstatus Pending atau Rejected yang dapat dihapus.');
    } else {
        try {
            $pdo->beginTransaction();

            // Fetch all photo filenames to delete files from disk
            $stmtPhotos = $pdo->prepare("SELECT receipt_photo FROM nota_claim_items WHERE claim_id = ?");
            $stmtPhotos->execute([$id]);
            $photos = $stmtPhotos->fetchAll(PDO::FETCH_COLUMN);

            // Cascade delete on the db (FK has ON DELETE CASCADE)
            $stmtDel = $pdo->prepare("DELETE FROM nota_claims WHERE id = ?");
            $stmtDel->execute([$id]);

            $pdo->commit();

            // Delete physical files after transaction commits successfully
            foreach ($photos as $photo) {
                if ($photo) {
                    $filePath = UPLOADS_PATH . '/receipts/' . $photo;
                    if (file_exists($filePath)) {
                        @unlink($filePath);
                    }
                }
            }

            setFlash('success', "Klaim Nota {$claim['claim_number']} berhasil dihapus.");
        } catch (Exception $e) {
            $pdo->rollBack();
            setFlash('danger', "Gagal menghapus klaim: " . $e->getMessage());
        }
    }
} else {
    setFlash('danger', 'Metode request tidak diizinkan.');
}

header('Location: ' . APP_URL . '/modules/finance/claim_nota/index.php');
exit;
