<?php
session_name('CENTER_SESSION');
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

// Auto-create table if not exists
try {
    $conn->exec("CREATE TABLE IF NOT EXISTS announcements (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        content TEXT NOT NULL,
        priority ENUM('normal','important','urgent') DEFAULT 'normal',
        created_by INT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        expires_at DATE DEFAULT NULL,
        is_active TINYINT(1) DEFAULT 1,
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) { /* table may already exist */ }

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// CREATE announcement (admin only)
if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check admin
    $role = $conn->query("SELECT role FROM users WHERE id=" . $_SESSION['user_id'])->fetchColumn();
    if ($role !== 'admin') {
        echo json_encode(['status' => 'error', 'message' => 'Hanya admin']);
        exit;
    }
    
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $priority = $_POST['priority'] ?? 'normal';
    $expires = !empty($_POST['expires_at']) ? $_POST['expires_at'] : null;
    
    if (empty($title) || empty($content)) {
        echo json_encode(['status' => 'error', 'message' => 'Judul dan isi wajib diisi']);
        exit;
    }
    
    $stmt = $conn->prepare("INSERT INTO announcements (title, content, priority, created_by, expires_at) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$title, $content, $priority, $_SESSION['user_id'], $expires]);
    
    echo json_encode(['status' => 'success', 'message' => 'Pengumuman berhasil dibuat']);
    exit;
}

// DELETE announcement (admin only)
if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $role = $conn->query("SELECT role FROM users WHERE id=" . $_SESSION['user_id'])->fetchColumn();
    if ($role !== 'admin') {
        echo json_encode(['status' => 'error', 'message' => 'Hanya admin']);
        exit;
    }
    
    $id = (int)($_POST['id'] ?? 0);
    $conn->prepare("DELETE FROM announcements WHERE id = ?")->execute([$id]);
    echo json_encode(['status' => 'success']);
    exit;
}

// FETCH latest announcements
if ($action === 'fetch') {
    try {
        $announcements = $conn->query("
            SELECT a.*, u.name as author_name 
            FROM announcements a 
            JOIN users u ON a.created_by = u.id 
            WHERE a.is_active = 1 
            AND (a.expires_at IS NULL OR a.expires_at >= CURDATE())
            ORDER BY 
                CASE a.priority WHEN 'urgent' THEN 0 WHEN 'important' THEN 1 ELSE 2 END,
                a.created_at DESC 
            LIMIT 5
        ")->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['status' => 'success', 'data' => $announcements]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'success', 'data' => []]);
    }
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
