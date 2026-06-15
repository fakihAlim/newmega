<?php
/**
 * Login Page
 * E-Procurement System
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';

// If already logged in, redirect to dashboard
if (isset($_SESSION['user'])) {
    session_write_close();
    header('Location: ' . APP_URL . '/modules/dashboard/index.php');
    exit;
}

$error = '';
if (isset($_GET['error'])) {
    if ($_GET['error'] === 'deactivated') {
        $error = 'Akun Anda telah dinonaktifkan. Hubungi administrator.';
    } elseif ($_GET['error'] === 'timeout') {
        $error = 'Sesi Anda telah berakhir karena tidak ada aktifitas. Silakan login kembali.';
    } elseif ($_GET['error'] === 'invalid_session') {
        $error = 'Sesi tidak valid. Silakan login kembali.';
    }
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Token keamanan tidak valid. Silakan muat ulang halaman.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            $error = 'Username dan password wajib diisi.';
        } else {
            try {
                $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND is_active = 1");
                $stmt->execute([$username]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['password'])) {
                    @session_regenerate_id(true);

                    // Update last login
                    $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);

                    // Fetch multiple roles
                    $stmtRoles = $pdo->prepare("SELECT r.role_key FROM roles r JOIN user_roles ur ON r.id = ur.role_id WHERE ur.user_id = ?");
                    $stmtRoles->execute([$user['id']]);
                    $userRoles = $stmtRoles->fetchAll(PDO::FETCH_COLUMN);

                    // Set session
                    $_SESSION['user'] = [
                        'id' => $user['id'],
                        'username' => $user['username'],
                        'roles' => $userRoles,
                        'full_name' => $user['full_name'],
                        'email' => $user['email'],
                        'photo' => $user['photo'],
                    ];

                    $_SESSION['user']['role'] = !empty($userRoles) ? $userRoles[0] : null;
                    $_SESSION['last_activity'] = time();
                    $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'];

                    session_write_close();
                    header('Location: ' . APP_URL . '/modules/dashboard/index.php');
                    exit;
                } else {
                    $error = 'Username atau password salah.';
                }
            } catch (PDOException $e) {
                if ((isset($e->errorInfo[1]) && $e->errorInfo[1] == 2006) || strpos($e->getMessage(), 'gone away') !== false) {
                    $error = 'Koneksi ke server database terputus. Silakan coba klik tombol MASUK sekali lagi.';
                } else {
                    $error = 'Terjadi kesalahan sistem: ' . $e->getMessage();
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login | <?= APP_NAME ?></title>

    <!-- Google Font: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

    <!-- AdminLTE -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">

    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/custom.css">

    <style>
        .login-page {
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #f7f9fb;
        }

        .login-box {
            margin: 0;
            width: 360px;
        }

        .login-bg-decoration {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            overflow: hidden;
            z-index: 0;
        }

        .login-bg-decoration .circle {
            position: absolute;
            border-radius: 50%;
            opacity: 0.05;
            background: #f28c28;
        }

        .login-bg-decoration .circle:nth-child(1) {
            width: 600px;
            height: 600px;
            top: -200px;
            right: -100px;
        }

        .login-bg-decoration .circle:nth-child(2) {
            width: 400px;
            height: 400px;
            bottom: -150px;
            left: -100px;
        }

        .login-bg-decoration .circle:nth-child(3) {
            width: 200px;
            height: 200px;
            top: 50%;
            left: 10%;
            opacity: 0.03;
        }

        .login-box {
            position: relative;
            z-index: 1;
        }

        .login-card-body {
            background: rgba(255, 255, 255, 0.97);
            backdrop-filter: blur(10px);
            border: 1px solid #e2e8f0;
            border-radius: 4px;
        }

        .login-subtitle {
            color: #94a3b8;
            font-size: 14px;
            margin-top: 4px;
        }

        .input-group-text {
            background: #f8fafc;
            border-color: #d1d5db;
            color: #94a3b8;
        }

        .form-control:focus+.input-group-append .input-group-text,
        .input-group-prepend .input-group-text {
            transition: all 0.2s;
        }

        .btn-login {
            background: #091426;
            color: #fff;
            border: none;
            padding: 12px;
            font-size: 15px;
            font-weight: 600;
            border-radius: 4px;
            letter-spacing: 0.3px;
            transition: all 0.3s ease;
        }

        .btn-login:hover {
            background: #1e293b;
            color: #fff;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(9, 20, 38, 0.2);
        }

        .error-msg {
            animation: shake 0.5s ease;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            20%, 60% { transform: translateX(-5px); }
            40%, 80% { transform: translateX(5px); }
        }
    </style>
</head>

<body class="hold-transition login-page">
    <!-- Background Decoration -->
    <div class="login-bg-decoration">
        <div class="circle"></div>
        <div class="circle"></div>
        <div class="circle"></div>
    </div>

    <div class="login-box">
        <div class="login-logo mb-3">
            <a href="index.php" class="d-flex align-items-center justify-content-center text-decoration-none" style="gap: 10px;">
                <strong>E-Procurement System</strong>
                 <!-- <span class="d-flex align-items-center justify-content-center" style="width:36px;height:36px;background:#091426;border-radius:4px;">
                    <i class="fas fa-hard-hat text-white" style="font-size:18px;"></i>
                </span>
                <span class="font-weight-bold" style="font-size: 24px; font-family: 'Montserrat', sans-serif;">
                    <span style="color:#091426;">MKM</span> <span style="color:#f28c28;">Procurement</span>
                </span> -->
            </a>
        </div>

        <div class="card shadow-sm">
            <div class="card-body login-card-body">
                <p class="login-box-msg font-weight-bold" style="font-size:16px;color:#1e293b;margin-bottom: 20px;">
                    Masuk ke akun Anda
                </p>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible error-msg" style="font-size:13px;">
                        <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
                        <i class="fas fa-exclamation-circle mr-1"></i> <?= sanitize($error) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" id="loginForm">
                    <?= csrfField() ?>
                    <div class="form-group">
                        <label for="username" style="font-size: 13px; color: #475569; font-weight: 600;">Username</label>
                        <div class="input-group">
                            <input type="text" id="username" name="username" class="form-control"
                                placeholder="Masukkan username" value="<?= sanitize($_POST['username'] ?? '') ?>"
                                autocomplete="username" autofocus required style="border-radius: 4px 0 0 4px;">
                            <div class="input-group-append">
                                <span class="input-group-text"><i class="fas fa-user"></i></span>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="password" style="font-size: 13px; color: #475569; font-weight: 600;">Password</label>
                        <div class="input-group">
                            <input type="password" id="password" name="password" class="form-control"
                                placeholder="Masukkan password" autocomplete="current-password" required>
                            <div class="input-group-append">
                                <button class="btn btn-outline-secondary" type="button" id="togglePassword"
                                    tabindex="-1" style="border-color: #d1d5db; color: #94a3b8; background: #f8fafc;">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-block btn-login mt-4">
                        <i class="fas fa-sign-in-alt mr-1"></i> MASUK
                    </button>
                    <button type="submit" class="btn btn-block btn-login mt-4">
                        <a href="index.php" class="d-flex align-items-center justify-content-center text-decoration-none" style="gap: 10px;">
                Ke Halaman Utama
            </a>
                    </button>
                </form>
            </div>
        </div>

        <!-- <p class="text-center mt-4" style="color:#64748b;font-size:12px;">
            &copy; <?= date('Y') ?> PT. Mega Karya Modern. All rights reserved.
        </p> -->
    </div>

    <!-- jQuery -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <!-- Bootstrap -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    <!-- AdminLTE -->
    <script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>

    <script>
        // Toggle password visibility
        document.getElementById('togglePassword').addEventListener('click', function () {
            const pwd = document.getElementById('password');
            const icon = this.querySelector('i');
            if (pwd.type === 'password') {
                pwd.type = 'text';
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                pwd.type = 'password';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        });
    </script>
</body>

</html>
