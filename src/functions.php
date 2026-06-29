<?php
function redirect($url) {
    header("Location: " . $url);
    exit();
}

function check_login() {
    if (!isset($_SESSION['user_id'])) {
        redirect('login.php');
    }
}

function generate_sso_token($user_id, $conn) {
    $token = bin2hex(random_bytes(32));
    $stmt = $conn->prepare("INSERT INTO sso_access_tokens (user_id, token, expires_at) VALUES (:uid, :token, DATE_ADD(NOW(), INTERVAL 1 MINUTE))");
    $stmt->execute([':uid' => $user_id, ':token' => $token]);
    return $token;
}
?>