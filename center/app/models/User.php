<?php
class User {
    private $db;

    public function __construct() {
        $this->db = new Database;
    }

    public function findUserByEmailOrUsername($input) {
        $this->db->query('SELECT * FROM users WHERE (email = :input OR username = :input) AND deleted_at IS NULL');
        $this->db->bind(':input', $input);
        return $this->db->single();
    }

    public function getUserById($id) {
        $this->db->query('SELECT * FROM users WHERE id = :id AND deleted_at IS NULL');
        $this->db->bind(':id', $id);
        return $this->db->single();
    }

    public function register($data) {
        $this->db->query('INSERT INTO users (name, email, username, password, division, telp, jabatan) VALUES (:name, :email, :username, :password, :division, :telp, :jabatan)');
        $this->db->bind(':name', $data['name']);
        $this->db->bind(':email', $data['email']);
        $this->db->bind(':username', $data['username']);
        $this->db->bind(':password', $data['password']);
        $this->db->bind(':division', $data['division']);
        $this->db->bind(':telp', $data['telp']);
        $this->db->bind(':jabatan', 'Staff');
        return $this->db->execute();
    }

    public function updateRememberToken($id, $token) {
        $this->db->query('UPDATE users SET remember_token = :token WHERE id = :id');
        $this->db->bind(':token', $token);
        $this->db->bind(':id', $id);
        $this->db->execute();
    }

    public function checkToken($token) {
        $this->db->query('SELECT * FROM users WHERE remember_token = :token AND deleted_at IS NULL');
        $this->db->bind(':token', $token);
        return $this->db->single();
    }

    public function getAllEmployees() {
        $this->db->query('SELECT * FROM users WHERE deleted_at IS NULL ORDER BY name ASC');
        return $this->db->resultSet();
    }

    public function createLoginToken($userId) {
        if (empty($userId)) return false;

        $token = bin2hex(random_bytes(32));
        $expiry = date('Y-m-d H:i:s', strtotime('+5 minutes')); 
        
        $this->db->query('INSERT INTO api_sessions (user_id, session_token, expires_at) VALUES (:uid, :token, :expiry)');
        $this->db->bind(':uid', $userId);
        $this->db->bind(':token', $token);
        $this->db->bind(':expiry', $expiry);
        
        if($this->db->execute()) {
            return $token;
        }
        return false;
    }
}