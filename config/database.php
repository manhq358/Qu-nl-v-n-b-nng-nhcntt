<?php
/**
 * File: config/database.php
 * Mục đích: Cấu hình kết nối database và các thiết lập hệ thống
 */

// ========================================
// CẤU HÌNH DATABASE
// ========================================
define('DB_HOST', 'localhost');        // Host MySQL (mặc định là localhost)
define('DB_USER', 'root');             // Username MySQL (mặc định XAMPP là root)
define('DB_PASS', '123456');                 // Password MySQL (mặc định XAMPP để trống)
define('DB_NAME', 'doc_management');   // Tên database
define('DB_CHARSET', 'utf8mb4');       // Character set

// ========================================
// CẤU HÌNH JWT (JSON Web Token)
// ========================================
define('JWT_SECRET', 'your-secret-key-change-this-in-production-2024');  // Secret key để mã hóa JWT
define('JWT_EXPIRATION', 86400);       // Thời gian token hết hạn (86400 = 24 giờ)

// ========================================
// CẤU HÌNH UPLOAD FILE TÀI LIỆU
// ========================================
define('UPLOAD_PATH', __DIR__ . '/../uploads/');           // Đường dẫn lưu file
define('MAX_FILE_SIZE', 524288000);                        // 500MB (500 * 1024 * 1024)
define('ALLOWED_EXTENSIONS', ['pdf', 'docx', 'zip']);      // Các định dạng cho phép

// ========================================
// CẤU HÌNH UPLOAD AVATAR
// ========================================
define('AVATAR_PATH', __DIR__ . '/../uploads/avatars/');   // Đường dẫn lưu avatar
define('MAX_AVATAR_SIZE', 2097152);                        // 2MB (2 * 1024 * 1024)
define('ALLOWED_IMAGE_EXTENSIONS', ['jpg', 'jpeg', 'png']); // Các định dạng ảnh cho phép

// ========================================
// TẠO THƯ MỤC UPLOAD NẾU CHƯA CÓ
// ========================================
if (!file_exists(UPLOAD_PATH)) {
    mkdir(UPLOAD_PATH, 0777, true);
    chmod(UPLOAD_PATH, 0777);
}

if (!file_exists(AVATAR_PATH)) {
    mkdir(AVATAR_PATH, 0777, true);
    chmod(AVATAR_PATH, 0777);
}

// ========================================
// CLASS KẾT NỐI DATABASE (Singleton Pattern)
// ========================================
class Database {
    private static $instance = null;
    private $conn;
    
    /**
     * Constructor - Tạo kết nối PDO
     */
    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            
            $this->conn = new PDO($dsn, DB_USER, DB_PASS, $options);
            
        } catch(PDOException $e) {
            // Log lỗi ra file thay vì hiển thị trực tiếp
            error_log("Database Connection Error: " . $e->getMessage());
            die(json_encode([
                'success' => false,
                'message' => 'Không thể kết nối database. Vui lòng kiểm tra cấu hình.'
            ]));
        }
    }
    
    /**
     * Lấy instance duy nhất của Database
     * @return Database
     */
    public static function getInstance() {
        if (self::$instance == null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }
    
    /**
     * Lấy connection PDO
     * @return PDO
     */
    public function getConnection() {
        return $this->conn;
    }
    
    /**
     * Ngăn clone object
     */
    private function __clone() {}
    
    /**
     * Ngăn unserialize object
     */
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}

// ========================================
// HÀM TIỆN ÍCH
// ========================================

/**
 * Hàm format kích thước file
 * @param int $bytes - Kích thước tính bằng bytes
 * @return string - Chuỗi đã format (KB, MB, GB)
 */
function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}

/**
 * Hàm sanitize input để tránh XSS
 * @param string $data - Dữ liệu cần sanitize
 * @return string - Dữ liệu đã được làm sạch
 */
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * Hàm validate email
 * @param string $email - Email cần validate
 * @return bool - true nếu hợp lệ
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Hàm validate password
 * @param string $password - Password cần validate
 * @return array - ['valid' => bool, 'message' => string]
 */
function validatePassword($password) {
    if (strlen($password) < 8) {
        return ['valid' => false, 'message' => 'Mật khẩu phải có ít nhất 8 ký tự'];
    }
    
    if (!preg_match('/[A-Z]/', $password)) {
        return ['valid' => false, 'message' => 'Mật khẩu phải có ít nhất 1 chữ hoa'];
    }
    
    if (!preg_match('/[0-9]/', $password)) {
        return ['valid' => false, 'message' => 'Mật khẩu phải có ít nhất 1 chữ số'];
    }
    
    return ['valid' => true, 'message' => 'Mật khẩu hợp lệ'];
}

/**
 * Hàm validate file upload
 * @param array $file - $_FILES['field_name']
 * @param array $allowedExtensions - Mảng các extension cho phép
 * @param int $maxSize - Kích thước tối đa (bytes)
 * @return array - ['valid' => bool, 'message' => string]
 */
function validateFileUpload($file, $allowedExtensions, $maxSize) {
    // Kiểm tra có file không
    if (!isset($file) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return ['valid' => false, 'message' => 'Vui lòng chọn file'];
    }
    
    // Kiểm tra lỗi upload
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['valid' => false, 'message' => 'Lỗi khi upload file'];
    }
    
    // Kiểm tra kích thước
    if ($file['size'] > $maxSize) {
        return ['valid' => false, 'message' => 'File vượt quá kích thước cho phép: ' . formatFileSize($maxSize)];
    }
    
    // Kiểm tra extension
    $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($fileExt, $allowedExtensions)) {
        return ['valid' => false, 'message' => 'Chỉ chấp nhận file: ' . implode(', ', $allowedExtensions)];
    }
    
    return ['valid' => true, 'message' => 'File hợp lệ'];
}

/**
 * Hàm tạo tên file unique
 * @param string $originalName - Tên file gốc
 * @return string - Tên file mới unique
 */
function generateUniqueFileName($originalName) {
    $extension = pathinfo($originalName, PATHINFO_EXTENSION);
    return uniqid() . '_' . time() . '.' . strtolower($extension);
}

/**
 * Hàm log lỗi ra file
 * @param string $message - Nội dung log
 * @param string $level - Mức độ: INFO, WARNING, ERROR
 */
function logMessage($message, $level = 'INFO') {
    $logFile = __DIR__ . '/../logs/app.log';
    $logDir = dirname($logFile);
    
    if (!file_exists($logDir)) {
        mkdir($logDir, 0777, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] [$level] $message" . PHP_EOL;
    
    file_put_contents($logFile, $logEntry, FILE_APPEND);
}

// ========================================
// THIẾT LẬP TIMEZONE
// ========================================
date_default_timezone_set('Asia/Ho_Chi_Minh');

// ========================================
// BẬT ERROR REPORTING (CHỈ DÙNG KHI DEVELOP)
// ========================================
// Tắt khi deploy production
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ========================================
// HEADER BẢO MẬT
// ========================================
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');

?>