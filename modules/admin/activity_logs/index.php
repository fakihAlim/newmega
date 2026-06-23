<?php
/**
 * Admin - Activity Logs Viewer
 * View all user activities in the system
 */
require_once __DIR__ . '/../../../includes/auth.php';
requirePermission('users'); // Only admin-level users can view logs

$pageTitle = 'Log Aktivitas';
$breadcrumbs = [
    ['label' => 'Administrasi', 'url' => '#'],
    ['label' => 'Log Aktivitas']
];

// Check if table exists
try {
    $pdo->query("SELECT 1 FROM activity_logs LIMIT 1");
} catch (Exception $e) {
    setFlash('danger', 'Tabel activity_logs belum dibuat. Silakan jalankan migrasi terlebih dahulu di: <code>/database/migrate_activity_logs.php</code>');
    header('Location: ' . APP_URL . '/modules/dashboard/index.php');
    exit;
}

// Filters
$filterAction = $_GET['action_filter'] ?? '';
$filterModule = $_GET['module_filter'] ?? '';
$filterUser   = $_GET['user_filter'] ?? '';
$filterDateFrom = $_GET['date_from'] ?? '';
$filterDateTo   = $_GET['date_to'] ?? '';
$filterSearch   = $_GET['search'] ?? '';

// Build query
$where = [];
$params = [];

if ($filterAction) {
    $where[] = "al.action = ?";
    $params[] = $filterAction;
}
if ($filterModule) {
    $where[] = "al.module = ?";
    $params[] = $filterModule;
}
if ($filterUser) {
    $where[] = "al.user_id = ?";
    $params[] = $filterUser;
}
if ($filterDateFrom) {
    $where[] = "DATE(al.created_at) >= ?";
    $params[] = $filterDateFrom;
}
if ($filterDateTo) {
    $where[] = "DATE(al.created_at) <= ?";
    $params[] = $filterDateTo;
}
if ($filterSearch) {
    $where[] = "(al.description LIKE ? OR al.user_name LIKE ?)";
    $params[] = "%$filterSearch%";
    $params[] = "%$filterSearch%";
}

$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;
$offset = ($page - 1) * $perPage;

// Count total
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM activity_logs al $whereClause");
$countStmt->execute($params);
$totalRows = $countStmt->fetchColumn();
$totalPages = ceil($totalRows / $perPage);

// Fetch logs
$sql = "SELECT al.* FROM activity_logs al $whereClause ORDER BY al.created_at DESC LIMIT $perPage OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Fetch distinct actions and modules for filter dropdowns
$actions = $pdo->query("SELECT DISTINCT action FROM activity_logs ORDER BY action")->fetchAll(PDO::FETCH_COLUMN);
$modules = $pdo->query("SELECT DISTINCT module FROM activity_logs WHERE module IS NOT NULL ORDER BY module")->fetchAll(PDO::FETCH_COLUMN);
$usersForFilter = $pdo->query("SELECT DISTINCT user_id, user_name FROM activity_logs WHERE user_id IS NOT NULL ORDER BY user_name")->fetchAll();

// Handle export CSV
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="activity_logs_' . date('Y-m-d_His') . '.csv"');
    $output = fopen('php://output', 'w');
    // BOM for Excel UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($output, ['ID', 'Waktu', 'Pengguna', 'Aksi', 'Modul', 'Deskripsi', 'Referensi', 'IP Address']);
    
    $exportSql = "SELECT al.* FROM activity_logs al $whereClause ORDER BY al.created_at DESC";
    $exportStmt = $pdo->prepare($exportSql);
    $exportStmt->execute($params);
    while ($row = $exportStmt->fetch()) {
        fputcsv($output, [
            $row['id'],
            $row['created_at'],
            $row['user_name'],
            $row['action'],
            $row['module'],
            $row['description'],
            ($row['reference_type'] ? $row['reference_type'] . '#' . $row['reference_id'] : ''),
            $row['ip_address'],
        ]);
    }
    fclose($output);
    exit;
}

// Handle clear logs
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'clear_logs') {
    $days = (int)($_POST['days'] ?? 90);
    $cutoff = date('Y-m-d', strtotime("-{$days} days"));
    $delStmt = $pdo->prepare("DELETE FROM activity_logs WHERE DATE(created_at) < ?");
    $delStmt->execute([$cutoff]);
    $deleted = $delStmt->rowCount();
    logActivity('delete', 'activity_logs', "Membersihkan $deleted log aktivitas yang lebih dari $days hari");
    setFlash('success', "Berhasil menghapus $deleted log aktivitas yang lebih dari $days hari.");
    header('Location: ' . APP_URL . '/modules/admin/activity_logs/index.php');
    exit;
}

require_once __DIR__ . '/../../../includes/header.php';

// Action badge mapping
function getActionBadge($action) {
    $map = [
        'login'    => ['badge-primary',   'fas fa-sign-in-alt'],
        'logout'   => ['badge-secondary', 'fas fa-sign-out-alt'],
        'create'   => ['badge-success',   'fas fa-plus-circle'],
        'update'   => ['badge-info',      'fas fa-edit'],
        'delete'   => ['badge-danger',    'fas fa-trash-alt'],
        'approve'  => ['badge-success',   'fas fa-check-circle'],
        'reject'   => ['badge-danger',    'fas fa-times-circle'],
        'truncate' => ['badge-danger',    'fas fa-eraser'],
        'export'   => ['badge-warning',   'fas fa-download'],
        'print'    => ['badge-warning',   'fas fa-print'],
        'upload'   => ['badge-info',      'fas fa-upload'],
    ];
    $badge = $map[$action] ?? ['badge-dark', 'fas fa-circle'];
    return '<span class="badge ' . $badge[0] . '"><i class="' . $badge[1] . ' mr-1"></i>' . strtoupper($action) . '</span>';
}
?>

<!-- Stats Cards -->
<div class="row mb-3">
    <div class="col-lg-3 col-6">
        <div class="small-box bg-info">
            <div class="inner">
                <h3><?= number_format($totalRows) ?></h3>
                <p>Total Log (Filter Aktif)</p>
            </div>
            <div class="icon"><i class="fas fa-history"></i></div>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="small-box bg-success">
            <div class="inner">
                <?php $todayCount = $pdo->query("SELECT COUNT(*) FROM activity_logs WHERE DATE(created_at) = CURDATE()")->fetchColumn(); ?>
                <h3><?= number_format($todayCount) ?></h3>
                <p>Aktivitas Hari Ini</p>
            </div>
            <div class="icon"><i class="fas fa-calendar-day"></i></div>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="small-box bg-warning">
            <div class="inner">
                <?php $uniqueUsers = $pdo->query("SELECT COUNT(DISTINCT user_id) FROM activity_logs WHERE DATE(created_at) = CURDATE()")->fetchColumn(); ?>
                <h3><?= number_format($uniqueUsers) ?></h3>
                <p>Pengguna Aktif Hari Ini</p>
            </div>
            <div class="icon"><i class="fas fa-users"></i></div>
        </div>
    </div>
    <div class="col-lg-3 col-6">
        <div class="small-box bg-danger">
            <div class="inner">
                <?php $totalAll = $pdo->query("SELECT COUNT(*) FROM activity_logs")->fetchColumn(); ?>
                <h3><?= number_format($totalAll) ?></h3>
                <p>Total Seluruh Log</p>
            </div>
            <div class="icon"><i class="fas fa-database"></i></div>
        </div>
    </div>
</div>

<!-- Filter Card -->
<div class="card card-outline card-primary collapsed-card">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-filter mr-2"></i> Filter & Pencarian</h3>
        <div class="card-tools">
            <button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fas fa-plus"></i></button>
        </div>
    </div>
    <div class="card-body">
        <form method="GET" id="filterForm">
            <div class="row">
                <div class="col-md-3 form-group">
                    <label>Aksi</label>
                    <select name="action_filter" class="form-control form-control-sm">
                        <option value="">-- Semua Aksi --</option>
                        <?php foreach ($actions as $a): ?>
                            <option value="<?= sanitize($a) ?>" <?= $filterAction === $a ? 'selected' : '' ?>><?= strtoupper(sanitize($a)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 form-group">
                    <label>Modul</label>
                    <select name="module_filter" class="form-control form-control-sm">
                        <option value="">-- Semua Modul --</option>
                        <?php foreach ($modules as $m): ?>
                            <option value="<?= sanitize($m) ?>" <?= $filterModule === $m ? 'selected' : '' ?>><?= sanitize($m) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 form-group">
                    <label>Pengguna</label>
                    <select name="user_filter" class="form-control form-control-sm">
                        <option value="">-- Semua Pengguna --</option>
                        <?php foreach ($usersForFilter as $uf): ?>
                            <option value="<?= $uf['user_id'] ?>" <?= $filterUser == $uf['user_id'] ? 'selected' : '' ?>><?= sanitize($uf['user_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 form-group">
                    <label>Pencarian</label>
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Cari deskripsi..." value="<?= sanitize($filterSearch) ?>">
                </div>
            </div>
            <div class="row">
                <div class="col-md-3 form-group">
                    <label>Dari Tanggal</label>
                    <input type="date" name="date_from" class="form-control form-control-sm" value="<?= sanitize($filterDateFrom) ?>">
                </div>
                <div class="col-md-3 form-group">
                    <label>Sampai Tanggal</label>
                    <input type="date" name="date_to" class="form-control form-control-sm" value="<?= sanitize($filterDateTo) ?>">
                </div>
                <div class="col-md-6 form-group d-flex align-items-end">
                    <button type="submit" class="btn btn-primary btn-sm mr-2"><i class="fas fa-search mr-1"></i> Filter</button>
                    <a href="<?= APP_URL ?>/modules/admin/activity_logs/index.php" class="btn btn-secondary btn-sm mr-2"><i class="fas fa-undo mr-1"></i> Reset</a>
                    <?php
                    $exportParams = $_GET;
                    $exportParams['export'] = 'csv';
                    ?>
                    <a href="?<?= http_build_query($exportParams) ?>" class="btn btn-success btn-sm"><i class="fas fa-file-csv mr-1"></i> Export CSV</a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Logs Table -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title"><i class="fas fa-list-alt mr-2"></i> Riwayat Aktivitas</h3>
        <div class="ml-auto">
            <button type="button" class="btn btn-outline-danger btn-sm" data-toggle="modal" data-target="#clearLogsModal">
                <i class="fas fa-broom mr-1"></i> Bersihkan Log Lama
            </button>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-striped table-sm mb-0">
                <thead class="thead-light">
                    <tr>
                        <th style="width:160px;">Waktu</th>
                        <th style="width:140px;">Pengguna</th>
                        <th style="width:100px;">Aksi</th>
                        <th style="width:130px;">Modul</th>
                        <th>Deskripsi</th>
                        <th style="width:110px;">IP Address</th>
                        <th style="width:50px;" class="text-center">Detail</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">
                                <i class="fas fa-inbox fa-3x mb-3 d-block text-gray"></i>
                                Tidak ada data log aktivitas yang ditemukan.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td>
                                    <small class="text-muted">
                                        <i class="fas fa-clock mr-1"></i>
                                        <?= date('d M Y', strtotime($log['created_at'])) ?>
                                        <br>
                                        <span class="ml-3"><?= date('H:i:s', strtotime($log['created_at'])) ?></span>
                                    </small>
                                </td>
                                <td>
                                    <strong class="d-block"><?= sanitize($log['user_name'] ?? 'System') ?></strong>
                                </td>
                                <td><?= getActionBadge($log['action']) ?></td>
                                <td>
                                    <?php if ($log['module']): ?>
                                        <code class="text-primary"><?= sanitize($log['module']) ?></code>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="d-inline-block text-truncate" style="max-width:350px;" title="<?= sanitize($log['description'] ?? '') ?>">
                                        <?= sanitize($log['description'] ?? '-') ?>
                                    </span>
                                    <?php if ($log['reference_type']): ?>
                                        <br><small class="text-muted">Ref: <?= sanitize($log['reference_type']) ?>#<?= $log['reference_id'] ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><small class="text-monospace"><?= sanitize($log['ip_address'] ?? '-') ?></small></td>
                                <td class="text-center">
                                    <?php if ($log['old_data'] || $log['new_data']): ?>
                                        <button type="button" class="btn btn-xs btn-outline-info btn-detail"
                                            data-id="<?= $log['id'] ?>"
                                            data-old="<?= htmlspecialchars($log['old_data'] ?? '{}') ?>"
                                            data-new="<?= htmlspecialchars($log['new_data'] ?? '{}') ?>"
                                            data-useragent="<?= sanitize($log['user_agent'] ?? '-') ?>"
                                            title="Lihat Detail">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if ($totalPages > 1): ?>
    <div class="card-footer clearfix">
        <div class="float-left text-muted" style="line-height:38px;">
            Menampilkan <?= number_format(($offset + 1)) ?> - <?= number_format(min($offset + $perPage, $totalRows)) ?> dari <?= number_format($totalRows) ?> log
        </div>
        <ul class="pagination pagination-sm m-0 float-right">
            <?php
            $queryParams = $_GET;
            unset($queryParams['page']);
            $baseUrl = '?' . http_build_query($queryParams);
            ?>
            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= $baseUrl ?>&page=<?= $page - 1 ?>">&laquo;</a>
            </li>
            <?php
            $startPage = max(1, $page - 2);
            $endPage = min($totalPages, $page + 2);
            if ($startPage > 1) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
            for ($p = $startPage; $p <= $endPage; $p++):
            ?>
                <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                    <a class="page-link" href="<?= $baseUrl ?>&page=<?= $p ?>"><?= $p ?></a>
                </li>
            <?php endfor;
            if ($endPage < $totalPages) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
            ?>
            <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= $baseUrl ?>&page=<?= $page + 1 ?>">&raquo;</a>
            </li>
        </ul>
    </div>
    <?php endif; ?>
</div>

<!-- Clear Logs Modal -->
<div class="modal fade" id="clearLogsModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <form method="POST">
            <input type="hidden" name="action" value="clear_logs">
            <div class="modal-content">
                <div class="modal-header bg-danger">
                    <h5 class="modal-title text-white"><i class="fas fa-broom mr-2"></i> Bersihkan Log Lama</h5>
                    <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <p>Hapus log aktivitas yang lebih lama dari:</p>
                    <div class="form-group">
                        <select name="days" class="form-control">
                            <option value="30">30 hari</option>
                            <option value="60">60 hari</option>
                            <option value="90" selected>90 hari</option>
                            <option value="180">180 hari</option>
                            <option value="365">1 tahun</option>
                        </select>
                    </div>
                    <div class="alert alert-warning mb-0">
                        <i class="fas fa-exclamation-triangle mr-1"></i> Tindakan ini tidak dapat dibatalkan.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger"><i class="fas fa-trash mr-1"></i> Hapus Log</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Detail Modal -->
<div class="modal fade" id="detailModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-info">
                <h5 class="modal-title text-white"><i class="fas fa-info-circle mr-2"></i> Detail Log Aktivitas</h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="text-danger font-weight-bold"><i class="fas fa-minus-circle mr-1"></i> Data Lama (Sebelum)</h6>
                        <pre id="oldDataPre" class="bg-light p-3 rounded" style="max-height:300px;overflow-y:auto;font-size:12px;">-</pre>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-success font-weight-bold"><i class="fas fa-plus-circle mr-1"></i> Data Baru (Sesudah)</h6>
                        <pre id="newDataPre" class="bg-light p-3 rounded" style="max-height:300px;overflow-y:auto;font-size:12px;">-</pre>
                    </div>
                </div>
                <hr>
                <h6 class="text-muted"><i class="fas fa-globe mr-1"></i> User Agent</h6>
                <p id="userAgentText" class="small text-muted bg-light p-2 rounded mb-0" style="word-break:break-all;">-</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<?php
$extraJS = <<<'JS'
<script>
$(document).ready(function() {
    // Auto-open filter card if any filter is active
    const urlParams = new URLSearchParams(window.location.search);
    const filterKeys = ['action_filter', 'module_filter', 'user_filter', 'date_from', 'date_to', 'search'];
    const hasFilter = filterKeys.some(key => urlParams.get(key));
    if (hasFilter) {
        $('.collapsed-card').removeClass('collapsed-card');
        $('.collapsed-card .btn-tool i').removeClass('fa-plus').addClass('fa-minus');
    }

    // Detail modal
    $('.btn-detail').on('click', function() {
        const oldData = $(this).data('old');
        const newData = $(this).data('new');
        const userAgent = $(this).data('useragent');

        try {
            const oldObj = typeof oldData === 'string' ? JSON.parse(oldData) : oldData;
            $('#oldDataPre').text(JSON.stringify(oldObj, null, 2));
        } catch(e) {
            $('#oldDataPre').text(oldData || '-');
        }

        try {
            const newObj = typeof newData === 'string' ? JSON.parse(newData) : newData;
            $('#newDataPre').text(JSON.stringify(newObj, null, 2));
        } catch(e) {
            $('#newDataPre').text(newData || '-');
        }

        $('#userAgentText').text(userAgent || '-');
        $('#detailModal').modal('show');
    });
});
</script>
JS;
require_once __DIR__ . '/../../../includes/footer.php';
?>
