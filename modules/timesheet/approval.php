<?php
/**
 * Timesheet Approval
 */
require_once __DIR__ . '/../../includes/auth.php';
requirePermission('timesheet_approve');

$user = getCurrentUser();
$pageTitle = 'Approval Timesheet';
$breadcrumbs = [
    ['label' => 'Timesheet', 'url' => '#'],
    ['label' => 'Approval']
];

// Handle Approve / Reject
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $id = $_POST['id'] ?? 0;
    $action = $_POST['action'];
    
    if (in_array($action, ['approve', 'reject'])) {
        $status = ($action === 'approve') ? 'approved' : 'rejected';
        
        $stmt = $pdo->prepare("UPDATE timesheet_entries SET status = ?, approved_by = ?, approved_at = NOW() WHERE id = ?");
        if ($stmt->execute([$status, $user['id'], $id])) {
            logActivity('update', 'timesheet', ucfirst($status) . " absensi ID: {$id}", 'timesheet_entries', $id);
            setFlash('success', "Timesheet berhasil di-" . $status . ".");
        } else {
            setFlash('danger', 'Gagal memproses timesheet.');
        }
    }
    header('Location: ' . APP_URL . '/modules/timesheet/approval.php');
    exit;
}

// Fetch pending timesheets
$pending = $pdo->query("
    SELECT t.*, u.full_name as employee_name, w.jabatan_name, p.name as project_name, c.name as company_name
    FROM timesheet_entries t
    JOIN employees e ON t.employee_id = e.id
    JOIN users u ON e.user_id = u.id
    JOIN master_wages w ON e.wage_id = w.id
    JOIN projects p ON t.project_id = p.id
    JOIN companies c ON t.company_id = c.id
    WHERE t.status = 'pending'
    ORDER BY t.work_date ASC, t.created_at ASC
")->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="card">
    <div class="card-header border-bottom-0">
        <h3 class="card-title"><i class="fas fa-check-double text-primary mr-2"></i> Menunggu Persetujuan</h3>
    </div>
    <div class="card-body p-0">
        <table id="approvalTable" class="table table-striped table-hover m-0">
            <thead class="bg-light">
                <tr>
                    <th width="5%" class="text-center">No</th>
                    <th width="15%">Tanggal</th>
                    <th width="20%">Karyawan</th>
                    <th width="25%">Perusahaan & Proyek</th>
                    <th width="15%">Detail Kerja</th>
                    <th width="10%">Status</th>
                    <th width="10%" class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pending as $i => $row): ?>
                <tr>
                    <td class="text-center align-middle"><?= $i + 1 ?></td>
                    <td class="align-middle"><strong><?= date('d M Y', strtotime($row['work_date'])) ?></strong></td>
                    <td class="align-middle">
                        <strong class="text-dark"><?= sanitize($row['employee_name']) ?></strong><br>
                        <small class="text-muted"><?= sanitize($row['jabatan_name']) ?></small>
                    </td>
                    <td class="align-middle">
                        <span class="text-primary font-weight-bold"><?= sanitize($row['company_name']) ?></span><br>
                        <small><?= sanitize($row['project_name']) ?></small>
                    </td>
                    <td class="align-middle">
                        <?= $row['work_type'] == 'full' ? 'Full Day (8 Jam)' : 'Half Day (4 Jam)' ?><br>
                        <?php if ($row['overtime_hours'] > 0): ?>
                            <small class="text-danger">+ <?= $row['overtime_hours'] ?> Jam Lembur</small>
                        <?php endif; ?>
                    </td>
                    <td class="align-middle">
                        <span class="badge badge-warning">Pending</span>
                    </td>
                    <td class="text-center align-middle">
                        <form method="POST" class="d-inline approve-form">
                            <input type="hidden" name="id" value="<?= $row['id'] ?>">
                            <input type="hidden" name="action" value="approve">
                            <button type="submit" class="btn btn-success btn-sm mb-1" data-toggle="tooltip" title="Approve">
                                <i class="fas fa-check"></i>
                            </button>
                        </form>
                        <form method="POST" class="d-inline reject-form">
                            <input type="hidden" name="id" value="<?= $row['id'] ?>">
                            <input type="hidden" name="action" value="reject">
                            <button type="submit" class="btn btn-danger btn-sm mb-1" data-toggle="tooltip" title="Reject">
                                <i class="fas fa-times"></i>
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($pending)): ?>
                <tr>
                    <td colspan="7" class="text-center py-4 text-muted">
                        <i class="fas fa-inbox fa-3x mb-3 opacity-50"></i><br>
                        Semua timesheet sudah diproses.
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
$hasPending = !empty($pending);
$extraJS = <<<JS
<script>
$(document).ready(function() {
    var hasPending = {$hasPending};
    if (hasPending) {
        initDataTable('#approvalTable', { paging: true, searching: true });
    }

    $('.approve-form').on('submit', function(e) {
        e.preventDefault();
        var form = this;
        confirmAction('Konfirmasi Menyetujui', 'Apakah Anda yakin ingin menyetujui timesheet ini?', function() {
            form.submit();
        });
    });

    $('.reject-form').on('submit', function(e) {
        e.preventDefault();
        var form = this;
        confirmAction('Konfirmasi Menolak', 'Apakah Anda yakin ingin menolak timesheet ini?', function() {
            form.submit();
        });
    });
});
</script>
JS;
require_once __DIR__ . '/../../includes/footer.php';
?>
