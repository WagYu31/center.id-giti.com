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

if (!isset($_SESSION['verify_email'])) {
    header("Location: login.php");
    exit();
}

$email = $_SESSION['verify_email'];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $otp_input = $_POST['digit1'] . $_POST['digit2'] . $_POST['digit3'] . $_POST['digit4'] . $_POST['digit5'] . $_POST['digit6'];

    $stmt = $conn->prepare("SELECT * FROM users WHERE email = :email AND verification_code = :otp");
    $stmt->execute([':email' => $email, ':otp' => $otp_input]);
    $user = $stmt->fetch();

    if ($user) {
        $now = date("Y-m-d H:i:s");
        if ($now <= $user['otp_expires_at']) {
            $update = $conn->prepare("UPDATE users SET email_verified_at = :now, verification_code = NULL, otp_expires_at = NULL WHERE id = :id");
            $update->execute([':now' => $now, ':id' => $user['id']]);

            unset($_SESSION['verify_email']);
            $_SESSION['success_msg'] = "Verifikasi berhasil! Silahkan login.";
            header("Location: login.php");
            exit();
        } else {
            $error = "Kode OTP sudah kadaluarsa.";
        }
    } else {
        $error = "Kode OTP salah.";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifikasi OTP - Grav Center</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=11.0">
    <style>
        .otp-input-group { display: flex; gap: 10px; justify-content: center; margin: 20px 0; }
        .otp-field { width: 50px; height: 55px; text-align: center; font-size: 24px; font-weight: bold; border-radius: 12px; border: 1.5px solid rgba(0,0,0,0.08); background: rgba(255,255,255,0.5); transition: all 0.3s ease; }
        .otp-field:focus { border-color: var(--gv-primary); box-shadow: 0 0 0 4px rgba(234, 179, 8, 0.12); outline: none; background: white; }
    </style>
</head>
<body>

    <div class="auth-container">
        <div class="auth-card text-center">
            
            <div class="auth-header text-center">
                <img src="assets/uploads/logo-gravitti.png" alt="GraViTTi Technology" style="max-width: 240px; height: auto; margin: 0 auto 1.5rem auto; display: block;">
                <h4 class="fw-bold mb-1" style="font-size: 1.15rem; color: var(--text-primary);">Verifikasi Email</h4>
                <p class="text-secondary small">Kode 6 digit telah dikirim ke <b><?= htmlspecialchars($email) ?></b></p>
            </div>

            <?php if($error): ?>
                <div class="alert alert-danger py-2 small border-0 bg-danger-subtle text-danger mb-3">
                    <?= $error ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="" id="otpForm">
                <div class="otp-input-group">
                    <input type="text" class="otp-field" name="digit1" maxlength="1" required autofocus>
                    <input type="text" class="otp-field" name="digit2" maxlength="1" required>
                    <input type="text" class="otp-field" name="digit3" maxlength="1" required>
                    <input type="text" class="otp-field" name="digit4" maxlength="1" required>
                    <input type="text" class="otp-field" name="digit5" maxlength="1" required>
                    <input type="text" class="otp-field" name="digit6" maxlength="1" required>
                </div>

                <button class="btn btn-login-dark mb-3" type="submit">Verifikasi</button>
            </form>

            <p class="small text-secondary">
                Tidak menerima kode? <a href="register.php" class="text-dark fw-bold text-decoration-none">Kirim Ulang</a>
            </p>
        </div>
    </div>

<script>
    const inputs = document.querySelectorAll('.otp-field');
    inputs.forEach((input, index) => {
        input.addEventListener('input', (e) => {
            if (e.target.value.length === 1) {
                if (index < inputs.length - 1) inputs[index + 1].focus();
            }
        });
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Backspace' && e.target.value === '') {
                if (index > 0) inputs[index - 1].focus();
            }
        });
    });
</script>
</body>
</html>