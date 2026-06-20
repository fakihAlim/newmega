<?php
/**
 * Tool: Truncate All Transaction Data
 * Keeps Master Data (Users, Companies, Items, Customers, Vendors, Projects, Employees, etc.)
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

$confirm = isset($_GET['confirm']) && $_GET['confirm'] === 'YES_DELETE_ALL_TRANSACTIONS';

?>
<!DOCTYPE html>
<html>
<head>
    <title>Truncate Transaction Data</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <style>
        body { background: #f4f6f9; padding-top: 50px; }
        .card { max-width: 600px; margin: auto; }
        .danger-zone { border: 2px dashed #dc3545; padding: 20px; border-radius: 8px; background: #fff5f5; }
    </style>
</head>
<body>
    <div class="card card-outline card-danger shadow">
        <div class="card-header">
            <h3 class="card-title text-danger"><b>⚠️ PERINGATAN KRITIS</b></h3>
        </div>
        <div class="card-body">
            <?php if (!$confirm): ?>
                <div class="danger-zone text-center">
                    <h4>Anda akan menghapus SELURUH data transaksi!</h4>
                    <p>Tindakan ini akan mengosongkan tabel berikut:</p>
                    <ul class="text-left" style="display:inline-block;">
                        <li>Material Requests & Items</li>
                        <li>Purchase Orders & Items</li>
                        <li>Goods Receiving / Laporan Penerimaan</li>
                        <li>Stock Transactions (History Gudang)</li>
                        <li>Warehouse Transfers</li>
                        <li>Quotations & Invoices</li>
                        <li>Payments (Vendor & Customer)</li>
                        <li>Timesheet Entries (Data Kerja Harian)</li>
                        <li>Claim Nota / Reimbursement Karyawan</li>
                    </ul>
                    <hr>
                    <p><b>Data Master</b> (User, Item, Supplier, Customer, Proyek, Karyawan) akan <b>TETAP ADA</b>.</p>
                    
                    <a href="?confirm=YES_DELETE_ALL_TRANSACTIONS" class="btn btn-danger btn-lg mt-3" 
                       onclick="return confirm('APAKAH ANDA YAKIN? Data yang dihapus tidak dapat dikembalikan!')">
                        YA, HAPUS SEMUA TRANSAKSI SEKARANG
                    </a>
                    <br>
                    <a href="../index.php" class="btn btn-secondary mt-3">Batalkan</a>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    <h5><i class="icon fas fa-info"></i> Memproses Truncate...</h5>
                    <pre style="background: #eee; padding: 10px; border-radius: 5px;"><?php
                    try {
                        $pdo->beginTransaction();
                        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");

                        $tables = [
                            'material_request_items',
                            'material_requests',
                            'purchase_order_items',
                            'po_mr_links',
                            'purchase_orders',
                            'goods_receiving_items',
                            'goods_receivings',
                            'stock_transactions',
                            'warehouse_transfer_items',
                            'warehouse_transfers',
                            'vendor_payments',
                            'quotation_items',
                            'quotations',
                            'invoice_items',
                            'invoices',
                            'customer_payments',
                            'timesheet_entries',
                            'nota_claim_items',
                            'nota_claims'
                        ];

                        foreach ($tables as $table) {
                            $pdo->exec("TRUNCATE TABLE $table");
                            echo "[OK] Truncated: $table\n";
                        }

                        // Reset stock count in Master Items
                        $pdo->exec("UPDATE items SET current_stock = 0");
                        echo "[OK] Reset current_stock in items table\n";

                        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
                        $pdo->commit();

                        echo "\n✅ PROSES SELESAI BERHASIL!";
                    } catch (Exception $e) {
                        if ($pdo->inTransaction()) $pdo->rollBack();
                        echo "\n❌ ERROR: " . $e->getMessage();
                    }
                    ?></pre>
                    <a href="../index.php" class="btn btn-primary btn-block mt-3">Kembali ke Dashboard</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
