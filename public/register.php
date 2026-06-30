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
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $division = $_POST['division'];
    $jabatan = $_POST['jabatan'];
    $telp = $_POST['telp'];

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
                    ':div' => $division,
                    ':jab' => $jabatan,
                    ':telp' => $telp,
                    ':otp' => $otp,
                    ':exp' => $otp_expires
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
    <title>Register - Grav Center</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=11.0">
    <link rel="icon" type="image/png" href="assets/uploads/logo-square.png">
    <style>
        /* Split Layout Styles */
        .auth-split-wrapper {
            display: flex;
            min-height: 100vh;
            width: 100%;
        }
        .auth-split-left {
            flex: 1.1;
            background: linear-gradient(135deg, #111827, #030712);
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 4rem;
            overflow: hidden;
            color: white;
        }
        .auth-split-left::before {
            content: '';
            position: absolute;
            inset: 0;
            background: 
                radial-gradient(circle at 80% 20%, rgba(234, 179, 8, 0.12), transparent 45%),
                radial-gradient(circle at 20% 80%, rgba(250, 204, 21, 0.08), transparent 45%);
            z-index: 1;
        }
        .auth-split-right {
            flex: 0.9;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: var(--bg-body);
            background-image: 
                radial-gradient(circle at 50% 50%, rgba(255, 255, 255, 0.6) 0%, transparent 100%);
            padding: 2rem;
            position: relative;
        }
        @media (max-width: 991px) {
            .auth-split-left {
                display: none;
            }
            .auth-split-right {
                flex: 1;
                background-color: var(--bg-body);
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>

    <div class="auth-split-wrapper">
        <!-- Left Side: Brand Visuals -->
        <div class="auth-split-left">
            <div style="z-index: 2; display: flex; align-items: center; gap: 8px;">
                <div style="width: 10px; height: 10px; border-radius: 50%; background: var(--gv-primary); box-shadow: 0 0 10px var(--gv-primary);"></div>
                <span class="fw-bold" style="letter-spacing: 1.5px; font-size: 0.78rem; color: var(--gv-primary); text-transform: uppercase;">GRAVITTI CORE</span>
            </div>
            
            <div style="z-index: 2; max-width: 480px; margin-bottom: 4rem; margin-top: auto;">
                <h1 class="fw-bold mb-3" style="font-size: 2.8rem; line-height: 1.15; letter-spacing: -0.03em;">Platform Kolaborasi Internal Tim</h1>
                <p style="font-size: 1.05rem; color: #9ca3af; line-height: 1.6; font-weight: 300;">Pantau progress harian, koordinasi divisi, target penjualan, absensi, dan kelola pekerjaan dalam satu dashboard terintegrasi.</p>
            </div>
            
            <div style="z-index: 2; font-size: 0.75rem; color: #4b5563; letter-spacing: 0.3px;">
                &copy; <?= date('Y') ?> GraViTTi Technology. All rights reserved.
            </div>
        </div>

        <!-- Right Side: Form Card -->
        <div class="auth-split-right">
            <div class="auth-card" style="max-width: 600px;">
                
                <div class="auth-header text-center">
                    <img src="assets/uploads/logo-gravitti.png" alt="GraViTTi Technology" style="max-width: 220px; height: auto; margin: 0 auto 1.2rem auto; display: block;">
                    <h4 class="fw-bold mb-1" style="font-size: 1.15rem; color: var(--text-primary);">Create Account</h4>
                    <p class="text-secondary small">Bergabung dengan Grav Tech Team</p>
                </div>

                <?php if($error): ?>
                    <div class="alert alert-danger py-2 small text-center border-0 bg-danger-subtle text-danger mb-4">
                        <i class="bi bi-exclamation-circle me-1"></i> <?= $error ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                <div class="row g-3">
                    <div class="col-md-6 text-start">
                        <label for="name" class="form-label fw-bold text-secondary mb-1" style="font-size: 0.78rem; letter-spacing: 0.3px; text-transform: uppercase;">Nama Lengkap</label>
                        <input type="text" class="form-control" id="name" name="name" placeholder="Nama Lengkap Anda" required style="border-radius: 12px; border: 1.5px solid rgba(0,0,0,0.08); padding: 11px 16px; font-size: 0.88rem; background-color: rgba(255,255,255,0.6); transition: all 0.2s;">
                    </div>
                    <div class="col-md-6 text-start">
                        <label for="telp" class="form-label fw-bold text-secondary mb-1" style="font-size: 0.78rem; letter-spacing: 0.3px; text-transform: uppercase;">No. WhatsApp</label>
                        <input type="text" class="form-control" id="telp" name="telp" placeholder="Contoh: 08123456789" required style="border-radius: 12px; border: 1.5px solid rgba(0,0,0,0.08); padding: 11px 16px; font-size: 0.88rem; background-color: rgba(255,255,255,0.6); transition: all 0.2s;">
                    </div>
                    
                    <div class="col-12 text-start">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label for="email" class="form-label fw-bold text-secondary mb-0" style="font-size: 0.78rem; letter-spacing: 0.3px; text-transform: uppercase;">Email Address</label>
                            <span style="font-size:10px; color:red; font-weight: 500;">*Harus sama dengan Web Penggajian (SSLL)</span>
                        </div>
                        <input type="email" class="form-control" id="email" name="email" placeholder="name@example.com" required style="border-radius: 12px; border: 1.5px solid rgba(0,0,0,0.08); padding: 11px 16px; font-size: 0.88rem; background-color: rgba(255,255,255,0.6); transition: all 0.2s;">
                    </div>

                    <div class="col-md-6 text-start">
                        <label for="division" class="form-label fw-bold text-secondary mb-1" style="font-size: 0.78rem; letter-spacing: 0.3px; text-transform: uppercase;">Divisi</label>
                        <select class="form-select" id="division" name="division" required style="border-radius: 12px; border: 1.5px solid rgba(0,0,0,0.08); padding: 11px 16px; font-size: 0.88rem; background-color: rgba(255,255,255,0.6); transition: all 0.2s; cursor: pointer;">
                            <option value="" selected disabled>Pilih Divisi</option>
                            <option value="Marketing">Marketing</option>
                            <option value="Sales">Sales</option>
                            <option value="Teknisi">Teknisi</option>
                            <option value="Produksi">Produksi</option>
                            <option value="Finance">Finance</option>
                            <option value="Leader">Leader</option>
                        </select>
                    </div>
                    <div class="col-md-6 text-start">
                        <label for="jabatan" class="form-label fw-bold text-secondary mb-1" style="font-size: 0.78rem; letter-spacing: 0.3px; text-transform: uppercase;">Jabatan</label>
                        <input type="text" class="form-control" id="jabatan" name="jabatan" placeholder="Contoh: Staff" required style="border-radius: 12px; border: 1.5px solid rgba(0,0,0,0.08); padding: 11px 16px; font-size: 0.88rem; background-color: rgba(255,255,255,0.6); transition: all 0.2s;">
                    </div>

                    <div class="col-md-6 text-start">
                        <label for="password" class="form-label fw-bold text-secondary mb-1" style="font-size: 0.78rem; letter-spacing: 0.3px; text-transform: uppercase;">Password</label>
                        <div style="position: relative;">
                            <input type="password" class="form-control" id="password" name="password" placeholder="Password" required style="border-radius: 12px; border: 1.5px solid rgba(0,0,0,0.08); padding: 11px 16px; padding-right: 46px; font-size: 0.88rem; background-color: rgba(255,255,255,0.6); transition: all 0.2s; width: 100%;">
                            <button type="button" id="togglePassword" aria-label="Toggle Password Visibility" style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); background: none; border: none; color: #9ca3af; cursor: pointer; font-size: 1.2rem; display: flex; align-items: center; justify-content: center; padding: 4px; z-index: 5;">
                                <i class="bi bi-eye" id="toggleIcon"></i>
                            </button>
                        </div>
                    </div>
                    <div class="col-md-6 text-start">
                        <label for="confirm_password" class="form-label fw-bold text-secondary mb-1" style="font-size: 0.78rem; letter-spacing: 0.3px; text-transform: uppercase;">Ulangi Password</label>
                        <div style="position: relative;">
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="Ulangi Password" required style="border-radius: 12px; border: 1.5px solid rgba(0,0,0,0.08); padding: 11px 16px; padding-right: 46px; font-size: 0.88rem; background-color: rgba(255,255,255,0.6); transition: all 0.2s; width: 100%;">
                            <button type="button" id="toggleConfirmPassword" aria-label="Toggle Confirm Password Visibility" style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); background: none; border: none; color: #9ca3af; cursor: pointer; font-size: 1.2rem; display: flex; align-items: center; justify-content: center; padding: 4px; z-index: 5;">
                                <i class="bi bi-eye" id="toggleConfirmIcon"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <button class="btn btn-login-dark mt-4 mb-3" type="submit" style="background: linear-gradient(135deg, var(--gv-primary), var(--gv-primary-light)); color: #1a1a1a; font-weight: 700; border-radius: 12px; padding: 12px; width: 100%; border: none; font-size: 0.95rem; box-shadow: 0 4px 16px rgba(234, 179, 8, 0.15); transition: all 0.2s;">
                    Daftar & Kirim OTP
                </button>
            </form>

            <div class="text-center mt-3">
                <p class="small text-secondary mb-0" style="font-size: 0.82rem;">
                    Sudah punya akun? <a href="login.php" class="text-dark fw-bold text-decoration-none">Login disini</a>
                </p>
            </div>

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

        document.getElementById('toggleConfirmPassword').addEventListener('click', function () {
            const passwordInput = document.getElementById('confirm_password');
            const icon = document.getElementById('toggleConfirmIcon');
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