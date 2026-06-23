<?php
/**
 * Master Employees - List
 */
require_once __DIR__ . '/../../../includes/auth.php';
requirePermission('master_employees');

$pageTitle = 'Master Karyawan';
$breadcrumbs = [
    ['label' => 'Master Data', 'url' => '#'],
    ['label' => 'Karyawan']
];

// Handle Status Toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_status') {
    $id = $_POST['id'] ?? 0;

    // Get current status
    $stmt = $pdo->prepare("SELECT is_active, user_id FROM employees WHERE id = ?");
    $stmt->execute([$id]);
    $emp = $stmt->fetch();

    if ($emp) {
        $newStatus = $emp['is_active'] ? 0 : 1;
        $pdo->prepare("UPDATE employees SET is_active = ? WHERE id = ?")->execute([$newStatus, $id]);
        $pdo->prepare("UPDATE users SET is_active = ? WHERE id = ?")->execute([$newStatus, $emp['user_id']]);
        
        $statusText = $newStatus ? 'mengaktifkan' : 'menonaktifkan';
        logActivity('update', 'master_employees', ucfirst($statusText) . " karyawan ID {$id}", 'employees', $emp['user_id']);
        
        setFlash('success', 'Status karyawan berhasil diubah.');
    }
    header('Location: ' . APP_URL . '/modules/master/employees/index.php');
    exit;
}

// Fetch Employees
$employees = $pdo->query("
    SELECT e.*, u.full_name, u.username, u.phone, w.jabatan_name, w.daily_wage
    FROM employees e
    JOIN users u ON e.user_id = u.id
    JOIN master_wages w ON e.wage_id = w.id
    ORDER BY u.full_name ASC
")->fetchAll();

require_once __DIR__ . '/../../../includes/header.php';
?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title">Daftar Karyawan Lapangan</h3>
        <div class="ml-auto">
            <a href="<?= APP_URL ?>/modules/master/employees/create.php" class="btn btn-primary btn-sm">
                <i class="fas fa-plus mr-1"></i> Tambah Karyawan
            </a>
            <button type="button" class="btn btn-success btn-sm ml-1" data-toggle="modal" data-target="#importModal">
                <i class="fas fa-file-csv mr-1"></i> Import CSV
            </button>
            <a href="<?= APP_URL ?>/modules/master/export_excel.php?type=employees" class="btn btn-info btn-sm ml-1">
                <i class="fas fa-file-excel mr-1"></i> Export Excel
            </a>
            <button onclick="window.print()" class="btn btn-secondary btn-sm ml-1">
                <i class="fas fa-print mr-1"></i> Cetak
            </button>
        </div>
    </div>
    <div class="card-body">
        <table id="employeesTable" class="table table-bordered table-striped table-hover table-sm w-100" >
            <thead>
                <tr>
                    <th width="5%">No</th>
                    <th width="15%">Kode Karyawan</th>
                    <th width="20%">Nama Lengkap</th>
                    <th width="15%">Username Login</th>
                    <th width="15%">Jabatan</th>
                    <th width="15%">Upah Harian</th>
                    <th width="5%">Status</th>
                    <th width="10%" class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($employees as $i => $e): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><strong><?= sanitize($e['employee_code'] ?? '-') ?></strong></td>
                        <td>
                            <strong class="text-dark"><?= sanitize($e['full_name']) ?></strong><br>
                            <small class="text-muted"><i class="fas fa-phone mr-1"></i>
                                <?= sanitize($e['phone'] ?: '-') ?></small>
                        </td>
                        <td><?= sanitize($e['username']) ?></td>
                        <td><span class="badge badge-info"><?= sanitize($e['jabatan_name']) ?></span></td>
                        <td><?= formatRupiah($e['daily_wage']) ?></td>
                        <td>
                            <?php if ($e['is_active']): ?>
                                <span class="badge badge-success">Aktif</span>
                            <?php else: ?>
                                <span class="badge badge-danger">Nonaktif</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <div class="btn-group">
                                <a href="<?= APP_URL ?>/modules/master/employees/edit.php?id=<?= $e['id'] ?>"
                                    class="btn btn-info btn-sm" data-toggle="tooltip" title="Ubah">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <button type="button"
                                    class="btn btn-<?= $e['is_active'] ? 'warning' : 'success' ?> btn-sm action-btn"
                                    data-id="<?= $e['id'] ?>" data-name="<?= sanitize($e['full_name']) ?>"
                                    data-action="toggle_status" data-toggle="tooltip"
                                    title="<?= $e['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?>">
                                    <i class="fas fa-<?= $e['is_active'] ? 'ban' : 'check' ?>"></i>
                                </button>
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
    <input type="hidden" name="action" id="formAction">
    <input type="hidden" name="id" id="formId">
</form>

<!-- Import CSV Modal -->
<div class="modal fade" id="importModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <form action="<?= APP_URL ?>/modules/master/employees/import_csv.php" method="POST"
            enctype="multipart/form-data">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-file-csv mr-2"></i>Import Data Karyawan dari CSV</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info" style="font-size: 13px;">
                        <i class="fas fa-info-circle mr-1"></i>
                        Import banyak karyawan sekaligus. Akun login & password default <strong>123456</strong> akan
                        dibuat otomatis untuk setiap karyawan.
                    </div>

                    <!-- Format Guide -->
                    <!-- <div class="card bg-light border mb-3">
                        <div class="card-header py-2">
                            <h6 class="mb-0"><i class="fas fa-table mr-1"></i> Format CSV yang Dibutuhkan</h6>
                        </div>
                        <div class="card-body py-2">
                            <table class="table table-bordered table-striped table-hover table-sm w-100" >
                                <thead class="thead-light">
                                    <tr>
                                        <th>Kolom</th>
                                        <th>Header</th>
                                        <th>Keterangan</th>
                                        <th>Wajib</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td class="text-center"><strong>A</strong></td>
                                        <td><code>nama_lengkap</code></td>
                                        <td>Nama lengkap karyawan</td>
                                        <td><span class="badge badge-danger">Ya</span></td>
                                    </tr>
                                    <tr>
                                        <td class="text-center"><strong>B</strong></td>
                                        <td><code>jabatan</code></td>
                                        <td>Nama jabatan (harus cocok dengan Master Upah)</td>
                                        <td><span class="badge badge-danger">Ya</span></td>
                                    </tr>
                                    <tr>
                                        <td class="text-center"><strong>C</strong></td>
                                        <td><code>no_telepon</code></td>
                                        <td>Nomor HP karyawan</td>
                                        <td><span class="badge badge-secondary">Opsional</span></td>
                                    </tr>
                                </tbody>
                            </table>
                            <pre class="bg-dark text-light p-2 rounded mb-2" style="font-size: 12px; overflow-x: auto;">nama_lengkap,jabatan,no_telepon
Ahmad Fauzi,Tukang Las,081234567890
Budi Santoso,Helper,
Candra Wijaya,Mandor,089876543210</pre>
                        </div>
                    </div> -->

                    <!-- Available Jabatan -->
                    <!-- <?php
                    $wagesList = $pdo->query("SELECT jabatan_name, daily_wage FROM master_wages ORDER BY jabatan_name ASC")->fetchAll();
                    ?>
                    <?php if (!empty($wagesList)): ?>
                        <div class="mb-3">
                            <label class="font-weight-bold" style="font-size: 13px;"><i class="fas fa-list-alt mr-1"></i>
                                Jabatan yang tersedia di Master Upah:</label>
                            <div class="d-flex flex-wrap mt-1">
                                <?php foreach ($wagesList as $wl): ?>
                                    <span class="badge badge-info mr-2 mb-1 py-1 px-2" style="font-size: 12px;">
                                        <?= sanitize($wl['jabatan_name']) ?>
                                        <small class="text-light">(Rp
                                            <?= number_format($wl['daily_wage'], 0, ',', '.') ?>)</small>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning mb-3" style="font-size: 13px;">
                            <i class="fas fa-exclamation-triangle mr-1"></i> Belum ada jabatan di Master Upah. <a
                                href="<?= APP_URL ?>/modules/master/wages/create.php">Tambah dulu</a>.
                        </div>
                    <?php endif; ?> -->

                    <!-- Download Template -->
                    <div class="form-group mb-3">
                        <a href="<?= APP_URL ?>/modules/master/employees/download_template.php"
                            class="btn btn-outline-success btn-sm">
                            <i class="fas fa-download mr-1"></i> Download Template CSV
                        </a>
                    </div>

                    <!-- Upload -->
                    <div class="form-group mb-0">
                        <label>Upload File CSV (.csv) <span class="text-danger">*</span></label>
                        <div class="custom-file">
                            <input type="file" class="custom-file-input" id="csvFileInput" name="csv_file" accept=".csv"
                                required>
                            <label class="custom-file-label" for="csvFileInput" data-browse="Pilih File">Belum ada file
                                dipilih...</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success"><i class="fas fa-upload mr-1"></i> Proses
                        Import</button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php
$extraJS = <<<'JS'
<script>
$(document).ready(function() {
    initDataTable('#employeesTable');

    $('.action-btn').on('click', function() {
        const id = $(this).data('id');
        const action = $(this).data('action');
        
        if (action === 'toggle_status') {
            const name = $(this).data('name');
            confirmAction('Ubah Status?', `Anda yakin ingin mengubah status karyawan "${name}"?`, function() {
                $('#formAction').val(action);
                $('#formId').val(id);
                $('#actionForm').submit();
            });
        }
    });
    
    // Custom file input label
    $('.custom-file-input').on('change', function() {
        let fileName = $(this).val().split('\\').pop();
        $(this).next('.custom-file-label').text(fileName || 'Belum ada file dipilih...');
    });
});
</script>
JS;
require_once __DIR__ . '/../../../includes/footer.php';
?>