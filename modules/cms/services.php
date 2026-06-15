<?php
/**
 * CMS - Manage Services
 */
require_once __DIR__ . '/../../includes/auth.php';
requirePermission('cms_landing');

$pageTitle = 'CMS Kelola Layanan';
$breadcrumbs = [
    ['label' => 'CMS Landing', 'url' => '#'],
    ['label' => 'Layanan']
];

// Handle Add / Edit / Delete Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create' || $action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $icon = trim($_POST['icon'] ?? 'fa-hard-hat');
        $orderNum = (int)($_POST['order_num'] ?? 0);
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        
        if (empty($title) || empty($description)) {
            setFlash('danger', 'Judul dan Deskripsi wajib diisi.');
        } else {
            if ($action === 'create') {
                $stmt = $pdo->prepare("INSERT INTO landing_services (title, description, icon, order_num, is_active) VALUES (?, ?, ?, ?, ?)");
                if ($stmt->execute([$title, $description, $icon, $orderNum, $isActive])) {
                    setFlash('success', 'Layanan baru berhasil ditambahkan.');
                } else {
                    setFlash('danger', 'Gagal menambahkan layanan.');
                }
            } else {
                $stmt = $pdo->prepare("UPDATE landing_services SET title = ?, description = ?, icon = ?, order_num = ?, is_active = ? WHERE id = ?");
                if ($stmt->execute([$title, $description, $icon, $orderNum, $isActive, $id])) {
                    setFlash('success', 'Layanan berhasil diperbarui.');
                } else {
                    setFlash('danger', 'Gagal memperbarui layanan.');
                }
            }
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM landing_services WHERE id = ?");
        if ($stmt->execute([$id])) {
            setFlash('success', 'Layanan berhasil dihapus.');
        } else {
            setFlash('danger', 'Gagal menghapus layanan.');
        }
    }
    
    header('Location: ' . APP_URL . '/modules/cms/services.php');
    exit;
}

// Fetch Services
$services = $pdo->query("SELECT * FROM landing_services ORDER BY order_num ASC, id DESC")->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title">Pengaturan Spesialisasi / Layanan</h3>
        <div class="ml-auto">
            <button type="button" class="btn btn-primary btn-sm" id="btnTambah">
                <i class="fas fa-plus mr-1"></i> Tambah Layanan
            </button>
        </div>
    </div>
    <div class="card-body">
        <table id="servicesTable" class="table table-bordered table-striped w-100" style="font-size: 13.5px;">
            <thead>
                <tr>
                    <th width="5%">No</th>
                    <th width="10%">Ikon Preview</th>
                    <th width="25%">Judul Layanan</th>
                    <th width="40%">Deskripsi Layanan</th>
                    <th width="10%">Urutan</th>
                    <th width="5%">Status</th>
                    <th width="5%" class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($services as $i => $s): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td class="text-center">
                        <span class="d-inline-flex align-items-center justify-content-center bg-light border rounded" style="width: 40px; height: 40px; font-size: 18px; color: var(--accent);">
                            <i class="fas <?= sanitize($s['icon'] ?: 'fa-hard-hat') ?>"></i>
                        </span>
                        <div class="text-muted mt-1" style="font-size: 11px;"><?= sanitize($s['icon'] ?: 'fa-hard-hat') ?></div>
                    </td>
                    <td><strong><?= sanitize($s['title']) ?></strong></td>
                    <td><?= nl2br(sanitize($s['description'])) ?></td>
                    <td><?= $s['order_num'] ?></td>
                    <td>
                        <?php if ($s['is_active']): ?>
                            <span class="badge badge-success">Aktif</span>
                        <?php else: ?>
                            <span class="badge badge-secondary">Non-Aktif</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-center">
                        <div class="btn-group">
                            <button type="button" class="btn btn-info btn-sm btn-edit" 
                                    data-id="<?= $s['id'] ?>"
                                    data-title="<?= sanitize($s['title']) ?>"
                                    data-description="<?= sanitize($s['description']) ?>"
                                    data-icon="<?= sanitize($s['icon']) ?>"
                                    data-order="<?= $s['order_num'] ?>"
                                    data-active="<?= $s['is_active'] ?>"
                                    title="Edit">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button type="button" class="btn btn-danger btn-sm btn-delete" 
                                    data-id="<?= $s['id'] ?>" 
                                    data-title="<?= sanitize($s['title']) ?>"
                                    title="Hapus">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Form -->
<div class="modal fade" id="formModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-md" role="document">
        <form action="" method="POST" id="serviceForm">
            <input type="hidden" name="action" id="formAction" value="create">
            <input type="hidden" name="id" id="formId" value="">
            
            <div class="modal-content" style="border-radius: 4px;">
                <div class="modal-header bg-dark text-white" style="border-bottom: 2px solid var(--accent);">
                    <h5 class="modal-title" id="modalTitle">Tambah Layanan Baru</h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Nama Layanan <span class="text-danger">*</span></label>
                        <input type="text" name="title" id="inputTitle" class="form-control" required placeholder="Contoh: Pembangunan Residensial">
                    </div>
                    
                    <div class="form-group mt-3">
                        <label>Ikon FontAwesome <span class="text-danger">*</span></label>
                        <select name="icon" id="inputIcon" class="form-control select2-icon">
                            <option value="fa-hard-hat" data-icon="fa-hard-hat">Safety Helmet (fa-hard-hat)</option>
                            <option value="fa-home" data-icon="fa-home">Rumah (fa-home)</option>
                            <option value="fa-building" data-icon="fa-building">Gedung (fa-building)</option>
                            <option value="fa-tools" data-icon="fa-tools">Peralatan (fa-tools)</option>
                            <option value="fa-drafting-compass" data-icon="fa-drafting-compass">Blueprint / Desain (fa-drafting-compass)</option>
                            <option value="fa-hammer" data-icon="fa-hammer">Palu (fa-hammer)</option>
                            <option value="fa-road" data-icon="fa-road">Jalan / Infrastruktur (fa-road)</option>
                            <option value="fa-warehouse" data-icon="fa-warehouse">Gudang (fa-warehouse)</option>
                            <option value="fa-city" data-icon="fa-city">Kota (fa-city)</option>
                            <option value="fa-paint-roller" data-icon="fa-paint-roller">Pengecatan (fa-paint-roller)</option>
                        </select>
                        <div class="text-muted mt-1" style="font-size:12px;">Pilih salah satu ikon konstruksi utama di atas.</div>
                    </div>

                    <div class="form-group mt-3">
                        <label>Deskripsi Layanan <span class="text-danger">*</span></label>
                        <textarea name="description" id="inputDescription" class="form-control" rows="4" required placeholder="Deskripsikan secara rinci tentang layanan ini..."></textarea>
                    </div>

                    <div class="row mt-3">
                        <div class="col-md-6 form-group">
                            <label>Nomor Urutan</label>
                            <input type="number" name="order_num" id="inputOrder" class="form-control" value="1" min="0">
                        </div>
                        <div class="col-md-6 form-group d-flex align-items-center mt-4 pt-2">
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" name="is_active" class="custom-control-input" id="inputActive" checked>
                                <label class="custom-control-label font-weight-bold" for="inputActive">Aktifkan Layanan</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save mr-1"></i> Simpan Data</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Hidden Action Form -->
<form id="actionForm" method="POST" style="display: none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="actionId">
</form>

<?php
$extraJS = <<<'JS'
<script>
$(document).ready(function() {
    initDataTable('#servicesTable');

    // Reset form for create
    $('#btnTambah').click(function() {
        $('#serviceForm')[0].reset();
        $('#formAction').val('create');
        $('#formId').val('');
        $('#modalTitle').text('Tambah Layanan Baru');
        $('#formModal').modal('show');
    });

    // Populate form for edit
    $('.btn-edit').click(function() {
        $('#serviceForm')[0].reset();
        $('#formAction').val('update');
        $('#formId').val($(this).data('id'));
        
        $('#inputTitle').val($(this).data('title'));
        $('#inputDescription').val($(this).data('description'));
        $('#inputIcon').val($(this).data('icon'));
        $('#inputOrder').val($(this).data('order'));

        if ($(this).data('active') == 1) {
            $('#inputActive').prop('checked', true);
        } else {
            $('#inputActive').prop('checked', false);
        }

        $('#modalTitle').text('Edit Data Layanan');
        $('#formModal').modal('show');
    });

    // Handle delete action
    $('.btn-delete').click(function() {
        const id = $(this).data('id');
        const title = $(this).data('title');
        
        confirmAction('Hapus Layanan?', `Anda yakin ingin menghapus layanan "${title}"?`, function() {
            $('#actionId').val(id);
            $('#actionForm').submit();
        });
    });
});
</script>
JS;
require_once __DIR__ . '/../../includes/footer.php';
?>
