<?php
session_name('CENTER_SESSION');
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

// Auto-add column if not exists
try {
    $conn->exec("ALTER TABLE users ADD COLUMN monthly_target INT DEFAULT 30");
} catch (Exception $e) { /* column already exists */ }

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// UPDATE target (admin only, or self)
if ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $role = $conn->query("SELECT role FROM users WHERE id=" . $_SESSION['user_id'])->fetchColumn();
    
    $targetUserId = (int)($_POST['user_id'] ?? $_SESSION['user_id']);
    $newTarget = (int)($_POST['target'] ?? 30);
    
    // Only admin can set other user's target
    if ($targetUserId !== $_SESSION['user_id'] && $role !== 'admin') {
        echo json_encode(['status' => 'error', 'message' => 'Hanya admin']);
        exit;
    }
    
    if ($newTarget < 1 || $newTarget > 999) {
        echo json_encode(['status' => 'error', 'message' => 'Target harus antara 1-999']);
        exit;
    }
    
    $stmt = $conn->prepare("UPDATE users SET monthly_target = ? WHERE id = ?");
    $stmt->execute([$newTarget, $targetUserId]);
    
    echo json_encode(['status' => 'success', 'target' => $newTarget]);
    exit;
}

// GET target for a user
if ($action === 'get') {
    $uid = (int)($_GET['user_id'] ?? $_SESSION['user_id']);
    try {
        $t = $conn->query("SELECT monthly_target FROM users WHERE id=$uid")->fetchColumn();
        echo json_encode(['status' => 'success', 'target' => (int)($t ?: 30)]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'success', 'target' => 30]);
    }
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
