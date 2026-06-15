<?php
/**
 * CMS - Manage Tips & Tricks
 */
require_once __DIR__ . '/../../includes/auth.php';
requirePermission('cms_landing');

$pageTitle = 'CMS Kelola Tips & Trick';
$breadcrumbs = [
    ['label' => 'CMS Landing', 'url' => '#'],
    ['label' => 'Tips & Trick']
];

$uploadDir = UPLOADS_PATH . '/landing';

// Handle Add / Edit / Delete Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create' || $action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $excerpt = trim($_POST['excerpt'] ?? '');
        $author = trim($_POST['author'] ?? 'Admin');
        $publishedDate = trim($_POST['published_date'] ?? null);
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        
        $imageUrl = $_POST['current_image'] ?? '';

        // Handle Image Upload
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $uploadResult = uploadFile($_FILES['image'], $uploadDir, ['jpg', 'jpeg', 'png', 'gif']);
            if ($uploadResult['success']) {
                $imageUrl = APP_URL . '/assets/uploads/landing/' . $uploadResult['filename'];
            } else {
                setFlash('danger', 'Gagal mengunggah foto artikel: ' . $uploadResult['message']);
                header('Location: ' . APP_URL . '/modules/cms/tips.php');
                exit;
            }
        } elseif (empty($imageUrl) && isset($_POST['image_url_text']) && !empty(trim($_POST['image_url_text']))) {
            $imageUrl = trim($_POST['image_url_text']);
        }

        if (empty($title) || empty($content) || empty($imageUrl)) {
            setFlash('danger', 'Judul, Isi Konten, dan Gambar Utama wajib diisi.');
        } else {
            // Default published date to today if empty
            $dbPublishedDate = empty($publishedDate) ? date('Y-m-d') : $publishedDate;
            
            // Auto generate excerpt if empty
            if (empty($excerpt)) {
                $excerpt = substr(strip_tags($content), 0, 150) . '...';
            }

            if ($action === 'create') {
                $stmt = $pdo->prepare("INSERT INTO landing_tips (title, content, excerpt, author, image_url, published_date, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)");
                if ($stmt->execute([$title, $content, $excerpt, $author, $imageUrl, $dbPublishedDate, $isActive])) {
                    setFlash('success', 'Tips/Artikel baru berhasil ditambahkan.');
                } else {
                    setFlash('danger', 'Gagal menambahkan artikel.');
                }
            } else {
                $stmt = $pdo->prepare("UPDATE landing_tips SET title = ?, content = ?, excerpt = ?, author = ?, image_url = ?, published_date = ?, is_active = ? WHERE id = ?");
                if ($stmt->execute([$title, $content, $excerpt, $author, $imageUrl, $dbPublishedDate, $isActive, $id])) {
                    setFlash('success', 'Artikel berhasil diperbarui.');
                } else {
                    setFlash('danger', 'Gagal memperbarui artikel.');
                }
            }
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM landing_tips WHERE id = ?");
        if ($stmt->execute([$id])) {
            setFlash('success', 'Artikel berhasil dihapus.');
        } else {
            setFlash('danger', 'Gagal menghapus artikel.');
        }
    }
    
    header('Location: ' . APP_URL . '/modules/cms/tips.php');
    exit;
}

// Fetch Tips & Tricks
$tips = $pdo->query("SELECT * FROM landing_tips ORDER BY published_date DESC, id DESC")->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title">Pengaturan Artikel Tips & Trick</h3>
        <div class="ml-auto">
            <button type="button" class="btn btn-primary btn-sm" id="btnTambah">
                <i class="fas fa-plus mr-1"></i> Tambah Artikel
            </button>
        </div>
    </div>
    <div class="card-body">
        <table id="tipsTable" class="table table-bordered table-striped w-100" style="font-size: 13.5px;">
            <thead>
                <tr>
                    <th width="5%">No</th>
                    <th width="15%">Gambar</th>
                    <th width="25%">Judul Artikel</th>
                    <th width="15%">Penulis</th>
                    <th width="15%">Tanggal Rilis</th>
                    <th width="10%">Status</th>
                    <th width="15%" class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tips as $i => $t): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td class="text-center">
                        <img src="<?= sanitize($t['image_url']) ?>" alt="Artikel" style="max-width: 100px; max-height: 60px; object-fit: cover; border: 1px solid var(--border-color); border-radius: 4px;">
                    </td>
                    <td>
                        <strong><?= sanitize($t['title']) ?></strong>
                        <div class="text-muted font-italic" style="font-size:11px;"><?= sanitize(substr($t['excerpt'], 0, 80)) ?>...</div>
                    </td>
                    <td><?= sanitize($t['author'] ?: 'Admin') ?></td>
                    <td><?= $t['published_date'] ? formatDate($t['published_date']) : '-' ?></td>
                    <td>
                        <?php if ($t['is_active']): ?>
                            <span class="badge badge-success">Aktif</span>
                        <?php else: ?>
                            <span class="badge badge-secondary">Non-Aktif</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-center">
                        <div class="btn-group">
                            <button type="button" class="btn btn-info btn-sm btn-edit" 
                                    data-id="<?= $t['id'] ?>"
                                    data-title="<?= sanitize($t['title']) ?>"
                                    data-content="<?= sanitize($t['content']) ?>"
                                    data-excerpt="<?= sanitize($t['excerpt']) ?>"
                                    data-author="<?= sanitize($t['author']) ?>"
                                    data-date="<?= $t['published_date'] ?>"
                                    data-image="<?= sanitize($t['image_url']) ?>"
                                    data-active="<?= $t['is_active'] ?>"
                                    title="Edit">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button type="button" class="btn btn-danger btn-sm btn-cms-delete" 
                                    data-id="<?= $t['id'] ?>" 
                                    data-title="<?= sanitize($t['title']) ?>"
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
        <form action="" method="POST" enctype="multipart/form-data" id="tipsForm">
            <input type="hidden" name="action" id="formAction" value="create">
            <input type="hidden" name="id" id="formId" value="">
            <input type="hidden" name="current_image" id="formCurrentImage" value="">
            
            <div class="modal-content" style="border-radius: 4px;">
                <div class="modal-header bg-dark text-white" style="border-bottom: 2px solid var(--accent);">
                    <h5 class="modal-title" id="modalTitle">Tambah Artikel Baru</h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-8 form-group">
                            <label>Judul Artikel <span class="text-danger">*</span></label>
                            <input type="text" name="title" id="inputTitle" class="form-control" required placeholder="Contoh: Mengatasi Retak Rambut Pada Dinding Semen">
                        </div>
                        <div class="col-md-4 form-group">
                            <label>Penulis / Author</label>
                            <input type="text" name="author" id="inputAuthor" class="form-control" value="Admin" placeholder="Contoh: Ir. Hermawan">
                        </div>
                    </div>
                    
                    <div class="row mt-3">
                        <div class="col-md-6 form-group">
                            <label>Tanggal Rilis</label>
                            <input type="date" name="published_date" id="inputDate" class="form-control" value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-md-6 form-group d-flex align-items-center mt-4 pt-2">
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" name="is_active" class="custom-control-input" id="inputActive" checked>
                                <label class="custom-control-label font-weight-bold" for="inputActive">Tampilkan Artikel Publik</label>
                            </div>
                        </div>
                    </div>

                    <div class="form-group mt-3">
                        <label>Ringkasan Pendek (Excerpt / Kutipan)</label>
                        <textarea name="excerpt" id="inputExcerpt" class="form-control" rows="2" placeholder="Kosongkan jika ingin digenerate otomatis dari isi konten..."></textarea>
                    </div>

                    <div class="form-group mt-3">
                        <label>Isi Konten Artikel <span class="text-danger">*</span></label>
                        <textarea name="content" id="inputContent" class="form-control" rows="8" required placeholder="Tulis isi tips & tricks di sini secara detail..."></textarea>
                    </div>
                    
                    <div class="form-group mt-3">
                        <label>Gambar Utama Artikel <span class="text-danger">*</span></label>
                        <div class="custom-file mb-2">
                            <input type="file" name="image" class="custom-file-input" id="inputImage" accept="image/*">
                            <label class="custom-file-label" for="inputImage">Pilih file...</label>
                        </div>
                        <div class="text-center my-2" id="previewContainer" style="display:none;">
                            <p style="font-size:12px;color:#64748b;" class="mb-1">Gambar saat ini:</p>
                            <img src="" id="imgPreview" style="max-height: 120px; border: 1px solid var(--border-color); border-radius: 4px;">
                        </div>
                        <div class="text-muted" style="font-size:12px;">Atau masukkan URL gambar langsung:</div>
                        <input type="text" name="image_url_text" id="inputImageUrlText" class="form-control mt-1" placeholder="https://unsplash.com/...">
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
    initDataTable('#tipsTable');

    $('.custom-file-input').on('change', function() {
        let fileName = $(this).val().split('\\').pop();
        $(this).next('.custom-file-label').addClass("selected").html(fileName);
    });

    // Reset form for create
    $('#btnTambah').click(function() {
        $('#tipsForm')[0].reset();
        $('#formAction').val('create');
        $('#formId').val('');
        $('#formCurrentImage').val('');
        $('#previewContainer').hide();
        $('#imgPreview').attr('src', '');
        $('.custom-file-label').html('Pilih file...');
        $('#modalTitle').text('Tambah Artikel Tips & Trick Baru');
        $('#inputDate').val(new Date().toISOString().substring(0, 10));
        $('#modalTitle').text('Tambah Artikel Baru');
        $('#formModal').modal('show');
    });

    // Populate form for edit
    $('.btn-edit').click(function() {
        $('#tipsForm')[0].reset();
        $('#formAction').val('update');
        $('#formId').val($(this).data('id'));
        
        $('#inputTitle').val($(this).data('title'));
        $('#inputContent').val($(this).data('content'));
        $('#inputExcerpt').val($(this).data('excerpt'));
        $('#inputAuthor').val($(this).data('author'));
        $('#inputDate').val($(this).data('date'));
        
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

        $('#modalTitle').text('Edit Artikel Tips & Trick');
        $('#formModal').modal('show');
    });

    // Handle delete action
    $('.btn-cms-delete').click(function() {
        const id = $(this).data('id');
        const title = $(this).data('title');
        
        confirmAction('Hapus Artikel?', `Anda yakin ingin menghapus artikel "${title}"?`, function() {
            $('#actionId').val(id);
            $('#actionForm').submit();
        });
    });
});
</script>
JS;
require_once __DIR__ . '/../../includes/footer.php';
?>
