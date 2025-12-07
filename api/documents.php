<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, PUT, DELETE, OPTIONS');
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

// Lấy token và verify user
$headers = getallheaders();
$token = $headers['Authorization'] ?? '';
$token = str_replace('Bearer ', '', $token);
$currentUser = $auth->verifyToken($token);

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'list':
        // Lấy danh sách tài liệu với bộ lọc
        $page = $_GET['page'] ?? 1;
        $limit = $_GET['limit'] ?? 10;
        $offset = ($page - 1) * $limit;
        $search = $_GET['search'] ?? '';
        $category = $_GET['category'] ?? '';
        $docType = $_GET['doc_type'] ?? '';
        $format = $_GET['format'] ?? '';
        $timeRange = $_GET['time_range'] ?? '';
        
        $where = ['d.is_deleted = 0'];
        $params = [];
        
        // NÂNG CẤP: Tìm kiếm trong title, description VÀ author_name
        if ($search) {
            $where[] = '(d.title LIKE ? OR d.description LIKE ? OR u.full_name LIKE ?)';
            $searchTerm = "%$search%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        if ($category) {
            $where[] = 'd.category_id = ?';
            $params[] = $category;
        }
        
        if ($docType) {
            $where[] = 'd.document_type_id = ?';
            $params[] = $docType;
        }
        
        if ($format) {
            $formats = explode(',', $format);
            if (count($formats) > 0) {
                $formatPlaceholders = implode(',', array_fill(0, count($formats), '?'));
                $where[] = "d.file_format IN ($formatPlaceholders)";
                $params = array_merge($params, $formats);
            }
        }
        
        if ($timeRange) {
            switch ($timeRange) {
                case 'week':
                    $where[] = 'd.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)';
                    break;
                case 'month':
                    $where[] = 'd.created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)';
                    break;
                case 'year':
                    $where[] = 'd.created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)';
                    break;
            }
        }
        
        $whereClause = implode(' AND ', $where);
        
        // Count total - PHẢI JOIN với users để search author
        $countSql = "SELECT COUNT(*) as total 
                     FROM documents d 
                     LEFT JOIN users u ON d.author_id = u.id 
                     WHERE $whereClause";
        $stmt = $db->prepare($countSql);
        $stmt->execute($params);
        $total = $stmt->fetch()['total'];
        
        // Get documents
        $sql = "SELECT d.*, u.full_name as author_name, c.name as category_name, dt.name as doc_type_name 
                FROM documents d 
                LEFT JOIN users u ON d.author_id = u.id 
                LEFT JOIN categories c ON d.category_id = c.id 
                LEFT JOIN document_types dt ON d.document_type_id = dt.id 
                WHERE $whereClause 
                ORDER BY d.created_at DESC 
                LIMIT ? OFFSET ?";
        
        $params[] = (int)$limit;
        $params[] = (int)$offset;
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $documents = $stmt->fetchAll();
        
        echo json_encode([
            'success' => true,
            'data' => $documents,
            'pagination' => [
                'total' => $total,
                'page' => (int)$page,
                'limit' => (int)$limit,
                'pages' => ceil($total / $limit)
            ]
        ]);
        break;

    case 'get':
    case 'detail':
        $id = $_GET['id'] ?? 0;
        
        $stmt = $db->prepare("SELECT d.*, u.full_name as author_name, u.email as author_email, 
                              c.name as category_name, dt.name as doc_type_name 
                              FROM documents d 
                              LEFT JOIN users u ON d.author_id = u.id 
                              LEFT JOIN categories c ON d.category_id = c.id 
                              LEFT JOIN document_types dt ON d.document_type_id = dt.id 
                              WHERE d.id = ? AND d.is_deleted = 0");
        $stmt->execute([$id]);
        $document = $stmt->fetch();
        
        if ($document) {
            // Tăng view count
            $updateStmt = $db->prepare("UPDATE documents SET view_count = view_count + 1 WHERE id = ?");
            $updateStmt->execute([$id]);
            $document['full_path_url'] = '/doc_management/uploads/' . $document['file_path'];  
            echo json_encode(['success' => true, 'data' => $document]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Tài liệu không tồn tại']);
        }
        break;
        
    case 'my-documents':
        if (!$currentUser) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            break;
        }
        
        $page = $_GET['page'] ?? 1;
        $limit = $_GET['limit'] ?? 10;
        $offset = ($page - 1) * $limit;
        
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM documents WHERE author_id = ? AND is_deleted = 0");
        $stmt->execute([$currentUser['user_id']]);
        $total = $stmt->fetch()['total'];
        
        $stmt = $db->prepare("SELECT d.*, c.name as category_name, dt.name as doc_type_name 
                              FROM documents d 
                              LEFT JOIN categories c ON d.category_id = c.id 
                              LEFT JOIN document_types dt ON d.document_type_id = dt.id 
                              WHERE d.author_id = ? AND d.is_deleted = 0 
                              ORDER BY d.created_at DESC 
                              LIMIT ? OFFSET ?");
        $stmt->execute([$currentUser['user_id'], (int)$limit, (int)$offset]);
        $documents = $stmt->fetchAll();
        
        echo json_encode([
            'success' => true,
            'data' => $documents,
            'pagination' => [
                'total' => $total,
                'page' => (int)$page,
                'limit' => (int)$limit,
                'pages' => ceil($total / $limit)
            ]
        ]);
        break;
        
    case 'upload':
        if (!$currentUser) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            break;
        }
        
        if ($method !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            break;
        }
        
        if (!isset($_FILES['file'])) {
            echo json_encode(['success' => false, 'message' => 'Không có file được upload']);
            break;
        }
        
        $file = $_FILES['file'];
        $fileSize = $file['size'];
        $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if ($fileSize > MAX_FILE_SIZE) {
            echo json_encode(['success' => false, 'message' => 'File vượt quá 50MB']);
            break;
        }
        
        if (!in_array($fileExt, ALLOWED_EXTENSIONS)) {
            echo json_encode(['success' => false, 'message' => 'Chỉ chấp nhận file PDF, DOCX, ZIP']);
            break;
        }
        
        $newFileName = uniqid() . '_' . time() . '.' . $fileExt;
        $filePath = UPLOAD_PATH . $newFileName;
        
        if (move_uploaded_file($file['tmp_name'], $filePath)) {
            $stmt = $db->prepare("INSERT INTO documents (title, description, file_path, file_name, file_size, file_format, author_id, category_id, document_type_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $result = $stmt->execute([
                $_POST['title'],
                $_POST['description'],
                $newFileName,
                $file['name'],
                $fileSize,
                $fileExt,
                $currentUser['user_id'],
                $_POST['category_id'],
                $_POST['document_type_id']
            ]);
            
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Upload thành công', 'id' => $db->lastInsertId()]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Lưu database thất bại']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Upload file thất bại']);
        }
        break;
        
    case 'update':
        if (!$currentUser) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            break;
        }
        
        $id = $_POST['id'] ?? 0;
        
        $stmt = $db->prepare("SELECT author_id FROM documents WHERE id = ?");
        $stmt->execute([$id]);
        $doc = $stmt->fetch();
        
        if (!$doc || ($doc['author_id'] != $currentUser['user_id'] && $currentUser['role'] != 'admin')) {
            echo json_encode(['success' => false, 'message' => 'Không có quyền chỉnh sửa']);
            break;
        }
        
        if (isset($_FILES['file']) && $_FILES['file']['error'] === 0) {
            $file = $_FILES['file'];
            $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $newFileName = uniqid() . '_' . time() . '.' . $fileExt;
            $filePath = UPLOAD_PATH . $newFileName;
            
            if (move_uploaded_file($file['tmp_name'], $filePath)) {
                $stmt = $db->prepare("UPDATE documents SET title = ?, description = ?, category_id = ?, document_type_id = ?, file_path = ?, file_name = ?, file_size = ?, file_format = ? WHERE id = ?");
                $result = $stmt->execute([
                    $_POST['title'],
                    $_POST['description'],
                    $_POST['category_id'],
                    $_POST['document_type_id'],
                    $newFileName,
                    $file['name'],
                    $file['size'],
                    $fileExt,
                    $id
                ]);
            }
        } else {
            $stmt = $db->prepare("UPDATE documents SET title = ?, description = ?, category_id = ?, document_type_id = ? WHERE id = ?");
            $result = $stmt->execute([
                $_POST['title'],
                $_POST['description'],
                $_POST['category_id'],
                $_POST['document_type_id'],
                $id
            ]);
        }
        
        echo json_encode(['success' => $result, 'message' => $result ? 'Cập nhật thành công' : 'Cập nhật thất bại']);
        break;
        
    case 'delete':
        if (!$currentUser) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            break;
        }
        
        $id = $_GET['id'] ?? 0;
        
        $stmt = $db->prepare("SELECT author_id FROM documents WHERE id = ?");
        $stmt->execute([$id]);
        $doc = $stmt->fetch();
        
        if (!$doc || ($doc['author_id'] != $currentUser['user_id'] && $currentUser['role'] != 'admin')) {
            echo json_encode(['success' => false, 'message' => 'Không có quyền xóa']);
            break;
        }
        
        $stmt = $db->prepare("UPDATE documents SET is_deleted = 1 WHERE id = ?");
        $result = $stmt->execute([$id]);
        
        echo json_encode(['success' => $result, 'message' => $result ? 'Xóa thành công' : 'Xóa thất bại']);
        break;
        
    case 'download':
        $id = $_GET['id'] ?? 0;
        
        $stmt = $db->prepare("SELECT * FROM documents WHERE id = ? AND is_deleted = 0");
        $stmt->execute([$id]);
        $doc = $stmt->fetch();
        
        if (!$doc) {
            echo json_encode(['success' => false, 'message' => 'File không tồn tại']);
            break;
        }
        
        $updateStmt = $db->prepare("UPDATE documents SET download_count = download_count + 1 WHERE id = ?");
        $updateStmt->execute([$id]);
        
        $filePath = UPLOAD_PATH . $doc['file_path'];
        if (file_exists($filePath)) {
            echo json_encode(['success' => true, 'file_path' => 'uploads/' . $doc['file_path'], 'file_name' => $doc['file_name']]);
        } else {
            echo json_encode(['success' => false, 'message' => 'File không tồn tại trên server']);
        }
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
?>