<?php
session_name('CENTER_SESSION');
session_set_cookie_params([
    'lifetime' => 86400,
    'path' => '/', // Berlaku di root
    'domain' => '', 
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Lax'
]);

session_start();
require_once '../config/database.php';

// 1. Cek Login & Hak Akses
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo "Unauthorized";
    exit;
}

// 2. Proses Request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $id = $_POST['id'];
    $field = $_POST['field'];
    $value = $_POST['value'];

    // 3. KEAMANAN: Whitelist Kolom
    $allowed_fields = [
        'app_teknisi', 
        'app_bukti', 
        'app_service', 
        'app_quotation', 
        'app_produksi', 
        'app_sales', 
        'app_giti',
        'app_ssll',
        'app_warranty',
        
        'teknisi', 'bukti', 'service', 'quotation', 'sales'
    ];

    if (!in_array($field, $allowed_fields)) {
        http_response_code(400);
        echo "Invalid Field / Kolom tidak diizinkan: " . htmlspecialchars($field);
        exit;
    }

    // 4. Konversi Data (PENTING!)
    
    // OPSI A: Jika kolom di database tipe TINYINT/BOOLEAN (0 atau 1)
    $dbValue = ($value === 'Y') ? 1 : 0;

    // OPSI B: Jika kolom di database tipe ENUM/VARCHAR ('Y' atau 'N')
    // Jika Opsi A tidak jalan (data tidak berubah)
    // $dbValue = $value; 

    try {
        // 5. Eksekusi Update ke Database
        $sql = "UPDATE users SET $field = :val WHERE id = :id";
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':val' => $dbValue, 
            ':id' => $id
        ]);
        
        echo "Sukses Update: ID $id -> $field = $value";
    } catch (PDOException $e) {
        http_response_code(500);
        echo "Database Error: " . $e->getMessage();
    }

} else {
    echo "Invalid Request Method";
}
?>