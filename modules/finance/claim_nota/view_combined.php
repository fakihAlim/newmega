<?php
/**
 * Finance - Claim Nota Combined Print Preview
 */
require_once __DIR__ . '/../../../includes/auth.php';
requirePermission('claim_nota');

$user = getCurrentUser();

// Get filters from GET request
$filterStart = $_GET['start_date'] ?? '';
$filterEnd = $_GET['end_date'] ?? '';
$filterCompany = $_GET['company_id'] ?? '';
$filterEmployee = $_GET['employee_name'] ?? '';
$filterStatus = $_GET['status'] ?? '';

// Build conditions
$conditions = [];
$params = [];

if ($filterStart) {
    $conditions[] = "c.claim_date >= ?";
    $params[] = $filterStart;
}
if ($filterEnd) {
    $conditions[] = "c.claim_date <= ?";
    $params[] = $filterEnd;
}
if ($filterCompany) {
    $conditions[] = "c.company_id = ?";
    $params[] = $filterCompany;
}
if ($filterEmployee) {
    $conditions[] = "c.employee_name LIKE ?";
    $params[] = "%$filterEmployee%";
}
if ($filterStatus) {
    $conditions[] = "c.status = ?";
    $params[] = $filterStatus;
}

$whereClause = "";
if (!empty($conditions)) {
    $whereClause = "WHERE " . implode(" AND ", $conditions);
}

// Fetch all matching items grouped by group_name
$sql = "
    SELECT ci.*, c.claim_number, c.employee_name, comp.name as company_name 
    FROM nota_claim_items ci
    JOIN nota_claims c ON ci.claim_id = c.id
    LEFT JOIN companies comp ON c.company_id = comp.id
    $whereClause
    ORDER BY ci.item_date ASC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$claimItems = $stmt->fetchAll();

// Group items by group_name
$groupedItems = [];
$totalSum = 0;
foreach ($claimItems as $item) {
    $group = !empty($item['group_name']) ? trim($item['group_name']) : 'Money change';
    $groupedItems[$group][] = $item;
    $totalSum += $item['amount'];
}

// Fetch filter text details for header display
$selectedCompanyName = 'Semua Perusahaan';
if ($filterCompany) {
    $compStmt = $pdo->prepare("SELECT name FROM companies WHERE id = ?");
    $compStmt->execute([$filterCompany]);
    $selectedCompanyName = $compStmt->fetchColumn() ?: 'Semua Perusahaan';
}

$pageTitle = 'Laporan Cetak Gabungan Claim Nota';
$breadcrumbs = [
    ['label' => 'Finance', 'url' => '#'],
    ['label' => 'Claim Nota', 'url' => APP_URL . '/modules/finance/claim_nota/index.php'],
    ['label' => 'Cetak Gabungan']
];

require_once __DIR__ . '/../../../includes/header.php';
?>

<div class="row">
    <div class="col-md-12">
        <div class="card card-outline card-info">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title text-info"><i class="fas fa-file-alt mr-2"></i> Laporan Gabungan Claim Nota</h3>
                <div class="ml-auto">
                    <button class="btn btn-default btn-sm mr-1" onclick="window.print()"><i class="fas fa-print mr-1"></i> Cetak</button>
                    <!-- Redirect back to index retaining filters -->
                    <a href="index.php?<?= http_build_query($_GET) ?>" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left mr-1"></i> Kembali</a>
                </div>
            </div>

            <div class="card-body printable-area">
                <h3 class="text-center font-weight-bold mb-4 text-uppercase">Nota Developer dan Kantor (Gabungan)</h3>

                <table class="table table-sm table-borderless table-header-info mb-4">
                    <tr>
                        <td width="15%" class="font-weight-bold">Karyawan Filter</td>
                        <td width="45%">: <?= $filterEmployee ? sanitize($filterEmployee) : 'Semua Karyawan' ?></td>
                        <td width="15%" class="font-weight-bold">Periode Laporan</td>
                        <td width="25%">: 
                            <?php 
                            if ($filterStart && $filterEnd) {
                                echo date('d/m/Y', strtotime($filterStart)) . ' s/d ' . date('d/m/Y', strtotime($filterEnd));
                            } elseif ($filterStart) {
                                echo 'Sejak ' . date('d/m/Y', strtotime($filterStart));
                            } elseif ($filterEnd) {
                                echo 'Sampai ' . date('d/m/Y', strtotime($filterEnd));
                            } else {
                                echo 'Semua Tanggal';
                            }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td class="font-weight-bold">Perusahaan</td>
                        <td>: <?= sanitize($selectedCompanyName) ?></td>
                        <td class="font-weight-bold">Status Klaim</td>
                        <td>: 
                            <?php 
                            $statusLabels = [
                                'pending' => 'Pending Approval',
                                'approved' => 'Approved',
                                'paid' => 'Lunas (Paid)',
                                'rejected' => 'Rejected'
                            ];
                            echo $filterStatus ? ($statusLabels[$filterStatus] ?? ucfirst($filterStatus)) : 'Semua Status';
                            ?>
                        </td>
                    </tr>
                </table>

                <?php if (empty($groupedItems)): ?>
                    <div class="alert alert-warning text-center">
                        <i class="fas fa-exclamation-triangle mr-2"></i> Tidak ada data klaim nota yang cocok dengan filter yang dipilih.
                    </div>
                <?php else: ?>
                    <!-- Grouped Items Tables -->
                    <?php foreach ($groupedItems as $groupName => $items): ?>
                        <div class="group-section mb-4">
                            <h5 class="font-weight-bold text-dark mb-1" style="font-size:15px; border-bottom: 2px solid #dee2e6; padding-bottom: 4px;">
                                <?= sanitize($groupName) ?>
                            </h5>
                            
                            <table class="table table-bordered table-sm excel-table mb-2">
                                <thead>
                                    <tr class="bg-light">
                                        <th width="12%" class="text-center">Tanggal</th>
                                        <th width="15%" class="text-center">No. Klaim</th>
                                        <th width="15%">Toko</th>
                                        <th width="25%">item (Deskripsi)</th>
                                        <th width="8%" class="text-center">Pcs</th>
                                        <th width="12%" class="text-right">Harga</th>
                                        <th width="13%" class="text-right">Jumlah</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $groupSubtotal = 0;
                                    foreach ($items as $item): 
                                        $groupSubtotal += $item['amount'];
                                    ?>
                                        <tr>
                                            <td class="text-center"><?= date('d/m/Y', strtotime($item['item_date'])) ?></td>
                                            <td class="text-center text-xs"><strong><?= sanitize($item['claim_number']) ?></strong></td>
                                            <td><?= sanitize($item['store_name']) ?: '-' ?></td>
                                            <td>
                                                <?= sanitize($item['item_name']) ?> <span class="text-muted text-xs">(Oleh: <?= sanitize($item['employee_name']) ?>)</span>
                                                <?php if ($item['receipt_photo']): ?>
                                                    <span class="d-print-none ml-2">
                                                        <a href="<?= APP_URL ?>/assets/uploads/receipts/<?= $item['receipt_photo'] ?>" target="_blank" class="text-info text-xs" title="Lihat Foto Nota">
                                                            <i class="fas fa-image"></i>
                                                        </a>
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center"><?= number_format($item['qty'], 0, '', '') ?></td>
                                            <td class="text-right"><?= formatRupiah($item['price'], '') ?></td>
                                            <td class="text-right"><?= formatRupiah($item['amount'], '') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <!-- Subtotal row for this group -->
                                    <tr class="font-weight-bold bg-light">
                                        <td colspan="5" class="text-right">Total</td>
                                        <td class="text-right"><?= formatRupiah($groupSubtotal, '') ?></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    <?php endforeach; ?>

                    <div class="row mt-4">
                        <div class="col-md-6 offset-md-6 text-right">
                            <div class="border p-2 bg-light d-inline-block text-right" style="min-width: 250px; border-radius: 4px;">
                                <span class="font-weight-normal text-muted">Grand Total Laporan:</span><br>
                                <span class="text-xl text-bold text-danger"><?= formatRupiah($totalSum) ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Signatures -->
                    <table class="table table-borderless text-center mt-5 signature-table d-print-table" style="width: 100%;">
                        <tr>
                            <td width="33%">Dibuat Oleh,</td>
                            <td width="34%">Disetujui Oleh,</td>
                            <td width="33%">Dibayar Oleh,</td>
                        </tr>
                        <tr>
                            <td style="height: 70px;"></td>
                            <td></td>
                            <td></td>
                        </tr>
                        <tr class="font-weight-bold">
                            <td>( ____________________ )</td>
                            <td>( ____________________ )</td>
                            <td>( ____________________ )</td>
                        </tr>
                        <tr class="text-muted" style="font-size: 11px;">
                            <td>Karyawan / Penerima</td>
                            <td>Finance / Admin</td>
                            <td>Kasir / Pembayar</td>
                        </tr>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
@media print {
    @page {
        size: A4 portrait;
        margin: 10mm;
    }
    body { background-color: white !important; font-family: Arial, sans-serif !important; }
    .main-sidebar, .main-header, .d-print-none, .card-footer, .brand-link { display: none !important; }
    .content-wrapper { margin-left: 0 !important; padding: 0 !important; background: white; }
    .card { border: none !important; box-shadow: none !important; margin: 0 !important; padding: 0 !important; }
    .card-header { padding-top: 0 !important; display: none !important; }
    .printable-area { width: 100% !important; padding: 0 !important; margin: 0 !important; }
    
    .excel-table { border-collapse: collapse; width: 100% !important; margin-bottom: 15px !important; }
    .excel-table th, .excel-table td {
        border: 1px solid #000 !important;
        padding: 4px 6px !important;
        background-color: transparent !important;
        color: #000 !important;
        -webkit-print-color-adjust: exact;
    }
    .signature-table td, .signature-table th {
        border: none !important;
    }
    .text-primary, .text-danger { color: #000 !important; }
}

.table-header-info { margin-bottom: 10px; }
.table-header-info td { padding: 0.3rem; vertical-align: top; }
.excel-table { border-collapse: collapse; }
.excel-table th, .excel-table td { border: 1px solid #000 !important; padding: 0.4rem; vertical-align: middle; }
.excel-table thead th { background-color: #f8f9fa; border-bottom: 2px solid #000 !important; }
.signature-table td { padding-top: 15px; }
</style>

<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>
