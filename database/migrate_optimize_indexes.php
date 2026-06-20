<?php
/**
 * Migration: Add Performance Indexes to Transaction Tables
 * Description: Menambahkan index pada kolom status dan tanggal untuk mempercepat query.
 */

require_once __DIR__ . '/../config.php';

try {
    // Array of queries to execute
    $queries = [
        // Indexes for Material Requests
        "ALTER TABLE material_requests ADD INDEX idx_mr_status (status)",
        "ALTER TABLE material_requests ADD INDEX idx_mr_date (request_date)",
        
        // Indexes for Purchase Orders
        "ALTER TABLE purchase_orders ADD INDEX idx_po_status (status)",
        "ALTER TABLE purchase_orders ADD INDEX idx_po_date (po_date)",
        
        // Indexes for Goods Receivings
        "ALTER TABLE goods_receivings ADD INDEX idx_gr_date (receive_date)",
        
        // Indexes for Quotations
        "ALTER TABLE quotations ADD INDEX idx_quot_status (status)",
        "ALTER TABLE quotations ADD INDEX idx_quot_date (quotation_date)",
        
        // Indexes for Invoices
        "ALTER TABLE invoices ADD INDEX idx_inv_status (status)",
        "ALTER TABLE invoices ADD INDEX idx_inv_date (invoice_date)",
        
        // Indexes for Stock Transactions
        "ALTER TABLE stock_transactions ADD INDEX idx_stock_type (transaction_type)",
        "ALTER TABLE stock_transactions ADD INDEX idx_stock_created (created_at)"
    ];

    $successCount = 0;
    
    foreach ($queries as $query) {
        try {
            // Check if index already exists to prevent duplicate key errors
            // A simple way to handle "Duplicate key name" in PDO is catching the exception
            $pdo->exec($query);
            echo "Success: $query\n";
            $successCount++;
        } catch (PDOException $e) {
            // 1061 is MySQL error code for Duplicate key name
            if ($e->errorInfo[1] == 1061) {
                echo "Skipped (Already exists): $query\n";
            } else {
                echo "Warning: Failed to execute '$query'. Error: " . $e->getMessage() . "\n";
            }
        }
    }

    echo "\nOptimization Migration completed. Added $successCount new indexes.\n";
    exit(0); // Success

} catch (Exception $e) {
    echo "Fatal Error: " . $e->getMessage() . "\n";
    exit(1); // Failure
}
