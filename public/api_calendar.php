<?php
session_name('CENTER_SESSION');
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

// Auto-create table
try {
    $conn->exec("CREATE TABLE IF NOT EXISTS calendar_events (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        event_date DATE NOT NULL,
        event_time TIME DEFAULT NULL,
        color VARCHAR(20) DEFAULT '#d97706',
        description TEXT DEFAULT NULL,
        is_done TINYINT(1) DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_user_date (user_id, event_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) {}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// CREATE event
if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $date = $_POST['event_date'] ?? '';
    $time = !empty($_POST['event_time']) ? $_POST['event_time'] : null;
    $color = $_POST['color'] ?? '#d97706';
    $desc = trim($_POST['description'] ?? '');
    
    if (empty($title) || empty($date)) {
        echo json_encode(['status' => 'error', 'message' => 'Judul dan tanggal wajib diisi']);
        exit;
    }
    
    $stmt = $conn->prepare("INSERT INTO calendar_events (user_id, title, event_date, event_time, color, description) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$_SESSION['user_id'], $title, $date, $time, $color, $desc]);
    
    echo json_encode(['status' => 'success', 'id' => $conn->lastInsertId()]);
    exit;
}

// FETCH events for a month
if ($action === 'fetch') {
    $month = (int)($_GET['month'] ?? date('m'));
    $year = (int)($_GET['year'] ?? date('Y'));
    
    $stmt = $conn->prepare("SELECT * FROM calendar_events WHERE user_id = ? AND MONTH(event_date) = ? AND YEAR(event_date) = ? ORDER BY event_date ASC, event_time ASC");
    $stmt->execute([$_SESSION['user_id'], $month, $year]);
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['status' => 'success', 'data' => $events]);
    exit;
}

// FETCH events for a specific date
if ($action === 'fetch_date') {
    $date = $_GET['date'] ?? date('Y-m-d');
    
    $stmt = $conn->prepare("SELECT * FROM calendar_events WHERE user_id = ? AND event_date = ? ORDER BY event_time ASC, created_at ASC");
    $stmt->execute([$_SESSION['user_id'], $date]);
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['status' => 'success', 'data' => $events]);
    exit;
}

// TOGGLE done
if ($action === 'toggle' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $conn->prepare("UPDATE calendar_events SET is_done = NOT is_done WHERE id = ? AND user_id = ?")->execute([$id, $_SESSION['user_id']]);
    echo json_encode(['status' => 'success']);
    exit;
}

// DELETE event
if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $conn->prepare("DELETE FROM calendar_events WHERE id = ? AND user_id = ?")->execute([$id, $_SESSION['user_id']]);
    echo json_encode(['status' => 'success']);
    exit;
}

// UPCOMING events (next 7 days)
if ($action === 'upcoming') {
    $stmt = $conn->prepare("SELECT * FROM calendar_events WHERE user_id = ? AND event_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND is_done = 0 ORDER BY event_date ASC, event_time ASC LIMIT 5");
    $stmt->execute([$_SESSION['user_id']]);
    echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
