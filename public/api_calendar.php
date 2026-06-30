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
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->exec("CREATE TABLE IF NOT EXISTS calendar_events (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        event_date DATE NOT NULL,
        end_date DATE DEFAULT NULL,
        event_time TIME DEFAULT NULL,
        color VARCHAR(20) DEFAULT '#d97706',
        description TEXT DEFAULT NULL,
        is_done TINYINT(1) DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    try { $conn->exec("ALTER TABLE calendar_events ADD INDEX idx_user_date (user_id, event_date)"); } catch(Exception $e2) {}
    // Add end_date column if missing
    try { $conn->exec("ALTER TABLE calendar_events ADD COLUMN end_date DATE DEFAULT NULL AFTER event_date"); } catch(Exception $e2) {}
} catch (Exception $e) {
    error_log("Calendar table creation error: " . $e->getMessage());
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// CREATE event
if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $date = $_POST['event_date'] ?? '';
    $endDate = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
    $time = !empty($_POST['event_time']) ? $_POST['event_time'] : null;
    $color = $_POST['color'] ?? '#d97706';
    $desc = trim($_POST['description'] ?? '');
    
    if (empty($title) || empty($date)) {
        echo json_encode(['status' => 'error', 'message' => 'Judul dan tanggal wajib diisi']);
        exit;
    }
    
    // Validate end_date >= event_date
    if ($endDate && $endDate < $date) {
        echo json_encode(['status' => 'error', 'message' => 'Tanggal selesai harus setelah tanggal mulai']);
        exit;
    }
    
    $stmt = $conn->prepare("INSERT INTO calendar_events (user_id, title, event_date, end_date, event_time, color, description) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$_SESSION['user_id'], $title, $date, $endDate, $time, $color, $desc]);
    
    echo json_encode(['status' => 'success', 'id' => $conn->lastInsertId()]);
    exit;
}

// FETCH events for a month (including multi-day events that overlap)
if ($action === 'fetch') {
    $month = (int)($_GET['month'] ?? date('m'));
    $year = (int)($_GET['year'] ?? date('Y'));
    
    $firstDay = sprintf('%04d-%02d-01', $year, $month);
    $lastDay = date('Y-m-t', strtotime($firstDay));
    
    // Events that overlap with this month:
    // event_date <= lastDay AND (end_date >= firstDay OR (end_date IS NULL AND event_date >= firstDay))
    $stmt = $conn->prepare("SELECT * FROM calendar_events WHERE user_id = ? AND event_date <= ? AND (COALESCE(end_date, event_date) >= ?) ORDER BY event_date ASC, event_time ASC");
    $stmt->execute([$_SESSION['user_id'], $lastDay, $firstDay]);
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['status' => 'success', 'data' => $events]);
    exit;
}

// FETCH events for a specific date (including multi-day that span this date)
if ($action === 'fetch_date') {
    $date = $_GET['date'] ?? date('Y-m-d');
    
    $stmt = $conn->prepare("SELECT * FROM calendar_events WHERE user_id = ? AND event_date <= ? AND COALESCE(end_date, event_date) >= ? ORDER BY event_time ASC, created_at ASC");
    $stmt->execute([$_SESSION['user_id'], $date, $date]);
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
    $stmt = $conn->prepare("SELECT * FROM calendar_events WHERE user_id = ? AND COALESCE(end_date, event_date) >= CURDATE() AND event_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND is_done = 0 ORDER BY event_date ASC, event_time ASC LIMIT 5");
    $stmt->execute([$_SESSION['user_id']]);
    echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
