<?php
session_name('CENTER_SESSION');
session_set_cookie_params([
    'lifetime' => 86400,
    'path' => '/',
    'domain' => '', 
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Lax'
]);
session_start();

require_once '../config/database.php';
require_once '../src/auth.php';
require_once '../src/functions.php';

if (auto_login($conn) || isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$error = '';
$success_msg = '';

if (isset($_SESSION['success_msg'])) {
    $success_msg = $_SESSION['success_msg'];
    unset($_SESSION['success_msg']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];
    $remember = isset($_POST['remember-me']);

    $loginStatus = login_user($email, $password, $remember, $conn);

    if ($loginStatus === 'SUCCESS') {
        header("Location: index.php");
        exit();
    } elseif ($loginStatus === 'UNVERIFIED') {
        $_SESSION['verify_email'] = $email;
        header("Location: verify-otp.php");
        exit();
    } else {
        $error = 'Email atau password salah.';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Grav Center</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=11.0">
    <link rel="icon" type="image/png" href="assets/uploads/logo-square.png">
</head>
<body>

    <div class="auth-container">
        <div class="auth-card">
            
            <div class="auth-header">
                <div class="auth-icon">
                    <i class="bi bi-grid-fill"></i>
                </div>
                <h3 class="fw-bold mb-1">Welcome Back!</h3>
                <p class="text-secondary small">Masuk untuk mengakses Grav Center</p>
            </div>

            <?php if($success_msg): ?>
                <div class="alert alert-success py-2 small text-center border-0 bg-success-subtle text-success mb-4">
                    <i class="bi bi-check-circle me-1"></i> <?= $success_msg ?>
                </div>
            <?php endif; ?>

            <?php if($error): ?>
                <div class="alert alert-danger py-2 small text-center border-0 bg-danger-subtle text-danger mb-4">
                    <i class="bi bi-exclamation-circle me-1"></i> <?= $error ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                
                <div class="mb-3 text-start">
                    <label for="email" class="form-label fw-bold text-secondary mb-1.5" style="font-size: 0.8rem; letter-spacing: 0.3px; text-transform: uppercase;">Email Address</label>
                    <input type="email" class="form-control" id="email" name="email" placeholder="Masukkan email Anda" required style="border-radius: 12px; border: 1.5px solid rgba(0,0,0,0.08); padding: 12px 16px; font-size: 0.9rem; background-color: rgba(255,255,255,0.6); transition: all 0.2s;">
                </div>

                <div class="mb-4 text-start">
                    <label for="password" class="form-label fw-bold text-secondary mb-1.5" style="font-size: 0.8rem; letter-spacing: 0.3px; text-transform: uppercase;">Password</label>
                    <div style="position: relative;">
                        <input type="password" class="form-control" id="password" name="password" placeholder="Masukkan password Anda" required style="border-radius: 12px; border: 1.5px solid rgba(0,0,0,0.08); padding: 12px 16px; padding-right: 46px; font-size: 0.9rem; background-color: rgba(255,255,255,0.6); transition: all 0.2s; width: 100%;">
                        <button type="button" id="togglePassword" aria-label="Toggle Password Visibility" style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); background: none; border: none; color: #9ca3af; cursor: pointer; font-size: 1.25rem; display: flex; align-items: center; justify-content: center; padding: 4px; z-index: 5;">
                            <i class="bi bi-eye" id="toggleIcon"></i>
                        </button>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="remember-me" name="remember-me" style="cursor: pointer; border-color: rgba(0,0,0,0.15);">
                        <label class="form-check-label text-secondary small" for="remember-me" style="cursor: pointer; user-select: none;">
                            Ingat Saya
                        </label>
                    </div>
                </div>

                <button class="btn btn-login-dark mb-3" type="submit" style="background: linear-gradient(135deg, var(--gv-primary), var(--gv-primary-light)); color: #1a1a1a; font-weight: 700; border-radius: 12px; padding: 12px; width: 100%; border: none; font-size: 0.95rem; box-shadow: 0 4px 16px rgba(234, 179, 8, 0.15); transition: all 0.2s;">
                    Sign In
                </button>

            </form>

            <div class="divider-text" style="color: #9ca3af; font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.5px; margin: 24px 0; display: flex; align-items: center; justify-content: center; gap: 10px;">
                <span style="height: 1px; background: rgba(0,0,0,0.06); flex-grow: 1;"></span>
                <span>atau</span>
                <span style="height: 1px; background: rgba(0,0,0,0.06); flex-grow: 1;"></span>
            </div>
                
            <a href="register.php" class="btn w-100 py-2.5 fw-bold" style="border: 1.5px solid rgba(0,0,0,0.08); border-radius: 12px; color: var(--text-secondary); background: transparent; font-size: 0.9rem; transition: all 0.2s;" onmouseover="this.style.borderColor='var(--gv-primary)'; this.style.color='var(--text-primary)'" onmouseout="this.style.borderColor='rgba(0,0,0,0.08)'; this.style.color='var(--text-secondary)'">
                Daftar Akun Baru
            </a>

            <div class="text-center mt-4">
                <p class="small text-secondary mb-0" style="font-size: 0.75rem;">
                    &copy; <?= date('Y') ?> Grav Technology
                </p>
            </div>

        </div>
    </div>

    <script>
        // Custom focus styles for inputs
        document.querySelectorAll('input[type="email"], input[type="password"]').forEach(input => {
            input.addEventListener('focus', () => {
                input.style.borderColor = 'var(--gv-primary)';
                input.style.boxShadow = '0 0 0 4px rgba(234, 179, 8, 0.12)';
                input.style.backgroundColor = '#ffffff';
            });
            input.addEventListener('blur', () => {
                input.style.borderColor = 'rgba(0,0,0,0.08)';
                input.style.boxShadow = 'none';
                input.style.backgroundColor = 'rgba(255,255,255,0.6)';
            });
        });

        // Password visibility toggler
        document.getElementById('togglePassword').addEventListener('click', function () {
            const passwordInput = document.getElementById('password');
            const icon = document.getElementById('toggleIcon');
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.remove('bi-eye');
                icon.classList.add('bi-eye-slash');
            } else {
                passwordInput.type = 'password';
                icon.classList.remove('bi-eye-slash');
                icon.classList.add('bi-eye');
            }
        });
    </script>

</body>
</html>