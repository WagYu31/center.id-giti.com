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
</head>
<body>

    <div class="auth-container">
        <div class="auth-card" style="max-width: 600px;">
            
            <div class="auth-header text-center">
                <img src="assets/uploads/logo-gravitti.png" alt="GraViTTi Technology" style="max-width: 240px; height: auto; margin: 0 auto 1.5rem auto; display: block;">
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
                    <div class="col-md-6">
                        <div class="form-floating">
                            <input type="text" class="form-control" id="name" name="name" placeholder="Nama Lengkap" required>
                            <label for="name" class="text-secondary">Nama Lengkap</label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-floating">
                            <input type="text" class="form-control" id="telp" name="telp" placeholder="No. WhatsApp" required>
                            <label for="telp" class="text-secondary">No. WhatsApp</label>
                        </div>
                    </div>
                    
                    <div class="col-12">
                        <label style="font-size:10px; color:red;" class="mt-0">*Email harus sama dengan yang terdaftar di Web Penggajian (SSLL) yaaaa...</label>
                        <div class="form-floating">
                            <input type="email" class="form-control" id="email" name="email" placeholder="name@example.com" required>
                            <label for="email" class="text-secondary">Email Address</label>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-floating">
                            <select class="form-select" id="division" name="division" required>
                                <option value="" selected disabled>Pilih Divisi</option>
                                <option value="Marketing">Marketing</option>
                                <option value="Sales">Sales</option>
                                <option value="Teknisi">Teknisi</option>
                                <option value="Produksi">Produksi</option>
                                <option value="Finance">Finance</option>
                                <option value="Leader">Leader</option>
                            </select>
                            <label for="division">Divisi</label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-floating">
                            <input type="text" class="form-control" id="jabatan" name="jabatan" placeholder="Jabatan" required>
                            <label for="jabatan" class="text-secondary">Jabatan</label>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="password-toggle-container">
                            <div class="form-floating">
                                <input type="password" class="form-control" id="password" name="password" placeholder="Password" required>
                                <label for="password" class="text-secondary">Password</label>
                            </div>
                            <button type="button" class="password-toggle-btn" id="togglePassword" aria-label="Toggle Password Visibility">
                                <i class="bi bi-eye" id="toggleIcon"></i>
                            </button>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="password-toggle-container">
                            <div class="form-floating">
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="Confirm Password" required>
                                <label for="confirm_password" class="text-secondary">Ulangi Password</label>
                            </div>
                            <button type="button" class="password-toggle-btn" id="toggleConfirmPassword" aria-label="Toggle Confirm Password Visibility">
                                <i class="bi bi-eye" id="toggleConfirmIcon"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <button class="btn btn-login-dark mt-4 mb-3" type="submit">
                    Daftar & Kirim OTP
                </button>
            </form>

            <div class="text-center mt-3">
                <p class="small text-secondary mb-0">
                    Sudah punya akun? <a href="login.php" class="text-dark fw-bold text-decoration-none">Login disini</a>
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