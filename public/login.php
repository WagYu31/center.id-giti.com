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
    <title>Login – Grav Center</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=11.0">
    <link rel="icon" type="image/png" href="assets/uploads/logo-square.png">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html, body { height: 100%; font-family: 'Plus Jakarta Sans', sans-serif; }

        /* ════════════════════════════════════
           LAYOUT
        ════════════════════════════════════ */
        .page {
            display: grid;
            grid-template-columns: 1fr 460px;
            grid-template-rows: 1fr;
            min-height: 100vh;
            background: #faf9f6;
        }

        /* ════════════════════════════════════
           LEFT PANEL
        ════════════════════════════════════ */
        .panel-left {
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 2.5rem 4rem;
            overflow: hidden;
            background: #faf9f6;
        }

        /* Soft golden glow top-right */
        .panel-left::before {
            content: '';
            position: absolute;
            width: 700px; height: 700px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(234,179,8,0.13) 0%, transparent 65%);
            top: -200px; right: -200px;
            pointer-events: none;
        }
        /* Soft golden glow bottom-left */
        .panel-left::after {
            content: '';
            position: absolute;
            width: 400px; height: 400px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(234,179,8,0.07) 0%, transparent 65%);
            bottom: -100px; left: -100px;
            pointer-events: none;
        }

        /* Subtle dot-grid texture */
        .dot-grid {
            position: absolute;
            inset: 0;
            background-image: radial-gradient(circle, rgba(0,0,0,0.07) 1px, transparent 1px);
            background-size: 28px 28px;
            mask-image: radial-gradient(ellipse 70% 70% at 40% 50%, black 20%, transparent 100%);
            pointer-events: none;
        }

        /* Top brand tag */
        .brand-tag {
            position: relative; z-index: 2;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(234,179,8,0.1);
            border: 1px solid rgba(234,179,8,0.28);
            border-radius: 100px;
            padding: 5px 14px 5px 8px;
            width: fit-content;
        }
        .live-dot {
            width: 8px; height: 8px;
            border-radius: 50%;
            background: #eab308;
            box-shadow: 0 0 0 3px rgba(234,179,8,0.2);
            animation: pulse 2.2s ease-in-out infinite;
        }
        @keyframes pulse {
            0%,100% { box-shadow: 0 0 0 3px rgba(234,179,8,0.2); }
            50%      { box-shadow: 0 0 0 7px rgba(234,179,8,0.05); }
        }
        .brand-tag span {
            font-size: 0.68rem;
            font-weight: 700;
            letter-spacing: 1.6px;
            color: #b45309;
            text-transform: uppercase;
        }

        /* Hero text */
        .hero {
            position: relative; z-index: 2;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .hero-eyebrow {
            font-size: 0.75rem;
            font-weight: 700;
            color: #eab308;
            letter-spacing: 0.8px;
            text-transform: uppercase;
            margin-bottom: 0.7rem;
        }
        .hero h1 {
            font-size: clamp(2rem, 2.6vw, 3rem);
            font-weight: 800;
            line-height: 1.1;
            letter-spacing: -0.04em;
            color: #0f172a;
            margin-bottom: 1rem;
        }
        .hero h1 em {
            font-style: normal;
            color: #eab308;
        }
        .hero-desc {
            font-size: 0.92rem;
            color: #64748b;
            line-height: 1.7;
            max-width: 400px;
            font-weight: 400;
            margin-bottom: 2.5rem;
        }

        /* Dashboard preview mockup */
        .mockup {
            position: relative;
            background: #ffffff;
            border: 1px solid rgba(0,0,0,0.07);
            border-radius: 20px;
            padding: 18px;
            box-shadow: 0 8px 40px rgba(0,0,0,0.07), 0 1px 3px rgba(0,0,0,0.04);
            max-width: 520px;
        }
        .mockup-header {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 14px;
        }
        .mockup-dots span {
            display: inline-block;
            width: 8px; height: 8px;
            border-radius: 50%;
        }
        .mockup-dots span:nth-child(1) { background: #fca5a5; }
        .mockup-dots span:nth-child(2) { background: #fcd34d; }
        .mockup-dots span:nth-child(3) { background: #86efac; }
        .mockup-title {
            font-size: 0.7rem;
            color: #94a3b8;
            font-weight: 500;
            margin-left: 4px;
        }
        .mockup-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-bottom: 12px;
        }
        .ms-card {
            background: #f8f9fb;
            border: 1px solid rgba(0,0,0,0.05);
            border-radius: 12px;
            padding: 12px 14px;
        }
        .ms-card .ms-label {
            font-size: 0.62rem;
            color: #94a3b8;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            margin-bottom: 5px;
        }
        .ms-card .ms-val {
            font-size: 1.3rem;
            font-weight: 800;
            color: #0f172a;
            letter-spacing: -0.03em;
        }
        .ms-card .ms-badge {
            display: inline-flex;
            align-items: center;
            gap: 2px;
            font-size: 0.6rem;
            font-weight: 700;
            margin-top: 3px;
        }
        .ms-badge.up   { color: #16a34a; }
        .ms-badge.down { color: #dc2626; }
        .ms-badge.neut { color: #eab308; }

        .mockup-bars {
            display: flex;
            align-items: flex-end;
            gap: 6px;
            height: 54px;
        }
        .bar {
            flex: 1;
            border-radius: 6px 6px 0 0;
            background: #f1f5f9;
            transition: background 0.3s;
        }
        .bar.active { background: linear-gradient(180deg, #fcd34d, #eab308); }
        .bar.semi   { background: rgba(234,179,8,0.3); }

        /* Feature pills */
        .pills {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 2rem;
        }
        .pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #fff;
            border: 1px solid rgba(0,0,0,0.08);
            border-radius: 100px;
            padding: 6px 14px;
            font-size: 0.76rem;
            font-weight: 600;
            color: #374151;
            box-shadow: 0 1px 4px rgba(0,0,0,0.04);
        }
        .pill i { color: #eab308; font-size: 0.85rem; }

        /* Footer */
        .left-footer {
            position: relative; z-index: 2;
            font-size: 0.7rem;
            color: #cbd5e1;
        }

        /* ════════════════════════════════════
           RIGHT PANEL
        ════════════════════════════════════ */
        .panel-right {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 2.5rem;
            background: #ffffff;
            border-left: 1px solid rgba(0,0,0,0.06);
            position: relative;
            min-height: 100vh;
        }

        .login-box {
            width: 100%;
            max-width: 380px;
        }

        /* Logo block */
        .logo-block {
            text-align: center;
            margin-bottom: 2rem;
        }
        .logo-block img {
            max-width: 180px;
            height: auto;
            display: block;
            margin: 0 auto 10px;
        }
        .logo-block p {
            font-size: 0.82rem;
            color: #94a3b8;
        }

        /* Welcome line */
        .welcome-line {
            text-align: center;
            margin-bottom: 1.8rem;
        }
        .welcome-line h2 {
            font-size: 1.45rem;
            font-weight: 800;
            color: #0f172a;
            letter-spacing: -0.02em;
            margin-bottom: 4px;
        }
        .welcome-line p {
            font-size: 0.82rem;
            color: #94a3b8;
        }

        /* Field */
        .field { margin-bottom: 1.1rem; }
        .field label {
            display: block;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            color: #64748b;
            margin-bottom: 6px;
        }
        .field-wrap { position: relative; }
        .field input {
            width: 100%;
            border: 1.5px solid #e2e8f0;
            border-radius: 12px;
            padding: 11.5px 16px;
            font-size: 0.88rem;
            font-family: 'Plus Jakarta Sans', sans-serif;
            color: #0f172a;
            background: #f8fafc;
            outline: none;
            transition: border-color 0.18s, box-shadow 0.18s, background 0.18s;
        }
        .field input::placeholder { color: #cbd5e1; }
        .field input:focus {
            border-color: #eab308;
            background: #fff;
            box-shadow: 0 0 0 4px rgba(234,179,8,0.1);
        }
        .field input.has-btn { padding-right: 46px; }
        .eye-toggle {
            position: absolute;
            right: 13px; top: 50%;
            transform: translateY(-50%);
            background: none; border: none;
            color: #cbd5e1; cursor: pointer;
            font-size: 1.05rem;
            display: flex; align-items: center;
            padding: 4px;
            transition: color 0.18s;
        }
        .eye-toggle:hover { color: #64748b; }

        /* Remember row */
        .remember-row {
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 0.6rem 0 1.4rem;
        }
        .remember-row input[type="checkbox"] {
            width: 15px; height: 15px;
            accent-color: #eab308;
            cursor: pointer;
        }
        .remember-row label {
            font-size: 0.8rem;
            color: #64748b;
            cursor: pointer;
            user-select: none;
        }

        /* Sign in button */
        .btn-signin {
            width: 100%;
            background: linear-gradient(135deg, #f59e0b, #eab308);
            color: #fff;
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-weight: 800;
            font-size: 0.92rem;
            letter-spacing: 0.2px;
            border: none;
            border-radius: 12px;
            padding: 13px;
            cursor: pointer;
            box-shadow: 0 4px 18px rgba(234,179,8,0.35), inset 0 1px 0 rgba(255,255,255,0.15);
            transition: all 0.18s ease;
            display: flex; align-items: center; justify-content: center; gap: 8px;
        }
        .btn-signin:hover {
            transform: translateY(-1px);
            box-shadow: 0 7px 24px rgba(234,179,8,0.45), inset 0 1px 0 rgba(255,255,255,0.15);
        }
        .btn-signin:active { transform: none; box-shadow: 0 2px 8px rgba(234,179,8,0.3); }
        .btn-signin .arrow-icon {
            width: 26px; height: 26px;
            background: rgba(255,255,255,0.2);
            border-radius: 7px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1rem;
        }

        /* Divider */
        .or-divider {
            display: flex; align-items: center; gap: 12px;
            margin: 1.2rem 0;
            font-size: 0.72rem; color: #cbd5e1;
            text-transform: uppercase; letter-spacing: 0.5px;
        }
        .or-divider::before, .or-divider::after {
            content: ''; flex: 1;
            height: 1px; background: #f1f5f9;
        }

        /* Register button */
        .btn-register {
            width: 100%;
            background: #f8fafc;
            color: #374151;
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-weight: 600;
            font-size: 0.85rem;
            border: 1.5px solid #e2e8f0;
            border-radius: 12px;
            padding: 12px;
            text-align: center;
            text-decoration: none;
            display: flex; align-items: center; justify-content: center; gap: 7px;
            transition: all 0.18s ease;
        }
        .btn-register:hover {
            border-color: #eab308;
            color: #0f172a;
            background: rgba(234,179,8,0.04);
        }

        /* Alert */
        .auth-alert {
            display: flex; align-items: center; gap: 9px;
            border-radius: 12px;
            padding: 10px 14px;
            font-size: 0.81rem;
            margin-bottom: 14px;
            font-weight: 500;
        }
        .auth-alert.danger  { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
        .auth-alert.success { background: #f0fdf4; color: #16a34a; border: 1px solid #bbf7d0; }

        /* Security footnote */
        .footnote {
            text-align: center;
            margin-top: 1.5rem;
            font-size: 0.68rem;
            color: #e2e8f0;
            display: flex; align-items: center; justify-content: center; gap: 5px;
        }

        /* ════════════════════════════════════
           RESPONSIVE
        ════════════════════════════════════ */
        @media (max-width: 1280px) {
            .page { grid-template-columns: 1fr 420px; }
            .panel-left { padding: 2.5rem 2.5rem; }
            .mockup { max-width: 440px; }
        }
        @media (max-width: 1100px) {
            .page { grid-template-columns: 1fr 400px; }
            .pills { display: none; }
            .hero h1 { font-size: 2.2rem; }
        }
        @media (max-width: 900px) {
            .page { grid-template-columns: 1fr; }
            .panel-left { display: none; }
            .panel-right { border-left: none; min-height: 100vh; }
        }
    </style>
</head>
<body>

<div class="page">

    <!-- ═══════════════════ LEFT PANEL ═══════════════════ -->
    <div class="panel-left">
        <div class="dot-grid"></div>

        <!-- Brand Tag -->
        <div class="brand-tag" style="position:relative;z-index:2;">
            <div class="live-dot"></div>
            <span>Gravitti Core &nbsp;·&nbsp; Internal Platform</span>
        </div>

        <!-- Hero + Content -->
        <div class="hero">
            <div class="hero-eyebrow">Platform Manajemen Tim</div>
            <h1>Satu Tempat<br>untuk <em>Semua</em><br>Kendali Tim.</h1>
            <p class="hero-desc">
                Dashboard terpadu untuk memantau target penjualan, absensi, progress divisi, dan koordinasi tim GraViTTi secara real-time.
            </p>

            <!-- Pills -->
            <div class="pills">
                <div class="pill"><i class="bi bi-graph-up-arrow"></i> Target & Sales</div>
                <div class="pill"><i class="bi bi-people-fill"></i> Kolaborasi Tim</div>
                <div class="pill"><i class="bi bi-calendar2-check"></i> Absensi</div>
                <div class="pill"><i class="bi bi-bell-fill"></i> Notifikasi</div>
            </div>

            <!-- Dashboard Mockup -->
            <div class="mockup">
                <div class="mockup-header">
                    <div class="mockup-dots">
                        <span></span><span></span><span></span>
                    </div>
                    <div class="mockup-title">Grav Center &nbsp;·&nbsp; Dashboard Overview</div>
                </div>

                <div class="mockup-stats">
                    <div class="ms-card">
                        <div class="ms-label">Target Bulan Ini</div>
                        <div class="ms-val">87<span style="font-size:0.8rem;font-weight:600;color:#94a3b8;">%</span></div>
                        <div class="ms-badge up"><i class="bi bi-arrow-up"></i> +12%</div>
                    </div>
                    <div class="ms-card">
                        <div class="ms-label">Anggota Aktif</div>
                        <div class="ms-val">24</div>
                        <div class="ms-badge neut"><i class="bi bi-circle-fill" style="font-size:5px;"></i> Online</div>
                    </div>
                    <div class="ms-card">
                        <div class="ms-label">Tugas Selesai</div>
                        <div class="ms-val">142</div>
                        <div class="ms-badge up"><i class="bi bi-arrow-up"></i> +8%</div>
                    </div>
                </div>

                <div style="margin-bottom:8px;">
                    <div style="font-size:0.62rem;color:#94a3b8;font-weight:600;text-transform:uppercase;letter-spacing:0.4px;margin-bottom:8px;">Aktivitas Minggu Ini</div>
                    <div class="mockup-bars">
                        <div class="bar semi"  style="height:35%;"></div>
                        <div class="bar semi"  style="height:50%;"></div>
                        <div class="bar"       style="height:40%;"></div>
                        <div class="bar active"style="height:80%;"></div>
                        <div class="bar active"style="height:95%;"></div>
                        <div class="bar semi"  style="height:65%;"></div>
                        <div class="bar semi"  style="height:55%;"></div>
                        <div class="bar"       style="height:30%;"></div>
                        <div class="bar active"style="height:72%;"></div>
                        <div class="bar active"style="height:88%;"></div>
                        <div class="bar semi"  style="height:60%;"></div>
                        <div class="bar"       style="height:45%;"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="left-footer">
            &copy; <?= date('Y') ?> GraViTTi Technology. All rights reserved.
        </div>
    </div>

    <!-- ═══════════════════ RIGHT PANEL ═══════════════════ -->
    <div class="panel-right">
        <div class="login-box">

            <!-- Logo -->
            <div class="logo-block">
                <img src="assets/uploads/logo-gravitti.png" alt="GraViTTi Technology">
                <p>Selamat datang kembali 👋</p>
            </div>

            <!-- Welcome -->
            <div class="welcome-line">
                <h2>Masuk ke Akun Kamu</h2>
                <p>Masukkan email & password untuk melanjutkan</p>
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

                <div class="field">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" placeholder="nama@example.com" required autocomplete="email">
                </div>

                <div class="field">
                    <label for="password">Password</label>
                    <div class="field-wrap">
                        <input type="password" class="has-btn" id="password" name="password" placeholder="Masukkan password" required autocomplete="current-password">
                        <button type="button" id="togglePassword" class="eye-toggle" aria-label="Toggle Password">
                            <i class="bi bi-eye" id="toggleIcon"></i>
                        </button>
                    </div>
                </div>

                <div class="remember-row">
                    <input type="checkbox" id="remember-me" name="remember-me">
                    <label for="remember-me">Ingat saya selama 30 hari</label>
                </div>

                <button type="submit" class="btn-signin">
                    Masuk ke Dashboard
                    <span class="arrow-icon"><i class="bi bi-arrow-right"></i></span>
                </button>

            </form>

            <div class="or-divider">atau</div>

            <a href="register.php" class="btn-register">
                <i class="bi bi-person-plus"></i>
                Buat Akun Baru
            </a>

            <div class="footnote">
                <i class="bi bi-shield-lock-fill"></i>
                Dilindungi sistem keamanan GraViTTi
            </div>

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