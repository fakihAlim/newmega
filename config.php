<?php
/**
 * Application Configuration
 * E-Procurement System - PT. Mega Karya Modern
 */

// Use a unique session name for this project to avoid conflicts on shared servers/localhost
session_name('MKM_PROCUREMENT_SESSION');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load environment variables if exists
if (file_exists(__DIR__ . '/env.php')) {
    require_once __DIR__ . '/env.php';
}

// Production-safe error handling:
// Hide errors from output, but still log them server-side.
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Database Configuration has been moved to env.php

// Application Configuration
define('APP_NAME', 'MKM Procurement');
define('APP_VERSION', '1.0.0');

// Dynamic APP_URL calculation
$current_protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$current_host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
// Get the directory of the current script (config.php is in root, but it's included everywhere)
// We want the base path relative to the domain
$base_dir = isset($_SERVER['SCRIPT_NAME']) ? str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])) : '/';
// If config.php is in the root, base_dir might be / or \. We want to normalize it.
if (basename(dirname(__FILE__)) == 'newmega' || strpos(__FILE__, 'newmega') !== false) {
    // This is a bit risky if nested differently, let's use a simpler approach:
    // If the project is always in a folder or root, we just need to know where we are.
    $app_path = rtrim($base_dir, '/');
    // If we are deep in a module, this will be wrong. 
    // Best way: use the known path relative to this file.
    define('APP_URL', '/newmega'); // Keep manual for now but verify with user
} else {
    define('APP_URL', ''); // Root
}
// Actually, let's stick to the manual one but allow user to check it.
// define('APP_URL', '/newmega'); 


// Base path
define('BASE_PATH', __DIR__);
define('UPLOADS_PATH', BASE_PATH . '/assets/uploads');
define('PROFILES_PATH', UPLOADS_PATH . '/profiles');
define('LOGOS_PATH', UPLOADS_PATH . '/company_logos');

// PDO Connection
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::ATTR_TIMEOUT            => 3 // 3 seconds timeout for fast fallback
];

try {
    // 1. Try Online Database
    $dsnOnline = "mysql:host=" . (defined('DB_HOST_ONLINE') ? DB_HOST_ONLINE : 'localhost') . ";dbname=" . (defined('DB_NAME_ONLINE') ? DB_NAME_ONLINE : '') . ";charset=" . (defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4');
    $pdo = new PDO($dsnOnline, defined('DB_USER_ONLINE') ? DB_USER_ONLINE : '', defined('DB_PASS_ONLINE') ? DB_PASS_ONLINE : '', $options);
} catch (PDOException $eOnline) {
    // 2. Fallback to Local Database
    try {
        $dsnLocal = "mysql:host=" . (defined('DB_HOST_LOCAL') ? DB_HOST_LOCAL : 'localhost') . ";dbname=" . (defined('DB_NAME_LOCAL') ? DB_NAME_LOCAL : 'procurementDB') . ";charset=" . (defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4');
        $pdo = new PDO($dsnLocal, defined('DB_USER_LOCAL') ? DB_USER_LOCAL : 'root', defined('DB_PASS_LOCAL') ? DB_PASS_LOCAL : '', $options);
    } catch (PDOException $eLocal) {
        die('<div style="text-align:center;padding:50px;font-family:Arial;">
            <h2>⚠️ Database Connection Error</h2>
            <p>Gagal terhubung ke server database utama maupun lokal.</p>
            <p style="color:#999;font-size:12px;"><strong>Online Error:</strong> ' . $eOnline->getMessage() . '<br><strong>Local Error:</strong> ' . $eLocal->getMessage() . '</p>
        </div>');
    }
}

// Timezone
date_default_timezone_set('Asia/Jakarta');

// Composer Autoload
if (file_exists(BASE_PATH . '/vendor/autoload.php')) {
    require_once BASE_PATH . '/vendor/autoload.php';
}
