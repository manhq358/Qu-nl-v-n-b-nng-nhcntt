<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

$db = Database::getInstance()->getConnection();
$auth = new Auth();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$headers = getallheaders();
$token = $headers['Authorization'] ?? '';
$token = str_replace('Bearer ', '', $token);
$currentUser = $auth->verifyToken($token);

if (!$currentUser) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'profile':
        $stmt = $db->prepare("SELECT id, email, full_name, student_code, role, department, avatar_url, created_at FROM users WHERE id = ?");
        $stmt->execute([$currentUser['user_id']]);
        $user = $stmt->fetch();
        
        echo json_encode(['success' => true, 'data' => $user]);
        break;
        
    case 'update-profile':
        if ($method !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            break;
        }
        
        $fullName = $_POST['full_name'] ?? '';
        $studentCode = $_POST['student_code'] ?? '';
        $department = $_POST['department'] ?? '';
        
        if (empty($fullName)) {
            echo json_encode(['success' => false, 'message' => 'Họ tên không được để trống']);
            break;
        }
        
        $stmt = $db->prepare("UPDATE users SET full_name = ?, student_code = ?, department = ? WHERE id = ?");
        $result = $stmt->execute([$fullName, $studentCode, $department, $currentUser['user_id']]);
        
        echo json_encode(['success' => $result, 'message' => $result ? 'Cập nhật thành công' : 'Cập nhật thất bại']);
        break;
        
    case 'upload-avatar':
        if ($method !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            break;
        }
        
        if (!isset($_FILES['avatar'])) {
            echo json_encode(['success' => false, 'message' => 'Không có file được upload']);
            break;
        }
        
        $file = $_FILES['avatar'];
        $fileSize = $file['size'];
        $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if ($fileSize > MAX_AVATAR_SIZE) {
            echo json_encode(['success' => false, 'message' => 'Ảnh vượt quá 2MB']);
            break;
        }
        
        if (!in_array($fileExt, ALLOWED_IMAGE_EXTENSIONS)) {
            echo json_encode(['success' => false, 'message' => 'Chỉ chấp nhận file JPG, JPEG, PNG']);
            break;
        }
        
        $newFileName = 'avatar_' . $currentUser['user_id'] . '_' . time() . '.' . $fileExt;
        $filePath = AVATAR_PATH . $newFileName;
        
        if (move_uploaded_file($file['tmp_name'], $filePath)) {
            $stmt = $db->prepare("UPDATE users SET avatar_url = ? WHERE id = ?");
            $result = $stmt->execute(['uploads/avatars/' . $newFileName, $currentUser['user_id']]);
            
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Upload avatar thành công', 'avatar_url' => 'uploads/avatars/' . $newFileName]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Lưu database thất bại']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Upload file thất bại']);
        }
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
?>