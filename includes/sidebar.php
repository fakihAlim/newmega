<?php
/**
 * Sidebar Navigation (Role-Based)
 */
$user = getCurrentUser();
$currentPage = basename($_SERVER['PHP_SELF']);
$currentDir = basename(dirname($_SERVER['PHP_SELF']));
$parentDir = basename(dirname(dirname($_SERVER['PHP_SELF'])));

// Helper to check active state
function isActive($dirs, $pages = []) {
    global $currentDir, $currentPage;
    if (is_string($dirs)) $dirs = [$dirs];
    
    $dirMatch = empty($dirs) || in_array($currentDir, $dirs);
    $pageMatch = empty($pages) || in_array($currentPage, (array)$pages);
    
    if (empty($pages)) {
        return !empty($dirs) && in_array($currentDir, $dirs);
    }
    return $dirMatch && $pageMatch;
}

function isMenuOpen($dirs) {
    return isActive($dirs) ? 'menu-open' : '';
}

function isActiveClass($dirs, $pages = []) {
    return isActive($dirs, $pages) ? 'active' : '';
}
?>

<!-- Main Sidebar Container -->
<aside class="main-sidebar sidebar-dark-warning elevation-4">
    <!-- Brand Logo -->
    <a href="<?= APP_URL ?>/modules/dashboard/index.php" class="brand-link">
        <img src="<?= APP_URL ?>/assets/img/logo-perusahaan.png" alt="Logo PT MKM" class="brand-image img-circle elevation-3" style="opacity: .8; width: 33px; height: 33px; object-fit: cover;">
        <span class="brand-text font-weight-light ml-2">Procurement</span>
    </a>

    <!-- Sidebar -->
    <div class="sidebar">
        <!-- User Panel -->
        <div class="user-panel mt-3 pb-3 mb-3 d-flex">
            <div class="image">
                <img src="<?= getProfilePhoto($user['photo'] ?? null) ?>" class="img-circle elevation-2" alt="User">
            </div>
            <div class="info">
                <a href="<?= APP_URL ?>/modules/auth/profile.php" class="d-block" style="font-size:14px;">
                    <?= sanitize($user['full_name'] ?? 'User') ?>
                </a>
                <span class="text-warning" style="font-size:11px;"><?= getRoleName($user['role'] ?? '') ?></span>
            </div>
        </div>

        <!-- Sidebar Menu -->
        <nav class="mt-2">
            <ul class="nav nav-pills nav-sidebar flex-column nav-child-indent nav-compact" data-widget="treeview" role="menu" data-accordion="false">
                
                <!-- Dashboard -->
                <li class="nav-item">
                    <a href="<?= APP_URL ?>/modules/dashboard/index.php" class="nav-link <?= isActiveClass(['dashboard']) ?>">
                        <i class="nav-icon fas fa-tachometer-alt"></i>
                        <p>Dashboard</p>
                    </a>
                </li>

                <?php if (canAccess('master_items') || canAccess('master_vendors') || canAccess('master_customers') || canAccess('master_companies')): ?>
                <!-- Master Data (Collapsible) -->
                <li class="nav-item has-treeview <?= isMenuOpen(['categories','items','vendors','customers','companies','wages','employees']) ?>">
                    <a href="#" class="nav-link <?= isActiveClass(['categories','items','vendors','customers','companies','wages','employees']) ?>">
                        <i class="nav-icon fas fa-database"></i>
                        <p>
                            Master Data
                            <i class="right fas fa-angle-left"></i>
                        </p>
                    </a>
                    <ul class="nav nav-treeview">
                        <?php if (canAccess('master_categories')): ?>
                        <li class="nav-item">
                            <a href="<?= APP_URL ?>/modules/master/categories/index.php" class="nav-link <?= isActiveClass(['categories']) ?>">
                                <i class="nav-icon fas fa-tags"></i>
                                <p>Kategori</p>
                            </a>
                        </li>
                        <?php endif; ?>
                        <?php if (canAccess('master_items')): ?>
                        <li class="nav-item">
                            <a href="<?= APP_URL ?>/modules/master/items/index.php" class="nav-link <?= isActiveClass(['items']) ?>">
                                <i class="nav-icon fas fa-boxes"></i>
                                <p>Barang</p>
                            </a>
                        </li>
                        <?php endif; ?>
                        <?php if (canAccess('master_vendors')): ?>
                        <li class="nav-item">
                            <a href="<?= APP_URL ?>/modules/master/vendors/index.php" class="nav-link <?= isActiveClass(['vendors']) ?>">
                                <i class="nav-icon fas fa-truck"></i>
                                <p>Vendor</p>
                            </a>
                        </li>
                        <?php endif; ?>
                        <?php if (canAccess('master_customers')): ?>
                        <li class="nav-item">
                            <a href="<?= APP_URL ?>/modules/master/customers/index.php" class="nav-link <?= isActiveClass(['customers']) ?>">
                                <i class="nav-icon fas fa-building"></i>
                                <p>Customer</p>
                            </a>
                        </li>
                        <?php endif; ?>

                        <?php if (canAccess('master_companies')): ?>
                        <li class="nav-item">
                            <a href="<?= APP_URL ?>/modules/master/companies/index.php" class="nav-link <?= isActiveClass(['companies']) ?>">
                                <i class="nav-icon fas fa-city"></i>
                                <p>Perusahaan</p>
                            </a>
                        </li>
                        <?php endif; ?>

                        <?php if (canAccess('master_wages')): ?>
                        <li class="nav-item">
                            <a href="<?= APP_URL ?>/modules/master/wages/index.php" class="nav-link <?= isActiveClass(['wages']) ?>">
                                <i class="nav-icon fas fa-money-bill-wave"></i>
                                <p>Master Upah</p>
                            </a>
                        </li>
                        <?php endif; ?>

                        <?php if (canAccess('master_employees')): ?>
                        <li class="nav-item">
                            <a href="<?= APP_URL ?>/modules/master/employees/index.php" class="nav-link <?= isActiveClass(['employees']) ?>">
                                <i class="nav-icon fas fa-hard-hat"></i>
                                <p>Master Karyawan</p>
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </li>
                <?php endif; ?>
                
                <?php if (canAccess('master_projects')): ?>
                <!-- Proyek -->
                <li class="nav-item">
                    <a href="<?= APP_URL ?>/modules/master/projects/index.php" class="nav-link <?= isActiveClass(['projects']) ?>">
                        <i class="nav-icon fas fa-project-diagram"></i>
                        <p>Proyek</p>
                    </a>
                </li>
                <?php endif; ?>

                <?php if (canAccess('mr_list') || canAccess('po_list') || canAccess('receiving_list')): ?>
                <!-- Procurement (Collapsible) -->
                <li class="nav-item has-treeview <?= isMenuOpen(['mr','po','receiving']) ?>">
                    <a href="#" class="nav-link <?= isActiveClass(['mr','po','receiving']) ?>">
                        <i class="nav-icon fas fa-shopping-cart"></i>
                        <p>
                            Procurement
                            <i class="right fas fa-angle-left"></i>
                        </p>
                    </a>
                    <ul class="nav nav-treeview">
                        <?php if (canAccess('mr_list')): ?>
                        <li class="nav-item">
                            <a href="<?= APP_URL ?>/modules/procurement/mr/index.php" class="nav-link <?= isActiveClass(['mr']) ?>">
                                <i class="nav-icon fas fa-clipboard-list"></i>
                                <p>Material Request</p>
                            </a>
                        </li>
                        <?php endif; ?>
                        <?php if (canAccess('po_list')): ?>
                        <li class="nav-item">
                            <a href="<?= APP_URL ?>/modules/procurement/po/index.php" class="nav-link <?= isActiveClass(['po']) ?>">
                                <i class="nav-icon fas fa-file-invoice"></i>
                                <p>Purchase Order</p>
                            </a>
                        </li>
                        <?php endif; ?>
                        <?php if (canAccess('receiving_list')): ?>
                        <li class="nav-item">
                            <a href="<?= APP_URL ?>/modules/procurement/receiving/index.php" class="nav-link <?= isActiveClass(['receiving']) ?>">
                                <i class="nav-icon fas fa-dolly"></i>
                                <p>Penerimaan Barang</p>
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </li>
                <?php endif; ?>
                
                <?php if (canAccess('stock') || canAccess('transfer_list') || canAccess('stock_alerts')): ?>
                <!-- Gudang (Collapsible) -->
                <li class="nav-item has-treeview <?= isMenuOpen(['stock','transfer','alerts']) ?>">
                    <a href="#" class="nav-link <?= isActiveClass(['stock','transfer','alerts']) ?>">
                        <i class="nav-icon fas fa-warehouse"></i>
                        <p>
                            Gudang
                            <i class="right fas fa-angle-left"></i>
                        </p>
                    </a>
                    <ul class="nav nav-treeview">
                        <?php if (canAccess('stock')): ?>
                        <li class="nav-item">
                            <a href="<?= APP_URL ?>/modules/warehouse/stock/index.php" class="nav-link <?= isActiveClass(['stock']) ?>">
                                <i class="nav-icon fas fa-cubes"></i>
                                <p>Stok Barang</p>
                            </a>
                        </li>
                        <?php endif; ?>
                        <?php if (canAccess('transfer_list')): ?>
                        <li class="nav-item">
                            <a href="<?= APP_URL ?>/modules/warehouse/transfer/index.php" class="nav-link <?= isActiveClass(['transfer']) ?>">
                                <i class="nav-icon fas fa-exchange-alt"></i>
                                <p>Transfer Barang</p>
                            </a>
                        </li>
                        <?php endif; ?>
                        <?php if (canAccess('stock_alerts')): ?>
                        <li class="nav-item">
                            <a href="<?= APP_URL ?>/modules/warehouse/alerts/index.php" class="nav-link <?= isActiveClass(['alerts']) ?>">
                                <i class="nav-icon fas fa-exclamation-triangle"></i>
                                <p>Stok Minimum</p>
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </li>
                <?php endif; ?>
                
                <?php if (canAccess('vendor_payments') || canAccess('customer_payments') || canAccess('quotation_list') || canAccess('invoice_list') || canAccess('claim_nota')): ?>
                <!-- Finance (Collapsible) -->
                <li class="nav-item has-treeview <?= isMenuOpen(['vendor_payments','customer_payments','quotations','invoices','claim_nota']) ?>">
                    <a href="#" class="nav-link <?= isActiveClass(['vendor_payments','customer_payments','quotations','invoices','claim_nota']) ?>">
                        <i class="nav-icon fas fa-coins"></i>
                        <p>
                            Finance
                            <i class="right fas fa-angle-left"></i>
                        </p>
                    </a>
                    <ul class="nav nav-treeview">
                        <?php if (canAccess('quotation_list')): ?>
                        <li class="nav-item">
                            <a href="<?= APP_URL ?>/modules/sales/quotations/index.php" class="nav-link <?= isActiveClass(['quotations']) ?>">
                                <i class="nav-icon fas fa-file-alt"></i>
                                <p>Quotation</p>
                            </a>
                        </li>
                        <?php endif; ?>
                        <?php if (canAccess('invoice_list')): ?>
                        <li class="nav-item">
                            <a href="<?= APP_URL ?>/modules/sales/invoices/index.php" class="nav-link <?= isActiveClass(['invoices']) ?>">
                                <i class="nav-icon fas fa-file-invoice-dollar"></i>
                                <p>Invoice</p>
                            </a>
                        </li>
                        <?php endif; ?>
                        <?php if (canAccess('vendor_payments')): ?>
                        <li class="nav-item">
                            <a href="<?= APP_URL ?>/modules/finance/vendor_payments/index.php" class="nav-link <?= isActiveClass(['vendor_payments']) ?>">
                                <i class="nav-icon fas fa-money-check-alt"></i>
                                <p>Pembayaran Vendor</p>
                            </a>
                        </li>
                        <?php endif; ?>
                        <?php if (canAccess('customer_payments')): ?>
                        <li class="nav-item">
                            <a href="<?= APP_URL ?>/modules/finance/customer_payments/index.php" class="nav-link <?= isActiveClass(['customer_payments']) ?>">
                                <i class="nav-icon fas fa-hand-holding-usd"></i>
                                <p>Penerimaan Customer</p>
                            </a>
                        </li>
                        <?php endif; ?>
                        <?php if (canAccess('claim_nota')): ?>
                        <li class="nav-item">
                            <a href="<?= APP_URL ?>/modules/finance/claim_nota/index.php" class="nav-link <?= isActiveClass(['claim_nota']) ?>">
                                <i class="nav-icon fas fa-receipt"></i>
                                <p>Claim Nota</p>
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </li>
                <?php endif; ?>
                
                <?php if (canAccess('timesheet_input') || canAccess('timesheet_approve') || canAccess('report_timesheet')): ?>
                <!-- Timesheet (Collapsible) -->
                <li class="nav-item has-treeview <?= isMenuOpen(['timesheet']) ?> <?= isActive([], ['timesheet.php','timesheet_detail.php']) ? 'menu-open' : '' ?>">
                    <a href="#" class="nav-link <?= isActiveClass(['timesheet']) ?> <?= isActive([], ['timesheet.php','timesheet_detail.php']) ? 'active' : '' ?>">
                        <i class="nav-icon fas fa-clock"></i>
                        <p>
                            Timesheet
                            <i class="right fas fa-angle-left"></i>
                        </p>
                    </a>
                    <ul class="nav nav-treeview">
                        <?php if (canAccess('timesheet_input')): ?>
                        <li class="nav-item">
                            <a href="<?= APP_URL ?>/modules/timesheet/input.php" class="nav-link <?= isActiveClass(['timesheet'], ['input.php']) ?>">
                                <i class="nav-icon fas fa-calendar-check"></i>
                                <p>Input Timesheet</p>
                            </a>
                        </li>
                        <?php endif; ?>
                        <?php if (canAccess('timesheet_approve')): ?>
                        <li class="nav-item">
                            <a href="<?= APP_URL ?>/modules/timesheet/approval.php" class="nav-link <?= isActiveClass(['timesheet'], ['approval.php']) ?>">
                                <i class="nav-icon fas fa-check-double"></i>
                                <p>Approval Timesheet</p>
                            </a>
                        </li>
                        <?php endif; ?>
                        <?php if (canAccess('report_timesheet')): ?>
                        <li class="nav-item">
                            <a href="<?= APP_URL ?>/modules/reports/timesheet.php" class="nav-link <?= isActiveClass([], ['timesheet.php','timesheet_detail.php']) ?>">
                                <i class="nav-icon fas fa-business-time"></i>
                                <p>Laporan Timesheet</p>
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </li>
                <?php endif; ?>

                <?php if (canAccess('report_project_expense') || canAccess('report_vendor_outstanding') || canAccess('report_claim_nota')): ?>
                <!-- Laporan (Collapsible) -->
                <li class="nav-item has-treeview <?= isMenuOpen(['reports']) ?> <?= isActive([], ['project_expense.php','vendor_outstanding.php','customer_outstanding.php','profit_loss.php','stock_report.php','claim_nota.php']) ? 'menu-open' : '' ?>">
                    <a href="#" class="nav-link <?= isActiveClass(['reports'], ['project_expense.php','vendor_outstanding.php','customer_outstanding.php','profit_loss.php','stock_report.php','claim_nota.php']) ?>">
                        <i class="nav-icon fas fa-chart-pie"></i>
                        <p>
                            Laporan
                            <i class="right fas fa-angle-left"></i>
                        </p>
                    </a>
                    <ul class="nav nav-treeview">
                        <?php if (canAccess('report_project_expense')): ?>
                        <li class="nav-item">
                            <a href="<?= APP_URL ?>/modules/reports/project_expense.php" class="nav-link <?= isActiveClass([], 'project_expense.php') ?>">
                                <i class="nav-icon fas fa-chart-bar"></i>
                                <p>Pengeluaran Proyek</p>
                            </a>
                        </li>
                        <?php endif; ?>
                        <?php if (canAccess('report_vendor_outstanding')): ?>
                        <li class="nav-item">
                            <a href="<?= APP_URL ?>/modules/reports/vendor_outstanding.php" class="nav-link <?= isActiveClass([], 'vendor_outstanding.php') ?>">
                                <i class="nav-icon fas fa-file-invoice"></i>
                                <p>Outstanding Vendor</p>
                            </a>
                        </li>
                        <?php endif; ?>
                        <?php if (canAccess('report_customer_outstanding')): ?>
                        <li class="nav-item">
                            <a href="<?= APP_URL ?>/modules/reports/customer_outstanding.php" class="nav-link <?= isActiveClass([], 'customer_outstanding.php') ?>">
                                <i class="nav-icon fas fa-file-invoice-dollar"></i>
                                <p>Outstanding Customer</p>
                            </a>
                        </li>
                        <?php endif; ?>
                        <?php if (canAccess('report_profit_loss')): ?>
                        <li class="nav-item">
                            <a href="<?= APP_URL ?>/modules/reports/profit_loss.php" class="nav-link <?= isActiveClass([], 'profit_loss.php') ?>">
                                <i class="nav-icon fas fa-balance-scale"></i>
                                <p>Profit & Loss</p>
                            </a>
                        </li>
                        <?php endif; ?>
                        <?php if (canAccess('report_stock')): ?>
                        <li class="nav-item">
                            <a href="<?= APP_URL ?>/modules/reports/stock_report.php" class="nav-link <?= isActiveClass([], 'stock_report.php') ?>">
                                <i class="nav-icon fas fa-clipboard-check"></i>
                                <p>Laporan Stok</p>
                            </a>
                        </li>
                        <?php endif; ?>
                        <?php if (canAccess('report_claim_nota')): ?>
                        <li class="nav-item">
                            <a href="<?= APP_URL ?>/modules/reports/claim_nota.php" class="nav-link <?= isActiveClass([], 'claim_nota.php') ?>">
                                <i class="nav-icon fas fa-receipt"></i>
                                <p>Laporan Claim Nota</p>
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </li>
                <?php endif; ?>

                <?php if (canAccess('users')): ?>
                <!-- Administrasi -->
                <li class="nav-item has-treeview <?= isMenuOpen(['users', 'roles']) ?>">
                    <a href="#" class="nav-link <?= isActiveClass(['users', 'roles']) ?>">
                        <i class="nav-icon fas fa-cogs"></i>
                        <p>
                            Administrasi
                            <i class="right fas fa-angle-left"></i>
                        </p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="<?= APP_URL ?>/modules/users/index.php" class="nav-link <?= isActiveClass(['users']) ?>">
                                <i class="nav-icon fas fa-users-cog"></i>
                                <p>Manajemen User</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?= APP_URL ?>/modules/master/roles/index.php" class="nav-link <?= isActiveClass(['roles']) ?>">
                                <i class="nav-icon fas fa-user-tag"></i>
                                <p>Role & Akses</p>
                            </a>
                        </li>
                    </ul>
                </li>
                <?php endif; ?>

            </ul>
        </nav>
    </div>
</aside>
