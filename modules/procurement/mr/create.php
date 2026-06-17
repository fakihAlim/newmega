<?php
/**
 * Procurement - Material Request Create
 */
require_once __DIR__ . '/../../../includes/auth.php';
requirePermission('mr_create');

$pageTitle = 'Buat Material Request (MR)';
$breadcrumbs = [
    ['label' => 'Procurement', 'url' => '#'],
    ['label' => 'MR', 'url' => APP_URL . '/modules/procurement/mr/index.php'],
    ['label' => 'Baru']
];

$user = getCurrentUser();

// Fetch Projects
// If PM or Gudang, maybe fetch active projects only. If PM, typically filter by pm_id, but per earlier requirements, anyone can fetch active projects (or we restrict).
$projects_sql = "SELECT id, name, location FROM projects WHERE status IN ('planning', 'active') ORDER BY name ASC";
$projects = $pdo->query($projects_sql)->fetchAll();

// Fetch active items
$items_sql = "SELECT id, item_code, description, uom FROM items WHERE is_active = 1 ORDER BY description ASC";
$items = $pdo->query($items_sql)->fetchAll();

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
            
            // Fetch project name and generate abbreviation
            $pStmt = $pdo->prepare("SELECT name FROM projects WHERE id = ?");
            $pStmt->execute([$projectId]);
            $pName = $pStmt->fetchColumn() ?: 'Unknown Project';
            $pAbbr = generateAbbreviation($pName);
            
            // Auto-generate MR Number
            $mrNumber = generateDocNumber($pdo, 'MR', $pAbbr);
            
            // Insert MR Header
            $stmt = $pdo->prepare("
                INSERT INTO material_requests (mr_number, project_id, requested_by, request_date, location, status, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$mrNumber, $projectId, $user['id'], $requestDate, $location, $status, $notes]);
            $mrId = $pdo->lastInsertId();
            
            // Insert Items
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
                            $mrId, 
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
            
            $msg = $status === 'pending' ? "MR $mrNumber berhasil di-submit." : "MR $mrNumber disimpan sebagai Draft.";
            setFlash('success', $msg);
            header('Location: ' . APP_URL . '/modules/procurement/mr/index.php');
            exit;
            
        } catch (Exception $e) {
            $pdo->rollBack();
            setFlash('danger', "Terjadi kesalahan sistem: " . $e->getMessage());
        }
    }
    
    if (!empty($errors)) {
        setFlash('danger', implode('<br>', $errors));
    }
}

require_once __DIR__ . '/../../../includes/header.php';
?>

<!-- Load Montserrat and Work Sans fonts -->
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&family=Work+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
/* Style overrides for Iron & Oak Foundation style */
body {
    font-family: 'Work Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    background-color: #f7f9fb !important;
}

/* Card Redesign */
.mr-card {
    background-color: #ffffff;
    border: 1px solid #e2e8f0 !important;
    border-radius: 4px !important;
    box-shadow: none !important;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    margin-bottom: 2rem;
}
.mr-card:hover {
    transform: translateY(-2px);
    box-shadow: 4px 4px 0px #1e293b !important;
}

/* Header Redesign */
.mr-card-header {
    background-color: #ffffff !important;
    border-bottom: 1px solid #e2e8f0 !important;
    padding: 1.25rem 1.5rem !important;
    border-top-left-radius: 4px !important;
    border-top-right-radius: 4px !important;
}
.mr-card-title {
    font-family: 'Montserrat', sans-serif !important;
    font-weight: 700 !important;
    font-size: 1.25rem !important;
    color: #091426 !important;
    margin: 0 !important;
    letter-spacing: -0.01em;
}

/* Section Headers */
.section-title {
    font-family: 'Montserrat', sans-serif !important;
    font-weight: 700 !important;
    font-size: 13px !important;
    letter-spacing: 0.1em !important;
    text-transform: uppercase !important;
    color: #1e293b !important;
    margin-bottom: 1.25rem !important;
    border-bottom: 2px solid #e2e8f0;
    padding-bottom: 6px;
}

/* Form Styling */
.form-group label {
    font-family: 'Work Sans', sans-serif !important;
    font-weight: 600 !important;
    font-size: 11px !important;
    letter-spacing: 0.08em !important;
    text-transform: uppercase !important;
    color: #475569 !important;
    margin-bottom: 0.5rem !important;
}
.form-control {
    font-family: 'Work Sans', sans-serif !important;
    font-size: 14px !important;
    border: 1px solid #cbd5e1 !important;
    border-radius: 4px !important;
    padding: 0.5rem 0.75rem !important;
    height: auto !important;
    background-color: #ffffff !important;
    transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out !important;
}
.form-control:focus {
    border-color: #cbd5e1 !important;
    border-bottom: 2px solid #f28c28 !important;
    box-shadow: none !important;
    outline: none !important;
}

/* Buttons styling based on design.md */
.btn-primary-cta {
    font-family: 'Montserrat', sans-serif !important;
    font-weight: 600 !important;
    font-size: 14px !important;
    background-color: #f28c28 !important;
    color: #ffffff !important;
    border: none !important;
    border-radius: 4px !important;
    padding: 0.6rem 1.25rem !important;
    transition: background-color 0.2s ease;
}
.btn-primary-cta:hover {
    background-color: #d97706 !important;
    color: #ffffff !important;
}

.btn-secondary-cta {
    font-family: 'Montserrat', sans-serif !important;
    font-weight: 600 !important;
    font-size: 14px !important;
    background-color: #1e293b !important;
    color: #ffffff !important;
    border: none !important;
    border-radius: 4px !important;
    padding: 0.6rem 1.25rem !important;
    transition: background-color 0.2s ease;
}
.btn-secondary-cta:hover {
    background-color: #0f172a !important;
    color: #ffffff !important;
}

.btn-outline-cta {
    font-family: 'Montserrat', sans-serif !important;
    font-weight: 600 !important;
    font-size: 13px !important;
    background-color: transparent !important;
    color: #1e293b !important;
    border: 2px solid #1e293b !important;
    border-radius: 4px !important;
    padding: 0.4rem 1rem !important;
    transition: background-color 0.2s ease, color 0.2s ease;
}
.btn-outline-cta:hover {
    background-color: #1e293b !important;
    color: #ffffff !important;
}

.btn-outline-danger-cta {
    font-family: 'Montserrat', sans-serif !important;
    font-weight: 600 !important;
    font-size: 14px !important;
    background-color: transparent !important;
    color: #ba1a1a !important;
    border: 2px solid #ba1a1a !important;
    border-radius: 4px !important;
    padding: 0.5rem 1.25rem !important;
    transition: background-color 0.2s ease, color 0.2s ease;
}
.btn-outline-danger-cta:hover {
    background-color: #ba1a1a !important;
    color: #ffffff !important;
}

/* Custom Table Styling */
.table-minimalist {
    border-collapse: collapse !important;
    width: 100% !important;
    font-size: 13px !important;
}
.table-minimalist th {
    font-family: 'Montserrat', sans-serif !important;
    font-weight: 600 !important;
    text-transform: uppercase !important;
    font-size: 11px !important;
    letter-spacing: 0.05em !important;
    background-color: #1e293b !important;
    color: #ffffff !important;
    border: 1px solid #334155 !important;
    padding: 10px 12px !important;
}
.table-minimalist td {
    font-family: 'Work Sans', sans-serif !important;
    border: 1px solid #e2e8f0 !important;
    padding: 10px 12px !important;
    vertical-align: middle !important;
    background-color: #ffffff;
}

/* Select2 overrides to match custom styles */
.select2-container--bootstrap4 .select2-selection--single {
    border: 1px solid #cbd5e1 !important;
    border-radius: 4px !important;
    height: calc(2.25rem + 2px) !important;
    font-family: 'Work Sans', sans-serif !important;
    font-size: 14px !important;
}
.select2-container--bootstrap4 .select2-selection--single .select2-selection__rendered {
    line-height: calc(2.25rem) !important;
    padding-left: 0.75rem !important;
    color: #1f2937 !important;
}
.select2-container--bootstrap4 .select2-selection--single:focus,
.select2-container--bootstrap4.select2-container--focus .select2-selection--single {
    border-color: #cbd5e1 !important;
    border-bottom: 2px solid #f28c28 !important;
    box-shadow: none !important;
}

.item-info {
    font-family: 'Work Sans', sans-serif !important;
    font-size: 12px !important;
    line-height: 1.4 !important;
}
.qty-input {
    text-align: center !important;
    font-weight: 600 !important;
}
.btn-remove-row {
    color: #ba1a1a !important;
    background-color: transparent !important;
    border: 1px solid #ba1a1a !important;
    transition: all 0.2s ease;
    border-radius: 4px !important;
    padding: 0.25rem 0.5rem !important;
}
.btn-remove-row:hover {
    color: #ffffff !important;
    background-color: #ba1a1a !important;
}
</style>

<div class="card mr-card">
    <div class="card-header mr-card-header">
        <h3 class="card-title mr-card-title"><i class="fas fa-file-alt mr-2" style="color: #f28c28;"></i> FORM PERMINTAAN MATERIAL (MR)</h3>
    </div>
    
    <form method="POST" id="mrForm">
        <div class="card-body bg-white p-4">
            <!-- Header Section -->
            <h5 class="section-title">1. Informasi Proyek</h5>
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Tujuan Proyek <span class="text-danger">*</span></label>
                        <select name="project_id" id="project_id" class="form-control select2" required>
                            <option value="">-- Pilih Proyek --</option>
                            <?php foreach ($projects as $p): ?>
                                <option value="<?= $p['id'] ?>" data-location="<?= htmlspecialchars($p['location']) ?>"><?= sanitize($p['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Tanggal Request <span class="text-danger">*</span></label>
                        <input type="date" name="request_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                </div>
                <div class="col-md-12">
                    <div class="form-group">
                        <label>Alamat Pengiriman (Delivery Location) <span class="text-danger">*</span></label>
                        <textarea name="location" id="location" class="form-control" rows="2" placeholder="Otomatis terisi jika memilih proyek" required></textarea>
                    </div>
                </div>
                <div class="col-md-12">
                    <div class="form-group">
                        <label>Catatan Tambahan (Header)</label>
                        <textarea name="notes" class="form-control" rows="2"></textarea>
                    </div>
                </div>
            </div>
            
            <hr class="my-4" style="border-top: 1px solid #e2e8f0;">
            
            <!-- Items Section -->
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="section-title m-0" style="border-bottom: none; padding-bottom: 0;">2. Detail Material</h5>
                <button type="button" class="btn btn-outline-cta" id="btnAddRow"><i class="fas fa-plus mr-1"></i> Tambah Baris</button>
            </div>
            
            <div class="table-responsive">
                <table class="table table-minimalist table-bordered table-sm" id="itemsTable">
                    <thead>
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
                        <!-- Rows injected via JS -->
                    </tbody>
                </table>
            </div>
            
        </div>
        
        <div class="card-footer bg-white border-top-0 px-4 pb-4 pt-2 text-right">
            <input type="hidden" name="action" id="formAction" value="draft">
            <a href="<?= APP_URL ?>/modules/procurement/mr/index.php" class="btn btn-outline-danger-cta mr-2"><i class="fas fa-times mr-1"></i> Batal</a>
            <button type="button" class="btn btn-secondary-cta mr-2" onclick="submitForm('draft')"><i class="fas fa-save mr-1"></i> Simpan Draft</button>
            <button type="button" class="btn btn-primary-cta" onclick="submitForm('submit')"><i class="fas fa-paper-plane mr-1"></i> Submit untuk Approval</button>
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
            <div class="item-info text-muted">Pilih barang...</div>
        </td>
        <td>
            <input type="text" name="qty[]" class="form-control qty-input" value="1" required>
        </td>
        <td class="item-uom text-center" style="vertical-align:middle;font-weight:bold;">
            -
        </td>
        <td>
            <input type="text" name="item_remark[]" class="form-control" placeholder="Cth: Untuk cor lantai 1">
        </td>
        <td class="text-center" style="vertical-align:middle;">
            <button type="button" class="btn btn-remove-row"><i class="fas fa-times"></i></button>
        </td>
    </tr>
</template>

<?php
$extraJS = <<<'JS'
<script>
$(document).ready(function() {
    initSelect2('.select2');
    
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
    
    // Add first row by default
    addRow();
    
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
