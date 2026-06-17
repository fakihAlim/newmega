<?php
/**
 * Timesheet Input
 * - Karyawan: simple self-input form
 * - Admin/Finance/PM: bulk input with employee selection
 */
require_once __DIR__ . '/../../includes/auth.php';
requirePermission('timesheet_input');

$user = getCurrentUser();

// Check if user is employee
$stmt = $pdo->prepare("SELECT e.*, w.daily_wage FROM employees e JOIN master_wages w ON e.wage_id = w.id WHERE e.user_id = ?");
$stmt->execute([$user['id']]);
$emp = $stmt->fetch();

$isKaryawan = !empty($emp);
$isAdmin = in_array('super_admin', $user['roles'] ?? [$user['role']]) || in_array('finance', $user['roles'] ?? [$user['role']]) || in_array('project_manager', $user['roles'] ?? [$user['role']]);

$pageTitle = 'Input Timesheet';
$breadcrumbs = [
    ['label' => 'Timesheet', 'url' => '#'],
    ['label' => 'Input']
];

// ============ HANDLE POST ============
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['post_action'] ?? '';

    // --- KARYAWAN SELF INPUT ---
    if ($postAction === 'self_input' && $isKaryawan) {
        $company_id = $_POST['company_id'] ?? '';
        $project_id = $_POST['project_id'] ?? '';
        $work_date = $_POST['work_date'] ?? date('Y-m-d');
        $work_type = $_POST['work_type'] ?? 'full';
        $overtime_hours = floatval($_POST['overtime_hours'] ?? 0);
        $notes = trim($_POST['notes'] ?? '');

        $errors = [];
        if (empty($company_id)) $errors[] = "Perusahaan harus dipilih.";
        if (empty($project_id)) $errors[] = "Proyek harus dipilih.";

        $stmt = $pdo->prepare("SELECT id FROM timesheet_entries WHERE employee_id = ? AND work_date = ? AND project_id = ?");
        $stmt->execute([$emp['id'], $work_date, $project_id]);
        if ($stmt->fetch()) $errors[] = "Anda sudah mengisi timesheet untuk proyek ini pada tanggal tersebut.";

        if (empty($errors)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO timesheet_entries (employee_id, company_id, project_id, work_date, work_type, overtime_hours, daily_wage_at_time, notes, status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?)");
                $stmt->execute([$emp['id'], $company_id, $project_id, $work_date, $work_type, $overtime_hours, $emp['daily_wage'], $notes, $user['id']]);
                setFlash('success', 'Timesheet berhasil disimpan!');
            } catch (PDOException $e) {
                error_log('[NEWMEGA] ' . $e->getMessage());
                setFlash('danger', 'Terjadi kesalahan sistem saat menyimpan timesheet.');
            }
        } else {
            setFlash('danger', implode('<br>', $errors));
        }
        header('Location: ' . APP_URL . '/modules/timesheet/input.php');
        exit;
    }

    // --- ADMIN BULK INPUT ---
    if ($postAction === 'bulk_input' && $isAdmin) {
        $company_id = $_POST['company_id'] ?? '';
        $project_id = $_POST['project_id'] ?? '';
        $work_date = $_POST['work_date'] ?? date('Y-m-d');
        $default_work_type = $_POST['default_work_type'] ?? 'full';
        $employee_ids = $_POST['employee_ids'] ?? [];
        $work_types = $_POST['work_types'] ?? [];
        $overtimes = $_POST['overtimes'] ?? [];
        $emp_notes = $_POST['emp_notes'] ?? [];

        $errors = [];
        if (empty($company_id)) $errors[] = "Perusahaan harus dipilih.";
        if (empty($project_id)) $errors[] = "Proyek harus dipilih.";
        if (empty($work_date)) $errors[] = "Tanggal harus diisi.";
        if (empty($employee_ids)) $errors[] = "Minimal pilih 1 karyawan.";

        if (!empty($errors)) {
            setFlash('danger', implode('<br>', $errors));
            header('Location: ' . APP_URL . '/modules/timesheet/input.php');
            exit;
        }

        $successCount = 0;
        $updateCount = 0;
        $skipCount = 0;
        $pdo->beginTransaction();
        try {
            foreach ($employee_ids as $eid) {
                $eid = intval($eid);
                
                $wt = $work_types[$eid] ?? $default_work_type;
                $requestedDur = ($wt === 'full') ? 1 : 0.5;
                
                // Backend validation for full day check (excluding current project if updating)
                $stmtDur = $pdo->prepare("SELECT SUM(CASE WHEN work_type = 'full' THEN 1 ELSE 0.5 END) FROM timesheet_entries WHERE employee_id = ? AND work_date = ? AND project_id != ?");
                $stmtDur->execute([$eid, $work_date, $project_id]);
                $otherProjectsDur = floatval($stmtDur->fetchColumn() ?: 0);
                
                if ($otherProjectsDur + $requestedDur > 1) {
                    if ($otherProjectsDur == 0.5 && $requestedDur == 1) {
                        $wt = 'half'; // downgrade to fit 1 day max
                    } else {
                        $skipCount++;
                        continue;
                    }
                }

                $ot = floatval($overtimes[$eid] ?? 0);
                $nt = trim($emp_notes[$eid] ?? '');

                // Check duplicate project entry
                $stmt = $pdo->prepare("SELECT id FROM timesheet_entries WHERE employee_id = ? AND work_date = ? AND project_id = ?");
                $stmt->execute([$eid, $work_date, $project_id]);
                $existingEntry = $stmt->fetch();
                
                if ($existingEntry) {
                    // Update
                    $updateStmt = $pdo->prepare("UPDATE timesheet_entries SET work_type = ?, overtime_hours = ?, notes = ?, status = 'approved', approved_by = ?, approved_at = NOW() WHERE id = ?");
                    $updateStmt->execute([$wt, $ot, $nt, $user['id'], $existingEntry['id']]);
                    $updateCount++;
                } else {
                    // Insert
                    $stmtW = $pdo->prepare("SELECT w.daily_wage FROM employees e JOIN master_wages w ON e.wage_id = w.id WHERE e.id = ?");
                    $stmtW->execute([$eid]);
                    $wage = $stmtW->fetchColumn() ?: 0;

                    $insertStmt = $pdo->prepare("INSERT INTO timesheet_entries (employee_id, company_id, project_id, work_date, work_type, overtime_hours, daily_wage_at_time, notes, status, approved_by, approved_at, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'approved', ?, NOW(), ?)");
                    $insertStmt->execute([$eid, $company_id, $project_id, $work_date, $wt, $ot, $wage, $nt, $user['id'], $user['id']]);
                    $successCount++;
                }
            }
            $pdo->commit();
            $msg = "<strong>$successCount</strong> ditambah baru. <strong>$updateCount</strong> berhasil di-update.";
            if ($skipCount > 0) $msg .= " <strong>$skipCount</strong> dilewati (melebihi batas Full Day).";
            setFlash('success', $msg);
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log('[NEWMEGA] ' . $e->getMessage());
            setFlash('danger', 'Gagal memproses timesheet. Terjadi kesalahan sistem.');
        }
        header('Location: ' . APP_URL . '/modules/timesheet/input.php');
        exit;
    }
}

// Fetch data for forms
$companies = $pdo->query("SELECT id, name FROM companies ORDER BY name")->fetchAll();
$projects = $pdo->query("SELECT id, name FROM projects WHERE status IN ('planning','active') ORDER BY name")->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<?php if ($isAdmin): ?>
<!-- ==================== ADMIN BULK VIEW ==================== -->
<form method="POST" id="bulkForm">
    <input type="hidden" name="post_action" value="bulk_input">

    <!-- STEP 1: Header Global -->
    <div class="card mb-3">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-calendar-check text-warning mr-2"></i>Input Timesheet Bulk</h3>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-3">
                    <div class="form-group mb-md-0">
                        <label class="font-weight-bold">Perusahaan <span class="text-danger">*</span></label>
                        <select name="company_id" id="bulkCompanyId" class="form-control select2" required>
                            <option value="">-- Pilih PT --</option>
                            <?php foreach ($companies as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= sanitize($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group mb-md-0">
                        <label class="font-weight-bold">Proyek <span class="text-danger">*</span></label>
                        <select name="project_id" id="bulkProjectId" class="form-control select2" required>
                            <option value="">-- Pilih Proyek --</option>
                            <?php foreach ($projects as $p): ?>
                                <option value="<?= $p['id'] ?>"><?= sanitize($p['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group mb-md-0">
                        <label class="font-weight-bold">Tanggal <span class="text-danger">*</span></label>
                        <input type="date" name="work_date" id="bulkWorkDate" class="form-control" required value="<?= date('Y-m-d') ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group mb-md-0">
                        <label class="font-weight-bold">Tipe Default</label>
                        <select name="default_work_type" id="defaultWorkType" class="form-control">
                            <option value="full">Full Day (8 Jam)</option>
                            <option value="half">Half Day (4 Jam)</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- STEP 2: Employee Selection -->
    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title"><i class="fas fa-users mr-2"></i>Pilih Karyawan</h3>
            <span class="text-muted" style="font-size:12px;">*Data akan dimuat ulang otomatis jika Perusahaan/Proyek/Tanggal diubah.</span>
        </div>
        <div class="card-body">
            <div class="row align-items-end">
                <div class="col-md-8">
                    <div class="form-group mb-0">
                        <label class="font-weight-bold">Cari Karyawan</label>
                        <select id="empSearchSelect" class="form-control select2">
                            <option value="">-- Ketik Nama Karyawan --</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-2">
                    <button type="button" class="btn btn-primary w-100 font-weight-bold" id="btnAddEmp">
                        <i class="fas fa-plus mr-1"></i> Proses
                    </button>
                </div>
                <div class="col-md-2">
                    <button type="button" class="btn btn-outline-success w-100 font-weight-bold" id="btnAddAllEmp">
                        <i class="fas fa-check-double mr-1"></i> Pilih Semua
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- STEP 3: Detail Input Timesheet -->
    <div class="card mb-3" id="customizeCard" style="display:none;">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-table mr-2"></i>Tabel Detail Input <span class="badge badge-warning ml-2" id="selectedCount">0 Karyawan</span></h3>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                <table class="table table-bordered table-sm mb-0" style="font-size: 13px;">
                    <thead class="thead-light" style="position: sticky; top: 0; z-index: 1;">
                        <tr>
                            <th width="5%" class="text-center align-middle">No</th>
                            <th width="20%" class="align-middle">Nama</th>
                            <th width="15%" class="align-middle">Jabatan</th>
                            <th width="20%" class="align-middle">Tipe Kerja</th>
                            <th width="15%" class="align-middle">Lembur (Jam)</th>
                            <th width="20%" class="align-middle">Catatan</th>
                            <th width="5%" class="text-center align-middle">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="customizeBody">
                        <!-- Baris akan ditambahkan via JS -->
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer text-right">
            <span class="float-left text-muted pt-1" style="font-size:13px;"><i class="fas fa-info-circle mr-1"></i>Status otomatis: <strong class="text-success">Approved</strong></span>
            <button type="submit" class="btn btn-warning text-white font-weight-bold px-4" id="btnSubmitBulk">
                <i class="fas fa-paper-plane mr-1"></i> Submit / Update <span id="submitCount">0</span> Timesheet
            </button>
        </div>
    </div>
</form>

<?php elseif ($isKaryawan): ?>
<!-- ==================== KARYAWAN SELF VIEW ==================== -->
<div class="row justify-content-center">
    <div class="col-md-8 col-lg-6">
        <div class="card shadow-sm" style="border-radius: 12px; border-top: 4px solid #d97706;">
            <div class="card-header bg-white border-bottom-0 pt-4 pb-0">
                <h4 class="card-title font-weight-bold text-center w-100" style="color: #4a5568;">
                    <i class="fas fa-calendar-check text-warning mr-2"></i> Input Absensi / Timesheet
                </h4>
            </div>
            <div class="card-body">
                <form action="" method="POST">
                    <input type="hidden" name="post_action" value="self_input">

                    <div class="form-group mb-3">
                        <label class="font-weight-bold text-secondary">Tanggal Pekerjaan <span class="text-danger">*</span></label>
                        <input type="date" name="work_date" class="form-control form-control-lg" required value="<?= date('Y-m-d') ?>" style="border-radius: 8px;">
                    </div>

                    <div class="form-group mb-3">
                        <label class="font-weight-bold text-secondary">Perusahaan <span class="text-danger">*</span></label>
                        <select name="company_id" class="form-control form-control-lg select2" required>
                            <option value="">-- Pilih Perusahaan --</option>
                            <?php foreach ($companies as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= sanitize($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group mb-4">
                        <label class="font-weight-bold text-secondary">Proyek <span class="text-danger">*</span></label>
                        <select name="project_id" class="form-control form-control-lg select2" required>
                            <option value="">-- Pilih Proyek --</option>
                            <?php foreach ($projects as $p): ?>
                                <option value="<?= $p['id'] ?>"><?= sanitize($p['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <hr class="my-4">

                    <div class="form-group mb-4">
                        <label class="font-weight-bold text-secondary d-block">Tipe Kehadiran <span class="text-danger">*</span></label>
                        <div class="row">
                            <div class="col-6">
                                <div class="custom-control custom-radio custom-control-inline w-100 border p-3 rounded" style="background:#f8f9fa;">
                                    <input type="radio" id="type_full" name="work_type" class="custom-control-input" value="full" checked>
                                    <label class="custom-control-label font-weight-bold w-100" style="cursor:pointer" for="type_full">Full Day <br><small class="text-muted font-weight-normal">(8 Jam)</small></label>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="custom-control custom-radio custom-control-inline w-100 border p-3 rounded" style="background:#f8f9fa;">
                                    <input type="radio" id="type_half" name="work_type" class="custom-control-input" value="half">
                                    <label class="custom-control-label font-weight-bold w-100" style="cursor:pointer" for="type_half">Half Day <br><small class="text-muted font-weight-normal">(4 Jam)</small></label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-group mb-4">
                        <label class="font-weight-bold text-secondary">Jam Lembur (Opsional)</label>
                        <div class="input-group input-group-lg">
                            <input type="number" step="0.5" min="0" max="24" name="overtime_hours" class="form-control" value="0">
                            <div class="input-group-append"><span class="input-group-text bg-white">Jam</span></div>
                        </div>
                    </div>

                    <div class="form-group mb-4">
                        <label class="font-weight-bold text-secondary">Keterangan / Catatan</label>
                        <textarea name="notes" class="form-control" rows="3" placeholder="Apa yang dikerjakan hari ini..." style="border-radius:8px;"></textarea>
                    </div>

                    <button type="submit" class="btn btn-warning btn-lg btn-block text-white font-weight-bold" style="border-radius:8px; font-size:1.1rem; box-shadow:0 4px 6px rgba(217,119,6,0.3);">
                        <i class="fas fa-paper-plane mr-2"></i> Submit Timesheet
                    </button>
                </form>
            </div>
        </div>

        <!-- Riwayat Singkat -->
        <div class="card shadow-sm mt-4" style="border-radius: 12px;">
            <div class="card-header bg-white">
                <h5 class="card-title font-weight-bold mb-0" style="color: #4a5568;">Riwayat 5 Hari Terakhir</h5>
            </div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush">
                    <?php
                    $history = $pdo->prepare("SELECT t.*, p.name as project_name FROM timesheet_entries t JOIN projects p ON t.project_id = p.id WHERE t.employee_id = ? ORDER BY t.work_date DESC, t.id DESC LIMIT 5");
                    $history->execute([$emp['id']]);
                    $records = $history->fetchAll();
                    if (empty($records)): ?>
                        <li class="list-group-item text-center text-muted py-4">Belum ada data timesheet.</li>
                    <?php else:
                        foreach ($records as $r):
                            $statusColor = 'warning';
                            if ($r['status'] == 'approved') $statusColor = 'success';
                            if ($r['status'] == 'rejected') $statusColor = 'danger';
                    ?>
                        <li class="list-group-item flex-column align-items-start">
                            <div class="d-flex w-100 justify-content-between">
                                <h6 class="mb-1 font-weight-bold"><?= date('d M Y', strtotime($r['work_date'])) ?></h6>
                                <span class="badge badge-<?= $statusColor ?>"><?= strtoupper($r['status']) ?></span>
                            </div>
                            <p class="mb-1 text-muted" style="font-size: 13px;">
                                <?= sanitize($r['project_name']) ?> <br>
                                <?= $r['work_type'] == 'full' ? 'Full Day (8 Jam)' : 'Half Day (4 Jam)' ?>
                                <?= $r['overtime_hours'] > 0 ? " + {$r['overtime_hours']} Jam Lembur" : '' ?>
                            </p>
                        </li>
                    <?php endforeach; endif; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php else: ?>
<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="alert alert-danger text-center"><i class="fas fa-exclamation-triangle mr-2"></i>Akun Anda tidak tertaut dengan data karyawan dan bukan admin. Harap hubungi Admin.</div>
    </div>
</div>
<?php endif; ?>

<?php
$appUrl = APP_URL;
$extraJS = <<<JS
<script>
const APP_URL = '{$appUrl}';
</script>
JS;

if ($isAdmin) {
$extraJS .= <<<'JS'
<script>
$(document).ready(function() {
    $('.select2').select2({ theme: 'bootstrap4', width: '100%' });

    let allEmployees = [];
    let selectedIds = new Set();
    let empDurations = {}; // Mapping of emp_id to existing duration
    let isReloading = false;

    // Load employees via AJAX
    $.getJSON(APP_URL + '/api/get_employees.php', function(data) {
        allEmployees = data;
        
        // Populate Select2
        const select = $('#empSearchSelect');
        data.forEach(function(e) {
            select.append(new Option(e.full_name + ' (' + e.employee_code + ') - ' + e.jabatan_name, e.id));
        });
        
        // Initialize
        select.val('').trigger('change');
        checkAutoLoad();
    });

    // Fetch durations based on selected date
    function loadDurations(date, callback) {
        if (!date) return;
        $.getJSON(APP_URL + '/api/get_timesheet_duration.php?date=' + date, function(data) {
            empDurations = data;
            if (callback) callback();
        });
    }

    // Auto load existing entries based on Company + Project + Date
    function checkAutoLoad() {
        const comp = $('#bulkCompanyId').val();
        const proj = $('#bulkProjectId').val();
        const date = $('#bulkWorkDate').val();

        if (comp && proj && date) {
            isReloading = true;
            // Load global durations first
            loadDurations(date, function() {
                // Fetch entries for this specific combination
                $.getJSON(APP_URL + '/api/get_timesheet_entries.php', { date: date, company_id: comp, project_id: proj }, function(entries) {
                    selectedIds.clear();
                    $('#customizeBody').empty();

                    entries.forEach(function(entry) {
                        // Subtract the duration of THIS entry from the global duration so that addEmployeeToTable sees the correct remaining quota
                        let entryDur = (entry.work_type === 'full') ? 1 : 0.5;
                        if (empDurations[entry.employee_id]) {
                            empDurations[entry.employee_id] -= entryDur; 
                        }
                        
                        addEmployeeToTable(entry.employee_id, false, entry);
                    });

                    updateCustomizePanel();
                    isReloading = false;
                });
            });
        } else {
            // Just clear draft
            selectedIds.clear();
            $('#customizeBody').empty();
            updateCustomizePanel();
        }
    }

    $('#bulkCompanyId, #bulkProjectId, #bulkWorkDate').on('change', function() {
        checkAutoLoad();
    });

    function updateCustomizePanel() {
        const count = selectedIds.size;
        $('#selectedCount').text(count + ' Karyawan');
        $('#submitCount').text(count);

        if (count === 0) {
            $('#customizeCard').slideUp(200);
        } else {
            $('#customizeCard').slideDown(200);
        }
        
        // Update Row Numbers
        let num = 1;
        $('#customizeBody tr').each(function() {
            $(this).find('.row-num').text(num++);
        });
    }

    // Optional third param `existingEntry` if loading from DB
    function addEmployeeToTable(id, showWarning = true, existingEntry = null) {
        if (!id) return false;
        id = String(id);
        
        if (selectedIds.has(id)) {
            if (showWarning) Swal.fire('Info', 'Karyawan sudah ditambahkan ke daftar tabel.', 'info');
            return false;
        }

        const emp = allEmployees.find(e => String(e.id) === id);
        if (!emp) return false;

        // Validation against existing duration (excluding current project if modifying)
        const existingDur = empDurations[id] || 0;
        if (existingDur >= 1) {
            if (showWarning) {
                Swal.fire('Tidak Dapat Diproses', `${emp.full_name} sudah memiliki absensi Full Day pada tanggal ini di proyek lain.`, 'error');
            }
            return false; // Skip
        }

        let defaultType = existingEntry ? existingEntry.work_type : $('#defaultWorkType').val();
        let selectHtml = '';
        
        if (existingDur === 0.5) {
            if (showWarning) {
                Swal.fire('Info', `${emp.full_name} sudah bekerja Half Day di proyek lain. Sisa kuota hanya Half Day.`, 'info');
            }
            selectHtml = `<select name="work_types[${emp.id}]" class="form-control form-control-sm work-type-select" style="pointer-events: none; background-color: #e9ecef;" tabindex="-1" readonly><option value="half" selected>Half Day</option></select>`;
        } else {
            selectHtml = `
                <select name="work_types[${emp.id}]" class="form-control form-control-sm work-type-select">
                    <option value="full" ${defaultType === 'full' ? 'selected' : ''}>Full Day</option>
                    <option value="half" ${defaultType === 'half' ? 'selected' : ''}>Half Day</option>
                </select>
            `;
        }

        selectedIds.add(id);

        let trClass = existingEntry ? 'bg-light' : '';
        let statusBadge = existingEntry ? '<br><span class="badge badge-success mt-1">Tersimpan</span>' : '';
        let existingIdAttr = existingEntry ? `data-existing-id="${existingEntry.entry_id}"` : '';

        let overtimeVal = existingEntry ? existingEntry.overtime_hours : 0;
        let notesVal = existingEntry ? (existingEntry.notes || '') : '';

        const tr = `
            <tr data-id="${emp.id}" class="${trClass}" ${existingIdAttr}>
                <td class="text-center align-middle">
                    <span class="row-num"></span>
                    <input type="hidden" name="employee_ids[]" value="${emp.id}">
                </td>
                <td class="align-middle"><strong>${emp.full_name}</strong><br><code class="text-muted" style="font-size:11px;">${emp.employee_code}</code>${statusBadge}</td>
                <td class="align-middle"><span class="badge badge-info">${emp.jabatan_name}</span></td>
                <td class="align-middle">${selectHtml}</td>
                <td class="align-middle"><input type="number" name="overtimes[${emp.id}]" class="form-control form-control-sm" value="${overtimeVal}" step="0.5" min="0" max="24"></td>
                <td class="align-middle"><input type="text" name="emp_notes[${emp.id}]" class="form-control form-control-sm" placeholder="Opsional" value="${notesVal}"></td>
                <td class="text-center align-middle">
                    <button type="button" class="btn btn-sm btn-danger btn-remove-emp" title="Hapus dari daftar"><i class="fas fa-trash"></i></button>
                </td>
            </tr>
        `;
        
        $('#customizeBody').append(tr);
        if (!isReloading) updateCustomizePanel();
        
        // Reset select2 search
        $('#empSearchSelect').val('').trigger('change');
        return true;
    }

    // Button Tambah (Proses)
    $('#btnAddEmp').on('click', function() {
        const id = $('#empSearchSelect').val();
        if (id) {
            addEmployeeToTable(id, true);
        } else {
            Swal.fire('Peringatan', 'Silakan cari dan pilih karyawan terlebih dahulu.', 'warning');
        }
    });

    // Button Pilih Semua
    $('#btnAddAllEmp').on('click', function() {
        if (allEmployees.length === 0) return;
        
        let added = 0;
        let blocked = 0;
        allEmployees.forEach(e => {
            const idStr = String(e.id);
            if (!selectedIds.has(idStr)) {
                if (addEmployeeToTable(idStr, false)) {
                    added++;
                } else {
                    blocked++;
                }
            }
        });
        
        if (added > 0) {
            let msg = `${added} karyawan ditambahkan ke tabel.`;
            if (blocked > 0) msg += ` <br><small class="text-danger">${blocked} karyawan dilewati karena sudah memenuhi kuota Full Day.</small>`;
            Swal.fire({ title: 'Sukses', html: msg, icon: 'success' });
        } else {
            Swal.fire('Info', 'Semua karyawan yang tersedia sudah ditambahkan. Karyawan lain mungkin sudah memenuhi kuota Full Day.', 'info');
        }
    });

    // Remove Employee Button
    $(document).on('click', '.btn-remove-emp', function() {
        const tr = $(this).closest('tr');
        const id = String(tr.data('id'));
        const existingId = tr.attr('data-existing-id');

        if (existingId) {
            Swal.fire({
                title: 'Hapus Data Tersimpan?',
                text: "Karyawan ini sudah memiliki timesheet yang tersimpan. Menghapus baris ini akan langsung MENGHAPUS datanya dari database secara permanen!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Ya, Hapus!',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.post(APP_URL + '/api/delete_timesheet.php', { id: existingId }, function(res) {
                        if (res.success) {
                            selectedIds.delete(id);
                            tr.remove();
                            updateCustomizePanel();
                            Swal.fire('Terhapus!', 'Data berhasil dihapus dari database.', 'success');
                            // refresh quota
                            checkAutoLoad();
                        } else {
                            Swal.fire('Gagal', res.message || 'Terjadi kesalahan.', 'error');
                        }
                    }, 'json');
                }
            });
        } else {
            // Unsaved row
            selectedIds.delete(id);
            tr.remove();
            updateCustomizePanel();
        }
    });

    // Update customize when default type changes
    $('#defaultWorkType').on('change', function() {
        const val = $(this).val();
        // Only update selects that are not readonly/pointer-events: none
        $('#customizeBody .work-type-select:not([style*="pointer-events: none"])').val(val);
    });

    // Form submit validation
    $('#bulkForm').on('submit', function(e) {
        if (selectedIds.size === 0) {
            e.preventDefault();
            Swal.fire('Oops', 'Pilih minimal 1 karyawan untuk di-submit.', 'warning');
            return false;
        }
    });
});
</script>
JS;
} else {
$extraJS .= <<<'JS'
<script>
$(document).ready(function() {
    $('.select2').select2({ theme: 'bootstrap4', width: '100%' });
});
</script>
JS;
}

require_once __DIR__ . '/../../includes/footer.php';
?>
