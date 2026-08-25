<?php
// File: public/sso_redirect.php
// Just-In-Time (JIT) SSO Token Generator & Gateway

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
require_once '../src/functions.php';

// Pastikan user sedang login di Center
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$target = $_GET['target'] ?? '';
$app = $_GET['app'] ?? '';

$target_map = [
    'bukti'   => 'https://center.id-giti.com/bukti/auth-sso.php',
    'service' => 'https://center.id-giti.com/service/auth-sso.php',
];

if (isset($target_map[$app])) {
    $target = $target_map[$app];
}

if (empty($target)) {
    header("Location: index.php");
    exit();
}

// Generate token segar langsung saat user mengklik link (0 detik umur token)
$token = generate_sso_token($user_id, $conn);

$separator = (strpos($target, '?') !== false) ? '&' : '?';
header("Location: " . $target . $separator . "sso_token=" . $token);
exit();
?>
