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
                
                <div class="form-floating mb-3">
                    <input type="email" class="form-control" id="email" name="email" placeholder="name@example.com" required>
                    <label for="email" class="text-secondary">Email Address</label>
                </div>

                <div class="password-toggle-container mb-3">
                    <div class="form-floating">
                        <input type="password" class="form-control" id="password" name="password" placeholder="Password" required>
                        <label for="password" class="text-secondary">Password</label>
                    </div>
                    <button type="button" class="password-toggle-btn" id="togglePassword" aria-label="Toggle Password Visibility">
                        <i class="bi bi-eye" id="toggleIcon"></i>
                    </button>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="remember-me" name="remember-me">
                        <label class="form-check-label text-secondary small" for="remember-me">
                            Ingat Saya
                        </label>
                    </div>
                </div>

                <button class="btn btn-login-dark mb-3" type="submit">
                    Sign In
                </button>

            </form>

            <div class="divider-text">atau masuk dengan</div>
                
            <a href="register.php" class="btn btn-outline-secondary w-100 rounded-pill py-2 fw-bold" style="border: 1px solid #e0e0e0;">
                Sign Up
            </a>

            <!--<a href="https://accounts.google.com/o/oauth2/auth?response_type=code&access_type=online&client_id=GANTI_CLIENT_ID&redirect_uri=GANTI_REDIRECT_URL&scope=email+profile" class="btn btn-login-google">-->
            <!--    <i class="bi bi-google"></i> Google Account-->
            <!--</a>-->

            <div class="text-center mt-4">
                <p class="small text-secondary mb-0">
                    &copy; <?= date('Y') ?> Grav Technology
                </p>
            </div>

        </div>
    </div>

    <script>
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