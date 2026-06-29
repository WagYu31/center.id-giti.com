<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    http_response_code(403);
    exit('Unauthorized');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = $_POST['user_id'];
    $appColumn = $_POST['app_column']; // e.g., app_teknisi
    $status = $_POST['status'];

    // Whitelist columns for security
    $allowedCols = ['app_teknisi', 'app_bukti', 'app_sales', 'app_quotation', 'app_service', 'app_produksi', 'app_giti'];
    if (!in_array($appColumn, $allowedCols)) exit('Invalid Column');

    $stmt = $conn->prepare("UPDATE users SET $appColumn = :status WHERE id = :id");
    $stmt->execute([':status' => $status, ':id' => $userId]);
    echo "Success";
}
?>