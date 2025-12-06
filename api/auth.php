<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once __DIR__ . '/../includes/auth.php';

$auth = new Auth();
$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

// Xử lý OPTIONS request cho CORS
if ($method === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'register':
        if ($method !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            break;
        }
        
        $result = $auth->register($input);
        echo json_encode($result);
        break;
        
    case 'login':
        if ($method !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            break;
        }
        
        $result = $auth->login($input['email'], $input['password']);
        echo json_encode($result);
        break;
        
    case 'verify':
        if ($method !== 'GET') {
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            break;
        }
        
        $headers = getallheaders();
        $token = $headers['Authorization'] ?? '';
        $token = str_replace('Bearer ', '', $token);
        
        $user = $auth->getCurrentUser($token);
        if ($user) {
            unset($user['password']);
            echo json_encode(['success' => true, 'user' => $user]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Token không hợp lệ']);
        }
        break;
        
    case 'change-password':
        if ($method !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            break;
        }
        
        $headers = getallheaders();
        $token = $headers['Authorization'] ?? '';
        $token = str_replace('Bearer ', '', $token);
        
        $userData = $auth->verifyToken($token);
        if (!$userData) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            break;
        }
        
        $result = $auth->changePassword($userData['user_id'], $input['old_password'], $input['new_password']);
        echo json_encode($result);
        break;
        
    case 'forgot-password':
        if ($method !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            break;
        }
        
        $result = $auth->createResetToken($input['email']);
        echo json_encode($result);
        break;
        
    case 'reset-password':
        if ($method !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            break;
        }
        
        $result = $auth->resetPassword($input['token'], $input['new_password']);
        echo json_encode($result);
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
?>