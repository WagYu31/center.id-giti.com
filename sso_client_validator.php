<?php
// Letakkan file ini di website target (misal: di folder root website Teknisi)
// Panggil file ini di halaman auth-sso.php di website target

class LoewixSSO {
    private $centerUrl;

    public function __construct($centerUrl) {
        $this->centerUrl = rtrim($centerUrl, '/');
    }

    public function validateToken($token) {
        $url = $this->centerUrl . '/src/sso_server.php?action=validate&token=' . $token;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Set true jika sudah HTTPS valid
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            return json_decode($response, true);
        }
        return false;
    }
}

// PENGGUNAAN DI WEBSITE LAIN:
/*
session_start();
require 'sso_client_validator.php';

if (isset($_GET['sso_token'])) {
    $sso = new LoewixSSO('https://center.grav-tech.com'); // URL Center Login
    $userData = $sso->validateToken($_GET['sso_token']);

    if ($userData && isset($userData['user_id'])) {
        // LOGIN SUKSES DI WEBSITE INI
        $_SESSION['user_id'] = $userData['user_id'];
        $_SESSION['nama'] = $userData['name'];
        $_SESSION['role'] = $userData['role'];
        
        header("Location: index.php"); // Masuk ke dashboard website ini
        exit();
    } else {
        die("Token SSO Invalid atau Expired.");
    }
} else {
    die("Token tidak ditemukan.");
}
*/
?>