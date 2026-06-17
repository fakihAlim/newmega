<?php
/**
 * Settings - General Settings
 */
require_once __DIR__ . '/../../includes/auth.php';

// Prevent non-admin users from accessing if roles exist, 
// for now we just make sure they are logged in.

$pageTitle = 'Pengaturan Umum';
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => APP_URL . '/modules/dashboard/index.php'],
    ['label' => 'Pengaturan']
];

$envFile = __DIR__ . '/../../env.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newApiKey = trim($_POST['gemini_api_key'] ?? '');
    
    if (file_exists($envFile)) {
        $envContent = file_get_contents($envFile);
        
        // Use Regex to safely replace the API key definition
        $pattern = "/define\s*\(\s*'GOOGLE_GEMINI_API_KEY'\s*,\s*'(.*?)'\s*\)\s*;/i";
        $replacement = "define('GOOGLE_GEMINI_API_KEY', '{$newApiKey}');";
        
        if (preg_match($pattern, $envContent)) {
            $newEnvContent = preg_replace($pattern, $replacement, $envContent);
            if (file_put_contents($envFile, $newEnvContent)) {
                setFlash('success', 'Berhasil memperbarui Google Gemini API Key.');
                // Refresh the page so the constant is re-read from the file on the next load,
                // although it won't affect this exact run since it's already defined.
                header('Location: ' . APP_URL . '/modules/settings/index.php');
                exit;
            } else {
                setFlash('danger', 'Gagal menyimpan perubahan ke file env.php. Pastikan folder memiliki izin tulis (write permissions).');
            }
        } else {
            // If the pattern wasn't found (e.g. it was deleted manually)
            $appendContent = "\n// API Key ditambahkan secara otomatis\ndefine('GOOGLE_GEMINI_API_KEY', '{$newApiKey}');\n";
            if (file_put_contents($envFile, $appendContent, FILE_APPEND)) {
                setFlash('success', 'Berhasil menambahkan Google Gemini API Key baru.');
                header('Location: ' . APP_URL . '/modules/settings/index.php');
                exit;
            } else {
                setFlash('danger', 'Gagal menulis ke file env.php.');
            }
        }
    } else {
        setFlash('danger', 'File env.php tidak ditemukan di server.');
    }
}

// Get the current API key safely
// In PHP, constants defined with define() are accessible directly.
$currentApiKey = defined('GOOGLE_GEMINI_API_KEY') ? GOOGLE_GEMINI_API_KEY : '';

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="row">
    <div class="col-md-6 mx-auto">
        <div class="card card-outline card-primary">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-cogs mr-2"></i> Pengaturan Integrasi AI</h3>
            </div>
            
            <form method="POST">
                <div class="card-body">                    
                    <div class="form-group">
                        <label>Google Gemini API Key</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fas fa-key"></i></span>
                            </div>
                            <!-- Type password initially so it's not shoulder-surfed, with an eye icon to toggle -->
                            <input type="password" name="gemini_api_key" id="gemini_api_key" class="form-control" value="<?= sanitize($currentApiKey) ?>" required placeholder="AIzaSyA... dsb">
                            <div class="input-group-append">
                                <button type="button" class="btn btn-outline-secondary" id="toggleKey"><i class="fas fa-eye"></i></button>
                            </div>
                        </div>
                        <small class="text-muted mt-2 d-block">Digunaan Untuk Menjalankan Fitur AI di Aplikasi. Untuk menggantinya silahkan masuk di url: https://aistudio.google.com/ dan login dengan akun google anda </small>
                    </div>
                    
                </div>
                
                <div class="card-footer text-right">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Simpan Pengaturan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$extraJS = <<<'JS'
<script>
$(document).ready(function() {
    $('#toggleKey').on('click', function() {
        var input = $('#gemini_api_key');
        var icon = $(this).find('i');
        
        if (input.attr('type') === 'password') {
            input.attr('type', 'text');
            icon.removeClass('fa-eye').addClass('fa-eye-slash');
        } else {
            input.attr('type', 'password');
            icon.removeClass('fa-eye-slash').addClass('fa-eye');
        }
    });
});
</script>
JS;
require_once __DIR__ . '/../../includes/footer.php';
?>
