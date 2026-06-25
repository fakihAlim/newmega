<?php
/**
 * Procurement - Create Goods Receiving
 */
require_once __DIR__ . '/../../../includes/auth.php';
// Note: using po_create as fallback if receiving_create is not defined, 
// but we just assume the user is authorized.
requirePermission('receiving_list'); // Change to receiving_btn when matrix applies

$user = getCurrentUser();

// Fetch Projects for dropdown
$stmtProjects = $pdo->query("SELECT id, name FROM projects WHERE status IN ('planning', 'active') ORDER BY name");
$projects = $stmtProjects->fetchAll();

// Fetch Eligible POs
$stmtPOs = $pdo->query("SELECT id, po_number, vendor_id, po_date FROM purchase_orders WHERE status IN ('approved', 'partially_received') ORDER BY id DESC");
$eligiblePOs = $stmtPOs->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $poId = $_POST['po_id'] ?? 0;
    $receiveDate = $_POST['receive_date'] ?? date('Y-m-d');
    $suratJalan = $_POST['surat_jalan_no'] ?? '';
    $receivedAt = $_POST['received_at'] ?? 'warehouse';
    $projectId = ($receivedAt === 'project') ? ($_POST['project_id'] ?? null) : null;
    $notes = trim($_POST['notes'] ?? '');
    
    $receiveQtys = $_POST['qty_received'] ?? [];
    $rejectQtys = $_POST['qty_rejected'] ?? [];
    $rejectReasons = $_POST['reject_reason'] ?? [];
    $poItemIds = $_POST['po_item_id'] ?? [];
    $itemIds = $_POST['item_id'] ?? []; // for stock update
    
    if (empty($poId) || empty($receiveQtys)) {
        setFlash('danger', 'Detail PO atau Barang tidak valid.');
        header('Location: create.php');
        exit;
    }
    
    // Handle File Upload
    $fileName = null;
    if (isset($_FILES['surat_jalan_file']) && $_FILES['surat_jalan_file']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/../../../uploads/receiving';
        $uploadResult = uploadFile($_FILES['surat_jalan_file'], $uploadDir, ['jpg', 'jpeg', 'png', 'pdf'], 5242880, ['quality' => 75, 'maxWidth' => 800, 'maxHeight' => 800]);
        if ($uploadResult['success']) {
            $fileName = $uploadResult['filename'];
        } else {
            setFlash('danger', $uploadResult['message']);
            header('Location: create.php');
            exit;
        }
    }

    try {
        $pdo->beginTransaction();
        
        // 1. Insert into goods_receivings
        $stmtGR = $pdo->prepare("
            INSERT INTO goods_receivings (po_id, receive_date, surat_jalan_no, surat_jalan_file, received_by, received_at, project_id, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmtGR->execute([$poId, $receiveDate, $suratJalan, $fileName, $user['id'], $receivedAt, $projectId, $notes]);
        $grId = $pdo->lastInsertId();
        
        $totalPOItems = 0;
        $fullyReceivedItems = 0;
        
        // Check initial PO to see how many items exist
        $stmtPOItemsCount = $pdo->prepare("SELECT id, qty, qty_received FROM purchase_order_items WHERE po_id = ?");
        $stmtPOItemsCount->execute([$poId]);
        $originalItems = $stmtPOItemsCount->fetchAll();
        $totalPOItems = count($originalItems);
        
        // Loop through submitted arrays
        foreach ($receiveQtys as $index => $qtyR) {
            $qtyR = (float)$qtyR;
            $qtyRej = isset($rejectQtys[$index]) ? (float)$rejectQtys[$index] : 0;
            $rReason = $rejectReasons[$index] ?? '';
            $pItemId = $poItemIds[$index] ?? 0;
            $mItemId = $itemIds[$index] ?? 0;
            
            if ($qtyR > 0 || $qtyRej > 0) {
                // Insert GR item
                $stmtInsertGRItem = $pdo->prepare("
                    INSERT INTO goods_receiving_items (receiving_id, po_item_id, qty_received, qty_rejected, reject_reason)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmtInsertGRItem->execute([$grId, $pItemId, $qtyR, $qtyRej, $rReason]);
                
                if ($qtyR > 0) {
                    // Update purchase_order_items qty_received
                    $stmtUpdatePOItem = $pdo->prepare("UPDATE purchase_order_items SET qty_received = qty_received + ? WHERE id = ?");
                    $stmtUpdatePOItem->execute([$qtyR, $pItemId]);
                    
                    // Update Stock if received at warehouse
                    if ($receivedAt === 'warehouse' && $mItemId > 0) {
                        // Increase item current_stock
                        $stmtUpdateStock = $pdo->prepare("UPDATE items SET current_stock = current_stock + ? WHERE id = ?");
                        $stmtUpdateStock->execute([$qtyR, $mItemId]);
                        
                        // Insert stock_transactions
                        $stmtLogStock = $pdo->prepare("
                            INSERT INTO stock_transactions (item_id, transaction_type, qty, reference_type, reference_id, created_by, notes)
                            VALUES (?, 'in', ?, 'goods_receiving', ?, ?, ?)
                        ");
                        $stmtLogStock->execute([$mItemId, $qtyR, $grId, $user['id'], "Masuk dari SJ: " . $suratJalan]);
                    }
                }
            }
        }
        
        // Read updated items for PO status calculation
        $stmtUpdatedPOItems = $pdo->prepare("SELECT qty, qty_received FROM purchase_order_items WHERE po_id = ?");
        $stmtUpdatedPOItems->execute([$poId]);
        $updatedItems = $stmtUpdatedPOItems->fetchAll();
        
        foreach ($updatedItems as $ui) {
            if ($ui['qty_received'] >= $ui['qty']) {
                $fullyReceivedItems++;
            }
        }
        
        $newStatus = ($fullyReceivedItems === $totalPOItems) ? 'completed' : 'partially_received';
        
        $stmtUpdatePO = $pdo->prepare("UPDATE purchase_orders SET status = ? WHERE id = ?");
        $stmtUpdatePO->execute([$newStatus, $poId]);
        
        $stmtPONum = $pdo->prepare("SELECT po_number FROM purchase_orders WHERE id = ?");
        $stmtPONum->execute([$poId]);
        $poNumber = $stmtPONum->fetchColumn();
        
        logActivity('create', 'goods_receiving', "Menerima Barang (SJ: {$suratJalan}) untuk PO: {$poNumber}", 'goods_receivings', $grId);
        
        $pdo->commit();
        setFlash('success', 'Barang berhasil diterima dan disimpan. Status PO terupdate: ' . $newStatus);
        header('Location: index.php');
        exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log('[NEWMEGA] ' . $e->getMessage());
        setFlash('danger', 'Gagal menyimpan penerimaan. Terjadi kesalahan sistem.');
    }
}

$pageTitle = 'Terima Barang';
$breadcrumbs = [
    ['label' => 'Procurement', 'url' => '#'],
    ['label' => 'Penerimaan', 'url' => 'index.php'],
    ['label' => 'Terima Baru']
];

require_once __DIR__ . '/../../../includes/header.php';
?>

<style>
.form-group {
    margin-bottom: 0.5rem !important;
}
.form-group label {
    margin-bottom: 0.25rem !important;
    font-size: 13px;
}
/* Make custom file input match form-control-sm (31px) */
.custom-file, .custom-file-input, .custom-file-label {
    height: 31px !important;
}
.custom-file-label {
    padding: .25rem .75rem !important;
    line-height: 1.5 !important;
    font-size: 13px;
}
.custom-file-label::after {
    height: 29px !important;
    line-height: 1.5 !important;
    padding: .25rem .75rem !important;
    font-size: 13px;
}
</style>

<div class="row">
    <div class="col-md-12">
        <form action="" method="POST" id="formReceive" enctype="multipart/form-data">
            <!-- Header section -->
            <div class="card card-primary card-outline">
                <div class="card-header">
                    <h3 class="card-title">Informasi Penerimaan</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Pilih Purchase Order <span class="text-danger">*</span></label>
                                <select class="form-control form-control-sm select2" name="po_id" id="po_id" required>
                                    <option value="">-- Pilih PO --</option>
                                    <?php foreach ($eligiblePOs as $po): ?>
                                        <option value="<?= $po['id'] ?>"><?= sanitize($po['po_number']) ?> (<?= date('d-m-Y', strtotime($po['po_date'])) ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Tanggal Terima <span class="text-danger">*</span></label>
                                <input type="date" name="receive_date" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>No. Surat Jalan (S/J) <span class="text-danger">*</span></label>
                                <input type="text" name="surat_jalan_no" class="form-control form-control-sm" required placeholder="Contoh: SJ-2026-X11">
                            </div>
                        </div>
                    </div>
                    <div class="row mt-1">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Lokasi Terima <span class="text-danger">*</span></label>
                                <select class="form-control form-control-sm" name="received_at" id="received_at" required>
                                    <option value="warehouse">Gudang Utama (Warehouse)</option>
                                    <option value="project">Lokasi Proyek</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Upload Surat Jalan (Scan/Foto)</label>
                                <div class="custom-file">
                                    <input type="file" name="surat_jalan_file" class="custom-file-input" id="surat_jalan_file" accept=".jpg,.jpeg,.png,.pdf">
                                    <label class="custom-file-label" for="surat_jalan_file">Pilih file...</label>
                                </div>
                                <small class="text-muted" style="font-size: 11px;">Format: JPG, PNG, PDF. Max: 5MB.</small>
                            </div>
                        </div>
                        <div class="col-md-4" id="project_selection" style="display:none;">
                            <div class="form-group">
                                <label>Pilih Proyek <span class="text-danger">*</span></label>
                                <select class="form-control form-control-sm select2" name="project_id" id="project_id" style="width:100%;">
                                    <option value="">-- Proyek --</option>
                                    <?php foreach ($projects as $p): ?>
                                    <option value="<?= $p['id'] ?>"><?= sanitize($p['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted" style="font-size: 11px;">Penerimaan di proyek tidak menambah stok di gudang.</small>
                            </div>
                        </div>
                    </div>
                    <div class="row mt-1">
                        <div class="col-md-8">
                            <div class="form-group">
                                <label>Catatan</label>
                                <textarea name="notes" class="form-control form-control-sm" rows="1" placeholder="Catatan tambahan opsional..."></textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Items Section -->
            <div class="card card-success card-outline" id="itemsCard">
                <div class="card-header">
                    <h3 class="card-title">Daftar Barang Diterima</h3>
                </div>
                <div class="card-body p-0">
                    <table class="table table-bordered table-sm table-striped m-0" id="tableItems" >
                        <thead class="bg-light">
                            <tr>
                                <th width="30%">Nama Barang</th>
                                <th width="10%" class="text-center">Di Pesan</th>
                                <th width="10%" class="text-center">Diterima Sblm</th>
                                <th width="10%" class="text-center">Sisa (Pending)</th>
                                <th width="15%" class="text-center">Penerimaan Saat Ini <span class="text-danger">*</span></th>
                                <th width="25%">Barang Rusak / Ditolak</th>
                            </tr>
                        </thead>
                        <tbody id="itemsBody">
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">Silakan pilih PO terlebih dahulu.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="card-footer text-right">
                    <a href="index.php" class="btn btn-default">Batal</a>
                    <button type="submit" class="btn btn-success ml-2"><i class="fas fa-save mr-1"></i> Simpan Penerimaan</button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php
$extraJS = <<<JS
<script>
$(document).ready(function() {
    // Initialize custom file input
    if (typeof bsCustomFileInput !== 'undefined') {
        bsCustomFileInput.init();
    }

    $('.select2').select2({ theme: 'bootstrap4' });

    // Handle Logic for Project specific fields
    $('#received_at').on('change', function() {
        if ($(this).val() === 'project') {
            $('#project_selection').show();
            $('#project_id').attr('required', true);
        } else {
            $('#project_selection').hide();
            $('#project_id').attr('required', false);
        }
    });

    // Handle PO Selection
    $('#po_id').on('change', function() {
        let poId = $(this).val();
        let tbody = $('#itemsBody');
        
        if (!poId) {
            tbody.html('<tr><td colspan="6" class="text-center text-muted py-4">Silakan pilih PO terlebih dahulu.</td></tr>');
            return;
        }

        tbody.html('<tr><td colspan="6" class="text-center py-4"><i class="fas fa-spinner fa-spin mr-2"></i> Memuat data...</td></tr>');

        $.ajax({
            url: '../../../api/get_po_for_receiving.php',
            data: { po_id: poId },
            dataType: 'json',
            success: function(res) {
                if (res.error) {
                    tbody.html(`<tr><td colspan="6" class="text-center text-danger">\${res.error}</td></tr>`);
                    return;
                }

                // Auto-select project if PO is linked to one
                if (res.project_id) {
                    $('#received_at').val('project').trigger('change');
                    $('#project_id').val(res.project_id).trigger('change');
                } else {
                    $('#received_at').val('warehouse').trigger('change');
                    $('#project_id').val('').trigger('change');
                }
                
                let html = '';
                
                res.items.forEach((item, index) => {
                    let itemName = item.item_name;
                    let uom = item.uom;
                    let qtyOrdered = parseFloat(item.qty);
                    let qtyHistory = parseFloat(item.qty_received);
                    let pendingQty = parseFloat(item.pending_qty);

                    let rowClass = (pendingQty <= 0) ? 'bg-light text-muted' : '';
                    let readonlyAttr = (pendingQty <= 0) ? 'readonly' : '';
                    
                    html += `<tr class="\${rowClass}">
                        <td>
                            <strong>\${itemName}</strong>
                            <input type="hidden" name="po_item_id[]" value="\${item.id}">
                            <input type="hidden" name="item_id[]" value="\${item.item_id}">
                        </td>
                        <td class="text-center font-weight-bold">\${qtyOrdered}</td>
                        <td class="text-center text-info">\${qtyHistory}</td>
                        <td class="text-center text-danger font-weight-bold">\${pendingQty}</td>
                        <td>
                            <div class="input-group input-group-sm">
                                <input type="number" step="0.01" min="0" max="\${pendingQty}" name="qty_received[]" class="form-control form-control-sm input-qty" value="\${pendingQty > 0 ? pendingQty : 0}" \${readonlyAttr} required>
                                <div class="input-group-append"><span class="input-group-text">\${uom}</span></div>
                            </div>
                        </td>
                        <td>
                            <div class="row">
                                <div class="col-sm-5">
                                    <input type="number" step="0.01" min="0" max="\${pendingQty}" name="qty_rejected[]" class="form-control form-control-sm" placeholder="Qty Rusak" \${readonlyAttr}>
                                </div>
                                <div class="col-sm-7 pl-0">
                                    <input type="text" name="reject_reason[]" class="form-control form-control-sm" placeholder="Alasan (Opsional)" \${readonlyAttr}>
                                </div>
                            </div>
                        </td>
                    </tr>`;
                });

                if (html === '') {
                    html = '<tr><td colspan="6" class="text-center text-muted">Tidak ada item yang perlu diterima.</td></tr>';
                }
                
                tbody.html(html);
            },
            error: function() {
                tbody.html('<tr><td colspan="6" class="text-center text-danger">Gagal mengambil data dari server.</td></tr>');
            }
        });
    });
});
</script>
JS;

require_once __DIR__ . '/../../../includes/footer.php';
?>
