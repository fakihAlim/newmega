<?php
/**
 * Migration Script - General Ledger Feature Permissions
 * Run this script to register default permissions for the ledger.
 */

require_once __DIR__ . '/../config.php';

try {
    echo "Starting ledger permission migration...\n";

    // Find roles 'super_admin' and 'finance'
    $stmtRoles = $pdo->query("SELECT id, role_key FROM roles WHERE role_key IN ('super_admin', 'finance')");
    $roles = $stmtRoles->fetchAll();

    if (empty($roles)) {
        echo "No roles found with keys 'super_admin' or 'finance'.\n";
    }

    $stmtCheckPerm = $pdo->prepare("SELECT COUNT(*) FROM role_permissions WHERE role_id = ? AND module_key = 'ledger'");
    $stmtInsPerm = $pdo->prepare("INSERT INTO role_permissions (role_id, module_key, can_view, can_create, can_edit, can_delete) VALUES (?, 'ledger', 1, 1, 1, 1)");

    foreach ($roles as $role) {
        $stmtCheckPerm->execute([$role['id']]);
        if ($stmtCheckPerm->fetchColumn() == 0) {
            $stmtInsPerm->execute([$role['id']]);
            echo "Registered 'ledger' permissions for role '{$role['role_key']}'.\n";
        } else {
            echo "Permissions for 'ledger' already exist for role '{$role['role_key']}'.\n";
        }
    }

    echo "Ledger permission migration completed successfully!\n";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
