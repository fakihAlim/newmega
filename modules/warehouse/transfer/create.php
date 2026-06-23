<?php
/**
 * Warehouse - Create Transfer (SJ Keluar)
 */
require_once __DIR__ . '/../../../includes/auth.php';
requirePermission('transfer_create');

$user = getCurrentUser();

$stmtProjects = $pdo->query("SELECT id, name, location FROM projects WHERE status IN ('planning', 'active') ORDER BY name");
$projects = $stmtProjects->fetchAll();

$stmtCompanies = $pdo->query("SELECT id, name, is_default FROM companies ORDER BY name");
$companies = $stmtCompanies->fetchAll();

$stmtItems = $pdo->query("SELECT id, item_code, description, uom FROM items WHERE is_active = 1 ORDER BY description");
$itemsMaster = $stmtItems->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $toProjectId = $_POST['to_project_id'] ?? 0;
    $companyId = $_POST['company_id'] ?? 0;
    $driverName = trim($_POST['driver_name'] ?? '');
    $transferDate = $_POST['transfer_date'] ?? date('Y-m-d');
    $notes = trim($_POST['notes'] ?? '');
    
    $itemIds = $_POST['item_id'] ?? [];
    $qtys = $_POST['qty'] ?? [];
    
    if (empty($toProjectId) || empty($companyId) || empty($itemIds)) {
        setFlash('danger', 'Proyek tujuan, Perusahaan header, dan minimal satu barang harus diisi.');
        header('Location: create.php');
        exit;
    }
    
    try {
        $pdo->beginTransaction();
        
        // Validasi Stok
        foreach ($itemIds as $index => $itemId) {
            $q = (float)($qtys[$index] ?? 0);
            if ($q <= 0) continue;
            
            $stmtCek = $pdo->prepare("SELECT current_stock, item_code FROM items WHERE id = ? FOR UPDATE");
            $stmtCek->execute([$itemId]);
            $item = $stmtCek->fetch();
            
            if (!$item || $item['current_stock'] < $q) {
                throw new Exception("Stok untuk barang " . ($item['item_code'] ?? 'ID:'.$itemId) . " tidak mencukupi.");
            }
        }
        
        // Generate TR Number
        $stmtTr = $pdo->query("SELECT COUNT(*) FROM warehouse_transfers WHERE MONTH(transfer_date) = MONTH(CURRENT_DATE()) AND YEAR(transfer_date) = YEAR(CURRENT_DATE())");
        $count = $stmtTr->fetchColumn();
        $transferNumber = 'TR-' . date('Ym') . '-' . str_pad($count + 1, 3, '0', STR_PAD_LEFT);
        
        // Insert Header
        $stmtInsert = $pdo->prepare("
            INSERT INTO warehouse_transfers (transfer_number, transfer_date, to_project_id, transferred_by, driver_name, company_id, notes, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'completed')
        ");
        $stmtInsert->execute([$transferNumber, $transferDate, $toProjectId, $user['id'], $driverName, $companyId, $notes]);
        $transferId = $pdo->lastInsertId();
        
        // Insert Items & Update Stock
        foreach ($itemIds as $index => $itemId) {
            $q = (float)($qtys[$index] ?? 0);
            if ($q <= 0) continue;
            
            // Insert Detail
            $stmtDetail = $pdo->prepare("INSERT INTO warehouse_transfer_items (transfer_id, item_id, qty) VALUES (?, ?, ?)");
            $stmtDetail->execute([$transferId, $itemId, $q]);
            
            // Deduct Stock
            $stmtUpdateStock = $pdo->prepare("UPDATE items SET current_stock = current_stock - ? WHERE id = ?");
            $stmtUpdateStock->execute([$q, $itemId]);
            
            // Stock Transaction Log
            $stmtLog = $pdo->prepare("
                INSERT INTO stock_transactions (item_id, transaction_type, qty, reference_type, reference_id, project_id, created_by, notes)
                VALUES (?, 'transfer_out', ?, 'transfer', ?, ?, ?, ?)
            ");
            
            // Get Project Name for log
            $pName = '';
            foreach($projects as $p) { if($p['id'] == $toProjectId) $pName = $p['name']; }
            
            $logNotes = "Transfer keluar ke proyek: " . $pName . " (" . $transferNumber . ")";
            $stmtLog->execute([$itemId, $q, $transferId, $toProjectId, $user['id'], $logNotes]);
        }
        
        logActivity('create', 'warehouse_transfer', "Membuat Surat Jalan Keluar: {$transferNumber} ke Proyek {$pName}", 'warehouse_transfers', $transferId);
        
        $pdo->commit();
        setFlash('success', 'Transfer Barang (' . $transferNumber . ') berhasil disimpan dan stok telah terpotong.');
        header("Location: view.php?id=$transferId");
        exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log('[NEWMEGA] ' . $e->getMessage());
        setFlash('danger', 'Gagal membuat transfer. Terjadi kesalahan sistem.');
    }
}

$pageTitle = 'Buat Transfer Barang (SJ Keluar)';
$breadcrumbs = [
    ['label' => 'Warehouse', 'url' => '#'],
    ['label' => 'Transfer', 'url' => 'index.php'],
    ['label' => 'Buat Baru']
];

require_once __DIR__ . '/../../../includes/header.php';
?>

<div class="row">
    <div class="col-md-12">
        <form action="" method="POST" id="formTransfer">
            <div class="card card-warning card-outline">
                <div class="card-header">
                    <h3 class="card-title text-warning font-weight-bold"><i class="fas fa-truck-loading mr-2"></i> Pembuatan Surat Jalan Keluar (Proyek)</h3>
                </div>
                <div class="card-body bg-light">
                    <div class="row">
                        <!-- Left side: General Info -->
                        <div class="col-md-6 border-right">
                            
                            <div class="form-group row">
                                <label class="col-sm-4 col-form-label">Perusahaan Header <span class="text-danger">*</span></label>
                                <div class="col-sm-8">
                                    <select name="company_id" class="form-control select2" required>
                                        <option value="">-- Pilih Perusahaan --</option>
                                        <?php foreach ($companies as $c): ?>
                                            <option value="<?= $c['id'] ?>" <?= $c['is_default'] ? 'selected' : '' ?>><?= sanitize($c['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="form-group row">
                                <label class="col-sm-4 col-form-label">Proyek Tujuan <span class="text-danger">*</span></label>
                                <div class="col-sm-8">
                                    <select class="form-control select2" name="to_project_id" required>
                                        <option value="">-- Pilih Proyek --</option>
                                        <?php foreach ($projects as $p): ?>
                                            <option value="<?= $p['id'] ?>"><?= sanitize($p['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="form-group row">
                                <label class="col-sm-4 col-form-label">Tanggal Transfer <span class="text-danger">*</span></label>
                                <div class="col-sm-8">
                                    <input type="date" name="transfer_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                                </div>
                            </div>
                        </div>

                        <!-- Right side: Driver & Notes -->
                        <div class="col-md-6">
                            <div class="form-group row">
                                <label class="col-sm-4 col-form-label">Nama Sopir</label>
                                <div class="col-sm-8">
                                    <input type="text" name="driver_name" class="form-control" placeholder="Cth: Pak Budi">
                                </div>
                            </div>

                            <div class="form-group row">
                                <label class="col-sm-4 col-form-label">Keterangan Pengiriman</label>
                                <div class="col-sm-8">
                                    <textarea name="notes" class="form-control" rows="3" placeholder="Additional Notes (Akan muncul di print out)"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Items Section -->
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <button type="button" class="btn btn-sm btn-primary" id="btnAddRow"><i class="fas fa-plus mr-1"></i> Tambah Baris</button>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-bordered table-sm" id="itemsTable">
                            <thead class="bg-dark text-white">
                                <tr>
                                    <th width="40%">Barang (Pilih dari Master)</th>
                                    <th width="15%" class="text-center">Stok Saat Ini</th>
                                    <th width="20%">Qty Dikirim <span class="text-danger">*</span></th>
                                    <th width="15%" class="text-center">Satuan</th>
                                    <th width="10%" class="text-center"><i class="fas fa-trash"></i></th>
                                </tr>
                            </thead>
                            <tbody id="itemsBody">
                                <!-- Dynamic rows -->
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer text-right bg-white">
                    <a href="index.php" class="btn btn-default">Batal</a>
                    <button type="submit" class="btn btn-warning font-weight-bold ml-2 text-dark"><i class="fas fa-save mr-1"></i> Simpan & Kirim Barang</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Template Row -->
<template id="rowTemplate">
    <tr class="item-row">
        <td>
            <select name="item_id[]" class="form-control item-select" required style="width: 100%;">
                <option value="">-- Pilih Barang --</option>
                <?php foreach ($itemsMaster as $itm): ?>
                    <option value="<?= $itm['id'] ?>"><?= sanitize($itm['item_code'] . ' - ' . $itm['description']) ?></option>
                <?php endforeach; ?>
            </select>
        </td>
        <td class="text-center font-weight-bold text-success item-stock-display" style="vertical-align: middle;">-</td>
        <td>
            <input type="number" step="0.01" min="0.01" name="qty[]" class="form-control form-control-sm qty-input" required>
        </td>
        <td class="text-center item-uom-display" style="vertical-align: middle;">-</td>
        <td class="text-center" style="vertical-align: middle;">
            <button type="button" class="btn btn-danger btn-sm btn-remove-row"><i class="fas fa-times"></i></button>
        </td>
    </tr>
</template>

<?php
$extraJS = <<<JS
<script>
$(document).ready(function() {
    $('.select2').select2({ theme: 'bootstrap4' });
    
    var tbody = $('#itemsBody');
    var template = $('#rowTemplate').html();

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
            
            var changed = false;
            currentSelect.find('option').each(function() {
                var shouldDisable = (this.value !== "" && this.value !== currentVal && selectedIds.includes(this.value));
                if ($(this).prop('disabled') !== shouldDisable) {
                    $(this).prop('disabled', shouldDisable);
                    changed = true;
                }
            });
            
            if (changed && currentSelect.hasClass("select2-hidden-accessible")) {
                currentSelect.select2('destroy').select2({ theme: 'bootstrap4', width: '100%' });
            }
        });
    }

    function addRow() {
        var html = $(template);
        tbody.append(html);
        
        html.find('.item-select').select2({
            theme: 'bootstrap4',
            width: '100%'
        });
        updateItemSelects();
    }

    // Add first row
    addRow();

    $('#btnAddRow').on('click', function() {
        addRow();
    });

    tbody.on('click', '.btn-remove-row', function() {
        if (tbody.find('.item-row').length > 1) {
            $(this).closest('tr').remove();
            updateItemSelects();
        } else {
            alert('Minimal harus ada 1 baris item.');
        }
    });

    tbody.on('change', '.item-select', function() {
        var row = $(this).closest('tr');
        var itemId = $(this).val();
        var stockDisplay = row.find('.item-stock-display');
        var uomDisplay = row.find('.item-uom-display');
        var qtyInput = row.find('.qty-input');

        if (!itemId) {
            stockDisplay.text('-');
            uomDisplay.text('-');
            qtyInput.attr('max', '');
            updateItemSelects();
            return;
        }

        stockDisplay.html('<i class="fas fa-spinner fa-spin"></i>');

        $.get(APP_URL + '/api/get_item.php?id=' + itemId, function(res) {
            if (res.error) {
                stockDisplay.text('Error');
            } else {
                stockDisplay.text(parseFloat(res.current_stock).toLocaleString('id-ID'));
                uomDisplay.text(res.uom);
                qtyInput.attr('max', res.current_stock);
                
                if (parseFloat(res.current_stock) <= 0) {
                    qtyInput.val(0).prop('disabled', true);
                    alert('Barang ini sedang kosong (Stok: 0). Tidak bisa ditransfer.');
                } else {
                    qtyInput.prop('disabled', false);
                }
            }
        });
        updateItemSelects();
    });

    $('#formTransfer').on('submit', function(e) {
        var valid = true;
        $('.qty-input').each(function() {
            var qty = parseFloat($(this).val());
            var max = parseFloat($(this).attr('max'));
            if (qty > max) {
                alert('Jumlah dikirim tidak boleh melebihi stok yang tersedia!');
                valid = false;
                return false;
            }
        });
        if (!valid) e.preventDefault();
    });
});
</script>
JS;
require_once __DIR__ . '/../../../includes/footer.php';
?>
