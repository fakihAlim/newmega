<?php
/**
 * Procurement - Material Request Edit (Drafts Only)
 */
require_once __DIR__ . '/../../../includes/auth.php';
requirePermission('mr_create');

$id = $_GET['id'] ?? 0;

$stmt = $pdo->prepare("SELECT * FROM material_requests WHERE id = ?");
$stmt->execute([$id]);
$mr = $stmt->fetch();

if (!$mr) {
    setFlash('danger', 'MR tidak ditemukan.');
    header('Location: ' . APP_URL . '/modules/procurement/mr/index.php');
    exit;
}

$user = getCurrentUser();

// Authorization: Only the requester or super admin can edit. 
if ($user['role'] !== 'super_admin' && $mr['requested_by'] != $user['id']) {
    setFlash('danger', 'Anda tidak memiliki akses untuk mengubah MR ini.');
    header('Location: ' . APP_URL . '/modules/procurement/mr/index.php');
    exit;
}

// Logic: Only Draft can be edited
if ($mr['status'] !== 'draft') {
    setFlash('danger', 'MR yang sudah di-submit tidak dapat diubah.');
    header('Location: ' . APP_URL . '/modules/procurement/mr/index.php');
    exit;
}

$pageTitle = 'Edit MR: ' . sanitize($mr['mr_number']);
$breadcrumbs = [
    ['label' => 'Procurement', 'url' => '#'],
    ['label' => 'MR', 'url' => APP_URL . '/modules/procurement/mr/index.php'],
    ['label' => 'Edit']
];

// Fetch active projects
$projects_sql = "SELECT id, name, location FROM projects WHERE status IN ('planning', 'active') ORDER BY name ASC";
$projects = $pdo->query($projects_sql)->fetchAll();

// Fetch items for dropdown
$items_sql = "SELECT id, item_code, description, uom, current_stock, type_specification FROM items WHERE is_active = 1 ORDER BY description ASC";
$items = $pdo->query($items_sql)->fetchAll();
$itemsMap = [];
foreach($items as $itm) {
    $itemsMap[$itm['id']] = $itm;
}

// Fetch MR Items
$stmtItem = $pdo->prepare("SELECT * FROM material_request_items WHERE mr_id = ?");
$stmtItem->execute([$id]);
$mrItems = $stmtItem->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $projectId   = $_POST['project_id'] ?? null;
    $requestDate = $_POST['request_date'] ?? date('Y-m-d');
    $location    = trim($_POST['location'] ?? '');
    $notes       = trim($_POST['notes'] ?? '');
    $action      = $_POST['action'] ?? 'draft'; // draft or submit
    
    $itemIds     = $_POST['item_id'] ?? [];
    $qtys        = $_POST['qty'] ?? [];
    $remarks     = $_POST['item_remark'] ?? [];
    
    $status = ($action === 'submit') ? 'pending' : 'draft';
    
    $errors = [];
    if (empty($projectId)) $errors[] = "Proyek wajib dipilih.";
    if (empty($itemIds) || count($itemIds) === 0) $errors[] = "Minimal harus ada 1 barang yang direquest.";
    
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            // Update MR Header
            $stmt = $pdo->prepare("
                UPDATE material_requests 
                SET project_id = ?, request_date = ?, location = ?, status = ?, notes = ?
                WHERE id = ?
            ");
            $stmt->execute([$projectId, $requestDate, $location, $status, $notes, $id]);
            
            // Re-insert Items (Delete existing mapping, then re-add to avoid complex logic)
            $pdo->prepare("DELETE FROM material_request_items WHERE mr_id = ?")->execute([$id]);
            
            $insertItem = $pdo->prepare("
                INSERT INTO material_request_items (mr_id, item_id, description, type_specification, uom, qty, remark)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            $getItem = $pdo->prepare("SELECT description, type_specification, uom FROM items WHERE id = ?");
            
            for ($i = 0; $i < count($itemIds); $i++) {
                $i_id = $itemIds[$i];
                $i_qty = parseRupiah($qtys[$i] ?? '0');
                $i_rem = $remarks[$i] ?? '';
                
                if ($i_id && $i_qty > 0) {
                    $getItem->execute([$i_id]);
                    $itemMaster = $getItem->fetch();
                    if ($itemMaster) {
                        $insertItem->execute([
                            $id, 
                            $i_id, 
                            $itemMaster['description'], 
                            $itemMaster['type_specification'], 
                            $itemMaster['uom'], 
                            $i_qty, 
                            $i_rem
                        ]);
                    }
                }
            }
            
            $pdo->commit();
            
            $msg = $status === 'pending' ? "MR {$mr['mr_number']} berhasil di-submit." : "Draft MR {$mr['mr_number']} berhasil diperbarui.";
            setFlash('success', $msg);
            header('Location: ' . APP_URL . '/modules/procurement/mr/index.php');
            exit;
            
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log('[NEWMEGA] ' . $e->getMessage());
            setFlash('danger', 'Terjadi kesalahan sistem. Silakan coba lagi atau hubungi administrator.');
        }
    }
    
    if (!empty($errors)) {
        setFlash('danger', implode('<br>', $errors));
    }
}

require_once __DIR__ . '/../../../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title text-warning"><i class="fas fa-edit mr-2"></i> Edit Material Request: <?= sanitize($mr['mr_number']) ?></h3>
        <a href="<?= APP_URL ?>/modules/procurement/mr/index.php" class="btn btn-secondary btn-sm float-right"><i class="fas fa-arrow-left mr-1"></i> Batal</a>
    </div>
    
    <form method="POST" id="mrForm">
        <div class="card-body bg-light">
            <!-- Header Section -->
            <h5 class="mb-3 text-secondary text-uppercase" style="font-size:14px;letter-spacing:1px;font-weight:600;">1. Informasi Proyek</h5>
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Tujuan Proyek <span class="text-danger">*</span></label>
                        <select name="project_id" id="project_id" class="form-control select2" required>
                            <option value="">-- Pilih Proyek --</option>
                            <?php foreach ($projects as $p): ?>
                                <option value="<?= $p['id'] ?>" data-location="<?= htmlspecialchars($p['location']) ?>" <?= ($mr['project_id'] == $p['id']) ? 'selected' : '' ?>><?= sanitize($p['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Tanggal Request <span class="text-danger">*</span></label>
                        <input type="date" name="request_date" class="form-control" value="<?= sanitize($mr['request_date']) ?>" required>
                    </div>
                </div>
                <div class="col-md-12">
                    <div class="form-group">
                        <label>Alamat Pengiriman (Delivery Location) <span class="text-danger">*</span></label>
                        <textarea name="location" id="location" class="form-control" rows="2" placeholder="Otomatis terisi jika memilih proyek" required><?= sanitize($mr['location']) ?></textarea>
                    </div>
                </div>
                <div class="col-md-12">
                    <div class="form-group">
                        <label>Catatan Tambahan (Header)</label>
                        <textarea name="notes" class="form-control" rows="2"><?= sanitize($mr['notes']) ?></textarea>
                    </div>
                </div>
            </div>
            
            <hr class="my-4">
            
            <!-- Items Section -->
            <h5 class="mb-3 text-secondary text-uppercase d-flex justify-content-between align-items-center" style="font-size:14px;letter-spacing:1px;font-weight:600;">
                2. Detail Material
                <button type="button" class="btn btn-sm btn-info" id="btnAddRow"><i class="fas fa-plus"></i> Tambah Baris</button>
            </h5>
            
            <div class="table-responsive">
                <table class="table table-bordered table-sm" id="itemsTable">
                    <thead class="thead-dark">
                        <tr>
                            <th width="35%">Item Code & Description</th>
                            <th width="15%">Type</th>
                            <th width="15%">Qty</th>
                            <th width="10%">Uom</th>
                            <th width="20%">Remark</th>
                            <th width="5%" class="text-center"><i class="fas fa-trash"></i></th>
                        </tr>
                    </thead>
                    <tbody id="itemsBody">
                        <?php foreach($mrItems as $mi): ?>
                            <?php $master = $itemsMap[$mi['item_id']] ?? null; ?>
                            <tr class="item-row">
                                <td>
                                    <select name="item_id[]" class="form-control item-select" required>
                                        <option value="">-- Cari Barang --</option>
                                        <?php foreach ($items as $item): ?>
                                            <option value="<?= $item['id'] ?>" <?= ($item['id'] == $mi['item_id']) ? 'selected' : '' ?>><?= sanitize($item['item_code'] . ' - ' . $item['description']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td>
                                    <div class="item-info text-muted" style="font-size:12px;">
                                        <?php if ($master): ?>
                                            <b>Tipe:</b> <?= sanitize($master['type_specification'] ?: '-') ?><br>
                                            <b>Stok:</b> <?= number_format($master['current_stock'], 0) ?>
                                        <?php else: ?>
                                            Pilih barang...
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <input type="text" name="qty[]" class="form-control input-number qty-input" value="<?= number_format($mi['qty'], 0, '', '') ?>" required>
                                </td>
                                <td class="item-uom text-center" style="vertical-align:middle;font-weight:bold;">
                                    <?= sanitize($mi['uom']) ?>
                                </td>
                                <td>
                                    <input type="text" name="item_remark[]" class="form-control" value="<?= sanitize($mi['remark']) ?>">
                                </td>
                                <td class="text-center" style="vertical-align:middle;">
                                    <button type="button" class="btn btn-danger btn-sm btn-remove-row"><i class="fas fa-times"></i></button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
        </div>
        
        <div class="card-footer bg-white text-right">
            <input type="hidden" name="action" id="formAction" value="draft">
            <a href="<?= APP_URL ?>/modules/procurement/mr/index.php" class="btn btn-default mr-2"><i class="fas fa-times mr-1"></i> Batal</a>
            <button type="button" class="btn btn-secondary mr-2" onclick="submitForm('draft')"><i class="fas fa-save mr-1"></i> Update Draft</button>
            <button type="button" class="btn btn-success" onclick="submitForm('submit')"><i class="fas fa-paper-plane mr-1"></i> Kirim untuk Persetujuan</button>
        </div>
    </form>
</div>

<!-- Template Row for Items -->
<template id="rowTemplate">
    <tr class="item-row">
        <td>
            <select name="item_id[]" class="form-control item-select" required>
                <option value="">-- Cari Barang --</option>
                <?php foreach ($items as $item): ?>
                    <option value="<?= $item['id'] ?>"><?= sanitize($item['item_code'] . ' - ' . $item['description']) ?></option>
                <?php endforeach; ?>
            </select>
        </td>
        <td>
            <div class="item-info text-muted" style="font-size:12px;">Pilih barang...</div>
        </td>
        <td>
            <input type="text" name="qty[]" class="form-control input-number qty-input" value="1" required>
        </td>
        <td class="item-uom text-center" style="vertical-align:middle;font-weight:bold;">
            -
        </td>
        <td>
            <input type="text" name="item_remark[]" class="form-control" placeholder="Cth: Untuk cor lantai 1">
        </td>
        <td class="text-center" style="vertical-align:middle;">
            <button type="button" class="btn btn-danger btn-sm btn-remove-row"><i class="fas fa-times"></i></button>
        </td>
    </tr>
</template>

<?php
$extraJS = <<<'JS'
<script>
$(document).ready(function() {
    initSelect2('.select2');
    initSelect2('.item-select'); // Init existing rows
    
    setTimeout(function() { updateItemSelects(); }, 100);
    
    // Auto fill location on project change
    $('#project_id').on('change', function() {
        var loc = $(this).find('option:selected').data('location');
        if (loc) {
            $('#location').val(loc);
        } else {
            $('#location').val('');
        }
    });

    // Dynamic Rows Logic
    var template = $('#rowTemplate').html();
    var tbody = $('#itemsBody');
    
    function addRow() {
        var html = $(template);
        tbody.append(html);
        
        // Init select2 on new row
        html.find('.item-select').select2({
            theme: 'bootstrap4',
            width: '100%'
        });
        updateItemSelects();
    }
    
    $('#btnAddRow').on('click', function() {
        addRow();
    });
    
    function updateItemSelects() {
        var selectedIds = [];
        $('.item-select').each(function() {
            if ($(this).val()) {
                selectedIds.push($(this).val());
            }
        });
        
        $('.item-select').each(function() {
            var currentSelect = $(this);
            var currentVal = currentSelect.val();
            
            currentSelect.find('option').each(function() {
                if (this.value !== "" && this.value !== currentVal && selectedIds.includes(this.value)) {
                    $(this).prop('disabled', true);
                } else {
                    $(this).prop('disabled', false);
                }
            });
            // trigger select2 refresh without firing change event
            currentSelect.select2('destroy').select2({ theme: 'bootstrap4', width: '100%' });
        });
    }
    
    // Remove Row
    tbody.on('click', '.btn-remove-row', function() {
        if (tbody.find('.item-row').length > 1) {
            $(this).closest('tr').remove();
            updateItemSelects();
        } else {
            showError('Harus ada minimal 1 baris item.');
        }
    });
    
    // On Item Selection Change
    tbody.on('change', '.item-select', function() {
        var row = $(this).closest('tr');
        var itemId = $(this).val();
        var infoDiv = row.find('.item-info');
        var uomCol = row.find('.item-uom');
        
        if (!itemId) {
            infoDiv.html('Pilih barang...');
            uomCol.html('-');
            return;
        }
        
        infoDiv.html('<i class="fas fa-spinner fa-spin"></i> Loading...');
        
        $.get(APP_URL + '/api/get_item.php?id=' + itemId, function(row_data) {
            if (row_data.error) {
                infoDiv.html('<span class="text-danger">Item error</span>');
                uomCol.html('-');
            } else {
                var typeSpec = row_data.type_specification ? row_data.type_specification : '-';
                infoDiv.html(
                    '<b>Tipe:</b> ' + typeSpec + '<br>' +
                    '<b>Stok:</b> ' + parseFloat(row_data.current_stock).toLocaleString('id-ID')
                );
                uomCol.html(row_data.uom);
            }
        });
        updateItemSelects();
    });
});

function submitForm(actionType) {
    var form = $('#mrForm');
    
    // Basic validation
    if (!$('#project_id').val()) {
        showError('Pilih proyek terlebih dahulu.');
        return;
    }
    
    var hasItem = false;
    $('.qty-input').each(function() {
        if (parseFloat($(this).val()) > 0) hasItem = true;
    });
    
    if (!hasItem) {
        showError('Harap isi qty barang terlebih dahulu.');
        return;
    }
    
    $('#formAction').val(actionType);
    
    if (actionType === 'submit') {
        confirmAction('Submit Request?', 'MR akan dikirim untuk proses approval. Anda tidak dapat mengubah datanya lagi kecuali MR ditolak.', function() {
            form.submit();
        });
    } else {
        form.submit(); // Save Draft silently
    }
}
</script>
JS;
require_once __DIR__ . '/../../../includes/footer.php';
?>
