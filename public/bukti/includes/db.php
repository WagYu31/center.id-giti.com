<?php
$host = "localhost";
$user = "center_id_giti"; 
$pass = "pTxriSwn5ECcRPBN"; 
$name = "center_id_giti";

try {
    $conn = new PDO("mysql:host=$host;dbname=$name", $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Koneksi Gagal");
}

date_default_timezone_set('Asia/Jakarta');
$conn->query("SET time_zone = '+07:00'");

// Auto-create views table (safe - won't crash if fails)
try {
    $conn->exec("CREATE TABLE IF NOT EXISTS bukti_post_views (
        id INT AUTO_INCREMENT PRIMARY KEY,
        job_id INT NOT NULL,
        user_id INT NOT NULL,
        viewed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_view (job_id, user_id),
        INDEX idx_job (job_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch(Exception $e) {
    // Table may already exist or insufficient permissions - ignore
}

if (session_status() === PHP_SESSION_NONE) {
    session_name('BUKTI_SESSION');
    session_set_cookie_params([
        'lifetime' => 86400,
        'path' => '/bukti/',
        'domain' => '', 
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth-sso.php");
    exit;
}

function tgl_indo($timestamp = '', $date_format = 'l, j F Y | H:i') {
    if (trim($timestamp) == '') $timestamp = time();
    elseif (!ctype_digit($timestamp)) $timestamp = strtotime($timestamp);
    
    $hari = ['Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'];
    $bulan = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    
    $formatted = date($date_format, $timestamp);
    $formatted = str_replace(array_keys($hari), array_values($hari), $formatted);
    return preg_replace_callback('/ [a-zA-Z]+ \d{4}/', function($matches) use ($bulan) {
        $month_name = date('n', strtotime($matches[0]));
        return ' ' . $bulan[$month_name] . ' ' . date('Y', strtotime($matches[0]));
    }, $formatted);
}
?>