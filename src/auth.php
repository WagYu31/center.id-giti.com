<?php
// ============================================================
// Helper: catat setiap percobaan login ke tabel login_logs
// ============================================================
function log_login($conn, $user_id, $user_name, $email, $status, $app = 'Center') {
    // Ambil IP real (support proxy / Nginx / Cloudflare)
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR']
        ?? $_SERVER['HTTP_X_REAL_IP']
        ?? $_SERVER['REMOTE_ADDR']
        ?? '0.0.0.0';
    $ip = trim(explode(',', $ip)[0]); // Ambil IP pertama jika ada beberapa

    $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 300);

    try {
        // Buat tabel jika belum ada
        $conn->exec("CREATE TABLE IF NOT EXISTS login_logs (
            id          INT AUTO_INCREMENT PRIMARY KEY,
            user_id     INT NULL,
            user_name   VARCHAR(255) NULL,
            user_email  VARCHAR(255) NOT NULL,
            ip_address  VARCHAR(45)  NOT NULL,
            user_agent  TEXT NULL,
            app         VARCHAR(50)  DEFAULT 'Center',
            status      ENUM('success','failed') NOT NULL,
            login_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_login_at (login_at),
            INDEX idx_status   (status),
            INDEX idx_user_id  (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $stmt = $conn->prepare("INSERT INTO login_logs 
            (user_id, user_name, user_email, ip_address, user_agent, app, status) 
            VALUES (?,?,?,?,?,?,?)");
        $stmt->execute([$user_id, $user_name, $email, $ip, $ua, $app, $status]);
    } catch (Exception $e) {
        // Jangan putus proses login hanya karena log gagal
    }
}

// ============================================================
// Login utama
// ============================================================
function login_user($email, $password, $remember, $conn) {
    $stmt = $conn->prepare("SELECT * FROM users WHERE email = :email");
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        // Cek Status Verifikasi Email
        if ($user['email_verified_at'] === null) {
            return 'UNVERIFIED';
        }

        // Regenerasi Session ID untuk keamanan
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_role'] = $user['role'];
        
        // Simpan permission lengkap
        $_SESSION['permissions'] = [
            'teknisi'   => $user['app_teknisi'],
            'bukti'     => $user['app_bukti'],
            'service'   => $user['app_service'],
            'quotation' => $user['app_quotation'],
            'produksi'  => $user['app_produksi'],
            'sales'     => $user['app_sales'],
            'giti'      => $user['app_giti'],
            'ssll'      => $user['app_ssll'],
            'warranty'  => $user['app_warranty']
        ];

        if ($remember) {
            // Generate Selector & Validator
            $selector = base64_encode(random_bytes(9));
            $authenticator = random_bytes(33);
            $token_hash = hash('sha256', $authenticator);
            $expiry = date('Y-m-d H:i:s', time() + (86400 * 30)); // 30 Hari
            
            // Simpan ke database user_tokens
            $ins = $conn->prepare("INSERT INTO user_tokens (user_id, selector, hashed_validator, expires_at) VALUES (:uid, :sel, :val, :exp)");
            $ins->execute([
                ':uid' => $user['id'],
                ':sel' => $selector,
                ':val' => $token_hash,
                ':exp' => $expiry
            ]);
            
            // Set Cookie di browser (Selector + Raw Validator)
            $cookieValue = $selector . ':' . base64_encode($authenticator);
            setcookie('remember_user', $cookieValue, time() + (86400 * 30), "/", "", true, true);
        }

        // Catat login sukses
        log_login($conn, $user['id'], $user['name'], $email, 'success');
        return 'SUCCESS';
    }

    // Catat login gagal
    log_login($conn, null, null, $email, 'failed');
    return 'FAILED';
}

function auto_login($conn) {
    // Cek jika session kosong tapi cookie ada
    if (empty($_SESSION['user_id']) && !empty($_COOKIE['remember_user'])) {
        
        $parts = explode(':', $_COOKIE['remember_user']);
        
        if (count($parts) === 2) {
            $selector = $parts[0];
            $authenticator = $parts[1];
            
            // Cari selector di DB yang belum expired
            $stmt = $conn->prepare("SELECT * FROM user_tokens WHERE selector = :sel AND expires_at > NOW()");
            $stmt->execute([':sel' => $selector]);
            $token_row = $stmt->fetch();

            if ($token_row) {
                // Verifikasi hash validator
                if (hash_equals($token_row['hashed_validator'], hash('sha256', base64_decode($authenticator)))) {
                    
                    // Ambil data user
                    $stmt = $conn->prepare("SELECT * FROM users WHERE id = :id");
                    $stmt->execute([':id' => $token_row['user_id']]);
                    $user = $stmt->fetch();
                    
                    if ($user) {
                        session_regenerate_id(true);
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['user_name'] = $user['name'];
                        $_SESSION['user_role'] = $user['role'];
                        $_SESSION['permissions'] = [
                            'teknisi'   => $user['app_teknisi'],
                            'bukti'     => $user['app_bukti'],
                            'service'   => $user['app_service'],
                            'quotation' => $user['app_quotation'],
                            'produksi'  => $user['app_produksi'],
                            'sales'     => $user['app_sales'],
                            'giti'      => $user['app_giti'],
                            'ssll'      => $user['app_ssll'],
                            'warranty'  => $user['app_warranty']
                        ];
                        return true;
                    }
                }
            }
        }
    }
    return false;
}
?>