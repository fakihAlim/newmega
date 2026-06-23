<?php
/**
 * Migration: Create Activity Logs Table
 * Run this once to create the activity_logs table.
 */
require_once __DIR__ . '/../config.php';

echo "<pre style='font-family:monospace; padding:20px;'>\n";
echo "=== Migrasi: Tabel Activity Logs ===\n\n";

try {
    // Create activity_logs table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS activity_logs (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            user_id INT DEFAULT NULL,
            user_name VARCHAR(100) DEFAULT NULL,
            action VARCHAR(50) NOT NULL COMMENT 'login, logout, create, update, delete, approve, reject, truncate, etc.',
            module VARCHAR(100) DEFAULT NULL COMMENT 'material_request, purchase_order, users, etc.',
            description TEXT DEFAULT NULL,
            reference_type VARCHAR(50) DEFAULT NULL COMMENT 'Nama tabel yang direferensikan',
            reference_id INT DEFAULT NULL COMMENT 'ID record terkait',
            ip_address VARCHAR(45) DEFAULT NULL,
            user_agent TEXT DEFAULT NULL,
            old_data JSON DEFAULT NULL COMMENT 'Data sebelum perubahan (opsional)',
            new_data JSON DEFAULT NULL COMMENT 'Data setelah perubahan (opsional)',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_id (user_id),
            INDEX idx_action (action),
            INDEX idx_module (module),
            INDEX idx_created_at (created_at),
            INDEX idx_reference (reference_type, reference_id),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "[OK] Tabel 'activity_logs' berhasil dibuat.\n";

    echo "\n✅ Migrasi selesai!\n";
} catch (Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
}

echo "</pre>";
