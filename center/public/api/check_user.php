<?php
header('Content-Type: application/json');
require_once '../../app/config/config.php';
require_once '../../app/core/Database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method Not Allowed']);
    exit;
}

$input_key = $_POST['secret_key'] ?? '';
$token     = $_POST['token'] ?? '';

if ($input_key !== SHARED_SECRET_KEY) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Invalid Secret Key']);
    exit;
}

$db = new Database();
$db->query("SELECT u.id, u.name, u.division, u.jabatan, u.email, u.images, u.bukti, u.teknisi, u.service, u.quotation, u.sales 
            FROM users u
            JOIN api_sessions a ON u.id = a.user_id
            WHERE a.session_token = :token AND a.expires_at > NOW() AND u.deleted_at IS NULL");
$db->bind(':token', $token);
$user = $db->single();

if ($user) {
    echo json_encode(['status' => 'success', 'data' => $user]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Token invalid or expired']);
}