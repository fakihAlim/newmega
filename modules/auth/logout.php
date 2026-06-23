<?php
/**
 * Logout
 */
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/functions.php';

logActivity('logout', 'auth', 'Keluar dari sistem');

session_destroy();
header('Location: ' . APP_URL . '/login.php');
exit;
