<?php
session_name('CENTER_SESSION');
session_set_cookie_params([
    'lifetime' => 86400,
    'path'     => '/',
    'domain'   => '',
    'secure'   => true,
    'httponly' => true,
    'samesite' => 'Lax'
]);
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

// Hanya admin yang boleh hapus
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$id = intval($_POST['id'] ?? 0);

// Validasi: ID harus valid, bukan 0, bukan superadmin (id=1)
if ($id <= 0 || $id == 1) {
    echo json_encode(['status' => 'error', 'message' => 'ID tidak valid']);
    exit;
}

// Tidak boleh hapus akun sendiri
if ($id == intval($_SESSION['user_id'] ?? 0)) {
    echo json_encode(['status' => 'error', 'message' => 'Tidak bisa menghapus akun Anda sendiri']);
    exit;
}

try {
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND id != 1");
    $stmt->execute([$id]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['status' => 'success', 'message' => 'Akun berhasil dihapus']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'User tidak ditemukan atau tidak bisa dihapus']);
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Gagal: ' . $e->getMessage()]);
}
