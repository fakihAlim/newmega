<?php
/**
 * Migration Script - Claim Nota Feature
 * Run this script to create the required tables and register default permissions.
 */

require_once __DIR__ . '/../config.php';

try {
    echo "Starting migration...\n";

    // 1. Create nota_claims table
    $sqlClaims = "
    CREATE TABLE IF NOT EXISTS nota_claims (
        id INT AUTO_INCREMENT PRIMARY KEY,
        claim_number VARCHAR(20) UNIQUE NOT NULL,
        company_id INT NOT NULL,
        employee_name VARCHAR(150) NOT NULL,
        employee_id INT NULL,
        claim_date DATE NOT NULL,
        total_amount DECIMAL(15,2) DEFAULT 0.00,
        status ENUM('pending', 'approved', 'paid', 'rejected') DEFAULT 'pending',
        notes TEXT NULL,
        reject_reason TEXT NULL,
        created_by INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (company_id) REFERENCES companies(id),
        FOREIGN KEY (employee_id) REFERENCES users(id) ON DELETE SET NULL,
        FOREIGN KEY (created_by) REFERENCES users(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    $pdo->exec($sqlClaims);
    echo "Table 'nota_claims' created or verified.\n";

    // 2. Create nota_claim_items table
    $sqlItems = "
    CREATE TABLE IF NOT EXISTS nota_claim_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        claim_id INT NOT NULL,
        item_date DATE NOT NULL,
        project_id INT NULL,
        group_name VARCHAR(100) NOT NULL,
        item_name VARCHAR(255) NOT NULL,
        qty DECIMAL(12,2) NOT NULL DEFAULT 1.00,
        price DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        receipt_photo VARCHAR(255) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (claim_id) REFERENCES nota_claims(id) ON DELETE CASCADE,
        FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    $pdo->exec($sqlItems);
    echo "Table 'nota_claim_items' created or verified.\n";

    // 3. Register permissions in role_permissions table
    // Let's find roles 'super_admin' and 'finance'
    $stmtRoles = $pdo->query("SELECT id, role_key FROM roles WHERE role_key IN ('super_admin', 'finance')");
    $roles = $stmtRoles->fetchAll();

    $stmtCheckPerm = $pdo->prepare("SELECT COUNT(*) FROM role_permissions WHERE role_id = ? AND module_key = 'claim_nota'");
    $stmtInsPerm = $pdo->prepare("INSERT INTO role_permissions (role_id, module_key, can_view, can_create, can_edit, can_delete) VALUES (?, 'claim_nota', 1, 1, 1, 1)");

    foreach ($roles as $role) {
        $stmtCheckPerm->execute([$role['id']]);
        if ($stmtCheckPerm->fetchColumn() == 0) {
            $stmtInsPerm->execute([$role['id']]);
            echo "Registered 'claim_nota' permissions for role '{$role['role_key']}'.\n";
        } else {
            echo "Permissions for 'claim_nota' already exist for role '{$role['role_key']}'.\n";
        }
    }

    echo "Migration completed successfully!\n";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
