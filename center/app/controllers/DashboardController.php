<?php
require_once '../app/models/User.php';

class DashboardController {
    public function index() {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . BASE_URL . '/auth/login');
            exit;
        }

        $userModel = new User();
        
        // Perbaikan: Cari berdasarkan ID dari session, bukan username
        $user = $userModel->getUserById($_SESSION['user_id']);
        
        // Perbaikan: Cek jika user tidak ditemukan (misal dihapus saat sedang login)
        if (!$user) {
            session_destroy();
            header('Location: ' . BASE_URL . '/auth/login');
            exit;
        }

        $data['user'] = $user;
        $data['title'] = 'Dashboard Center';
        $data['sso_token'] = $userModel->createLoginToken($user['id']);

        require_once '../app/views/layouts/header.php';
        require_once '../app/views/dashboard/index.php';
        require_once '../app/views/layouts/footer.php';
    }
}