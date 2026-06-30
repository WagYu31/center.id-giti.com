<?php
session_name('CENTER_SESSION');
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

// Auto-create tables if not exists
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
        target_all TINYINT(1) DEFAULT 1,
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    // Add target_all column if missing (for existing tables)
    try {
        $conn->exec("ALTER TABLE announcements ADD COLUMN target_all TINYINT(1) DEFAULT 1");
    } catch (Exception $e) { /* column exists */ }
    
    $conn->exec("CREATE TABLE IF NOT EXISTS announcement_recipients (
        id INT AUTO_INCREMENT PRIMARY KEY,
        announcement_id INT NOT NULL,
        user_id INT NOT NULL,
        is_read TINYINT(1) DEFAULT 0,
        read_at DATETIME DEFAULT NULL,
        UNIQUE KEY unique_ann_user (announcement_id, user_id),
        FOREIGN KEY (announcement_id) REFERENCES announcements(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) { /* tables may already exist */ }

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// LIST USERS (for admin picker)
if ($action === 'list_users') {
    $role = $conn->query("SELECT role FROM users WHERE id=" . $_SESSION['user_id'])->fetchColumn();
    if ($role !== 'admin') {
        echo json_encode(['status' => 'error', 'message' => 'Hanya admin']);
        exit;
    }
    
    $users = $conn->query("SELECT id, name, role FROM users ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['status' => 'success', 'data' => $users]);
    exit;
}

// CREATE announcement (admin only)
if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $role = $conn->query("SELECT role FROM users WHERE id=" . $_SESSION['user_id'])->fetchColumn();
    if ($role !== 'admin') {
        echo json_encode(['status' => 'error', 'message' => 'Hanya admin']);
        exit;
    }
    
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $priority = $_POST['priority'] ?? 'normal';
    $expires = !empty($_POST['expires_at']) ? $_POST['expires_at'] : null;
    $targetAll = ($_POST['target_all'] ?? '1') === '1' ? 1 : 0;
    $recipientIds = !empty($_POST['recipients']) ? json_decode($_POST['recipients'], true) : [];
    
    if (empty($title) || empty($content)) {
        echo json_encode(['status' => 'error', 'message' => 'Judul dan isi wajib diisi']);
        exit;
    }
    
    if (!$targetAll && empty($recipientIds)) {
        echo json_encode(['status' => 'error', 'message' => 'Pilih minimal 1 penerima']);
        exit;
    }
    
    $conn->beginTransaction();
    try {
        $stmt = $conn->prepare("INSERT INTO announcements (title, content, priority, created_by, expires_at, target_all) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$title, $content, $priority, $_SESSION['user_id'], $expires, $targetAll]);
        $annId = $conn->lastInsertId();
        
        if (!$targetAll && !empty($recipientIds)) {
            $insertRecipient = $conn->prepare("INSERT INTO announcement_recipients (announcement_id, user_id) VALUES (?, ?)");
            foreach ($recipientIds as $uid) {
                $insertRecipient->execute([$annId, (int)$uid]);
            }
        }
        
        $conn->commit();
        
        $recipientCount = $targetAll ? 'Semua' : count($recipientIds) . ' orang';
        echo json_encode(['status' => 'success', 'message' => "Pengumuman berhasil dikirim ke $recipientCount"]);
    } catch (Exception $e) {
        $conn->rollBack();
        echo json_encode(['status' => 'error', 'message' => 'Gagal membuat pengumuman: ' . $e->getMessage()]);
    }
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

// FETCH announcements (filtered by current user's access)
if ($action === 'fetch') {
    try {
        $userId = $_SESSION['user_id'];
        $announcements = $conn->prepare("
            SELECT a.*, u.name as author_name, a.target_all,
                (SELECT GROUP_CONCAT(u2.name SEPARATOR ', ')
                 FROM announcement_recipients ar2 
                 JOIN users u2 ON ar2.user_id = u2.id
                 WHERE ar2.announcement_id = a.id) as recipient_names
            FROM announcements a 
            JOIN users u ON a.created_by = u.id 
            WHERE a.is_active = 1 
            AND (a.expires_at IS NULL OR a.expires_at >= CURDATE())
            AND (
                a.target_all = 1
                OR EXISTS (
                    SELECT 1 FROM announcement_recipients ar 
                    WHERE ar.announcement_id = a.id AND ar.user_id = ?
                )
                OR a.created_by = ?
            )
            ORDER BY 
                CASE a.priority WHEN 'urgent' THEN 0 WHEN 'important' THEN 1 ELSE 2 END,
                a.created_at DESC 
            LIMIT 10
        ");
        $announcements->execute([$userId, $userId]);
        $data = $announcements->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['status' => 'success', 'data' => $data]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'success', 'data' => []]);
    }
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
