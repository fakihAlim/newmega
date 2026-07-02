<?php
/**
 * CMS - Manage Portfolio
 */
require_once __DIR__ . '/../../includes/auth.php';
requirePermission('cms_landing');

$pageTitle = 'CMS Kelola Portofolio';
$breadcrumbs = [
    ['label' => 'CMS Landing', 'url' => '#'],
    ['label' => 'Portofolio']
];

$uploadDir = UPLOADS_PATH . '/landing';

// Handle Add / Edit / Delete Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create' || $action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $category = trim($_POST['category'] ?? 'Residensial');
        $client = trim($_POST['client'] ?? '');
        $projectDate = trim($_POST['project_date'] ?? null);
        $orderNum = (int)($_POST['order_num'] ?? 0);
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        
        $imageUrl = $_POST['current_image'] ?? '';

        // Handle Image Upload
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $uploadResult = uploadFile($_FILES['image'], $uploadDir, ['jpg', 'jpeg', 'png', 'gif']);
            if ($uploadResult['success']) {
                $imageUrl = APP_URL . '/assets/uploads/landing/' . $uploadResult['filename'];
            } else {
                setFlash('danger', 'Gagal mengunggah foto proyek: ' . $uploadResult['message']);
                header('Location: ' . APP_URL . '/modules/cms/portfolio.php');
                exit;
            }
        } elseif (empty($imageUrl) && isset($_POST['image_url_text']) && !empty(trim($_POST['image_url_text']))) {
            $imageUrl = trim($_POST['image_url_text']);
        }

        if (empty($title) || empty($imageUrl)) {
            setFlash('danger', 'Judul dan Foto Proyek wajib diisi.');
        } else {
            // Normalize empty project date to null for SQL
            $dbProjectDate = empty($projectDate) ? null : $projectDate;

            if ($action === 'create') {
                $stmt = $pdo->prepare("INSERT INTO landing_portfolios (title, description, category, client, project_date, image_url, order_num, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                if ($stmt->execute([$title, $description, $category, $client, $dbProjectDate, $imageUrl, $orderNum, $isActive])) {
                    setFlash('success', 'Portofolio baru berhasil ditambahkan.');
                } else {
                    setFlash('danger', 'Gagal menambahkan portofolio.');
                }
            } else {
                $stmt = $pdo->prepare("UPDATE landing_portfolios SET title = ?, description = ?, category = ?, client = ?, project_date = ?, image_url = ?, order_num = ?, is_active = ? WHERE id = ?");
                if ($stmt->execute([$title, $description, $category, $client, $dbProjectDate, $imageUrl, $orderNum, $isActive, $id])) {
                    setFlash('success', 'Portofolio berhasil diperbarui.');
                } else {
                    setFlash('danger', 'Gagal memperbarui portofolio.');
                }
            }
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM landing_portfolios WHERE id = ?");
        if ($stmt->execute([$id])) {
            setFlash('success', 'Portofolio berhasil dihapus.');
        } else {
            setFlash('danger', 'Gagal menghapus portofolio.');
        }
    }
    
    header('Location: ' . APP_URL . '/modules/cms/portfolio.php');
    exit;
}

// Fetch Portfolios
$portfolios = $pdo->query("SELECT * FROM landing_portfolios ORDER BY order_num ASC, id DESC")->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="card card-outline card-primary">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title">Pengaturan Galeri Portofolio Proyek</h3>
        <div class="ml-auto">
            <button type="button" class="btn btn-primary btn-sm" id="btnTambah">
                <i class="fas fa-plus mr-1"></i> Tambah Proyek
            </button>
        </div>
    </div>
    <div class="card-body">
        <table id="portfolioTable" class="table table-bordered table-striped w-100" >
            <thead>
                <tr>
                    <th width="5%">No</th>
                    <th width="15%">Foto Proyek</th>
                    <th width="20%">Judul Proyek</th>
                    <th width="15%">Kategori</th>
                    <th width="20%">Klien / Tanggal</th>
                    <th width="10%">Urutan</th>
                    <th width="10%">Status</th>
                    <th width="5%" class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($portfolios as $i => $p): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td class="text-center">
                        <img src="<?= sanitize($p['image_url']) ?>" alt="Proyek" style="max-width: 100px; max-height: 60px; object-fit: cover; border: 1px solid var(--border-color); border-radius: 4px;">
                    </td>
                    <td>
                        <strong><?= sanitize($p['title']) ?></strong>
                        <div class="text-muted" style="font-size:11px;"><?= sanitize(substr($p['description'], 0, 80)) ?>...</div>
                    </td>
                    <td>
                        <span class="badge badge-info" style="font-size:11px;"><?= sanitize($p['category']) ?></span>
                    </td>
                    <td>
                        <div>Klien: <strong><?= sanitize($p['client'] ?: '-') ?></strong></div>
                        <div class="text-muted" style="font-size:11px;">Selesai: <?= $p['project_date'] ? formatDate($p['project_date']) : '-' ?></div>
                    </td>
                    <td><?= $p['order_num'] ?></td>
                    <td>
                        <?php if ($p['is_active']): ?>
                            <span class="badge badge-success">Aktif</span>
                        <?php else: ?>
                            <span class="badge badge-secondary">Non-Aktif</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-center">
                        <div class="btn-group">
                            <button type="button" class="btn btn-info btn-sm btn-edit" 
                                    data-id="<?= $p['id'] ?>"
                                    data-title="<?= sanitize($p['title']) ?>"
                                    data-description="<?= sanitize($p['description']) ?>"
                                    data-category="<?= sanitize($p['category']) ?>"
                                    data-client="<?= sanitize($p['client']) ?>"
                                    data-date="<?= $p['project_date'] ?>"
                                    data-image="<?= sanitize($p['image_url']) ?>"
                                    data-order="<?= $p['order_num'] ?>"
                                    data-active="<?= $p['is_active'] ?>"
                                    title="Ubah">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button type="button" class="btn btn-danger btn-sm btn-cms-delete" 
                                    data-id="<?= $p['id'] ?>" 
                                    data-title="<?= sanitize($p['title']) ?>"
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
    <div class="modal-dialog modal-lg" role="document">
        <form action="" method="POST" enctype="multipart/form-data" id="portfolioForm">
            <input type="hidden" name="action" id="formAction" value="create">
            <input type="hidden" name="id" id="formId" value="">
            <input type="hidden" name="current_image" id="formCurrentImage" value="">
            
            <div class="modal-content" style="border-radius: 4px;">
                <div class="modal-header bg-dark text-white" style="border-bottom: 2px solid var(--accent);">
                    <h5 class="modal-title" id="modalTitle">Tambah Portofolio Proyek</h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-8 form-group">
                            <label>Judul Proyek / Nama Bangunan <span class="text-danger">*</span></label>
                            <input type="text" name="title" id="inputTitle" class="form-control" required placeholder="Contoh: Gedung Ruko Batam Center">
                        </div>
                        <div class="col-md-4 form-group">
                            <label>Kategori Proyek <span class="text-danger">*</span></label>
                            <select name="category" id="inputCategory" class="form-control" required>
                                <option value="Residensial">Residensial (Rumah, Villa)</option>
                                <option value="Komersial">Komersial (Ruko, Kantor, Toko)</option>
                                <option value="Infrastruktur">Infrastruktur (Jalan, Jembatan, Gudang)</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row mt-3">
                        <div class="col-md-6 form-group">
                            <label>Nama Pemilik / Klien (Opsional)</label>
                            <input type="text" name="client" id="inputClient" class="form-control" placeholder="Contoh: PT. Royal Properti">
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Tanggal Proyek Selesai (Opsional)</label>
                            <input type="date" name="project_date" id="inputDate" class="form-control">
                        </div>
                    </div>

                    <div class="form-group mt-3">
                        <label>Deskripsi Proyek</label>
                        <textarea name="description" id="inputDescription" class="form-control" rows="3" placeholder="Jelaskan spesifikasi pengerjaan, material beton yang dipakai, dsb..."></textarea>
                    </div>
                    
                    <div class="form-group mt-3">
                        <label>Foto Proyek <span class="text-danger">*</span></label>
                        <div class="custom-file mb-2">
                            <input type="file" name="image" class="custom-file-input" id="inputImage" accept="image/*">
                            <label class="custom-file-label" for="inputImage">Pilih file...</label>
                        </div>
                        <div class="text-center my-2" id="previewContainer" style="display:none;">
                            <p style="font-size:12px;color:#64748b;" class="mb-1">Foto saat ini:</p>
                            <img src="" id="imgPreview" style="max-height: 120px; border: 1px solid var(--border-color); border-radius: 4px;">
                        </div>
                        <div class="text-muted" style="font-size:12px;">Atau masukkan URL foto langsung:</div>
                        <input type="text" name="image_url_text" id="inputImageUrlText" class="form-control mt-1" placeholder="https://unsplash.com/...">
                    </div>

                    <div class="row mt-3">
                        <div class="col-md-6 form-group">
                            <label>Nomor Urutan</label>
                            <input type="number" name="order_num" id="inputOrder" class="form-control" value="1" min="0">
                        </div>
                        <div class="col-md-6 form-group d-flex align-items-center mt-4 pt-2">
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" name="is_active" class="custom-control-input" id="inputActive" checked>
                                <label class="custom-control-label font-weight-bold" for="inputActive">Aktifkan di Portofolio</label>
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
    initDataTable('#portfolioTable');

    $('.custom-file-input').on('change', function() {
        let fileName = $(this).val().split('\\').pop();
        $(this).next('.custom-file-label').addClass("selected").html(fileName);
    });

    // Reset form for create
    $('#btnTambah').click(function() {
        $('#portfolioForm')[0].reset();
        $('#formAction').val('create');
        $('#formId').val('');
        $('#formCurrentImage').val('');
        $('#previewContainer').hide();
        $('#imgPreview').attr('src', '');
        $('.custom-file-label').html('Pilih file...');
        $('#modalTitle').text('Tambah Portofolio Proyek Baru');
        $('#formModal').modal('show');
    });

    // Populate form for edit
    $('.btn-edit').click(function() {
        $('#portfolioForm')[0].reset();
        $('#formAction').val('update');
        $('#formId').val($(this).data('id'));
        
        $('#inputTitle').val($(this).data('title'));
        $('#inputDescription').val($(this).data('description'));
        $('#inputCategory').val($(this).data('category'));
        $('#inputClient').val($(this).data('client'));
        $('#inputDate').val($(this).data('date'));
        $('#inputOrder').val($(this).data('order'));
        
        const currentImg = $(this).data('image');
        $('#formCurrentImage').val(currentImg);
        $('#imgPreview').attr('src', currentImg);
        $('#previewContainer').show();
        $('.custom-file-label').html('Ganti file foto...');

        if ($(this).data('active') == 1) {
            $('#inputActive').prop('checked', true);
        } else {
            $('#inputActive').prop('checked', false);
        }

        $('#modalTitle').text('Edit Portofolio Proyek');
        $('#formModal').modal('show');
    });

    // Handle delete action
    $('.btn-cms-delete').click(function() {
        const id = $(this).data('id');
        const title = $(this).data('title');
        
        confirmAction('Hapus Proyek?', `Anda yakin ingin menghapus proyek "${title}" dari portofolio?`, function() {
            $('#actionId').val(id);
            $('#actionForm').submit();
        });
    });
});
</script>
JS;
require_once __DIR__ . '/../../includes/footer.php';
?>
