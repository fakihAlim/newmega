<?php
/**
 * Role-Based Access Control (Permissions)
 */

/**
 * Define all available modules for UI generation
 */
$AVAILABLE_MODULES = [
    'Dashboard' => [
        'dashboard' => 'Dashboard',
        'project_dashboard' => 'Dashboard Proyek',
        'ai_chat' => 'Tanya AI',
    ],
    'Master Data' => [
        'master_categories' => 'Kategori',
        'master_items' => 'Barang',
        'master_vendors' => 'Supplier',
        'master_customers' => 'Pelanggan',
        'master_companies' => 'Perusahaan',
        'master_wages' => 'Master Upah',
        'master_employees' => 'Master Karyawan',
    ],
    'Proyek' => [
        'master_projects' => 'Proyek',
    ],
    'Pengadaan' => [
        'material_request' => 'Material Request',
        'purchase_order' => 'Purchase Order',
        'receiving' => 'Penerimaan Barang',
    ],
    'Gudang' => [
        'stock' => 'Stok Barang',
        'stock_transfer' => 'Transfer Barang',
        'stock_alerts' => 'Stok Minimum',
    ],
    'Keuangan' => [
        'quotation' => 'Quotation',
        'invoice' => 'Invoice',
        'claim_nota' => 'Claim Nota',
        'vendor_payments' => 'Pembayaran Supplier',
        'customer_payments' => 'Penerimaan Pelanggan',
        'ledger' => 'Buku Kas',
    ],
    'Timesheet' => [
        'timesheet' => 'Input & Persetujuan Timesheet',
        'report_timesheet' => 'Laporan Timesheet',
    ],
    'Laporan' => [
        'report_project_expense' => 'Pengeluaran Proyek',
        'report_vendor_outstanding' => 'Tagihan Supplier',
        'report_customer_outstanding' => 'Piutang Pelanggan',
        'report_profit_loss' => 'Laba & Rugi',
        'report_stock' => 'Laporan Stok',
    ],
    'CMS Halaman Utama' => [
        'cms_landing' => 'CMS Halaman Utama',
    ],
    'Administrasi' => [
        'users' => 'Manajemen Pengguna',
        'roles' => 'Peran & Hak Akses',
    ]
];

/**
 * Check if current user can access a specific page/module
 * 
 * @param string $moduleKey
 * @param string $action 'view', 'create', 'edit', 'delete'
 * @return bool
 */
function canAccess($moduleKey, $action = 'view') {
    $user = getCurrentUser();
    if (!$user) return false;
    
    global $pdo;
    static $userPermissions = null;
    
    // Cache permissions per request
    if ($userPermissions === null) {
        $userPermissions = [];
        if (!empty($_SESSION['user']['id'])) {
            $stmt = $pdo->prepare("
                SELECT rp.module_key, rp.can_view, rp.can_create, rp.can_edit, rp.can_delete 
                FROM role_permissions rp
                JOIN user_roles ur ON rp.role_id = ur.role_id
                WHERE ur.user_id = ?
            ");
            $stmt->execute([$_SESSION['user']['id']]);
            $rows = $stmt->fetchAll();
            
            // Merge permissions if user has multiple roles
            foreach ($rows as $row) {
                $mk = $row['module_key'];
                if (!isset($userPermissions[$mk])) {
                    $userPermissions[$mk] = [
                        'can_view' => 0,
                        'can_create' => 0,
                        'can_edit' => 0,
                        'can_delete' => 0,
                    ];
                }
                
                if ($row['can_view']) $userPermissions[$mk]['can_view'] = 1;
                if ($row['can_create']) $userPermissions[$mk]['can_create'] = 1;
                if ($row['can_edit']) $userPermissions[$mk]['can_edit'] = 1;
                if ($row['can_delete']) $userPermissions[$mk]['can_delete'] = 1;
            }
            
            // Provide a fallback for legacy module keys temporarily while replacing in views
            $userPermissions['mr_list'] = $userPermissions['material_request'] ?? [];
            $userPermissions['mr_create'] = $userPermissions['material_request'] ?? [];
            $userPermissions['mr_view'] = $userPermissions['material_request'] ?? [];
            $userPermissions['mr_approve'] = $userPermissions['material_request'] ?? [];
            
            $userPermissions['po_list'] = $userPermissions['purchase_order'] ?? [];
            $userPermissions['po_create'] = $userPermissions['purchase_order'] ?? [];
            $userPermissions['po_view'] = $userPermissions['purchase_order'] ?? [];
            $userPermissions['po_approve'] = $userPermissions['purchase_order'] ?? [];
            
            $userPermissions['receiving_list'] = $userPermissions['receiving'] ?? [];
            $userPermissions['receiving_create'] = $userPermissions['receiving'] ?? [];
            $userPermissions['receiving_view'] = $userPermissions['receiving'] ?? [];
            
            $userPermissions['transfer_list'] = $userPermissions['stock_transfer'] ?? [];
            $userPermissions['transfer_create'] = $userPermissions['stock_transfer'] ?? [];
            
            $userPermissions['vendor_payment_create'] = $userPermissions['vendor_payments'] ?? [];
            
            $userPermissions['quotation_list'] = $userPermissions['quotation'] ?? [];
            $userPermissions['quotation_create'] = $userPermissions['quotation'] ?? [];
            $userPermissions['quotation_view'] = $userPermissions['quotation'] ?? [];
            $userPermissions['quotation_approve'] = $userPermissions['quotation'] ?? [];
            
            $userPermissions['invoice_list'] = $userPermissions['invoice'] ?? [];
            $userPermissions['invoice_create'] = $userPermissions['invoice'] ?? [];
            $userPermissions['invoice_view'] = $userPermissions['invoice'] ?? [];
            $userPermissions['invoice_approve'] = $userPermissions['invoice'] ?? [];
            
            $userPermissions['claim_nota_list'] = $userPermissions['claim_nota'] ?? [];
            $userPermissions['claim_nota_create'] = $userPermissions['claim_nota'] ?? [];
            $userPermissions['claim_nota_view'] = $userPermissions['claim_nota'] ?? [];
            $userPermissions['claim_nota_approve'] = $userPermissions['claim_nota'] ?? [];
            
            $userPermissions['ledger_list'] = $userPermissions['ledger'] ?? [];
            $userPermissions['ledger_export'] = $userPermissions['ledger'] ?? [];
            
            $userPermissions['timesheet_input'] = $userPermissions['timesheet'] ?? [];
            $userPermissions['timesheet_approve'] = $userPermissions['timesheet'] ?? [];
        }
    }
    
    if (!isset($userPermissions[$moduleKey])) {
        return false;
    }
    
    // If checking legacy module key which doesn't specify action, fallback to checking 'can_view' or mapped action
    // But since we mapped them to the whole array, they have can_view, can_create etc.
    // If the original call is `canAccess('mr_create')` without specifying action, it defaults to 'view'. 
    // We should treat `mr_create` as requesting 'create' on material_request if they pass just one argument.
    // Actually, I will explicitly replace the `canAccess` calls in views.
    
    $flagColumn = "can_{$action}";
    return !empty($userPermissions[$moduleKey][$flagColumn]);
}

/**
 * Require permission or redirect with error
 */
function requirePermission($moduleKey, $action = 'view') {
    if (!canAccess($moduleKey, $action)) {
        setFlash('danger', 'Anda tidak memiliki akses ke halaman ini.');
        header('Location: ' . APP_URL . '/modules/dashboard/index.php');
        exit;
    }
}
