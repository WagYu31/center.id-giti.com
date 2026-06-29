<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Center Loewix</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { background: #f5f7fa; display: flex; align-items: center; min-height: 100vh; }
        .card-login { border: none; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.08); }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-5">
                <div class="card card-login p-4">
                    <div class="text-center mb-4">
                        <h3 class="fw-bold text-primary">Center Loewix</h3>
                        <p class="text-muted">Masuk untuk mengakses portal karyawan</p>
                    </div>
                    <?php if(isset($_GET['success'])): ?>
                        <div class="alert alert-success py-2">Registrasi berhasil, silakan login.</div>
                    <?php endif; ?>
                    <?php if(isset($error)): ?>
                        <div class="alert alert-danger py-2"><?= $error ?></div>
                    <?php endif; ?>
                    <form action="<?= BASE_URL ?>/auth/login" method="POST">
                        <div class="mb-3">
                            <label class="form-label">Email / Username</label>
                            <input type="text" name="user_input" class="form-control" required autofocus>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" name="remember" id="remember">
                            <label class="form-check-label" for="remember">Ingat Saya</label>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 py-2 fw-bold">Login</button>
                    </form>
                    <div class="mt-3 text-center">
                         <button class="btn btn-outline-danger w-100 btn-sm"><i class="fab fa-google me-2"></i> Login with Google</button>
                    </div>
                    <div class="text-center mt-3">
                        <a href="<?= BASE_URL ?>/auth/register" class="text-decoration-none small">Belum punya akun? Daftar</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>