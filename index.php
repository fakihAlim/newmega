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
                // Attempt to regenerate session ID for security (prevent session fixation).
                // Use error suppression — on some shared hosting, this can fail silently
                // due to session save path restrictions. Login must still work regardless.
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
                    'roles' => $userRoles, // Store as array
                    'full_name' => $user['full_name'],
                    'email' => $user['email'],
                    'photo' => $user['photo'],
                ];

                // Backward compatibility for code still checking $user['role']
                $_SESSION['user']['role'] = !empty($userRoles) ? $userRoles[0] : null;

                // Session Security
                $_SESSION['last_activity'] = time();
                $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'];

                session_write_close();
                header('Location: ' . APP_URL . '/modules/dashboard/index.php');
                exit;
            } else {
                $error = 'Username atau password salah.';
            }
        } catch (PDOException $e) {
            // Check for MySQL server has gone away or connection lost
            if ((isset($e->errorInfo[1]) && $e->errorInfo[1] == 2006) || strpos($e->getMessage(), 'gone away') !== false) {
                // Attempt to reconnect if needed, but for login page, just show a friendly error
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

    <!-- Google Font: Roboto -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

    <!-- AdminLTE -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">

    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/custom.css">

    <style>
        .login-page {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-box {
            margin: 0;
            position: relative;
            z-index: 1;
        }

        .login-card-body {
            background: #ffffff;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
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
            background: #f59e0b;
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
            background: #d97706;
            transform: translateY(-1px);
        }

        .login-features {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #f1f5f9;
        }

        .login-features .feature-item {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 13px;
            color: #64748b;
            margin-bottom: 8px;
        }

        .login-features .feature-item i {
            color: #f59e0b;
            width: 16px;
            text-align: center;
        }

        .error-msg {
            animation: shake 0.5s ease;
        }

        @keyframes shake {

            0%,
            100% {
                transform: translateX(0);
            }

            20%,
            60% {
                transform: translateX(-5px);
            }

            40%,
            80% {
                transform: translateX(5px);
            }
        }
    </style>
</head>

<body class="hold-transition login-page">
    <div class="login-box">
        <div class="login-logo">
            <a href="#">
                <img src="<?= APP_URL ?>/assets/img/logo-perusahaan.png" alt="Logo PT MKM" style="height: 50px; margin-bottom: 10px; border-radius: 4px;">
                <br>
                <span style="color:#f59e0b; font-weight: 700;">MKM</span> <span style="color:#fff; font-weight: 700;">Procurement</span>
            </a>
        </div>

        <div class="card">
            <div class="card-body login-card-body">
                <p class="login-box-msg" style="font-size:15px;color:#475569;font-weight:500;">
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
                        <label for="username"><i class="fas fa-user mr-1 text-muted"></i> Username</label>
                        <input type="text" id="username" name="username" class="form-control"
                            placeholder="Masukkan username" value="<?= sanitize($_POST['username'] ?? '') ?>"
                            autocomplete="username" autofocus required>
                    </div>

                    <div class="form-group">
                        <label for="password"><i class="fas fa-lock mr-1 text-muted"></i> Password</label>
                        <div class="input-group">
                            <input type="password" id="password" name="password" class="form-control"
                                placeholder="Masukkan password" autocomplete="current-password" required>
                            <div class="input-group-append">
                                <button class="btn btn-outline-secondary" type="button" id="togglePassword"
                                    tabindex="-1">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-warning btn-block btn-login mt-4">
                        <i class="fas fa-sign-in-alt mr-1"></i> MASUK
                    </button>
                </form>

                <!-- <div class="login-features">
                    <div class="feature-item">
                        <i class="fas fa-check-circle"></i>
                        <span>Manajemen Material Request & Purchase Order</span>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-check-circle"></i>
                        <span>Quotation & Invoice Management</span>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-check-circle"></i>
                        <span>Stock & Warehouse Control</span>
                    </div>
                </div> -->
            </div>
        </div>

        <p class="text-center mt-3" style="color:#64748b;font-size:12px;">
            &copy; <?= date('Y') ?> PT. Mega Karya Modern. All rights reserved.
        </p>
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