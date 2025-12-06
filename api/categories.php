<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
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

$action = $_GET['action'] ?? '';
$input = json_decode(file_get_contents('php://input'), true);

switch ($action) {
    case 'list-categories':
        $stmt = $db->query("SELECT * FROM categories ORDER BY name");
        $categories = $stmt->fetchAll();
        echo json_encode(['success' => true, 'data' => $categories]);
        break;
        
    case 'list-types':
        $stmt = $db->query("SELECT * FROM document_types ORDER BY name");
        $types = $stmt->fetchAll();
        echo json_encode(['success' => true, 'data' => $types]);
        break;
        
    case 'add-category':
        if (!$currentUser || $currentUser['role'] !== 'admin') {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            break;
        }
        
        $stmt = $db->prepare("INSERT INTO categories (name, description, parent_id) VALUES (?, ?, ?)");
        $result = $stmt->execute([
            $input['name'],
            $input['description'] ?? null,
            $input['parent_id'] ?? null
        ]);
        
        echo json_encode(['success' => $result, 'message' => $result ? 'Thêm thành công' : 'Thêm thất bại']);
        break;
        
    case 'update-category':
        if (!$currentUser || $currentUser['role'] !== 'admin') {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            break;
        }
        
        $stmt = $db->prepare("UPDATE categories SET name = ?, description = ?, parent_id = ? WHERE id = ?");
        $result = $stmt->execute([
            $input['name'],
            $input['description'] ?? null,
            $input['parent_id'] ?? null,
            $input['id']
        ]);
        
        echo json_encode(['success' => $result, 'message' => $result ? 'Cập nhật thành công' : 'Cập nhật thất bại']);
        break;
        
    case 'delete-category':
        if (!$currentUser || $currentUser['role'] !== 'admin') {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            break;
        }
        
        $id = $input['id'];
        
        // Kiểm tra có tài liệu nào dùng category này không
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM documents WHERE category_id = ? AND is_deleted = 0");
        $stmt->execute([$id]);
        $count = $stmt->fetch()['count'];
        
        if ($count > 0) {
            echo json_encode(['success' => false, 'message' => "Không thể xóa. Có $count tài liệu đang sử dụng danh mục này"]);
            break;
        }
        
        $stmt = $db->prepare("DELETE FROM categories WHERE id = ?");
        $result = $stmt->execute([$id]);
        
        echo json_encode(['success' => $result, 'message' => $result ? 'Xóa thành công' : 'Xóa thất bại']);
        break;
        
    case 'add-type':
        if (!$currentUser || $currentUser['role'] !== 'admin') {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            break;
        }
        
        $stmt = $db->prepare("INSERT INTO document_types (name, description) VALUES (?, ?)");
        $result = $stmt->execute([
            $input['name'],
            $input['description'] ?? null
        ]);
        
        echo json_encode(['success' => $result, 'message' => $result ? 'Thêm thành công' : 'Thêm thất bại']);
        break;
        
    case 'update-type':
        if (!$currentUser || $currentUser['role'] !== 'admin') {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            break;
        }
        
        $stmt = $db->prepare("UPDATE document_types SET name = ?, description = ? WHERE id = ?");
        $result = $stmt->execute([
            $input['name'],
            $input['description'] ?? null,
            $input['id']
        ]);
        
        echo json_encode(['success' => $result, 'message' => $result ? 'Cập nhật thành công' : 'Cập nhật thất bại']);
        break;
        
    case 'delete-type':
        if (!$currentUser || $currentUser['role'] !== 'admin') {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            break;
        }
        
        $id = $input['id'];
        
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM documents WHERE document_type_id = ? AND is_deleted = 0");
        $stmt->execute([$id]);
        $count = $stmt->fetch()['count'];
        
        if ($count > 0) {
            echo json_encode(['success' => false, 'message' => "Không thể xóa. Có $count tài liệu đang sử dụng loại này"]);
            break;
        }
        
        $stmt = $db->prepare("DELETE FROM document_types WHERE id = ?");
        $result = $stmt->execute([$id]);
        
        echo json_encode(['success' => $result, 'message' => $result ? 'Xóa thành công' : 'Xóa thất bại']);
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
?>