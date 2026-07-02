<?php
/**
 * Master Items - List
 */
require_once __DIR__ . '/../../../includes/auth.php';
requirePermission('master_items');

// Handle AJAX Datatable request
if (isset($_GET['draw'])) {
    $draw = (int)($_GET['draw'] ?? 1);
    $start = (int)($_GET['start'] ?? 0);
    $length = (int)($_GET['length'] ?? 10);
    $searchValue = $_GET['search']['value'] ?? '';
    $orderColIndex = (int)($_GET['order'][0]['column'] ?? 0);
    $orderDir = $_GET['order'][0]['dir'] ?? 'asc';

    $columns = [
        0 => 'i.item_code',
        1 => 'c.name',
        2 => 'i.description',
        3 => 'i.uom',
        4 => 'i.warehouse_location',
        5 => 'i.stock_type',
        6 => 'i.minimum_stock',
        7 => 'i.is_active'
    ];
    $orderBy = $columns[$orderColIndex] ?? 'i.item_code';
    if (!in_array($orderDir, ['asc', 'desc'])) {
        $orderDir = 'asc';
    }

    $searchQuery = "";
    $params = [];
    if ($searchValue !== '') {
        $searchQuery = " AND (i.item_code LIKE ? OR c.name LIKE ? OR i.description LIKE ? OR i.type_specification LIKE ? OR i.warehouse_location LIKE ? OR i.uom LIKE ?)";
        $searchPattern = "%$searchValue%";
        $params = [$searchPattern, $searchPattern, $searchPattern, $searchPattern, $searchPattern, $searchPattern];
    }

    $totalRecords = $pdo->query("SELECT COUNT(*) FROM items")->fetchColumn();

    $countSql = "SELECT COUNT(*) FROM items i LEFT JOIN categories c ON i.category_id = c.id WHERE 1=1 $searchQuery";
    if ($searchValue !== '') {
        $stmt = $pdo->prepare($countSql);
        $stmt->execute($params);
        $recordsFiltered = $stmt->fetchColumn();
    } else {
        $recordsFiltered = $totalRecords;
    }

    $dataSql = "
        SELECT i.*, c.name as category_name,
        (SELECT COUNT(*) FROM purchase_order_items WHERE item_id = i.id) as po_count,
        (SELECT COUNT(*) FROM material_request_items WHERE item_id = i.id) as mr_count,
        (SELECT COUNT(*) FROM stock_transactions WHERE item_id = i.id) as st_count
        FROM items i
        LEFT JOIN categories c ON i.category_id = c.id
        WHERE 1=1 $searchQuery
        ORDER BY $orderBy $orderDir
        LIMIT $length OFFSET $start
    ";
    
    $stmt = $pdo->prepare($dataSql);
    $stmt->execute($params);
    $items = $stmt->fetchAll();

    $data = [];
    foreach ($items as $item) {
        $itemCodeHtml = '<a href="#" class="view-item-history fw-bold" data-id="' . $item['id'] . '" title="Lihat Riwayat Barang">' . sanitize($item['item_code']) . '</a>';
        
        $categoryNameHtml = sanitize($item['category_name'] ?? '-');
        
        $descHtml = sanitize($item['description']);
        if ($item['type_specification']) {
            $descHtml .= ' <span class="text-muted ml-1" style="font-size: 11px;">(' . sanitize($item['type_specification']) . ')</span>';
        }
        if ($item['remark']) {
            $descHtml .= ' <span class="text-info ml-1" style="font-size: 11px;"><i class="fas fa-info-circle"></i> ' . sanitize($item['remark']) . '</span>';
        }
        
        $uomHtml = sanitize($item['uom']);
        
        $locationHtml = sanitize($item['warehouse_location'] ?: '-');
        
        if ($item['stock_type'] === 'stock') {
            $stockTypeHtml = '<span class="badge badge-info">Stok Gudang</span>';
        } else {
            $stockTypeHtml = '<span class="badge badge-secondary">Langsung Proyek</span>';
        }
        
        if ($item['stock_type'] === 'stock' && $item['minimum_stock'] > 0) {
            $minStockHtml = number_format($item['minimum_stock'], 0);
        } else {
            $minStockHtml = '-';
        }
        
        $statusHtml = getStatusBadge($item['is_active'] ? 'active' : 'cancelled');
        
        $actionHtml = '<div class="btn-group">';
        $actionHtml .= '<a href="' . APP_URL . '/modules/master/items/edit.php?id=' . $item['id'] . '" class="btn btn-info btn-sm" data-toggle="tooltip" title="Ubah"><i class="fas fa-edit"></i></a>';
        
        if ($item['is_active']) {
            $actionHtml .= '<button type="button" class="btn btn-warning btn-sm action-btn" data-id="' . $item['id'] . '" data-name="' . sanitize($item['description']) . '" data-action="status_toggle" data-status="0" data-toggle="tooltip" title="Nonaktifkan"><i class="fas fa-ban text-white"></i></button>';
        } else {
            $actionHtml .= '<button type="button" class="btn btn-success btn-sm action-btn" data-id="' . $item['id'] . '" data-name="' . sanitize($item['description']) . '" data-action="status_toggle" data-status="1" data-toggle="tooltip" title="Aktifkan"><i class="fas fa-check"></i></button>';
        }
        
        $isUsed = ($item['po_count'] + $item['mr_count'] + $item['st_count']) > 0;
        if (!$isUsed) {
            $actionHtml .= '<button type="button" class="btn btn-danger btn-sm action-btn" data-id="' . $item['id'] . '" data-name="' . sanitize($item['description']) . '" data-action="delete" data-toggle="tooltip" title="Hapus Permanen"><i class="fas fa-trash"></i></button>';
        }
        $actionHtml .= '</div>';
        
        $data[] = [
            'item_code' => $itemCodeHtml,
            'category_name' => $categoryNameHtml,
            'description' => $descHtml,
            'uom' => $uomHtml,
            'warehouse_location' => $locationHtml,
            'stock_type' => $stockTypeHtml,
            'minimum_stock' => $minStockHtml,
            'status' => $statusHtml,
            'actions' => $actionHtml
        ];
    }

    header('Content-Type: application/json');
    echo json_encode([
        'draw' => $draw,
        'recordsTotal' => (int)$totalRecords,
        'recordsFiltered' => (int)$recordsFiltered,
        'data' => $data
    ]);
    exit;
}

$pageTitle = 'Master Barang / Material';
$breadcrumbs = [
    ['label' => 'Master Data', 'url' => '#'],
    ['label' => 'Barang']
];

// Handle Delete/Deactivate
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && in_array($_POST['action'], ['status_toggle', 'delete'])) {
    $id = $_POST['id'] ?? 0;
    
    $stmt = $pdo->prepare("SELECT item_code, description, is_active, current_stock FROM items WHERE id = ?");
    $stmt->execute([$id]);
    $item = $stmt->fetch();
    
    if ($item) {
        if ($_POST['action'] === 'status_toggle') {
            $newStatus = $item['is_active'] ? 0 : 1;
            $update = $pdo->prepare("UPDATE items SET is_active = ? WHERE id = ?");
            if ($update->execute([$newStatus, $id])) {
                $statusText = $newStatus ? 'Aktif' : 'Nonaktif';
                logActivity('update', 'master_items', "Mengubah Status Barang ({$statusText}): {$item['item_code']} - {$item['description']}", 'items', $id);
                setFlash('success', 'Status barang berhasil diubah.');
            } else {
                setFlash('danger', 'Gagal mengubah status barang.');
            }
        } elseif ($_POST['action'] === 'delete') {
            // Check if item is used
            $stmtUse = $pdo->prepare("
                SELECT 
                (SELECT COUNT(*) FROM purchase_order_items WHERE item_id = ?) +
                (SELECT COUNT(*) FROM material_request_items WHERE item_id = ?) +
                (SELECT COUNT(*) FROM stock_transactions WHERE item_id = ?) as total_use
            ");
            $stmtUse->execute([$id, $id, $id]);
            $isUsed = $stmtUse->fetchColumn() > 0;
            
            if ($isUsed) {
                setFlash('danger', 'Gagal menghapus! Barang sudah digunakan dalam transaksi.');
            } else {
                try {
                    $delete = $pdo->prepare("DELETE FROM items WHERE id = ?");
                    if ($delete->execute([$id])) {
                        logActivity('delete', 'master_items', "Menghapus Barang: {$item['item_code']} - {$item['description']}", 'items', $id);
                        setFlash('success', 'Barang berhasil dihapus secara permanen.');
                    } else {
                        setFlash('danger', 'Gagal menghapus barang.');
                    }
                } catch (PDOException $e) {
                    if ($e->getCode() == '23000') {
                        setFlash('danger', 'Gagal menghapus! Barang ini masih terikat dengan data lain di sistem.');
                    } else {
                        setFlash('danger', 'Terjadi kesalahan sistem saat menghapus data.');
                    }
                }
            }
        }
    }
    header('Location: ' . APP_URL . '/modules/master/items/index.php');
    exit;
}

// Fetch Items with Category join (Disabled for Server-side Pagination)
$items = [];

require_once __DIR__ . '/../../../includes/header.php';
?>

<div class="card card-outline card-primary">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title">Daftar Barang / Material</h3>
        <div class="ml-auto">
            <a href="<?= APP_URL ?>/modules/master/items/create.php" class="btn btn-primary btn-sm">
                <i class="fas fa-plus mr-1"></i> Tambah Barang
            </a>
            <button type="button" class="btn btn-success btn-sm ml-1" data-toggle="modal" data-target="#importModal">
                <i class="fas fa-file-excel mr-1"></i> Import Excel
            </button>
            <a href="<?= APP_URL ?>/modules/master/export_excel.php?type=items" class="btn btn-info btn-sm ml-1">
                <i class="fas fa-file-excel mr-1"></i> Export Excel
            </a>
            <button onclick="window.print()" class="btn btn-secondary btn-sm ml-1">
                <i class="fas fa-print mr-1"></i> Cetak
            </button>
        </div>
    </div>
    <div class="card-body">
        <table id="itemsTable" class="table table-bordered table-striped table-hover table-sm w-100" >
            <thead>
                <tr>
                    <th width="10%">Kode Barang</th>
                    <th width="20%">Kategori</th>
                    <th width="30%">Nama / Deskripsi</th>
                    <th width="8%">Satuan</th>
                    <th width="12%">Gudang / Rak</th>
                    <th width="10%">Tipe Stok</th>
                    <th width="10%">Stok Min.</th>
                    <th width="8%">Status</th>
                    <th width="10%" class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
        </table>
    </div>
</div>

<!-- Hidden Form for Actions -->
<form id="actionForm" method="POST" style="display: none;">
    <input type="hidden" name="action" value="status_toggle">
    <input type="hidden" name="id" id="formId">
</form>

<!-- Item History Modal -->
<div class="modal fade" id="itemHistoryModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Riwayat Barang</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" id="itemHistoryModalBody">
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="sr-only">Loading...</span>
                    </div>
                    <p class="mt-2 text-muted">Memuat data riwayat...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<!-- Import Modal -->
<div class="modal fade" id="importModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <form action="<?= APP_URL ?>/modules/master/import_process.php" method="POST" enctype="multipart/form-data">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Import Data Barang / Material</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="type" value="items">
                    <div class="alert alert-info" style="font-size: 13px;">
                        <i class="fas fa-info-circle mr-1"></i> Gunakan template Excel yang telah disediakan. Pastikan kolom Prefix Kategori sesuai dengan master kategori yang ada.
                    </div>
                    <div class="form-group mb-4">
                        <a href="<?= APP_URL ?>/modules/master/download_template.php?type=items" class="btn btn-outline-success btn-sm">
                            <i class="fas fa-download mr-1"></i> Download Template Excel
                        </a>
                    </div>
                    <div class="form-group">
                        <label>Upload File Excel (.xlsx) <span class="text-danger">*</span></label>
                        <input type="file" name="file" class="form-control-file" accept=".xls,.xlsx" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-upload mr-1"></i> Proses Import</button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php
$extraJS = <<<'JS'
<script>
$(document).ready(function() {
    initDataTable('#itemsTable', {
        processing: true,
        serverSide: true,
        ajax: {
            url: 'index.php',
            type: 'GET'
        },
        columns: [
            { data: 'item_code' },
            { data: 'category_name' },
            { data: 'description' },
            { data: 'uom' },
            { data: 'warehouse_location' },
            { data: 'stock_type' },
            { data: 'minimum_stock' },
            { data: 'status' },
            { data: 'actions', className: 'text-center', orderable: false, searchable: false }
        ]
    });

    $(document).on('click', '.action-btn', function() {
        const id = $(this).data('id');
        const name = $(this).data('name');
        const actionType = $(this).data('action');
        const status = $(this).data('status');
        
        let title, msg;
        if (actionType === 'delete') {
            title = 'Hapus Barang Permanen?';
            msg = `Apakah Anda yakin ingin menghapus "${name}" secara permanen? Data ini tidak dapat dikembalikan!`;
        } else {
            title = status == '1' ? 'Aktifkan Barang?' : 'Nonaktifkan Barang?';
            msg = status == '1' ? 
                `Barang "${name}" akan dapat digunakan kembali dalam transaksi.` : 
                `Barang "${name}" tidak akan bisa dipilih untuk transaksi baru.`;
        }
            
        confirmAction(title, msg, function() {
            $('#actionForm input[name="action"]').val(actionType);
            $('#formId').val(id);
            $('#actionForm').submit();
        });
    });

    // Handle Item History Modal
    $(document).on('click', '.view-item-history', function(e) {
        e.preventDefault();
        const id = $(this).data('id');
        
        $('#itemHistoryModalBody').html(`
            <div class="text-center py-4">
                <div class="spinner-border text-primary" role="status">
                    <span class="sr-only">Loading...</span>
                </div>
                <p class="mt-2 text-muted">Memuat data riwayat...</p>
            </div>
        `);
        $('#itemHistoryModal').modal('show');
        
        $.ajax({
            url: APP_URL + '/modules/dashboard/ajax_item_history.php',
            type: 'GET',
            data: { id: id },
            success: function(response) {
                $('#itemHistoryModalBody').html(response);
            },
            error: function() {
                $('#itemHistoryModalBody').html('<div class="alert alert-danger">Terjadi kesalahan saat memuat data riwayat.</div>');
            }
        });
    });
});
</script>
JS;
require_once __DIR__ . '/../../../includes/footer.php';
?>
