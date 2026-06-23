<?php
/**
 * Master Projects - List
 */
require_once __DIR__ . '/../../../includes/auth.php';
requirePermission('master_projects');

$pageTitle = 'Master Proyek';
$breadcrumbs = [
    ['label' => 'Master Data', 'url' => '#'],
    ['label' => 'Proyek']
];

// Handle Status Toggle / Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'change_status') {
        $id = $_POST['id'] ?? 0;
        $status = $_POST['status'] ?? '';
        
        $validStatuses = ['active', 'completed', 'cancelled'];
        if (in_array($status, $validStatuses)) {
            $stmt = $pdo->prepare("SELECT name FROM projects WHERE id = ?");
            $stmt->execute([$id]);
            $projectName = $stmt->fetchColumn();
            
            $update = $pdo->prepare("UPDATE projects SET status = ? WHERE id = ?");
            if ($update->execute([$status, $id])) {
                logActivity('update', 'master_projects', "Mengubah Status Proyek ({$status}): {$projectName}", 'projects', $id);
                setFlash('success', 'Status proyek berhasil diubah.');
            } else {
                setFlash('danger', 'Gagal mengubah status proyek.');
            }
        }
        header('Location: ' . APP_URL . '/modules/master/projects/index.php');
        exit;
    } elseif ($_POST['action'] === 'delete') {
        $id = $_POST['id'] ?? 0;
        
        $stmt = $pdo->prepare("SELECT name FROM projects WHERE id = ?");
        $stmt->execute([$id]);
        $projectName = $stmt->fetchColumn();
        
        try {
            $delete = $pdo->prepare("DELETE FROM projects WHERE id = ?");
            if ($delete->execute([$id])) {
                logActivity('delete', 'master_projects', "Menghapus Proyek: {$projectName}", 'projects', $id);
                setFlash('success', 'Proyek berhasil dihapus secara permanen.');
            } else {
                setFlash('danger', 'Gagal menghapus proyek.');
            }
        } catch (PDOException $e) {
            if ($e->getCode() == '23000') {
                setFlash('danger', 'Gagal menghapus! Proyek ini sedang digunakan pada data lainnya (seperti Timesheet, Invoice, atau Quotation).');
            } else {
                setFlash('danger', 'Terjadi kesalahan sistem saat menghapus data.');
            }
        }
        header('Location: ' . APP_URL . '/modules/master/projects/index.php');
        exit;
    }
}

// Fetch Projects
$sql = "
    SELECT p.*, c.company_name as customer_name, c.abbreviation as customer_code, u.full_name as pm_name 
    FROM projects p 
    JOIN customers c ON p.customer_id = c.id 
    LEFT JOIN users u ON p.project_manager_id = u.id 
    ORDER BY p.id DESC
";
$projects = $pdo->query($sql)->fetchAll();

require_once __DIR__ . '/../../../includes/header.php';
?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title">Daftar Proyek</h3>
        <?php if (canAccess('master_projects')): ?>
        <div class="ml-auto">
            <a href="<?= APP_URL ?>/modules/master/projects/create.php" class="btn btn-primary btn-sm">
                <i class="fas fa-plus mr-1"></i> Tambah Proyek
            </a>
            <button type="button" class="btn btn-success btn-sm ml-1" data-toggle="modal" data-target="#importModal">
                <i class="fas fa-file-excel mr-1"></i> Import Excel
            </button>
            <a href="<?= APP_URL ?>/modules/master/export_excel.php?type=projects" class="btn btn-info btn-sm ml-1">
                <i class="fas fa-file-excel mr-1"></i> Export Excel
            </a>
            <button onclick="window.print()" class="btn btn-secondary btn-sm ml-1">
                <i class="fas fa-print mr-1"></i> Cetak
            </button>
        </div>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <table id="projectsTable" class="table table-bordered table-striped table-hover table-sm w-100" >
            <thead>
                <tr>
                    <th width="5%">No</th>
                    <th width="25%">Nama Proyek</th>
                    <th width="20%">Customer</th>
                    <th width="15%">Project Manager</th>
                    <th width="15%">Periode</th>
                    <th width="10%">Status</th>
                    <th width="10%" class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($projects as $i => $p): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td>
                        <strong class="d-block text-dark"><?= sanitize($p['name']) ?></strong>
                        <small class="text-muted"><i class="fas fa-map-marker-alt"></i> <?= sanitize($p['location']) ?></small>
                    </td>
                    <td>
                        <span class="badge badge-primary mr-1"><?= sanitize($p['customer_code']) ?></span>
                        <?= sanitize($p['customer_name']) ?>
                    </td>
                    <td>
                        <?php if ($p['pm_name']): ?>
                            <i class="fas fa-helmet-safety text-warning mr-1"></i> <?= sanitize($p['pm_name']) ?>
                        <?php else: ?>
                            <em class="text-muted">Belum di assign</em>
                        <?php endif; ?>
                    </td>
                    <td>
                        <small>
                            S: <?= $p['start_date'] ? formatDate($p['start_date']) : '-' ?><br>
                            E: <?= $p['end_date'] ? formatDate($p['end_date']) : '-' ?>
                        </small>
                    </td>
                    <td><?= getStatusBadge($p['status']) ?></td>
                    <td class="text-center">
                        <div class="btn-group">
                            <button type="button" class="btn btn-secondary btn-sm dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" title="Aksi">
                                <i class="fas fa-cog"></i> Aksi
                            </button>
                            <div class="dropdown-menu dropdown-menu-right" style="font-size:13px;">
                                <?php if (canAccess('project_dashboard')): ?>
                                <a class="dropdown-item" href="<?= APP_URL ?>/modules/master/projects/dashboard.php?id=<?= $p['id'] ?>">
                                    <i class="fas fa-chart-line text-primary mr-2"></i> Dashboard Proyek
                                </a>
                                <?php endif; ?>
                                <a class="dropdown-item" href="<?= APP_URL ?>/modules/master/projects/edit.php?id=<?= $p['id'] ?>">
                                    <i class="fas fa-edit text-info mr-2"></i> Ubah Proyek
                                </a>
                                
                                <div class="dropdown-divider"></div>
                                <h6 class="dropdown-header">Ubah Status</h6>
                                <a class="dropdown-item status-btn" href="#" data-id="<?= $p['id'] ?>" data-status="active"><i class="fas fa-play text-primary mr-2"></i> Set Aktif</a>
                                <a class="dropdown-item status-btn" href="#" data-id="<?= $p['id'] ?>" data-status="completed"><i class="fas fa-check text-success mr-2"></i> Set Selesai</a>
                                <a class="dropdown-item status-btn" href="#" data-id="<?= $p['id'] ?>" data-status="cancelled"><i class="fas fa-times text-danger mr-2"></i> Set Dibatalkan</a>
                                <?php if (canAccess('master_projects', 'delete')): ?>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item delete-btn text-danger" href="#" data-id="<?= $p['id'] ?>" data-name="<?= sanitize($p['name']) ?>"><i class="fas fa-trash mr-2"></i> Hapus Permanen</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Hidden Form for Actions -->
<form id="actionForm" method="POST" style="display: none;">
    <input type="hidden" name="action" value="change_status">
    <input type="hidden" name="id" id="formId">
    <input type="hidden" name="status" id="formStatus">
</form>

<!-- Import Modal -->
<div class="modal fade" id="importModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <form action="<?= APP_URL ?>/modules/master/import_process.php" method="POST" enctype="multipart/form-data">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Import Data Proyek</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="type" value="projects">
                    <div class="alert alert-info" style="font-size: 13px;">
                        <i class="fas fa-info-circle mr-1"></i> Gunakan template Excel yang telah disediakan untuk menghindari error saat import. Pastikan kolom Singkatan Pelanggan sesuai dengan master pelanggan.
                    </div>
                    <div class="form-group mb-4">
                        <a href="<?= APP_URL ?>/modules/master/download_template.php?type=projects" class="btn btn-outline-success btn-sm">
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
    initDataTable('#projectsTable');

    $('.status-btn').on('click', function(e) {
        e.preventDefault();
        const id = $(this).data('id');
        const status = $(this).data('status');
        
        let title = 'Ubah Status Proyek?';
        let text = 'Anda yakin mengubah status menjadi ' + status + '?';
        
        confirmAction(title, text, function() {
            $('#formId').val(id);
            $('#formStatus').val(status);
            $('#actionForm').submit();
        });
    });

    $('.delete-btn').on('click', function(e) {
        e.preventDefault();
        const id = $(this).data('id');
        const name = $(this).data('name');
        
        let title = 'Hapus Proyek Permanen?';
        let text = 'Apakah Anda yakin ingin menghapus proyek "' + name + '" secara permanen? Data ini tidak dapat dikembalikan!';
        
        confirmAction(title, text, function() {
            $('#actionForm input[name="action"]').val('delete');
            $('#formId').val(id);
            $('#actionForm').submit();
        });
    });
});
</script>
JS;
require_once __DIR__ . '/../../../includes/footer.php';
?>
