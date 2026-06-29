<?php
// --- CONFIG SESSION CENTER ---
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
// -----------------------------

require_once '../config/database.php';

// Hapus token dari database jika ada cookie
if (isset($_COOKIE['remember_user'])) {
    $parts = explode(':', $_COOKIE['remember_user']);
    if (count($parts) === 2) {
        $selector = $parts[0];
        $stmt = $conn->prepare("DELETE FROM user_tokens WHERE selector = :sel");
        $stmt->execute([':sel' => $selector]);
    }
}

// Hapus Session
$_SESSION = [];
session_destroy();

// Hapus Cookie Remember Me
setcookie('remember_user', '', time() - 3600, "/", "", true, true);

// Hapus Cookie Sesi
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

header("Location: login.php");
exit();
?>