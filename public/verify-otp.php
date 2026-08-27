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

if (!isset($_SESSION['verify_email'])) {
    header("Location: login.php");
    exit();
}

$email = $_SESSION['verify_email'];
$error = '';
$success = '';

// ── Handle Resend OTP ──
if (isset($_GET['resend']) && $_GET['resend'] == '1') {
    $last_sent = $_SESSION['otp_last_sent'] ?? 0;
    $time_passed = time() - $last_sent;
    
    if ($time_passed < 60) {
        $remaining = 60 - $time_passed;
        $error = "Mohon tunggu {$remaining} detik sebelum meminta kirim ulang kode OTP.";
    } else {
        $stmt = $conn->prepare("SELECT name FROM users WHERE email = :email");
        $stmt->execute([':email' => $email]);
        $user_row = $stmt->fetch();

        if ($user_row) {
            $new_otp = rand(100000, 999999);
            $new_expires = date("Y-m-d H:i:s", strtotime("+15 minutes"));

            $upd = $conn->prepare("UPDATE users SET verification_code = :otp, otp_expires_at = :exp WHERE email = :email");
            $upd->execute([':otp' => $new_otp, ':exp' => $new_expires, ':email' => $email]);

            if (sendOTP($email, $new_otp, $user_row['name'])) {
                $_SESSION['otp_last_sent'] = time();
                $success = "Kode OTP baru berhasil dikirim ke <b>" . htmlspecialchars($email) . "</b>. Silahkan cek kotak masuk atau folder spam email Anda.";
            } else {
                $error = "Gagal mengirim email OTP. Silahkan coba lagi.";
            }
        } else {
            $error = "Akun tidak ditemukan. Silahkan daftar kembali.";
        }
    }
}

// ── Handle Verification Submit ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $digit1 = trim($_POST['digit1'] ?? '');
    $digit2 = trim($_POST['digit2'] ?? '');
    $digit3 = trim($_POST['digit3'] ?? '');
    $digit4 = trim($_POST['digit4'] ?? '');
    $digit5 = trim($_POST['digit5'] ?? '');
    $digit6 = trim($_POST['digit6'] ?? '');

    $otp_input = $digit1 . $digit2 . $digit3 . $digit4 . $digit5 . $digit6;

    if (strlen($otp_input) !== 6) {
        $error = "Harap masukkan 6 digit kode OTP secara lengkap.";
    } else {
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = :email AND verification_code = :otp");
        $stmt->execute([':email' => $email, ':otp' => $otp_input]);
        $user = $stmt->fetch();

        if ($user) {
            $now = date("Y-m-d H:i:s");
            if ($now <= $user['otp_expires_at']) {
                $update = $conn->prepare("UPDATE users SET email_verified_at = :now, verification_code = NULL, otp_expires_at = NULL WHERE id = :id");
                $update->execute([':now' => $now, ':id' => $user['id']]);

                unset($_SESSION['verify_email']);
                unset($_SESSION['otp_last_sent']);
                $_SESSION['success_msg'] = "🎉 Verifikasi berhasil! Akun Anda telah aktif, silahkan login.";
                header("Location: login.php");
                exit();
            } else {
                $error = "Kode OTP sudah kadaluarsa (melewati batas 15 menit). Silahkan klik 'Kirim Ulang Kode'.";
            }
        } else {
            $error = "Kode OTP yang Anda masukkan salah. Silahkan periksa kembali.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifikasi OTP – Grav Center</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="assets/uploads/logo-square.png">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #faf9f6;
            background-image: 
                radial-gradient(at 10% 10%, rgba(245, 158, 11, 0.15) 0px, transparent 40%),
                radial-gradient(at 90% 90%, rgba(14, 165, 233, 0.12) 0px, transparent 40%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }

        .otp-card {
            background: #ffffff;
            border-radius: 28px;
            padding: 3rem 2.5rem;
            max-width: 480px;
            width: 100%;
            border: 1px solid rgba(0, 0, 0, 0.06);
            box-shadow: 0 20px 60px -10px rgba(15, 23, 42, 0.08), 0 4px 16px rgba(0,0,0,0.03);
            text-align: center;
            animation: scaleIn 0.45s cubic-bezier(0.16, 1, 0.3, 1) both;
        }

        @keyframes scaleIn {
            from { opacity: 0; transform: scale(0.94) translateY(12px); }
            to { opacity: 1; transform: scale(1) translateY(0); }
        }

        .otp-icon-wrap {
            width: 72px;
            height: 72px;
            border-radius: 22px;
            background: linear-gradient(135deg, #fef3c7, #fde68a);
            color: #d97706;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 10px 24px -4px rgba(245, 158, 11, 0.3);
        }

        .otp-input-group {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin: 2rem 0 1.5rem;
        }

        .otp-field {
            width: 52px;
            height: 60px;
            text-align: center;
            font-size: 24px;
            font-weight: 800;
            border-radius: 14px;
            border: 2px solid #e2e8f0;
            background: #f8fafc;
            color: #0f172a;
            transition: all 0.2s ease;
            outline: none;
        }

        .otp-field:focus {
            border-color: #f59e0b;
            background: #ffffff;
            box-shadow: 0 0 0 4px rgba(245, 158, 11, 0.15);
            transform: translateY(-2px);
        }

        .btn-verify {
            width: 100%;
            background: linear-gradient(135deg, #f59e0b, #eab308);
            color: #ffffff;
            font-weight: 800;
            font-size: 0.95rem;
            border: none;
            border-radius: 14px;
            padding: 14px;
            cursor: pointer;
            box-shadow: 0 6px 20px rgba(245, 158, 11, 0.35);
            transition: all 0.2s ease;
        }

        .btn-verify:hover {
            transform: translateY(-1px);
            box-shadow: 0 8px 26px rgba(245, 158, 11, 0.45);
        }

        .btn-resend {
            color: #d97706;
            font-weight: 700;
            text-decoration: none;
            transition: color 0.18s;
        }
        .btn-resend:hover {
            color: #b45309;
            text-decoration: underline;
        }

        .badge-email {
            background: #f1f5f9;
            color: #334155;
            padding: 4px 10px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 0.85rem;
            display: inline-block;
            word-break: break-all;
        }
    </style>
</head>
<body>

    <div class="otp-card">
        
        <div class="otp-icon-wrap">
            <i class="bi bi-shield-lock-fill"></i>
        </div>

        <h3 class="fw-bold mb-2" style="color: #0f172a; letter-spacing: -0.02em;">Verifikasi Email</h3>
        <p class="text-muted small mb-3">
            Masukkan 6 digit kode OTP yang telah dikirimkan ke:
            <br>
            <span class="badge-email mt-1"><?= htmlspecialchars($email) ?></span>
        </p>

        <?php if($error): ?>
            <div class="alert alert-danger py-2 px-3 small border-0 bg-danger-subtle text-danger mb-3 rounded-3 text-start">
                <i class="bi bi-exclamation-circle-fill me-1"></i> <?= $error ?>
            </div>
        <?php endif; ?>

        <?php if($success): ?>
            <div class="alert alert-success py-2 px-3 small border-0 bg-success-subtle text-success mb-3 rounded-3 text-start">
                <i class="bi bi-check-circle-fill me-1"></i> <?= $success ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="" id="otpForm">
            <div class="otp-input-group">
                <input type="text" class="otp-field" name="digit1" id="d1" maxlength="1" inputmode="numeric" required autofocus>
                <input type="text" class="otp-field" name="digit2" id="d2" maxlength="1" inputmode="numeric" required>
                <input type="text" class="otp-field" name="digit3" id="d3" maxlength="1" inputmode="numeric" required>
                <input type="text" class="otp-field" name="digit4" id="d4" maxlength="1" inputmode="numeric" required>
                <input type="text" class="otp-field" name="digit5" id="d5" maxlength="1" inputmode="numeric" required>
                <input type="text" class="otp-field" name="digit6" id="d6" maxlength="1" inputmode="numeric" required>
            </div>

            <button class="btn btn-verify mb-3" type="submit">
                Verifikasi Akun <i class="bi bi-arrow-right-short ms-1" style="font-size: 1.2rem; vertical-align: middle;"></i>
            </button>
        </form>

        <p class="small text-muted mb-2">
            Tidak menerima kode? 
            <a href="?resend=1" class="btn-resend" id="resendLink">Kirim Ulang Kode</a>
        </p>

        <hr style="border-color: rgba(0,0,0,0.06); margin: 1.5rem 0 1rem;">

        <div class="d-flex justify-content-between align-items-center">
            <a href="register.php" class="text-secondary small text-decoration-none">
                <i class="bi bi-arrow-left"></i> Ganti Email
            </a>
            <a href="login.php" class="text-secondary small text-decoration-none">
                Sudah Verifikasi? <b>Login</b>
            </a>
        </div>

    </div>

<script>
    const inputs = document.querySelectorAll('.otp-field');

    // Auto-focus move & Backspace navigation
    inputs.forEach((input, index) => {
        input.addEventListener('input', (e) => {
            const val = e.target.value;
            // Clean non-digit
            e.target.value = val.replace(/[^0-9]/g, '');

            if (e.target.value.length === 1 && index < inputs.length - 1) {
                inputs[index + 1].focus();
            }
        });

        input.addEventListener('keydown', (e) => {
            if (e.key === 'Backspace' && e.target.value === '' && index > 0) {
                inputs[index - 1].focus();
            }
        });

        // Auto paste 6-digit support
        input.addEventListener('paste', (e) => {
            e.preventDefault();
            const pasteData = (e.clipboardData || window.clipboardData).getData('text').trim();
            if (/^\d{6}$/.test(pasteData)) {
                inputs.forEach((inp, idx) => {
                    inp.value = pasteData[idx] || '';
                });
                inputs[5].focus();
                document.getElementById('otpForm').submit();
            }
        });
    });
</script>
</body>
</html>