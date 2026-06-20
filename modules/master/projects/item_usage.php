<?php
/**
 * Project Item Usage - Penggunaan Barang Proyek
 */
require_once __DIR__ . '/../../../includes/auth.php';
requirePermission('project_dashboard');

$id = $_GET['id'] ?? 0;

$stmtProject = $pdo->prepare("SELECT id, name FROM projects WHERE id = ?");
$stmtProject->execute([$id]);
$project = $stmtProject->fetch();

if (!$project) {
    setFlash('danger', 'Proyek tidak ditemukan.');
    header('Location: index.php');
    exit;
}

// 1. Get items from Goods Receivings (Direct Purchase)
$stmtRcv = $pdo->prepare("
    SELECT 
        poi.item_id,
        poi.item_name as description,
        i.item_code,
        gri.qty_received as qty,
        poi.unit_price as price,
        gr.receive_date as doc_date,
        gr.surat_jalan_no as doc_no,
        'PO / Direct' as source_type
    FROM goods_receiving_items gri
    JOIN goods_receivings gr ON gri.receiving_id = gr.id
    JOIN purchase_order_items poi ON gri.po_item_id = poi.id
    JOIN purchase_orders po ON poi.po_id = po.id
    JOIN po_mr_links pml ON pml.po_id = po.id
    JOIN material_requests mr ON pml.mr_id = mr.id
    LEFT JOIN items i ON poi.item_id = i.id
    WHERE mr.project_id = ? AND gri.qty_received > 0
");
$stmtRcv->execute([$id]);
$rcvItems = $stmtRcv->fetchAll();

// 2. Get items from Warehouse Transfers
$stmtTrf = $pdo->prepare("
    SELECT 
        wti.item_id,
        i.description,
        i.item_code,
        wti.qty as qty,
        wt.transfer_date as doc_date,
        wt.transfer_number as doc_no,
        'Warehouse Transfer' as source_type
    FROM warehouse_transfer_items wti
    JOIN warehouse_transfers wt ON wti.transfer_id = wt.id
    JOIN items i ON wti.item_id = i.id
    WHERE wt.to_project_id = ? AND wt.status = 'completed' AND wti.qty > 0
");
$stmtTrf->execute([$id]);
$trfItems = $stmtTrf->fetchAll();

// 3. Process & Combine
$history = [];
$summary = [];

// Helper to get latest PO price for Warehouse Transfers
$latestPrices = [];
$getLatestPrice = function($itemId) use ($pdo, &$latestPrices) {
    if (!$itemId) return 0;
    if (isset($latestPrices[$itemId])) return $latestPrices[$itemId];
    
    $stmt = $pdo->prepare("
        SELECT poi.unit_price
        FROM purchase_order_items poi
        JOIN purchase_orders po ON poi.po_id = po.id
        WHERE poi.item_id = ? AND po.status NOT IN ('draft', 'cancelled', 'rejected')
        ORDER BY po.po_date DESC, po.id DESC
        LIMIT 1
    ");
    $stmt->execute([$itemId]);
    $price = $stmt->fetchColumn();
    $latestPrices[$itemId] = $price ? (float)$price : 0;
    return $latestPrices[$itemId];
};

// Process Receiving Items
foreach ($rcvItems as $row) {
    $row['total_value'] = $row['qty'] * $row['price'];
    $iId = $row['item_id'] ?: 'text_' . md5($row['description']);
    $row['group_id'] = $iId;
    $history[] = $row;
    
    if (!isset($summary[$iId])) {
        $summary[$iId] = [
            'id' => $iId,
            'item_code' => $row['item_code'],
            'description' => $row['description'],
            'total_qty' => 0,
            'total_value' => 0,
            'history' => []
        ];
    }
    $summary[$iId]['total_qty'] += $row['qty'];
    $summary[$iId]['total_value'] += $row['total_value'];
    $summary[$iId]['history'][] = $row;
}

// Process Warehouse Transfer Items
foreach ($trfItems as $row) {
    $row['price'] = $getLatestPrice($row['item_id']);
    $row['total_value'] = $row['qty'] * $row['price'];
    $iId = $row['item_id'];
    $row['group_id'] = $iId;
    $history[] = $row;
    
    if (!isset($summary[$iId])) {
        $summary[$iId] = [
            'id' => $iId,
            'item_code' => $row['item_code'],
            'description' => $row['description'],
            'total_qty' => 0,
            'total_value' => 0,
            'history' => []
        ];
    }
    $summary[$iId]['total_qty'] += $row['qty'];
    $summary[$iId]['total_value'] += $row['total_value'];
    $summary[$iId]['history'][] = $row;
}

// Sort history by date descending
usort($history, function($a, $b) {
    return strtotime($b['doc_date']) <=> strtotime($a['doc_date']);
});

// Sort summary alphabetically by description
usort($summary, function($a, $b) {
    return strcasecmp($a['description'], $b['description']);
});

// Calculate Grand Totals
$grandTotalValue = array_sum(array_column($summary, 'total_value'));

$pageTitle = 'Rincian Penggunaan Barang: ' . sanitize($project['name']);
$breadcrumbs = [
    ['label' => 'Proyek', 'url' => 'index.php'],
    ['label' => 'Dashboard', 'url' => 'dashboard.php?id=' . $id],
    ['label' => 'Penggunaan Barang']
];

require_once __DIR__ . '/../../../includes/header.php';
?>

<div class="card card-outline card-info">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fas fa-boxes mr-1"></i> Rekapitulasi Barang Digunakan
        </h3>
        <div class="card-tools">
            <h5 class="mb-0 text-info font-weight-bold">
                Total Nilai: <?= formatRupiah($grandTotalValue) ?>
            </h5>
        </div>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-striped table-hover datatable" >
                <thead class="bg-light">
                    <tr>
                        <th width="5%">No</th>
                        <th width="15%">Kode Barang</th>
                        <th width="35%">Nama Barang</th>
                        <th width="15%" class="text-center">Total Qty</th>
                        <th width="20%" class="text-right">Estimasi Total Nilai</th>
                        <th width="10%" class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $no = 1;
                    foreach ($summary as $item): 
                    ?>
                    <tr>
                        <td><?= $no++ ?></td>
                        <td><?= sanitize($item['item_code']) ?: '-' ?></td>
                        <td><?= sanitize($item['description']) ?></td>
                        <td class="text-center font-weight-bold"><?= number_format($item['total_qty'], 2) ?></td>
                        <td class="text-right text-success font-weight-bold"><?= formatRupiah($item['total_value']) ?></td>
                        <td class="text-center">
                            <button type="button" class="btn btn-xs btn-primary btn-history" 
                                data-id="<?= sanitize($item['id']) ?>" 
                                data-name="<?= sanitize($item['description']) ?>">
                                <i class="fas fa-history mr-1"></i> Riwayat
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="mt-3">
            <p class="text-muted" style="font-size: 12px;">
                <i class="fas fa-info-circle"></i> <strong>Catatan:</strong><br>
                - Barang dari <strong>PO / Direct</strong> menggunakan harga aktual dari Purchase Order.<br>
                - Barang dari <strong>Warehouse Transfer</strong> menggunakan estimasi harga berdasarkan riwayat PO terbaru untuk barang tersebut.
            </p>
        </div>
    </div>
    <div class="card-footer">
        <a href="dashboard.php?id=<?= $id ?>" class="btn btn-secondary"><i class="fas fa-arrow-left mr-1"></i> Kembali ke Dashboard</a>
    </div>
</div>

<!-- History Modal -->
<div class="modal fade" id="historyModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary">
                <h5 class="modal-title"><i class="fas fa-history mr-1"></i> Riwayat Barang: <span id="modalItemName"></span></h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-0">
                <table class="table table-sm table-striped m-0"  id="historyTable">
                    <thead class="bg-light">
                        <tr>
                            <th>Tanggal</th>
                            <th>No. Dokumen</th>
                            <th>Sumber</th>
                            <th class="text-center">Qty</th>
                            <th class="text-right">Harga Satuan</th>
                            <th class="text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Populated by JS -->
                    </tbody>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<!-- Data for JS -->
<script>
    const itemHistory = <?= json_encode($summary) ?>;
    
    // Format Rupiah helper in JS
    function formatRupiahJS(angka) {
        return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(angka);
    }
    
    function formatDateJS(dateString) {
        if (!dateString) return '-';
        const d = new Date(dateString);
        return d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
    }
</script>

<?php ob_start(); ?>
<script>
$(document).ready(function() {
    // Initialize DataTable
    if ($.fn.DataTable) {
        $('.datatable').DataTable({
            "language": { "url": "//cdn.datatables.net/plug-ins/1.10.24/i18n/Indonesian.json" },
            "pageLength": 25
        });
    }

    // Handle History Button Click using event delegation for DataTables
    $(document).on('click', '.btn-history', function() {
        const id = $(this).data('id');
        const name = $(this).data('name');
        
        $('#modalItemName').text(name);
        
        const tbody = $('#historyTable tbody');
        tbody.empty();
        
        // Find the item in the array
        const itemData = itemHistory.find(item => item.id == id);
        
        if (itemData && itemData.history && itemData.history.length > 0) {
            const history = itemData.history;
            
            // Sort by date descending
            history.sort((a, b) => new Date(b.doc_date) - new Date(a.doc_date));
            
            history.forEach(h => {
                let sourceBadge = '';
                if (h.source_type.includes('PO')) {
                    sourceBadge = '<span class="badge badge-success">PO / Direct</span>';
                } else {
                    sourceBadge = '<span class="badge badge-info">Gudang</span>';
                }
                
                const tr = `
                    <tr>
                        <td>${formatDateJS(h.doc_date)}</td>
                        <td><strong>${h.doc_no}</strong></td>
                        <td>${sourceBadge}</td>
                        <td class="text-center font-weight-bold">${parseFloat(h.qty).toLocaleString('id-ID')}</td>
                        <td class="text-right text-muted">${formatRupiahJS(h.price)}</td>
                        <td class="text-right font-weight-bold">${formatRupiahJS(h.total_value)}</td>
                    </tr>
                `;
                tbody.append(tr);
            });
        } else {
            tbody.append('<tr><td colspan="6" class="text-center">Tidak ada riwayat</td></tr>');
        }
        
        $('#historyModal').modal('show');
    });
});
</script>
<?php 
$extraJS = ob_get_clean();
require_once __DIR__ . '/../../../includes/footer.php'; 
?>
