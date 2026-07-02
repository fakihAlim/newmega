<?php
/**
 * CMS - Manage Banners
 */
require_once __DIR__ . '/../../includes/auth.php';
requirePermission('cms_landing');

$pageTitle = 'CMS Kelola Banner';
$breadcrumbs = [
    ['label' => 'CMS Landing', 'url' => '#'],
    ['label' => 'Banner']
];

$uploadDir = UPLOADS_PATH . '/landing';

// Handle Add / Edit Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create' || $action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $subtitle = trim($_POST['subtitle'] ?? '');
        $buttonText = trim($_POST['button_text'] ?? '');
        $buttonUrl = trim($_POST['button_url'] ?? '');
        $orderNum = (int)($_POST['order_num'] ?? 0);
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        
        $imageUrl = $_POST['current_image'] ?? '';

        // Handle File Upload
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $uploadResult = uploadFile($_FILES['image'], $uploadDir, ['jpg', 'jpeg', 'png', 'gif']);
            if ($uploadResult['success']) {
                $imageUrl = APP_URL . '/assets/uploads/landing/' . $uploadResult['filename'];
            } else {
                setFlash('danger', 'Gagal mengunggah gambar: ' . $uploadResult['message']);
                header('Location: ' . APP_URL . '/modules/cms/banner.php');
                exit;
            }
        } elseif (empty($imageUrl) && isset($_POST['image_url_text']) && !empty(trim($_POST['image_url_text']))) {
            $imageUrl = trim($_POST['image_url_text']);
        }

        if (empty($title) || empty($imageUrl)) {
            setFlash('danger', 'Judul dan Gambar wajib diisi.');
        } else {
            if ($action === 'create') {
                $stmt = $pdo->prepare("INSERT INTO landing_banners (title, subtitle, image_url, button_text, button_url, order_num, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)");
                if ($stmt->execute([$title, $subtitle, $imageUrl, $buttonText, $buttonUrl, $orderNum, $isActive])) {
                    setFlash('success', 'Banner baru berhasil ditambahkan.');
                } else {
                    setFlash('danger', 'Gagal menambahkan banner.');
                }
            } else {
                $stmt = $pdo->prepare("UPDATE landing_banners SET title = ?, subtitle = ?, image_url = ?, button_text = ?, button_url = ?, order_num = ?, is_active = ? WHERE id = ?");
                if ($stmt->execute([$title, $subtitle, $imageUrl, $buttonText, $buttonUrl, $orderNum, $isActive, $id])) {
                    setFlash('success', 'Banner berhasil diperbarui.');
                } else {
                    setFlash('danger', 'Gagal memperbarui banner.');
                }
            }
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM landing_banners WHERE id = ?");
        if ($stmt->execute([$id])) {
            setFlash('success', 'Banner berhasil dihapus.');
        } else {
            setFlash('danger', 'Gagal menghapus banner.');
        }
    }
    
    header('Location: ' . APP_URL . '/modules/cms/banner.php');
    exit;
}

// Fetch Banners
$banners = $pdo->query("SELECT * FROM landing_banners ORDER BY order_num ASC, id DESC")->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="card card-outline card-primary">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title">Pengaturan Hero Banner Landing Page</h3>
        <div class="ml-auto">
            <button type="button" class="btn btn-primary btn-sm" id="btnTambah">
                <i class="fas fa-plus mr-1"></i> Tambah Banner
            </button>
        </div>
    </div>
    <div class="card-body">
        <table id="bannersTable" class="table table-bordered table-striped w-100" >
            <thead>
                <tr>
                    <th width="5%">No</th>
                    <th width="15%">Gambar Preview</th>
                    <th width="25%">Judul Banner</th>
                    <th width="25%">Sub-judul</th>
                    <th width="10%">Urutan</th>
                    <th width="10%">Status</th>
                    <th width="10%" class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($banners as $i => $b): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td class="text-center">
                        <img src="<?= sanitize($b['image_url']) ?>" alt="Preview" style="max-width: 100px; max-height: 60px; object-fit: cover; border: 1px solid var(--border-color); border-radius: 4px;">
                    </td>
                    <td><strong><?= sanitize($b['title']) ?></strong></td>
                    <td><?= sanitize($b['subtitle'] ?: '-') ?></td>
                    <td><?= $b['order_num'] ?></td>
                    <td>
                        <?php if ($b['is_active']): ?>
                            <span class="badge badge-success">Aktif</span>
                        <?php else: ?>
                            <span class="badge badge-secondary">Non-Aktif</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-center">
                        <div class="btn-group">
                            <button type="button" class="btn btn-info btn-sm btn-edit" 
                                    data-id="<?= $b['id'] ?>"
                                    data-title="<?= sanitize($b['title']) ?>"
                                    data-subtitle="<?= sanitize($b['subtitle']) ?>"
                                    data-image="<?= sanitize($b['image_url']) ?>"
                                    data-btntext="<?= sanitize($b['button_text']) ?>"
                                    data-btnurl="<?= sanitize($b['button_url']) ?>"
                                    data-order="<?= $b['order_num'] ?>"
                                    data-active="<?= $b['is_active'] ?>"
                                    title="Ubah">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button type="button" class="btn btn-danger btn-sm btn-cms-delete" 
                                    data-id="<?= $b['id'] ?>" 
                                    data-title="<?= sanitize($b['title']) ?>"
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
        <form action="" method="POST" enctype="multipart/form-data" id="bannerForm">
            <input type="hidden" name="action" id="formAction" value="create">
            <input type="hidden" name="id" id="formId" value="">
            <input type="hidden" name="current_image" id="formCurrentImage" value="">
            
            <div class="modal-content" style="border-radius: 4px;">
                <div class="modal-header bg-dark text-white" style="border-bottom: 2px solid var(--accent);">
                    <h5 class="modal-title" id="modalTitle">Tambah Banner Baru</h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Judul Utama <span class="text-danger">*</span></label>
                        <input type="text" name="title" id="inputTitle" class="form-control" required placeholder="Contoh: Membangun dengan Presisi">
                    </div>
                    <div class="form-group mt-3">
                        <label>Sub-judul / Deskripsi Pendek</label>
                        <textarea name="subtitle" id="inputSubtitle" class="form-control" rows="3" placeholder="Deskripsi pendek banner..."></textarea>
                    </div>
                    <div class="row mt-3">
                        <div class="col-md-6 form-group">
                            <label>Teks Tombol CTA (Opsional)</label>
                            <input type="text" name="button_text" id="inputBtnText" class="form-control" placeholder="Contoh: Hubungi Kami">
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Link / URL Tombol CTA (Opsional)</label>
                            <input type="text" name="button_url" id="inputBtnUrl" class="form-control" placeholder="Contoh: #contact atau kalkulator.php">
                        </div>
                    </div>
                    
                    <div class="form-group mt-3">
                        <label>Gambar Banner <span class="text-danger">*</span></label>
                        <div class="custom-file mb-2">
                            <input type="file" name="image" class="custom-file-input" id="inputImage" accept="image/*">
                            <label class="custom-file-label" for="inputImage">Pilih file...</label>
                        </div>
                        <div class="text-center my-2" id="previewContainer" style="display:none;">
                            <p style="font-size:12px;color:#64748b;" class="mb-1">Gambar saat ini:</p>
                            <img src="" id="imgPreview" style="max-height: 120px; border: 1px solid var(--border-color); border-radius: 4px;">
                        </div>
                        <div class="text-muted" style="font-size:12px;">Atau masukkan URL gambar langsung (opsional jika tidak upload):</div>
                        <input type="text" name="image_url_text" id="inputImageUrlText" class="form-control mt-1" placeholder="https://unsplash.com/...">
                    </div>

                    <div class="row mt-3">
                        <div class="col-md-6 form-group">
                            <label>Nomor Urutan Tampilan</label>
                            <input type="number" name="order_num" id="inputOrder" class="form-control" value="1" min="0">
                        </div>
                        <div class="col-md-6 form-group d-flex align-items-center mt-4 pt-2">
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" name="is_active" class="custom-control-input" id="inputActive" checked>
                                <label class="custom-control-label font-weight-bold" for="inputActive">Aktifkan Banner</label>
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
    initDataTable('#bannersTable');

    // Update bootstrap file input label
    $('.custom-file-input').on('change', function() {
        let fileName = $(this).val().split('\\').pop();
        $(this).next('.custom-file-label').addClass("selected").html(fileName);
    });

    // Reset form for create
    $('#btnTambah').click(function() {
        $('#bannerForm')[0].reset();
        $('#formAction').val('create');
        $('#formId').val('');
        $('#formCurrentImage').val('');
        $('#previewContainer').hide();
        $('#imgPreview').attr('src', '');
        $('.custom-file-label').html('Pilih file...');
        $('#modalTitle').text('Tambah Banner Baru');
        $('#formModal').modal('show');
    });

    // Populate form for edit
    $('.btn-edit').click(function() {
        $('#bannerForm')[0].reset();
        $('#formAction').val('update');
        $('#formId').val($(this).data('id'));
        
        $('#inputTitle').val($(this).data('title'));
        $('#inputSubtitle').val($(this).data('subtitle'));
        $('#inputBtnText').val($(this).data('btntext'));
        $('#inputBtnUrl').val($(this).data('btnurl'));
        $('#inputOrder').val($(this).data('order'));
        
        const currentImg = $(this).data('image');
        $('#formCurrentImage').val(currentImg);
        $('#imgPreview').attr('src', currentImg);
        $('#previewContainer').show();
        $('.custom-file-label').html('Ganti file gambar...');

        if ($(this).data('active') == 1) {
            $('#inputActive').prop('checked', true);
        } else {
            $('#inputActive').prop('checked', false);
        }

        $('#modalTitle').text('Edit Data Banner');
        $('#formModal').modal('show');
    });

    // Handle delete action
    $('.btn-cms-delete').click(function() {
        const id = $(this).data('id');
        const title = $(this).data('title');
        
        confirmAction('Hapus Banner?', `Anda yakin ingin menghapus banner "${title}"?`, function() {
            $('#actionId').val(id);
            $('#actionForm').submit();
        });
    });
});
</script>
JS;
require_once __DIR__ . '/../../includes/footer.php';
?>
