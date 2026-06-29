<?php
require_once '../app/models/User.php';

class AuthController {
    public function login() {
        if (isset($_SESSION['user_id'])) {
            header('Location: ' . BASE_URL . '/dashboard');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $userModel = new User();
            $user = $userModel->findUserByEmailOrUsername($_POST['user_input']);

            if ($user && password_verify($_POST['password'], $user['password'])) {
                $this->setUserSession($user);

                if (isset($_POST['remember'])) {
                    $token = bin2hex(random_bytes(32));
                    $userModel->updateRememberToken($user['id'], $token);
                    setcookie('remember_me', $token, time() + (86400 * 30), "/");
                }

                header('Location: ' . BASE_URL . '/dashboard');
                exit;
            } else {
                $error = "Username atau Password salah.";
                require_once '../app/views/auth/login.php';
            }
        } else {
            if (isset($_COOKIE['remember_me'])) {
                $userModel = new User();
                $user = $userModel->checkToken($_COOKIE['remember_me']);
                if ($user) {
                    $this->setUserSession($user);
                    header('Location: ' . BASE_URL . '/dashboard');
                    exit;
                }
            }
            require_once '../app/views/auth/login.php';
        }
    }

    private function setUserSession($user) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_username'] = $user['username'];
        $_SESSION['user_division'] = $user['division'];
    }

    public function register() {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $data = [
                'name' => $_POST['name'],
                'email' => $_POST['email'],
                'username' => $_POST['username'],
                'password' => password_hash($_POST['password'], PASSWORD_DEFAULT),
                'division' => 'Staff',
                'telp' => $_POST['telp']
            ];
            $userModel = new User();
            try {
                if ($userModel->register($data)) {
                    header('Location: ' . BASE_URL . '/auth/login?success=1');
                }
            } catch (Exception $e) {
                $error = "Pendaftaran gagal. Email atau Username mungkin sudah digunakan.";
                require_once '../app/views/auth/register.php';
            }
        } else {
            require_once '../app/views/auth/register.php';
        }
    }

    public function logout() {
        session_destroy();
        if (isset($_COOKIE['remember_me'])) {
            setcookie('remember_me', '', time() - 3600, '/');
        }
        header('Location: ' . BASE_URL . '/auth/login');
    }
}