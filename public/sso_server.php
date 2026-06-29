<?php
// File: public/sso_server.php
require_once '../config/database.php'; // Pastikan path ini benar (sesuai posisi file di folder public)

header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");

// Tangani jika file config database tidak sengaja mencetak spasi/enter
ob_clean(); 

if (isset($_GET['action']) && $_GET['action'] === 'validate' && isset($_GET['token'])) {
    $token = $_GET['token'];

    try {
        // 1. Ambil data Token & User sekaligus
        // Kita gunakan FETCH_ASSOC agar arraynya rapi
        $sql = "SELECT u.id, u.name, u.email, u.role, u.division, u.jabatan 
                FROM sso_access_tokens t 
                JOIN users u ON t.user_id = u.id 
                WHERE t.token = :token AND t.expires_at > NOW() 
                LIMIT 1";
                
        $stmt = $conn->prepare($sql);
        $stmt->execute([':token' => $token]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // 2. HAPUS TOKEN (Hanya jika user ditemukan)
            $del = $conn->prepare("DELETE FROM sso_access_tokens WHERE token = :token");
            $del->execute([':token' => $token]);

            // 3. Kirim Respon Sukses
            echo json_encode([
                'status' => 'success',
                'data' => $user
            ]);
        } else {
            // Token tidak ada, atau kadaluarsa, atau user tidak ditemukan
            http_response_code(401);
            echo json_encode([
                'status' => 'error', 
                'message' => 'Token Invalid, Expired, or User Not Found'
            ]);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'status' => 'error', 
            'message' => 'Database Error: ' . $e->getMessage()
        ]);
    }
} else {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Bad Request']);
}
?>