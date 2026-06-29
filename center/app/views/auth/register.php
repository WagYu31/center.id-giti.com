<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Center Loewix</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f5f7fa; display: flex; align-items: center; min-height: 100vh; }
        .card-login { border: none; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.08); }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card card-login p-4">
                    <div class="text-center mb-4">
                        <h3 class="fw-bold text-primary">Daftar Akun</h3>
                    </div>
                    <?php if(isset($error)): ?>
                        <div class="alert alert-danger py-2"><?= $error ?></div>
                    <?php endif; ?>
                    <form action="<?= BASE_URL ?>/auth/register" method="POST">
                        <div class="mb-3">
                            <label class="form-label">Nama Lengkap</label>
                            <input type="text" name="name" class="form-control" required>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Username</label>
                                <input type="text" name="username" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">No. Telepon</label>
                                <input type="text" name="telp" class="form-control" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 py-2 fw-bold">Daftar</button>
                    </form>
                    <div class="text-center mt-3">
                        <a href="<?= BASE_URL ?>/auth/login" class="text-decoration-none small">Sudah punya akun? Login</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>