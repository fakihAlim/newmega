<?php
/**
 * Header Template
 * Include auth.php before this to ensure login
 * Set $pageTitle before including this file
 */
if (!isset($pageTitle)) $pageTitle = 'Dashboard';
$user = getCurrentUser();

// Include permissions
require_once __DIR__ . '/permissions.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?= APP_NAME ?> - Sistem E-Procurement">
    <title><?= sanitize($pageTitle) ?> | <?= APP_NAME ?></title>

    <!-- Google Font: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome 5 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    
    <!-- AdminLTE 3 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    
    <!-- DataTables -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap4.min.css">
    
    <!-- Select2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@ttskch/select2-bootstrap4-theme@1.5.2/dist/select2-bootstrap4.min.css">
    
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/custom.css?v=<?= time() ?>">
</head>
<body class="hold-transition sidebar-mini layout-navbar-fixed layout-fixed">
<div class="wrapper">

    <!-- Navbar -->
    <nav class="main-header navbar navbar-expand navbar-white navbar-light">
        <!-- Left navbar links -->
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
            </li>
        </ul>

        <!-- Right navbar links -->
        <ul class="navbar-nav ml-auto">
            <!-- Low Stock Alerts -->
            <?php if (canAccess('stock_alerts')): ?>
            <?php
            $alertStmt = $pdo->query("SELECT COUNT(*) FROM items WHERE current_stock <= minimum_stock AND minimum_stock > 0 AND is_active = 1");
            $lowStockCount = $alertStmt->fetchColumn();
            ?>
            <?php if ($lowStockCount > 0): ?>
            <li class="nav-item">
                <a class="nav-link" href="<?= APP_URL ?>/modules/warehouse/alerts/index.php" title="Stok Minimum Alert">
                    <i class="fas fa-bell text-warning"></i>
                    <span class="badge badge-warning navbar-badge"><?= $lowStockCount ?></span>
                </a>
            </li>
            <?php endif; ?>
            <?php endif; ?>
            
            <!-- Pending Approvals -->
            <?php if (hasRole(['super_admin', 'finance'])): ?>
            <?php
            $pendingMR = $pdo->query("SELECT COUNT(*) FROM material_requests WHERE status = 'pending'")->fetchColumn();
            $pendingPO = 0;
            $pendingQ = 0;
            $pendingInv = 0;
            if (hasRole(['super_admin'])) {
                $pendingPO = $pdo->query("SELECT COUNT(*) FROM purchase_orders WHERE status = 'pending'")->fetchColumn();
                $pendingQ = $pdo->query("SELECT COUNT(*) FROM quotations WHERE status = 'pending'")->fetchColumn();
                $pendingInv = $pdo->query("SELECT COUNT(*) FROM invoices WHERE status = 'pending'")->fetchColumn();
            }
            $totalPending = $pendingMR + $pendingPO + $pendingQ + $pendingInv;
            ?>
            <?php if ($totalPending > 0): ?>
            <li class="nav-item dropdown">
                <a class="nav-link" data-toggle="dropdown" href="#" title="Persetujuan Tertunda">
                    <i class="fas fa-tasks text-info"></i>
                    <span class="badge badge-info navbar-badge"><?= $totalPending ?></span>
                </a>
                <div class="dropdown-menu dropdown-menu-lg dropdown-menu-right">
                    <span class="dropdown-item dropdown-header">Menunggu Persetujuan</span>
                    <?php if ($pendingMR > 0): ?>
                    <a href="<?= APP_URL ?>/modules/procurement/mr/index.php?status=pending" class="dropdown-item">
                        <i class="fas fa-clipboard-list mr-2 text-warning"></i> <?= $pendingMR ?> Material Request
                    </a>
                    <?php endif; ?>
                    <?php if ($pendingPO > 0): ?>
                    <a href="<?= APP_URL ?>/modules/procurement/po/index.php?status=pending" class="dropdown-item">
                        <i class="fas fa-file-invoice mr-2 text-primary"></i> <?= $pendingPO ?> Purchase Order
                    </a>
                    <?php endif; ?>
                    <?php if ($pendingQ > 0): ?>
                    <a href="<?= APP_URL ?>/modules/sales/quotations/index.php?status=pending" class="dropdown-item">
                        <i class="fas fa-file-alt mr-2 text-success"></i> <?= $pendingQ ?> Quotation
                    </a>
                    <?php endif; ?>
                    <?php if ($pendingInv > 0): ?>
                    <a href="<?= APP_URL ?>/modules/sales/invoices/index.php?status=pending" class="dropdown-item">
                        <i class="fas fa-file-invoice-dollar mr-2 text-danger"></i> <?= $pendingInv ?> Invoice
                    </a>
                    <?php endif; ?>
                </div>
            </li>
            <?php endif; ?>
            <?php endif; ?>

            <!-- User Dropdown -->
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle" data-toggle="dropdown" href="#" style="display:flex;align-items:center;gap:8px;">
                    <img src="<?= getProfilePhoto($user['photo'] ?? null) ?>" alt="User" class="img-circle navbar-user-img">
                    <span class="d-none d-md-inline"><?= sanitize($user['full_name'] ?? 'User') ?></span>
                </a>
                <div class="dropdown-menu dropdown-menu-right">
                    <a href="<?= APP_URL ?>/modules/auth/profile.php" class="dropdown-item">
                        <i class="fas fa-user-circle mr-2"></i> Profil Saya
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="<?= APP_URL ?>/modules/auth/logout.php" class="dropdown-item text-danger">
                        <i class="fas fa-sign-out-alt mr-2"></i> Keluar
                    </a>
                </div>
            </li>
        </ul>
    </nav>

    <?php include __DIR__ . '/sidebar.php'; ?>

    <!-- Content Wrapper -->
    <div class="content-wrapper">
        <!-- Content Header -->
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0" style="font-size:1.5rem;font-weight:600;"><?= sanitize($pageTitle) ?></h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right" style="font-size:13px;">
                            <li class="breadcrumb-item"><a href="<?= APP_URL ?>/modules/dashboard/index.php">Beranda</a></li>
                            <?php if (isset($breadcrumbs) && is_array($breadcrumbs)): ?>
                                <?php foreach ($breadcrumbs as $bc): ?>
                                    <?php if (isset($bc['url'])): ?>
                                        <li class="breadcrumb-item"><a href="<?= $bc['url'] ?>"><?= $bc['label'] ?></a></li>
                                    <?php else: ?>
                                        <li class="breadcrumb-item active"><?= $bc['label'] ?></li>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <li class="breadcrumb-item active"><?= sanitize($pageTitle) ?></li>
                            <?php endif; ?>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <!-- Flash Messages -->
        <?php $flash = getFlash(); ?>
        <?php if ($flash): ?>
        <div class="container-fluid">
            <div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show" role="alert">
                <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
                <?php if ($flash['type'] === 'success'): ?>
                    <i class="fas fa-check-circle mr-1"></i>
                <?php elseif ($flash['type'] === 'danger'): ?>
                    <i class="fas fa-exclamation-circle mr-1"></i>
                <?php elseif ($flash['type'] === 'warning'): ?>
                    <i class="fas fa-exclamation-triangle mr-1"></i>
                <?php else: ?>
                    <i class="fas fa-info-circle mr-1"></i>
                <?php endif; ?>
                <?= $flash['message'] ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Main content -->
        <section class="content">
            <div class="container-fluid">
