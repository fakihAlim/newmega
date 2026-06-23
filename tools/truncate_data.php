<?php
/**
 * Tool: Truncate Selected Tables
 * Allows Super Admin to choose which tables to truncate
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';

// Only Super Admin can access this
if (!hasRole('super_admin')) {
    die('<div style="color:red; font-family:sans-serif; text-align:center; padding:50px;">
        <h2>🚫 Akses Ditolak</h2>
        <p>Hanya Super Admin yang dapat menjalankan perintah ini.</p>
    </div>');
}

// Fetch all tables from the database dynamically
try {
    $stmt = $pdo->query("SHOW TABLES");
    $all_tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    die("Gagal mengambil daftar tabel: " . $e->getMessage());
}

// Categorize tables for better UX
$transaction_keywords = [
    'request', 'order', 'link', 'receiving', 'transaction', 'transfer', 
    'payment', 'quotation', 'invoice', 'timesheet', 'claim'
];

$transaction_tables = [];
$master_tables = [];

foreach ($all_tables as $table) {
    $is_transaction = false;
    foreach ($transaction_keywords as $keyword) {
        if (strpos($table, $keyword) !== false) {
            $is_transaction = true;
            break;
        }
    }
    
    if ($is_transaction) {
        $transaction_tables[] = $table;
    } else {
        $master_tables[] = $table;
    }
}

$success_msg = '';
$error_msg = '';
$results = [];

// Handle post submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'truncate_selected') {
    $selected_tables = isset($_POST['tables']) ? $_POST['tables'] : [];
    $confirm_input = isset($_POST['confirm_text']) ? trim($_POST['confirm_text']) : '';

    if (empty($selected_tables)) {
        $error_msg = "Silakan pilih setidaknya satu tabel untuk dihapus.";
    } elseif ($confirm_input !== 'HAPUS_PERMANEN') {
        $error_msg = "Konfirmasi teks tidak cocok. Harap ketik 'HAPUS_PERMANEN' dengan benar.";
    } else {
        try {
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");

            foreach ($selected_tables as $table) {
                // Ensure table is in our list of database tables to prevent SQL injection
                if (in_array($table, $all_tables)) {
                    $pdo->exec("TRUNCATE TABLE `$table`");
                    $results[$table] = true;
                } else {
                    $results[$table] = false;
                }
            }

            // Special handling: if items table is truncated, reset current_stock to 0.
            // If items table is NOT truncated but stock transactions are, we might also want to reset stock.
            if (in_array('items', $all_tables) && !in_array('items', $selected_tables) && in_array('stock_transactions', $selected_tables)) {
                $pdo->exec("UPDATE items SET current_stock = 0");
                $results['[Update] Reset current_stock di tabel items'] = true;
            }

            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
            
            // Log the truncation action
            logActivity('truncate', 'tools', 'Mengosongkan tabel: ' . implode(', ', $selected_tables));
            
            $success_msg = "Proses truncate tabel terpilih selesai dijalankan.";
        } catch (Exception $e) {
            // Ensure foreign key checks are re-enabled even if error occurs
            try {
                $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
            } catch (Exception $ex) {}
            $error_msg = "Gagal memproses truncate: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pilih & Truncate Data Tabel</title>
    <!-- AdminLTE & FontAwesome -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body { background: #f4f6f9; padding: 30px 15px; }
        .container-custom { max-width: 900px; margin: auto; }
        .danger-zone { border: 2px dashed #dc3545; padding: 20px; border-radius: 8px; background: #fff5f5; }
        .card-title { font-weight: bold; }
        .table-list-container { max-height: 400px; overflow-y: auto; padding: 10px; border: 1px solid #dee2e6; border-radius: 4px; background: #fff; }
        .custom-control-label { cursor: pointer; }
    </style>
</head>
<body>
    <div class="container-custom">
        <div class="card card-outline card-danger shadow-lg">
            <div class="card-header bg-white">
                <h3 class="card-title text-danger">
                    <i class="fas fa-exclamation-triangle mr-2"></i> TRUNCATE DATA TABEL (PILIH SENDIRI)
                </h3>
                <div class="card-tools">
                    <a href="../index.php" class="btn btn-tool text-secondary"><i class="fas fa-times fa-lg"></i></a>
                </div>
            </div>
            
            <div class="card-body">
                <?php if ($success_msg): ?>
                    <div class="alert alert-success alert-dismissible">
                        <h5><i class="icon fas fa-check"></i> Sukses!</h5>
                        <?php echo htmlspecialchars($success_msg); ?>
                    </div>
                    
                    <div class="card card-secondary card-outline">
                        <div class="card-header">
                            <h3 class="card-title">Hasil Eksekusi</h3>
                        </div>
                        <div class="card-body p-0">
                            <table class="table table-striped table-sm mb-0">
                                <thead>
                                    <tr>
                                        <th>Nama Tabel / Proses</th>
                                        <th class="text-right" style="width: 150px;">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($results as $tbl => $status): ?>
                                        <tr>
                                            <td><code><?php echo htmlspecialchars($tbl); ?></code></td>
                                            <td class="text-right">
                                                <?php if ($status): ?>
                                                    <span class="badge badge-success"><i class="fas fa-check mr-1"></i> Berhasil di-truncate</span>
                                                <?php else: ?>
                                                    <span class="badge badge-danger"><i class="fas fa-times mr-1"></i> Gagal / Dilewati</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="text-center mt-4">
                        <a href="truncate_data.php" class="btn btn-primary"><i class="fas fa-redo mr-2"></i> Truncate Lagi</a>
                        <a href="../index.php" class="btn btn-secondary"><i class="fas fa-home mr-2"></i> Dashboard</a>
                    </div>
                    
                <?php else: ?>
                    
                    <?php if ($error_msg): ?>
                        <div class="alert alert-danger alert-dismissible">
                            <h5><i class="icon fas fa-ban"></i> Kesalahan!</h5>
                            <?php echo htmlspecialchars($error_msg); ?>
                        </div>
                    <?php endif; ?>

                    <div class="alert alert-warning">
                        <h5><i class="icon fas fa-info-circle"></i> Petunjuk Penggunaan</h5>
                        <p class="mb-0">
                            Pilih tabel-tabel di bawah ini yang ingin Anda hapus seluruh datanya (TRUNCATE). 
                            Tindakan ini tidak bisa dibatalkan dan semua data relasional akan disesuaikan dengan menonaktifkan pengecekan kunci asing sementara.
                        </p>
                    </div>

                    <form action="" method="POST" id="truncateForm">
                        <input type="hidden" name="action" value="truncate_selected">
                        
                        <div class="row">
                            <!-- Kategori Transaksi -->
                            <div class="col-md-6 mb-3">
                                <div class="card card-info card-outline h-100">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <h3 class="card-title text-info"><i class="fas fa-exchange-alt mr-2"></i> Data Transaksi</h3>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-2">
                                            <button type="button" class="btn btn-xs btn-outline-info" onclick="toggleGroup('transaction', true)">Pilih Semua</button>
                                            <button type="button" class="btn btn-xs btn-outline-secondary" onclick="toggleGroup('transaction', false)">Batalkan Semua</button>
                                        </div>
                                        <div class="table-list-container">
                                            <?php foreach ($transaction_tables as $table): ?>
                                                <div class="custom-control custom-checkbox py-1">
                                                    <input class="custom-control-input chk-table group-transaction" type="checkbox" name="tables[]" id="tbl_<?php echo $table; ?>" value="<?php echo $table; ?>">
                                                    <label for="tbl_<?php echo $table; ?>" class="custom-control-label font-weight-normal">
                                                        <code><?php echo $table; ?></code>
                                                    </label>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Kategori Master Data -->
                            <div class="col-md-6 mb-3">
                                <div class="card card-warning card-outline h-100">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <h3 class="card-title text-warning"><i class="fas fa-database mr-2"></i> Data Master & System</h3>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-2">
                                            <button type="button" class="btn btn-xs btn-outline-warning" onclick="toggleGroup('master', true)">Pilih Semua</button>
                                            <button type="button" class="btn btn-xs btn-outline-secondary" onclick="toggleGroup('master', false)">Batalkan Semua</button>
                                        </div>
                                        <div class="table-list-container">
                                            <?php foreach ($master_tables as $table): ?>
                                                <div class="custom-control custom-checkbox py-1">
                                                    <input class="custom-control-input chk-table group-master" type="checkbox" name="tables[]" id="tbl_<?php echo $table; ?>" value="<?php echo $table; ?>">
                                                    <label for="tbl_<?php echo $table; ?>" class="custom-control-label font-weight-normal text-danger">
                                                        <code><?php echo $table; ?></code> <small class="text-muted">(Sensitif)</small>
                                                    </label>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Danger Zone Confirmation -->
                        <div class="danger-zone mt-4">
                            <h5 class="text-danger font-weight-bold"><i class="fas fa-skull-crossbones mr-2"></i> Konfirmasi Penghapusan Permanen</h5>
                            <p>Data dari tabel yang dicentang di atas akan dihapus secara menyeluruh dari database.</p>
                            
                            <div class="form-group mb-0">
                                <label for="confirm_text">Ketik <strong class="text-danger">HAPUS_PERMANEN</strong> di bawah ini untuk konfirmasi:</label>
                                <input type="text" class="form-control col-md-6" id="confirm_text" name="confirm_text" placeholder="Ketik di sini..." required autocomplete="off">
                            </div>

                            <button type="submit" class="btn btn-danger btn-lg mt-3 btn-block shadow-sm" onclick="return confirmExecution()">
                                <i class="fas fa-trash-alt mr-2"></i> EKSEKUSI TRUNCATE TABEL TERPILIH
                            </button>
                        </div>
                    </form>
                    
                <?php endif; ?>
            </div>
            <div class="card-footer text-right">
                <a href="../index.php" class="btn btn-default">Kembali ke Dashboard</a>
            </div>
        </div>
    </div>

    <script>
        function toggleGroup(group, value) {
            const checkboxes = document.querySelectorAll('.group-' + group);
            checkboxes.forEach(chk => {
                chk.checked = value;
            });
        }

        function confirmExecution() {
            const checkedCount = document.querySelectorAll('.chk-table:checked').length;
            if (checkedCount === 0) {
                alert("Pilih minimal satu tabel sebelum melanjutkan eksekusi.");
                return false;
            }

            const confirmText = document.getElementById('confirm_text').value.trim();
            if (confirmText !== 'HAPUS_PERMANEN') {
                alert("Harap ketik 'HAPUS_PERMANEN' secara persis untuk konfirmasi.");
                return false;
            }

            return confirm("PERINGATAN AKHIR! Apakah Anda benar-benar yakin ingin menghapus data dari " + checkedCount + " tabel terpilih?");
        }
    </script>
</body>
</html>
