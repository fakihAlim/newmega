<?php
require __DIR__ . '/config.php';
$stmt = $pdo->query("SHOW TABLES LIKE 'role_permissions'");
if ($stmt->rowCount() > 0) {
    echo "Exists. Columns:\n";
    $cols = $pdo->query("SHOW COLUMNS FROM role_permissions")->fetchAll(PDO::FETCH_ASSOC);
    print_r($cols);
} else {
    echo "Not exists.";
}
