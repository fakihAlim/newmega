<?php
/**
 * Authentication - Impersonate (Login As)
 */
require_once __DIR__ . '/../../includes/auth.php';

$user = getCurrentUser();
$action = $_GET['action'] ?? '';
$target_user_id = intval($_GET['user_id'] ?? 0);

// Only super_admin is allowed to login as others
// If they are already impersonating, they can always log out of it
$isSuperAdmin = in_array('super_admin', $_SESSION['user']['roles'] ?? [$_SESSION['user']['role'] ?? '']);
$isImpersonating = isset($_SESSION['original_user']);

if (!$isSuperAdmin && !$isImpersonating) {
    setFlash('danger', 'Anda tidak memiliki akses untuk melakukan tindakan ini.');
    header('Location: ' . APP_URL . '/modules/dashboard/index.php');
    exit;
}

if ($action === 'login_as' && $target_user_id > 0) {
    // Prevent impersonating self
    if ($target_user_id == ($_SESSION['original_user']['id'] ?? $_SESSION['user']['id'])) {
        setFlash('warning', 'Anda sudah menggunakan akun ini.');
        header('Location: ' . APP_URL . '/modules/users/index.php');
        exit;
    }

    // Fetch target user details
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND is_active = 1");
    $stmt->execute([$target_user_id]);
    $targetUser = $stmt->fetch();

    if ($targetUser) {
        // Fetch target user's roles
        $stmtRoles = $pdo->prepare("SELECT r.role_key FROM roles r JOIN user_roles ur ON r.id = ur.role_id WHERE ur.user_id = ?");
        $stmtRoles->execute([$targetUser['id']]);
        $targetRoles = $stmtRoles->fetchAll(PDO::FETCH_COLUMN);

        // Store original user session if not already impersonating
        if (!isset($_SESSION['original_user'])) {
            $_SESSION['original_user'] = $_SESSION['user'];
        }

        // Set session to target user
        $_SESSION['user'] = [
            'id' => $targetUser['id'],
            'username' => $targetUser['username'],
            'roles' => $targetRoles,
            'full_name' => $targetUser['full_name'],
            'email' => $targetUser['email'],
            'photo' => $targetUser['photo'],
        ];
        
        $_SESSION['user']['role'] = !empty($targetRoles) ? $targetRoles[0] : null;

        logActivity('login_as', 'auth', "Bertindak sebagai user: {$targetUser['username']} (Impersonated)");
        setFlash('success', "Anda sekarang bertindak sebagai: <b>{$targetUser['full_name']}</b>");
        header('Location: ' . APP_URL . '/modules/dashboard/index.php');
        exit;
    } else {
        setFlash('danger', 'Pengguna tidak ditemukan atau tidak aktif.');
        header('Location: ' . APP_URL . '/modules/users/index.php');
        exit;
    }
}

if ($action === 'logout_as') {
    if (isset($_SESSION['original_user'])) {
        // Restore original user session
        $_SESSION['user'] = $_SESSION['original_user'];
        $restoredUsername = $_SESSION['user']['username'];
        unset($_SESSION['original_user']);

        logActivity('logout_as', 'auth', "Kembali ke akun asli: {$restoredUsername}");
        setFlash('success', "Kembali ke akun Administrator.");
        header('Location: ' . APP_URL . '/modules/users/index.php');
        exit;
    }
}

header('Location: ' . APP_URL . '/modules/dashboard/index.php');
exit;
