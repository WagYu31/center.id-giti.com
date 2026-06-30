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
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=11.0">
    <link rel="icon" type="image/png" href="assets/uploads/logo-square.png">
    <style>
        *, *::before, *::after { box-sizing: border-box; }

        body { margin: 0; padding: 0; font-family: 'Plus Jakarta Sans', sans-serif; }

        /* ─── WRAPPER ─────────────────────────────── */
        .auth-split-wrapper {
            display: flex;
            min-height: 100vh;
            width: 100%;
        }

        /* ─── LEFT PANEL ──────────────────────────── */
        .auth-split-left {
            flex: 1.15;
            background: #060b14;
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 3rem 3.5rem;
            overflow: hidden;
            color: white;
        }

        /* Animated gradient glow */
        .auth-split-left::before {
            content: '';
            position: absolute;
            width: 600px; height: 600px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(234,179,8,0.18) 0%, transparent 70%);
            top: -150px; right: -150px;
            animation: glowPulse 6s ease-in-out infinite;
            z-index: 0;
        }
        .auth-split-left::after {
            content: '';
            position: absolute;
            width: 400px; height: 400px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(234,179,8,0.10) 0%, transparent 70%);
            bottom: -100px; left: -100px;
            animation: glowPulse 8s ease-in-out infinite reverse;
            z-index: 0;
        }
        @keyframes glowPulse {
            0%, 100% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.15); opacity: 0.7; }
        }

        /* Grid overlay */
        .grid-overlay {
            position: absolute;
            inset: 0;
            background-image:
                linear-gradient(rgba(255,255,255,0.025) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,0.025) 1px, transparent 1px);
            background-size: 48px 48px;
            z-index: 0;
            mask-image: radial-gradient(ellipse 80% 80% at 50% 50%, black 40%, transparent 100%);
        }

        /* All content above overlays */
        .left-content { position: relative; z-index: 2; }

        /* Brand badge */
        .brand-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(234,179,8,0.1);
            border: 1px solid rgba(234,179,8,0.25);
            border-radius: 100px;
            padding: 6px 14px 6px 8px;
        }
        .brand-dot {
            width: 8px; height: 8px;
            border-radius: 50%;
            background: #eab308;
            box-shadow: 0 0 8px #eab308, 0 0 16px rgba(234,179,8,0.5);
            animation: blink 2s ease-in-out infinite;
        }
        @keyframes blink { 0%,100%{opacity:1} 50%{opacity:0.4} }

        /* Feature cards */
        .feature-cards {
            display: flex;
            flex-direction: column;
            gap: 14px;
            margin-bottom: 3rem;
            margin-top: 2.5rem;
        }
        .feature-card {
            display: flex;
            align-items: center;
            gap: 16px;
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.07);
            border-radius: 16px;
            padding: 16px 20px;
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
        }
        .feature-card:hover {
            background: rgba(255,255,255,0.07);
            border-color: rgba(234,179,8,0.25);
            transform: translateX(4px);
        }
        .feature-icon {
            width: 44px; height: 44px;
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.25rem;
            flex-shrink: 0;
        }
        .feature-icon.yellow { background: rgba(234,179,8,0.15); color: #eab308; }
        .feature-icon.blue   { background: rgba(59,130,246,0.12); color: #60a5fa; }
        .feature-icon.green  { background: rgba(34,197,94,0.12);  color: #4ade80; }
        .feature-card-text h6 {
            margin: 0 0 2px;
            font-size: 0.88rem;
            font-weight: 700;
            color: #f1f5f9;
        }
        .feature-card-text p {
            margin: 0;
            font-size: 0.76rem;
            color: #6b7280;
            font-weight: 300;
        }

        /* Stats row */
        .stats-row {
            display: flex;
            gap: 24px;
            margin-bottom: 2.5rem;
        }
        .stat-item { flex: 1; }
        .stat-item .stat-num {
            font-size: 1.6rem;
            font-weight: 800;
            color: #eab308;
            letter-spacing: -0.03em;
            line-height: 1;
        }
        .stat-item .stat-label {
            font-size: 0.72rem;
            color: #4b5563;
            font-weight: 400;
            margin-top: 2px;
        }

        /* ─── RIGHT PANEL ─────────────────────────── */
        .auth-split-right {
            flex: 0.85;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f8f7f4;
            background-image:
                radial-gradient(circle at 30% 20%, rgba(234,179,8,0.06) 0%, transparent 50%),
                radial-gradient(circle at 80% 80%, rgba(234,179,8,0.04) 0%, transparent 50%);
            padding: 2rem;
            position: relative;
        }

        /* Decorative circles on right */
        .auth-split-right::before {
            content: '';
            position: absolute;
            width: 300px; height: 300px;
            border-radius: 50%;
            border: 1px solid rgba(234,179,8,0.08);
            top: -80px; right: -80px;
        }
        .auth-split-right::after {
            content: '';
            position: absolute;
            width: 200px; height: 200px;
            border-radius: 50%;
            border: 1px solid rgba(234,179,8,0.06);
            bottom: -50px; left: -50px;
        }

        /* Login Card */
        .login-card {
            width: 100%;
            max-width: 440px;
            background: #ffffff;
            border-radius: 24px;
            padding: 2.5rem;
            box-shadow:
                0 0 0 1px rgba(0,0,0,0.04),
                0 8px 40px rgba(0,0,0,0.08),
                0 2px 8px rgba(0,0,0,0.04);
            position: relative;
            z-index: 1;
        }

        /* Top accent line */
        .card-accent {
            position: absolute;
            top: 0; left: 50%; transform: translateX(-50%);
            width: 60px; height: 3px;
            background: linear-gradient(90deg, #eab308, #fcd34d);
            border-radius: 0 0 4px 4px;
        }

        /* Form fields */
        .field-label {
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.6px;
            text-transform: uppercase;
            color: #6b7280;
            margin-bottom: 6px;
            display: block;
        }
        .field-input {
            width: 100%;
            border: 1.5px solid #e5e7eb;
            border-radius: 12px;
            padding: 12px 16px;
            font-size: 0.9rem;
            font-family: 'Plus Jakarta Sans', sans-serif;
            color: #111827;
            background: #fafafa;
            transition: all 0.2s ease;
            outline: none;
        }
        .field-input::placeholder { color: #9ca3af; }
        .field-input:focus {
            border-color: #eab308;
            box-shadow: 0 0 0 4px rgba(234,179,8,0.1);
            background: #ffffff;
        }
        .field-wrapper { position: relative; }
        .eye-btn {
            position: absolute;
            right: 12px; top: 50%; transform: translateY(-50%);
            background: none; border: none;
            color: #9ca3af; cursor: pointer;
            font-size: 1.1rem;
            display: flex; align-items: center;
            padding: 4px;
            transition: color 0.2s;
        }
        .eye-btn:hover { color: #374151; }

        /* Submit button */
        .btn-signin {
            width: 100%;
            background: linear-gradient(135deg, #eab308, #fcd34d);
            color: #111827;
            font-weight: 800;
            font-size: 0.95rem;
            border: none;
            border-radius: 12px;
            padding: 13px;
            letter-spacing: 0.3px;
            box-shadow: 0 4px 20px rgba(234,179,8,0.3);
            transition: all 0.2s ease;
            cursor: pointer;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }
        .btn-signin:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 28px rgba(234,179,8,0.4);
        }
        .btn-signin:active { transform: translateY(0); }

        /* Register link */
        .btn-register {
            width: 100%;
            background: transparent;
            color: #374151;
            font-weight: 600;
            font-size: 0.88rem;
            border: 1.5px solid #e5e7eb;
            border-radius: 12px;
            padding: 12px;
            text-align: center;
            text-decoration: none;
            display: block;
            transition: all 0.2s ease;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }
        .btn-register:hover {
            border-color: #eab308;
            color: #111827;
            background: rgba(234,179,8,0.04);
        }

        /* Divider */
        .divider {
            display: flex; align-items: center; gap: 12px;
            margin: 20px 0;
            color: #9ca3af; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px;
        }
        .divider::before, .divider::after {
            content: ''; flex: 1;
            height: 1px; background: #f3f4f6;
        }

        /* Checkbox */
        .check-label {
            display: flex; align-items: center; gap: 8px;
            font-size: 0.83rem; color: #6b7280; cursor: pointer;
            user-select: none;
        }
        .check-label input[type="checkbox"] {
            width: 16px; height: 16px; cursor: pointer;
            accent-color: #eab308;
        }

        /* Alert */
        .auth-alert {
            border-radius: 12px;
            padding: 10px 14px;
            font-size: 0.82rem;
            margin-bottom: 16px;
            display: flex; align-items: center; gap: 8px;
            border: none;
        }
        .auth-alert.danger { background: #fef2f2; color: #dc2626; }
        .auth-alert.success { background: #f0fdf4; color: #16a34a; }

        @media (max-width: 991px) {
            .auth-split-left { display: none; }
            .auth-split-right { flex: 1; padding: 1.5rem; }
        }
    </style>
</head>
<body>

<div class="auth-split-wrapper">

    <!-- ══════════════ LEFT PANEL ══════════════ -->
    <div class="auth-split-left">
        <div class="grid-overlay"></div>

        <!-- Top Brand -->
        <div class="left-content">
            <div class="brand-badge">
                <div class="brand-dot"></div>
                <span style="font-size:0.72rem; font-weight:700; letter-spacing:1.5px; color:#eab308; text-transform:uppercase;">Gravitti Core</span>
            </div>
        </div>

        <!-- Center: Headline + Cards -->
        <div class="left-content" style="margin: auto 0;">
            <h1 style="font-size:2.6rem; font-weight:800; line-height:1.12; letter-spacing:-0.03em; margin-bottom:0.75rem;">
                Satu Platform.<br>
                <span style="background: linear-gradient(135deg,#eab308,#fcd34d); -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text;">Semua Kendali.</span>
            </h1>
            <p style="font-size:0.95rem; color:#6b7280; line-height:1.65; margin-bottom:0; font-weight:300; max-width:380px;">
                Dashboard internal tim GraViTTi untuk memantau produktivitas, penjualan, dan kolaborasi divisi.
            </p>

            <div class="feature-cards">
                <div class="feature-card">
                    <div class="feature-icon yellow"><i class="bi bi-graph-up-arrow"></i></div>
                    <div class="feature-card-text">
                        <h6>Target & Progress</h6>
                        <p>Pantau target harian & pencapaian penjualan</p>
                    </div>
                </div>
                <div class="feature-card">
                    <div class="feature-icon blue"><i class="bi bi-people-fill"></i></div>
                    <div class="feature-card-text">
                        <h6>Kolaborasi Divisi</h6>
                        <p>Koordinasi antar tim secara real-time</p>
                    </div>
                </div>
                <div class="feature-card">
                    <div class="feature-icon green"><i class="bi bi-calendar2-check-fill"></i></div>
                    <div class="feature-card-text">
                        <h6>Absensi & Jadwal</h6>
                        <p>Kelola kehadiran & jadwal kerja tim</p>
                    </div>
                </div>
            </div>

            <!-- Stats -->
            <div class="stats-row">
                <div class="stat-item">
                    <div class="stat-num">5+</div>
                    <div class="stat-label">Divisi Aktif</div>
                </div>
                <div class="stat-item">
                    <div class="stat-num">24/7</div>
                    <div class="stat-label">Monitoring</div>
                </div>
                <div class="stat-item">
                    <div class="stat-num">100%</div>
                    <div class="stat-label">Realtime</div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="left-content" style="font-size:0.72rem; color:#374151; letter-spacing:0.3px;">
            &copy; <?= date('Y') ?> GraViTTi Technology. All rights reserved.
        </div>
    </div>

    <!-- ══════════════ RIGHT PANEL ══════════════ -->
    <div class="auth-split-right">
        <div class="login-card">
            <div class="card-accent"></div>

            <!-- Logo -->
            <div class="text-center mb-4">
                <img src="assets/uploads/logo-gravitti.png" alt="GraViTTi Technology" style="max-width:200px; height:auto; display:block; margin:0 auto 10px;">
                <p style="font-size:0.83rem; color:#9ca3af; margin:0;">Masuk untuk mengakses Grav Center</p>
            </div>

            <?php if($success_msg): ?>
                <div class="auth-alert success">
                    <i class="bi bi-check-circle-fill"></i> <?= $success_msg ?>
                </div>
            <?php endif; ?>

            <?php if($error): ?>
                <div class="auth-alert danger">
                    <i class="bi bi-exclamation-circle-fill"></i> <?= $error ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">

                <div class="mb-3">
                    <label for="email" class="field-label">Email Address</label>
                    <input type="email" class="field-input" id="email" name="email" placeholder="Masukkan email Anda" required autocomplete="email">
                </div>

                <div class="mb-2">
                    <label for="password" class="field-label">Password</label>
                    <div class="field-wrapper">
                        <input type="password" class="field-input" id="password" name="password" placeholder="Masukkan password Anda" required autocomplete="current-password" style="padding-right:46px;">
                        <button type="button" id="togglePassword" class="eye-btn" aria-label="Toggle Password">
                            <i class="bi bi-eye" id="toggleIcon"></i>
                        </button>
                    </div>
                </div>

                <div class="mb-4 mt-3">
                    <label class="check-label" for="remember-me">
                        <input type="checkbox" id="remember-me" name="remember-me">
                        Ingat Saya
                    </label>
                </div>

                <button type="submit" class="btn-signin mb-2">
                    Sign In &nbsp;<i class="bi bi-arrow-right-short" style="font-size:1.1rem;vertical-align:-1px;"></i>
                </button>

            </form>

            <div class="divider">atau</div>

            <a href="register.php" class="btn-register">
                <i class="bi bi-person-plus me-1"></i> Daftar Akun Baru
            </a>

            <p style="text-align:center; font-size:0.72rem; color:#d1d5db; margin-top:1.5rem; margin-bottom:0;">
                Protected by GraViTTi Security System
            </p>
        </div>
    </div>

</div>

<script>
    document.getElementById('togglePassword').addEventListener('click', function () {
        const input = document.getElementById('password');
        const icon  = document.getElementById('toggleIcon');
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.replace('bi-eye', 'bi-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.replace('bi-eye-slash', 'bi-eye');
        }
    });
</script>

</body>
</html>