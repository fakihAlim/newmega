<?php
/**
 * Master Projects - Edit
 */
require_once __DIR__ . '/../../../includes/auth.php';
requirePermission('master_projects');

$id = $_GET['id'] ?? 0;
$stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ?");
$stmt->execute([$id]);
$project = $stmt->fetch();

if (!$project) {
    setFlash('danger', 'Proyek tidak ditemukan.');
    header('Location: ' . APP_URL . '/modules/master/projects/index.php');
    exit;
}

$pageTitle = 'Edit Proyek: ' . sanitize($project['name']);
$breadcrumbs = [
    ['label' => 'Master Data', 'url' => '#'],
    ['label' => 'Proyek', 'url' => APP_URL . '/modules/master/projects/index.php'],
    ['label' => 'Edit']
];

// Fetch lists
$customers = $pdo->query("SELECT id, company_name, abbreviation FROM customers WHERE is_active = 1 ORDER BY company_name ASC")->fetchAll();
$pms = $pdo->query("SELECT DISTINCT u.id, u.full_name, u.username FROM users u JOIN user_roles ur ON u.id = ur.user_id JOIN roles r ON ur.role_id = r.id WHERE r.role_key = 'project_manager' AND u.is_active = 1 ORDER BY u.full_name ASC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name         = trim($_POST['name'] ?? '');
    $abbreviation = trim($_POST['abbreviation'] ?? '');
    $customerId   = $_POST['customer_id'] ?? null;
    $managerId    = $_POST['manager_id'] ?? null;
    $location     = trim($_POST['location'] ?? '');
    $startDate  = trim($_POST['start_date'] ?? '');
    $endDate    = trim($_POST['end_date'] ?? '');
    $budget     = parseRupiah($_POST['budget'] ?? '0');
    $status     = $_POST['status'] ?? 'active';
    
    if (empty($startDate)) $startDate = null;
    if (empty($endDate)) $endDate = null;
    if (empty($managerId)) $managerId = null;
    
    // Validation
    $errors = [];
    if (empty($name)) $errors[] = "Nama proyek wajib diisi.";
    if (empty($abbreviation)) $errors[] = "Singkatan proyek wajib diisi.";
    if (empty($customerId)) $errors[] = "Customer wajib dipilih.";
    if (empty($location)) $errors[] = "Lokasi proyek wajib diisi.";
    
    if (empty($errors)) {
        $stmt = $pdo->prepare("
            UPDATE projects 
            SET name=?, abbreviation=?, customer_id=?, project_manager_id=?, location=?, start_date=?, end_date=?, budget=?, status=? 
            WHERE id=?
        ");
        
        if ($stmt->execute([$name, $abbreviation, $customerId, $managerId, $location, $startDate, $endDate, $budget, $status, $id])) {
            setFlash('success', "Data proyek berhasil diperbarui.");
            header('Location: ' . APP_URL . '/modules/master/projects/index.php');
            exit;
        } else {
            setFlash('danger', 'Terjadi kesalahan sistem saat menyimpan data.');
        }
    }
    
    if (!empty($errors)) {
        setFlash('danger', implode('<br>', $errors));
        // Reset state for form display
        $project['name'] = $name;
        $project['abbreviation'] = $abbreviation;
        $project['customer_id'] = $customerId;
        $project['project_manager_id'] = $managerId;
        $project['location'] = $location;
        $project['start_date'] = $startDate;
        $project['end_date'] = $endDate;
        $project['budget'] = $budget;
        $project['status'] = $status;
    }
}

require_once __DIR__ . '/../../../includes/header.php';
?>

<div class="row">
    <div class="col-md-8 mx-auto">
        <div class="card card-outline card-primary">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-edit mr-2"></i>Form Edit Proyek</h3>
                <a href="<?= APP_URL ?>/modules/master/projects/index.php" class="btn btn-secondary btn-sm float-right"><i class="fas fa-arrow-left mr-1"></i> Kembali</a>
            </div>
            <form method="POST">
                <div class="card-body">
                    
                    <div class="form-group row">
                        <label class="col-sm-4 col-form-label">Nama Proyek <span class="text-danger">*</span></label>
                        <div class="col-sm-8">
                            <input type="text" name="name" class="form-control check-duplicate" data-type="project" data-id="<?= $id ?>" value="<?= sanitize($project['name']) ?>" required>
                            <div class="duplicate-warning text-danger" style="display:none; font-size: 12px; margin-top: 5px;"></div>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label class="col-sm-4 col-form-label">Singkatan <span class="text-danger">*</span></label>
                        <div class="col-sm-8">
                            <input type="text" name="abbreviation" class="form-control" value="<?= sanitize($project['abbreviation'] ?? '') ?>" placeholder="Cth: PKST1" required maxlength="10">
                        </div>
                    </div>
                    
                    <div class="form-group row">
                        <label class="col-sm-4 col-form-label">Pilih Klien / Customer <span class="text-danger">*</span></label>
                        <div class="col-sm-8">
                            <select name="customer_id" class="form-control select2" required>
                                <option value="">-- Pilih Customer --</option>
                                <?php foreach ($customers as $c): ?>
                                    <option value="<?= $c['id'] ?>" <?= $project['customer_id'] == $c['id'] ? 'selected' : '' ?>>
                                        <?= sanitize($c['company_name']) ?> (<?= sanitize($c['abbreviation']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label class="col-sm-4 col-form-label">Project Manager (PM)</label>
                        <div class="col-sm-8">
                            <select name="manager_id" class="form-control select2">
                                <option value="">-- Belum Assign PM --</option>
                                <?php foreach ($pms as $pm): ?>
                                    <option value="<?= $pm['id'] ?>" <?= $project['project_manager_id'] == $pm['id'] ? 'selected' : '' ?>>
                                        <?= sanitize($pm['full_name']) ?> (@<?= sanitize($pm['username']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group row">
                        <label class="col-sm-4 col-form-label">Lokasi Proyek / Alamat Pengiriman <span class="text-danger">*</span></label>
                        <div class="col-sm-8">
                            <textarea name="location" class="form-control" rows="2" required><?= sanitize($project['location']) ?></textarea>
                        </div>
                    </div>
                    
                    <div class="form-group row">
                        <label class="col-sm-4 col-form-label">Tgl. Mulai Proyek</label>
                        <div class="col-sm-8">
                            <input type="date" name="start_date" class="form-control" value="<?= sanitize($project['start_date']) ?>">
                        </div>
                    </div>

                    <div class="form-group row">
                        <label class="col-sm-4 col-form-label">Tgl. Selesai Target</label>
                        <div class="col-sm-8">
                            <input type="date" name="end_date" class="form-control" value="<?= sanitize($project['end_date']) ?>">
                        </div>
                    </div>

                    <div class="form-group row">
                        <label class="col-sm-4 col-form-label">Status Proyek</label>
                        <div class="col-sm-8">
                            <select name="status" class="form-control" required>
                                <option value="active" <?= $project['status'] === 'active' ? 'selected' : '' ?>>Active (Berjalan)</option>
                                <option value="completed" <?= $project['status'] === 'completed' ? 'selected' : '' ?>>Completed (Selesai)</option>
                                <option value="cancelled" <?= $project['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled (Dibatalkan)</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label class="col-sm-4 col-form-label">Nilai Rencana Anggaran (Budget)</label>
                        <div class="col-sm-8">
                            <input type="text" name="budget" class="form-control input-rupiah" value="<?= number_format($project['budget'], 0, ',', '.') ?>">
                        </div>
                    </div>
                    
                </div>
                <div class="card-footer text-right">
                    <button type="submit" class="btn btn-warning"><i class="fas fa-save mr-1"></i> Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$extraJS = <<<'JS'
<script>
$(document).ready(function() {
    initSelect2('.select2');
});
</script>
JS;
require_once __DIR__ . '/../../../includes/footer.php';
?>
