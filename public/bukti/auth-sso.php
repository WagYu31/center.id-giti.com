<?php
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

$center_url = "https://center.id-giti.com/sso_server.php"; 
$center_redirect = "https://center.id-giti.com/sso_redirect.php?app=bukti"; 
$center_login_page = "https://center.id-giti.com/login.php"; 

$db_host = "localhost";
$db_user = "center_id_giti"; 
$db_pass = "pTxriSwn5ECcRPBN";
$db_name = "center_id_giti";

try {
    $conn_local = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
    $conn_local->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Database Error");
}

// 1. Jika sudah ada sesi Bukti aktif, langsung arahkan ke dashboard Bukti
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// 2. Jika tidak ada token, minta token baru secara otomatis ke SSO Center gateway
if (!isset($_GET['sso_token']) || empty($_GET['sso_token'])) {
    header("Location: " . $center_redirect);
    exit();
}

$token = $_GET['sso_token'];
$verify_url = $center_url . "?action=validate&token=" . urlencode($token);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $verify_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.110 Safari/537.3');

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$result = json_decode($response, true);

if ($http_code == 200 && isset($result['status']) && $result['status'] == 'success') {
    $email_center = $result['data']['email'];

    $stmt = $conn_local->prepare("SELECT * FROM users WHERE email = :email LIMIT 1");
    $stmt->execute([':email' => $email_center]);
    $local_user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($local_user) {
        $_SESSION['user_id'] = $local_user['id'];
        $_SESSION['role'] = $local_user['role'];
        $_SESSION['name'] = $local_user['name'];
        $_SESSION['dashboard_mode'] = $local_user['dashboard_mode'] ?? 'social';
        
        session_write_close();
        header("Location: index.php");
        exit();
    } else {
        echo "<script>alert('Akun tidak ditemukan di database Bukti.'); window.location.href='{$center_login_page}';</script>";
        exit();
    }
} else {
    // 3. JIKA TOKEN INVALID / EXPIRED: 
    // JANGAN tampilkan halaman mati "Token SSO Invalid", melainkan otomatis regenerasi token ke Center gateway!
    header("Location: " . $center_redirect);
    exit();
}
?>