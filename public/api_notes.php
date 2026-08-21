<?php
session_name('CENTER_SESSION');
session_set_cookie_params(['lifetime'=>86400,'path'=>'/','secure'=>true,'httponly'=>true,'samesite'=>'Lax']);
session_start();
require_once '../config/database.php';
require_once '../src/auth.php';
require_once '../src/functions.php';
auto_login($conn);
if (empty($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['status'=>'error','message'=>'Unauthorized']); exit; }

header('Content-Type: application/json');
date_default_timezone_set('Asia/Jakarta');

$user_id = (int)$_SESSION['user_id'];
$action  = $_POST['action'] ?? $_GET['action'] ?? '';

// ── Auto-create tabel ─────────────────────────────────────────────
$conn->exec("CREATE TABLE IF NOT EXISTS personal_notes (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT NOT NULL,
    title      VARCHAR(255) NOT NULL DEFAULT '',
    content    TEXT,
    color      VARCHAR(20) DEFAULT '#f59e0b',
    is_pinned  TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_pin  (is_pinned, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// ── FETCH LIST ────────────────────────────────────────────────────
if ($action === 'fetch') {
    $q     = '%' . trim($_GET['q'] ?? '') . '%';
    $stmt  = $conn->prepare("SELECT * FROM personal_notes
        WHERE user_id = ? AND (title LIKE ? OR content LIKE ?)
        ORDER BY is_pinned DESC, updated_at DESC
        LIMIT 80");
    $stmt->execute([$user_id, $q, $q]);
    $notes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($notes as &$n) {
        $n['updated_fmt'] = (new DateTime($n['updated_at'], new DateTimeZone('Asia/Jakarta')))->format('d M Y, H:i');
        $n['created_fmt'] = (new DateTime($n['created_at'], new DateTimeZone('Asia/Jakarta')))->format('d M Y, H:i');
        $n['preview']     = mb_substr(strip_tags($n['content']), 0, 80);
        $n['word_count']  = str_word_count(strip_tags($n['content']));
    }
    echo json_encode(['status'=>'success', 'notes'=>$notes]);
    exit;
}

// ── CREATE ────────────────────────────────────────────────────────
if ($action === 'create') {
    $title   = trim($_POST['title']   ?? 'Catatan Baru');
    $content = trim($_POST['content'] ?? '');
    $color   = $_POST['color'] ?? '#f59e0b';
    if (!$title) $title = 'Catatan Baru';
    $stmt = $conn->prepare("INSERT INTO personal_notes (user_id, title, content, color) VALUES (?,?,?,?)");
    $stmt->execute([$user_id, $title, $content, $color]);
    echo json_encode(['status'=>'success', 'id'=>$conn->lastInsertId()]);
    exit;
}

// ── UPDATE ────────────────────────────────────────────────────────
if ($action === 'update') {
    $id      = (int)$_POST['id'];
    $title   = trim($_POST['title']   ?? '');
    $content = trim($_POST['content'] ?? '');
    $color   = $_POST['color'] ?? '#f59e0b';
    // Validasi ownership
    $chk = $conn->prepare("SELECT id FROM personal_notes WHERE id=? AND user_id=?");
    $chk->execute([$id, $user_id]);
    if (!$chk->fetch()) { echo json_encode(['status'=>'error','message'=>'Not found']); exit; }
    if (!$title) $title = 'Catatan Tanpa Judul';
    $conn->prepare("UPDATE personal_notes SET title=?, content=?, color=?, updated_at=NOW() WHERE id=? AND user_id=?")
         ->execute([$title, $content, $color, $id, $user_id]);
    echo json_encode(['status'=>'success']);
    exit;
}

// ── TOGGLE PIN ────────────────────────────────────────────────────
if ($action === 'pin') {
    $id = (int)$_POST['id'];
    $conn->prepare("UPDATE personal_notes SET is_pinned = NOT is_pinned WHERE id=? AND user_id=?")
         ->execute([$id, $user_id]);
    echo json_encode(['status'=>'success']);
    exit;
}

// ── DELETE ────────────────────────────────────────────────────────
if ($action === 'delete') {
    $id = (int)$_POST['id'];
    $conn->prepare("DELETE FROM personal_notes WHERE id=? AND user_id=?")
         ->execute([$id, $user_id]);
    echo json_encode(['status'=>'success']);
    exit;
}

echo json_encode(['status'=>'error','message'=>'Action tidak dikenal']);
