<?php
/**
 * API - Get Approved MR Items
 */
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user']['id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$sql = "
    SELECT 
        mri.id as mr_item_id,
        mr.id as mr_id,
        mr.mr_number,
        mr.request_date,
        p.name as project_name,
        u.full_name as requester_name,
        mri.item_id,
        mri.description,
        mri.type_specification,
        mri.uom,
        mri.qty as qty_requested,
        mri.qty_ordered,
        (mri.qty - mri.qty_ordered) as qty_available,
        mri.remark
    FROM material_request_items mri
    JOIN material_requests mr ON mri.mr_id = mr.id
    LEFT JOIN projects p ON mr.project_id = p.id
    LEFT JOIN users u ON mr.requested_by = u.id
    WHERE mr.status = 'approved' AND (mri.qty - mri.qty_ordered) > 0
    ORDER BY mr.id DESC, mri.id ASC
";

$stmt = $pdo->query($sql);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['data' => $items]);
