<?php
// Wajib memanggil nama sesi yang sama dengan index.php sebelum session_start()
session_name('CENTER_SESSION');
session_start();

require_once '../config/database.php';

// Cek Login
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo "Unauthorized";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ambil input notes
    $notes = $_POST['notes'] ?? '';
    $userId = $_SESSION['user_id'];

    try {
        $stmt = $conn->prepare("UPDATE users SET notes = :notes WHERE id = :id");
        $stmt->execute([':notes' => $notes, ':id' => $userId]);
        echo "success";
    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage();
    }
} else {
    echo "Invalid Request";
}
?>