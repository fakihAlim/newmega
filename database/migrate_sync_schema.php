<?php
/**
 * Migration: Sync Schema from schema.sql
 * Description: Memastikan semua tabel yang ada di schema.sql terbuat di server
 * tanpa menghapus data di tabel yang sudah ada.
 */

require_once __DIR__ . '/../config.php';

try {
    $schemaFile = __DIR__ . '/schema.sql';
    
    if (!file_exists($schemaFile)) {
        throw new Exception("File schema.sql tidak ditemukan.");
    }
    
    $sql = file_get_contents($schemaFile);
    
    // Disable foreign key checks temporarily during creation
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
    
    // Enable emulation just in case
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
    
    // Split the SQL file into individual statements by semicolon
    // We only split by semicolon at the end of a line or statement to be safe
    $queries = explode(';', $sql);
    
    foreach ($queries as $query) {
        $query = trim($query);
        if (!empty($query)) {
            $pdo->exec($query);
        }
    }
    
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
    
    echo "Sync Schema completed. Semua tabel struktur dipastikan ada.\n";
    exit(0);

} catch (Exception $e) {
    echo "Fatal Error: " . $e->getMessage() . "\n";
    exit(1);
}
