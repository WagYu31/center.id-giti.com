<?php
session_name('CENTER_SESSION');
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

// Only admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit;
}

$user_id = (int)($_POST['user_id'] ?? 0);
$new_password = trim($_POST['new_password'] ?? '');

if ($user_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'User tidak valid']);
    exit;
}

if (strlen($new_password) < 6) {
    echo json_encode(['status' => 'error', 'message' => 'Password minimal 6 karakter']);
    exit;
}

try {
    $hashed = password_hash($new_password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
    $stmt->execute([$hashed, $user_id]);
    
    // Get user name for confirmation
    $name = $conn->query("SELECT name FROM users WHERE id=$user_id")->fetchColumn();
    
    echo json_encode([
        'status' => 'success', 
        'message' => "Password $name berhasil direset."
    ]);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Gagal reset: ' . $e->getMessage()]);
}
