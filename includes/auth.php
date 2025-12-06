<?php
require_once __DIR__ . '/../config/database.php';

class Auth {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    // Đăng ký
    public function register($data) {
        // Validate email
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Email không hợp lệ'];
        }
        
        // Validate password
        if (strlen($data['password']) < 8) {
            return ['success' => false, 'message' => 'Mật khẩu phải có ít nhất 8 ký tự'];
        }
        if (!preg_match('/[A-Z]/', $data['password']) || !preg_match('/[0-9]/', $data['password'])) {
            return ['success' => false, 'message' => 'Mật khẩu phải có chữ hoa và số'];
        }
        
        // Kiểm tra email đã tồn tại
        $stmt = $this->db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$data['email']]);
        if ($stmt->rowCount() > 0) {
            return ['success' => false, 'message' => 'Email đã được sử dụng'];
        }
        
        // Hash password
        $hashedPassword = password_hash($data['password'], PASSWORD_BCRYPT);
        
        // Insert user
        $stmt = $this->db->prepare("INSERT INTO users (email, password, full_name, student_code, role, department) VALUES (?, ?, ?, ?, ?, ?)");
        $result = $stmt->execute([
            $data['email'],
            $hashedPassword,
            $data['full_name'],
            $data['student_code'] ?? null,
            $data['role'] ?? 'student',
            $data['department'] ?? null
        ]);
        
        if ($result) {
            return ['success' => true, 'message' => 'Đăng ký thành công'];
        }
        return ['success' => false, 'message' => 'Đăng ký thất bại'];
    }
    
    // Đăng nhập
    public function login($email, $password) {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE email = ? AND is_blocked = 0");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            $token = $this->generateToken($user);
            return [
                'success' => true,
                'token' => $token,
                'user' => [
                    'id' => $user['id'],
                    'email' => $user['email'],
                    'full_name' => $user['full_name'],
                    'role' => $user['role']
                ]
            ];
        }
        
        return ['success' => false, 'message' => 'Email hoặc mật khẩu không đúng'];
    }
    
    // Tạo JWT token
    private function generateToken($user) {
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        $payload = json_encode([
            'user_id' => $user['id'],
            'email' => $user['email'],
            'role' => $user['role'],
            'exp' => time() + JWT_EXPIRATION
        ]);
        
        $base64UrlHeader = $this->base64UrlEncode($header);
        $base64UrlPayload = $this->base64UrlEncode($payload);
        $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, JWT_SECRET, true);
        $base64UrlSignature = $this->base64UrlEncode($signature);
        
        return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
    }
    
    // Verify JWT token
    public function verifyToken($token) {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return false;
        }
        
        list($header, $payload, $signature) = $parts;
        $validSignature = $this->base64UrlEncode(
            hash_hmac('sha256', $header . "." . $payload, JWT_SECRET, true)
        );
        
        if ($signature !== $validSignature) {
            return false;
        }
        
        $payloadData = json_decode($this->base64UrlDecode($payload), true);
        if ($payloadData['exp'] < time()) {
            return false;
        }
        
        return $payloadData;
    }
    
    // Get current user từ token
    public function getCurrentUser($token) {
        $payload = $this->verifyToken($token);
        if (!$payload) {
            return null;
        }
        
        $stmt = $this->db->prepare("SELECT * FROM users WHERE id = ? AND is_blocked = 0");
        $stmt->execute([$payload['user_id']]);
        return $stmt->fetch();
    }
    
    // Đổi mật khẩu
    public function changePassword($userId, $oldPassword, $newPassword) {
        $stmt = $this->db->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if (!password_verify($oldPassword, $user['password'])) {
            return ['success' => false, 'message' => 'Mật khẩu cũ không đúng'];
        }
        
        if (strlen($newPassword) < 8 || !preg_match('/[A-Z]/', $newPassword) || !preg_match('/[0-9]/', $newPassword)) {
            return ['success' => false, 'message' => 'Mật khẩu mới phải có ít nhất 8 ký tự, bao gồm chữ hoa và số'];
        }
        
        $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
        $stmt = $this->db->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$hashedPassword, $userId]);
        
        return ['success' => true, 'message' => 'Đổi mật khẩu thành công'];
    }
    
    // Quên mật khẩu - Tạo token reset
    public function createResetToken($email) {
        $stmt = $this->db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->rowCount() === 0) {
            return ['success' => false, 'message' => 'Email không tồn tại'];
        }
        
        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', time() + 3600); // 1 giờ
        
        $stmt = $this->db->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
        $stmt->execute([$email, $token, $expiresAt]);
        
        return ['success' => true, 'token' => $token];
    }
    
    // Reset mật khẩu
    public function resetPassword($token, $newPassword) {
        $stmt = $this->db->prepare("SELECT * FROM password_resets WHERE token = ? AND expires_at > NOW()");
        $stmt->execute([$token]);
        $reset = $stmt->fetch();
        
        if (!$reset) {
            return ['success' => false, 'message' => 'Token không hợp lệ hoặc đã hết hạn'];
        }
        
        $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
        $stmt = $this->db->prepare("UPDATE users SET password = ? WHERE email = ?");
        $stmt->execute([$hashedPassword, $reset['email']]);
        
        // Xóa token đã sử dụng
        $stmt = $this->db->prepare("DELETE FROM password_resets WHERE token = ?");
        $stmt->execute([$token]);
        
        return ['success' => true, 'message' => 'Reset mật khẩu thành công'];
    }
    
    private function base64UrlEncode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
    
    private function base64UrlDecode($data) {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}
?>