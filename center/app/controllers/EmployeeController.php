<?php
require_once '../app/models/User.php';

class EmployeeController {
    public function index() {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . BASE_URL . '/auth/login');
            exit;
        }

        $userModel = new User();
        $data['employees'] = $userModel->getAllEmployees();
        $data['title'] = 'Data Karyawan';

        require_once '../app/views/layouts/header.php';
        require_once '../app/views/employee/index.php';
        require_once '../app/views/layouts/footer.php';
    }
}