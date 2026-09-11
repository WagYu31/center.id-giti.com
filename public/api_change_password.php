<?php
session_name('CENTER_SESSION');
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Sesi berakhir, silakan login kembali']);
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$old_password = trim($_POST['old_password'] ?? '');
$new_password = trim($_POST['new_password'] ?? '');
$confirm_password = trim($_POST['confirm_password'] ?? '');

if (empty($old_password) || empty($new_password)) {
    echo json_encode(['status' => 'error', 'message' => 'Semua kolom wajib diisi']);
    exit;
}

if (strlen($new_password) < 6) {
    echo json_encode(['status' => 'error', 'message' => 'Password baru minimal 6 karakter']);
    exit;
}

if ($new_password !== $confirm_password) {
    echo json_encode(['status' => 'error', 'message' => 'Konfirmasi password baru tidak cocok']);
    exit;
}

$stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$current = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$current || !password_verify($old_password, $current['password'])) {
    echo json_encode(['status' => 'error', 'message' => 'Password lama tidak sesuai']);
    exit;
}

$new_hash = password_hash($new_password, PASSWORD_DEFAULT);
$upStmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
$upStmt->execute([$new_hash, $user_id]);

echo json_encode(['status' => 'success', 'message' => 'Password berhasil diperbarui! Gunakan password baru untuk login berikutnya.']);
exit;
