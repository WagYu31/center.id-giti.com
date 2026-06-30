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
require_once '../src/send_email.php';

if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name']);
    $email    = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $division = $_POST['division'];
    $jabatan  = $_POST['jabatan'];
    $telp     = $_POST['telp'];

    if ($password !== $confirm_password) {
        $error = "Konfirmasi password tidak sesuai.";
    } else {
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = :email");
        $stmt->execute([':email' => $email]);
        if ($stmt->rowCount() > 0) {
            $error = "Email sudah terdaftar.";
        } else {
            $otp = rand(100000, 999999);
            $otp_expires = date("Y-m-d H:i:s", strtotime("+15 minutes"));
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            try {
                $sql = "INSERT INTO users (name, email, password, division, jabatan, telp, verification_code, otp_expires_at, role, app_bukti, app_teknisi, app_sales) 
                        VALUES (:name, :email, :pass, :div, :jab, :telp, :otp, :exp, 'user', 0, 0, 0)";
                
                $stmt = $conn->prepare($sql);
                $stmt->execute([
                    ':name' => $name,
                    ':email' => $email,
                    ':pass' => $hashed_password,
                    ':div'  => $division,
                    ':jab'  => $jabatan,
                    ':telp' => $telp,
                    ':otp'  => $otp,
                    ':exp'  => $otp_expires
                ]);

                if (sendOTP($email, $otp, $name)) {
                    $_SESSION['verify_email'] = $email;
                    header("Location: verify-otp.php");
                    exit();
                } else {
                    $error = "Gagal mengirim email verifikasi. Mohon coba lagi.";
                }

            } catch (PDOException $e) {
                $error = "Terjadi kesalahan sistem.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register – Grav Center</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=11.0">
    <link rel="icon" type="image/png" href="assets/uploads/logo-square.png">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html, body {
            height: 100%;
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: #faf9f6;
        }

        /* ════ WRAPPER ════ */
        .page-wrap {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }

        /* ════ CARD ════ */
        .page {
            display: grid;
            grid-template-columns: 1fr 520px;
            width: 100%;
            max-width: 1160px;
            min-height: 680px;
            background: #ffffff;
            border-radius: 24px;
            overflow: hidden;
            box-shadow:
                0 0 0 1px rgba(0,0,0,0.06),
                0 24px 80px rgba(0,0,0,0.08),
                0 4px 16px rgba(0,0,0,0.04);
        }

        /* ════ LEFT PANEL ════ */
        .panel-left {
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 2.5rem 3rem;
            overflow: hidden;
            background: #faf9f6;
        }

        .panel-left::before {
            content: '';
            position: absolute;
            width: 600px; height: 600px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(234,179,8,0.13) 0%, transparent 65%);
            top: -200px; right: -150px;
            pointer-events: none;
        }
        .panel-left::after {
            content: '';
            position: absolute;
            width: 350px; height: 350px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(234,179,8,0.07) 0%, transparent 65%);
            bottom: -80px; left: -80px;
            pointer-events: none;
        }

        .dot-grid {
            position: absolute; inset: 0;
            background-image: radial-gradient(circle, rgba(0,0,0,0.07) 1px, transparent 1px);
            background-size: 28px 28px;
            mask-image: radial-gradient(ellipse 70% 70% at 40% 50%, black 20%, transparent 100%);
            pointer-events: none;
        }

        /* Brand tag */
        .brand-tag {
            position: relative; z-index: 2;
            display: inline-flex; align-items: center; gap: 8px;
            background: rgba(234,179,8,0.1);
            border: 1px solid rgba(234,179,8,0.28);
            border-radius: 100px;
            padding: 5px 14px 5px 8px;
            width: fit-content;
        }
        .live-dot {
            width: 8px; height: 8px; border-radius: 50%;
            background: #eab308;
            box-shadow: 0 0 0 3px rgba(234,179,8,0.2);
            animation: pulse 2.2s ease-in-out infinite;
        }
        @keyframes pulse {
            0%,100% { box-shadow: 0 0 0 3px rgba(234,179,8,0.2); }
            50%      { box-shadow: 0 0 0 7px rgba(234,179,8,0.05); }
        }
        .brand-tag span {
            font-size: 0.68rem; font-weight: 700;
            letter-spacing: 1.6px; color: #b45309; text-transform: uppercase;
        }

        /* Hero */
        .hero {
            position: relative; z-index: 2;
            display: flex; flex-direction: column; justify-content: center;
        }
        .hero-eyebrow {
            font-size: 0.75rem; font-weight: 700; color: #eab308;
            letter-spacing: 0.8px; text-transform: uppercase; margin-bottom: 0.7rem;
        }
        .hero h1 {
            font-size: clamp(1.8rem, 2.2vw, 2.6rem);
            font-weight: 800; line-height: 1.1;
            letter-spacing: -0.04em; color: #0f172a; margin-bottom: 1rem;
        }
        .hero h1 em { font-style: normal; color: #eab308; }
        .hero-desc {
            font-size: 0.88rem; color: #64748b;
            line-height: 1.7; max-width: 360px;
            font-weight: 400; margin-bottom: 1.8rem;
        }

        /* Step indicators */
        .steps {
            display: flex; flex-direction: column; gap: 12px;
            position: relative; z-index: 2;
        }
        .step {
            display: flex; align-items: flex-start; gap: 14px;
            background: #fff;
            border: 1px solid rgba(0,0,0,0.06);
            border-radius: 14px;
            padding: 14px 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }
        .step-num {
            width: 28px; height: 28px; border-radius: 8px;
            background: linear-gradient(135deg, #eab308, #fcd34d);
            color: #7c2d12;
            font-size: 0.72rem; font-weight: 800;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        }
        .step-text h6 { margin: 0 0 2px; font-size: 0.83rem; font-weight: 700; color: #111827; }
        .step-text p  { margin: 0; font-size: 0.73rem; color: #6b7280; font-weight: 400; }

        .left-footer {
            position: relative; z-index: 2;
            font-size: 0.7rem; color: #cbd5e1;
        }

        /* ════ RIGHT PANEL ════ */
        .panel-right {
            display: flex; align-items: center; justify-content: center;
            padding: 2rem 2.5rem;
            background: #ffffff;
            border-left: 1px solid rgba(0,0,0,0.06);
            position: relative;
            overflow-y: auto;
        }

        .register-box {
            width: 100%;
            max-width: 440px;
            padding: 0.5rem 0;
        }

        /* Logo */
        .logo-block { text-align: center; margin-bottom: 1.5rem; }
        .logo-block img { max-width: 170px; height: auto; display: block; margin: 0 auto 8px; }
        .logo-block p  { font-size: 0.8rem; color: #94a3b8; }

        /* Welcome */
        .welcome-line { text-align: center; margin-bottom: 1.4rem; }
        .welcome-line h2 {
            font-size: 1.3rem; font-weight: 800;
            color: #0f172a; letter-spacing: -0.02em; margin-bottom: 3px;
        }
        .welcome-line p { font-size: 0.8rem; color: #94a3b8; }

        /* Fields */
        .field-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 0; }
        .field { margin-bottom: 1rem; }
        .field label {
            display: block; font-size: 0.67rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.6px;
            color: #64748b; margin-bottom: 5px;
        }
        .field-note { font-size: 0.62rem; color: #ef4444; font-weight: 600; }
        .field-wrap { position: relative; }
        .field input, .field select {
            width: 100%; border: 1.5px solid #e2e8f0; border-radius: 10px;
            padding: 10px 14px; font-size: 0.85rem;
            font-family: 'Plus Jakarta Sans', sans-serif;
            color: #0f172a; background: #f8fafc;
            outline: none; transition: border-color 0.18s, box-shadow 0.18s, background 0.18s;
            -webkit-appearance: none;
        }
        .field input::placeholder, .field select::placeholder { color: #cbd5e1; }
        .field input:focus, .field select:focus {
            border-color: #eab308; background: #fff;
            box-shadow: 0 0 0 4px rgba(234,179,8,0.1);
        }
        .field input.has-btn { padding-right: 42px; }
        .eye-toggle {
            position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
            background: none; border: none; color: #cbd5e1; cursor: pointer;
            font-size: 1rem; display: flex; align-items: center; padding: 4px;
            transition: color 0.18s;
        }
        .eye-toggle:hover { color: #64748b; }

        /* Submit */
        .btn-submit {
            width: 100%;
            background: linear-gradient(135deg, #f59e0b, #eab308);
            color: #fff; font-family: 'Plus Jakarta Sans', sans-serif;
            font-weight: 800; font-size: 0.9rem; letter-spacing: 0.2px;
            border: none; border-radius: 12px; padding: 12.5px;
            cursor: pointer;
            box-shadow: 0 4px 18px rgba(234,179,8,0.35), inset 0 1px 0 rgba(255,255,255,0.15);
            transition: all 0.18s ease;
            display: flex; align-items: center; justify-content: center; gap: 8px;
            margin-top: 4px;
        }
        .btn-submit:hover { transform: translateY(-1px); box-shadow: 0 7px 24px rgba(234,179,8,0.45); }
        .btn-submit:active { transform: none; }
        .btn-submit .arrow-icon {
            width: 24px; height: 24px;
            background: rgba(255,255,255,0.2); border-radius: 6px;
            display: flex; align-items: center; justify-content: center; font-size: 0.95rem;
        }

        /* Login link */
        .login-link {
            text-align: center; margin-top: 1rem;
            font-size: 0.78rem; color: #94a3b8;
        }
        .login-link a { color: #0f172a; font-weight: 700; text-decoration: none; }
        .login-link a:hover { color: #eab308; }

        /* Alert */
        .auth-alert {
            display: flex; align-items: center; gap: 9px;
            border-radius: 12px; padding: 10px 14px;
            font-size: 0.8rem; margin-bottom: 12px; font-weight: 500;
        }
        .auth-alert.danger  { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }

        /* ════ RESPONSIVE ════ */
        @media (max-width: 960px) {
            .page-wrap { padding: 0; align-items: stretch; }
            .page { grid-template-columns: 1fr; border-radius: 0; min-height: 100vh; box-shadow: none; }
            .panel-left { display: none; }
            .panel-right { padding: 2rem; }
        }
    </style>
</head>
<body>

<div class="page-wrap">
<div class="page">

    <!-- ═══ LEFT PANEL ═══ -->
    <div class="panel-left">
        <div class="dot-grid"></div>

        <!-- Brand -->
        <div class="brand-tag">
            <div class="live-dot"></div>
            <span>Gravitti Core &nbsp;·&nbsp; Bergabung Tim</span>
        </div>

        <!-- Hero -->
        <div class="hero">
            <div class="hero-eyebrow">Daftar Akun Baru</div>
            <h1>Mulai <em>Perjalanan</em><br>Bersama Tim<br>GraViTTi.</h1>
            <p class="hero-desc">
                Daftarkan dirimu sebagai anggota tim dan dapatkan akses penuh ke dashboard kolaborasi GraViTTi.
            </p>

            <!-- Steps -->
            <div class="steps">
                <div class="step">
                    <div class="step-num">1</div>
                    <div class="step-text">
                        <h6>Isi Data Diri</h6>
                        <p>Lengkapi nama, email, divisi, dan jabatan kamu</p>
                    </div>
                </div>
                <div class="step">
                    <div class="step-num">2</div>
                    <div class="step-text">
                        <h6>Verifikasi Email</h6>
                        <p>Kode OTP akan dikirim ke emailmu untuk konfirmasi</p>
                    </div>
                </div>
                <div class="step">
                    <div class="step-num">3</div>
                    <div class="step-text">
                        <h6>Akses Dashboard</h6>
                        <p>Langsung masuk dan mulai bekerja bersama tim</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="left-footer">
            &copy; <?= date('Y') ?> GraViTTi Technology. All rights reserved.
        </div>
    </div>

    <!-- ═══ RIGHT PANEL ═══ -->
    <div class="panel-right">
        <div class="register-box">

            <!-- Logo -->
            <div class="logo-block">
                <img src="assets/uploads/logo-gravitti.png" alt="GraViTTi Technology">
                <p>Buat akun baru untuk bergabung 🚀</p>
            </div>

            <!-- Title -->
            <div class="welcome-line">
                <h2>Create Account</h2>
                <p>Lengkapi form di bawah untuk mendaftar</p>
            </div>

            <?php if($error): ?>
                <div class="auth-alert danger">
                    <i class="bi bi-exclamation-circle-fill"></i> <?= $error ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">

                <!-- Row 1: Nama + WhatsApp -->
                <div class="field-row">
                    <div class="field">
                        <label for="name">Nama Lengkap</label>
                        <input type="text" id="name" name="name" placeholder="Nama Lengkap Anda" required autocomplete="name">
                    </div>
                    <div class="field">
                        <label for="telp">No. WhatsApp</label>
                        <input type="text" id="telp" name="telp" placeholder="08123456789" required>
                    </div>
                </div>

                <!-- Email -->
                <div class="field">
                    <label for="email">
                        Email Address &nbsp;
                        <span class="field-note">*Harus sama dengan Web Penggajian (SSLL)</span>
                    </label>
                    <input type="email" id="email" name="email" placeholder="nama@example.com" required autocomplete="email">
                </div>

                <!-- Row 2: Divisi + Jabatan -->
                <div class="field-row">
                    <div class="field">
                        <label for="division">Divisi</label>
                        <select id="division" name="division" required>
                            <option value="" selected disabled>Pilih Divisi</option>
                            <option value="Marketing">Marketing</option>
                            <option value="Sales">Sales</option>
                            <option value="Teknisi">Teknisi</option>
                            <option value="Produksi">Produksi</option>
                            <option value="Finance">Finance</option>
                            <option value="Leader">Leader</option>
                        </select>
                    </div>
                    <div class="field">
                        <label for="jabatan">Jabatan</label>
                        <input type="text" id="jabatan" name="jabatan" placeholder="Contoh: Staff" required>
                    </div>
                </div>

                <!-- Row 3: Password + Confirm -->
                <div class="field-row">
                    <div class="field">
                        <label for="password">Password</label>
                        <div class="field-wrap">
                            <input type="password" class="has-btn" id="password" name="password" placeholder="Password" required>
                            <button type="button" id="togglePassword" class="eye-toggle" aria-label="Toggle Password">
                                <i class="bi bi-eye" id="toggleIcon"></i>
                            </button>
                        </div>
                    </div>
                    <div class="field">
                        <label for="confirm_password">Ulangi Password</label>
                        <div class="field-wrap">
                            <input type="password" class="has-btn" id="confirm_password" name="confirm_password" placeholder="Ulangi Password" required>
                            <button type="button" id="toggleConfirmPassword" class="eye-toggle" aria-label="Toggle Confirm Password">
                                <i class="bi bi-eye" id="toggleConfirmIcon"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn-submit">
                    Daftar & Kirim OTP
                    <span class="arrow-icon"><i class="bi bi-send-fill"></i></span>
                </button>

            </form>

            <div class="login-link">
                Sudah punya akun? <a href="login.php">Login disini</a>
            </div>

        </div>
    </div>

</div><!-- /.page -->
</div><!-- /.page-wrap -->

<script>
    document.getElementById('togglePassword').addEventListener('click', function () {
        const input = document.getElementById('password');
        const icon  = document.getElementById('toggleIcon');
        input.type === 'password'
            ? (input.type = 'text', icon.classList.replace('bi-eye', 'bi-eye-slash'))
            : (input.type = 'password', icon.classList.replace('bi-eye-slash', 'bi-eye'));
    });

    document.getElementById('toggleConfirmPassword').addEventListener('click', function () {
        const input = document.getElementById('confirm_password');
        const icon  = document.getElementById('toggleConfirmIcon');
        input.type === 'password'
            ? (input.type = 'text', icon.classList.replace('bi-eye', 'bi-eye-slash'))
            : (input.type = 'password', icon.classList.replace('bi-eye-slash', 'bi-eye'));
    });
</script>

</body>
</html>