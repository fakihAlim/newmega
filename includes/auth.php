<?php
/**
 * Authentication Middleware
 * Include this at the top of every protected page
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/permissions.php';

// Check if user is logged in
function requireLogin() {
    if (!isset($_SESSION['user']) || !isset($_SESSION['user']['id'])) {
        header('Location: ' . APP_URL . '/index.php');
        exit;
    }
    // Session timeout (60 minutes)
    $timeout_duration = 3600;
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout_duration) {
        session_unset();
        session_destroy();
        header('Location: ' . APP_URL . '/index.php?error=timeout');
        exit;
    }
    $_SESSION['last_activity'] = time(); // update last activity time

    // IP Binding validation
    if (isset($_SESSION['ip_address']) && $_SESSION['ip_address'] !== $_SERVER['REMOTE_ADDR']) {
        session_unset();
        session_destroy();
        header('Location: ' . APP_URL . '/index.php?error=invalid_session');
        exit;
    }

    // Check if user is still active in DB
    global $pdo;
    $stmt = $pdo->prepare("SELECT is_active FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user']['id']]);
    $user = $stmt->fetch();
    
    if (!$user || !$user['is_active']) {
        session_destroy();
        header('Location: ' . APP_URL . '/index.php?error=deactivated');
        exit;
    }
}

// Call requireLogin automatically when this file is included
requireLogin();
