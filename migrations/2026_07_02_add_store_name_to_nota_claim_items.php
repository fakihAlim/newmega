<?php
/**
 * Migration: Tambah kolom store_name pada tabel nota_claim_items
 * Tanggal: 2026-07-02
 * Deskripsi: Menambahkan kolom 'store_name' (VARCHAR 255, NULL) untuk menyimpan nama toko
 *            pada setiap item pengeluaran di modul Claim Nota.
 *
 * Cara pakai:
 *   1. Upload file ini ke server
 *   2. Jalankan via browser: https://domain.com/migrations/2026_07_02_add_store_name_to_nota_claim_items.php
 *   3. Pastikan muncul pesan "Migration berhasil"
 *   4. Hapus file ini dari server setelah selesai
 */

require_once __DIR__ . '/../includes/auth.php';

// Hanya Super Admin yang boleh menjalankan migrasi
$user = getCurrentUser();
if (!$user || $user['role'] !== 'super_admin') {
    die('Akses ditolak. Hanya Super Admin yang dapat menjalankan migrasi.');
}

try {
    // Cek apakah kolom store_name sudah ada
    $checkColumn = $pdo->query("SHOW COLUMNS FROM `nota_claim_items` LIKE 'store_name'");
    
    if ($checkColumn->rowCount() > 0) {
        echo "<p style='color: orange; font-weight: bold;'>⚠ Kolom 'store_name' sudah ada di tabel 'nota_claim_items'. Tidak perlu migrasi ulang.</p>";
    } else {
        // Tambahkan kolom store_name setelah kolom group_name
        $pdo->exec("ALTER TABLE `nota_claim_items` ADD COLUMN `store_name` VARCHAR(255) NULL DEFAULT NULL AFTER `group_name`");
        echo "<p style='color: green; font-weight: bold;'>✅ Migration berhasil! Kolom 'store_name' telah ditambahkan ke tabel 'nota_claim_items'.</p>";
    }

    // Tampilkan struktur tabel terkini untuk verifikasi
    echo "<h4>Struktur tabel nota_claim_items saat ini:</h4>";
    echo "<table border='1' cellpadding='5' cellspacing='0' style='border-collapse: collapse; font-family: monospace;'>";
    echo "<tr style='background: #f0f0f0;'><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    
    $columns = $pdo->query("DESCRIBE `nota_claim_items`");
    while ($col = $columns->fetch()) {
        $highlight = ($col['Field'] === 'store_name') ? "style='background: #d4edda;'" : "";
        echo "<tr {$highlight}>";
        echo "<td>{$col['Field']}</td>";
        echo "<td>{$col['Type']}</td>";
        echo "<td>{$col['Null']}</td>";
        echo "<td>{$col['Key']}</td>";
        echo "<td>" . ($col['Default'] ?? 'NULL') . "</td>";
        echo "<td>{$col['Extra']}</td>";
        echo "</tr>";
    }
    echo "</table>";

    echo "<br><p style='color: #666;'>ℹ Data yang sudah ada <strong>tidak terpengaruh</strong>. Kolom baru bernilai NULL untuk semua baris lama.</p>";
    echo "<p style='color: red;'><strong>⚠ Hapus file migrasi ini dari server setelah selesai dijalankan!</strong></p>";

} catch (PDOException $e) {
    echo "<p style='color: red; font-weight: bold;'>❌ Migration gagal: " . htmlspecialchars($e->getMessage()) . "</p>";
}
