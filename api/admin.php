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

if (!$currentUser || $currentUser['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$action = $_GET['action'] ?? '';
$input = json_decode(file_get_contents('php://input'), true);

switch ($action) {
    case 'users':
        $page = $_GET['page'] ?? 1;
        $limit = $_GET['limit'] ?? 20;
        $offset = ($page - 1) * $limit;
        $role = $_GET['role'] ?? '';
        $status = $_GET['status'] ?? '';
        
        $where = ['1=1'];
        $params = [];
        
        if ($role) {
            $where[] = 'role = ?';
            $params[] = $role;
        }
        
        if ($status === 'active') {
            $where[] = 'is_blocked = 0';
        } elseif ($status === 'blocked') {
            $where[] = 'is_blocked = 1';
        }
        
        $whereClause = implode(' AND ', $where);
        
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM users WHERE $whereClause");
        $stmt->execute($params);
        $total = $stmt->fetch()['total'];
        
        $sql = "SELECT id, email, full_name, student_code, role, department, is_blocked, created_at 
                FROM users 
                WHERE $whereClause 
                ORDER BY created_at DESC 
                LIMIT ? OFFSET ?";
        $params[] = (int)$limit;
        $params[] = (int)$offset;
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $users = $stmt->fetchAll();
        
        echo json_encode([
            'success' => true,
            'data' => $users,
            'pagination' => [
                'total' => $total,
                'page' => (int)$page,
                'limit' => (int)$limit,
                'pages' => ceil($total / $limit)
            ]
        ]);
        break;
        
    case 'block-user':
        $userId = $input['user_id'];
        $stmt = $db->prepare("UPDATE users SET is_blocked = 1 WHERE id = ?");
        $result = $stmt->execute([$userId]);
        
        if ($result) {
            $logStmt = $db->prepare("INSERT INTO admin_logs (admin_id, action, target_type, target_id, reason) VALUES (?, 'block_user', 'user', ?, ?)");
            $logStmt->execute([$currentUser['user_id'], $userId, $input['reason'] ?? 'Không có lý do']);
        }
        
        echo json_encode(['success' => $result, 'message' => $result ? 'Khóa tài khoản thành công' : 'Khóa thất bại']);
        break;
        
    case 'unblock-user':
        $userId = $input['user_id'];
        $stmt = $db->prepare("UPDATE users SET is_blocked = 0 WHERE id = ?");
        $result = $stmt->execute([$userId]);
        
        if ($result) {
            $logStmt = $db->prepare("INSERT INTO admin_logs (admin_id, action, target_type, target_id) VALUES (?, 'unblock_user', 'user', ?)");
            $logStmt->execute([$currentUser['user_id'], $userId]);
        }
        
        echo json_encode(['success' => $result, 'message' => $result ? 'Mở khóa tài khoản thành công' : 'Mở khóa thất bại']);
        break;
        
    case 'statistics':
        // Tổng số tài liệu
        $stmt = $db->query("SELECT COUNT(*) as total FROM documents WHERE is_deleted = 0");
        $totalDocs = $stmt->fetch()['total'];
        
        // Tổng số users
        $stmt = $db->query("SELECT COUNT(*) as total FROM users WHERE role != 'admin'");
        $totalUsers = $stmt->fetch()['total'];
        
        // Tổng lượt tải
        $stmt = $db->query("SELECT SUM(download_count) as total FROM documents WHERE is_deleted = 0");
        $totalDownloads = $stmt->fetch()['total'] ?? 0;
        
        // Tài liệu mới theo tháng (12 tháng)
        $stmt = $db->query("SELECT 
                DATE_FORMAT(created_at, '%Y-%m') as month,
                COUNT(*) as count
            FROM documents 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH) AND is_deleted = 0
            GROUP BY month
            ORDER BY month");
        $monthlyDocs = $stmt->fetchAll();
        
        // Tài liệu theo chuyên ngành
        $stmt = $db->query("SELECT c.name, COUNT(d.id) as count 
            FROM categories c 
            LEFT JOIN documents d ON c.id = d.category_id AND d.is_deleted = 0
            GROUP BY c.id");
        $docsByCategory = $stmt->fetchAll();
        
        // Tài liệu theo loại
        $stmt = $db->query("SELECT dt.name, COUNT(d.id) as count 
            FROM document_types dt 
            LEFT JOIN documents d ON dt.id = d.document_type_id AND d.is_deleted = 0
            GROUP BY dt.id");
        $docsByType = $stmt->fetchAll();
        
        // Top 10 tài liệu có lượt tải cao nhất
        $stmt = $db->query("SELECT id, title, download_count, view_count 
            FROM documents 
            WHERE is_deleted = 0 
            ORDER BY download_count DESC 
            LIMIT 10");
        $topDocuments = $stmt->fetchAll();
        
        echo json_encode([
            'success' => true,
            'data' => [
                'total_documents' => $totalDocs,
                'total_users' => $totalUsers,
                'total_downloads' => $totalDownloads,
                'monthly_documents' => $monthlyDocs,
                'docs_by_category' => $docsByCategory,
                'docs_by_type' => $docsByType,
                'top_documents' => $topDocuments
            ]
        ]);
        break;
        
    case 'all-documents':
        $page = $_GET['page'] ?? 1;
        $limit = $_GET['limit'] ?? 20;
        $offset = ($page - 1) * $limit;
        $category = $_GET['category'] ?? '';
        $author = $_GET['author'] ?? '';
        
        $where = ['1=1'];
        $params = [];
        
        if ($category) {
            $where[] = 'd.category_id = ?';
            $params[] = $category;
        }
        
        if ($author) {
            $where[] = 'd.author_id = ?';
            $params[] = $author;
        }
        
        $whereClause = implode(' AND ', $where);
        
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM documents d WHERE $whereClause");
        $stmt->execute($params);
        $total = $stmt->fetch()['total'];
        
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
        
    case 'delete-document':
        // Kiểm tra quyền hạn (chỉ Admin mới được xóa)
        if (!$currentUser || $currentUser['role'] !== 'admin') {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            break;
        }

        $docId = $input['doc_id'] ?? 0;
        $reason = $input['reason'] ?? 'Admin xóa dữ liệu';
        
        // 1. Lấy thông tin file để xóa file vật lý trước
        $stmt = $db->prepare("SELECT file_path FROM documents WHERE id = ?");
        $stmt->execute([$docId]);
        $doc = $stmt->fetch();

        if ($doc) {
            // Đường dẫn đầy đủ tới file
            $filePath = UPLOAD_PATH . $doc['file_path'];
            
            // Kiểm tra file có tồn tại không rồi mới xóa (tránh lỗi code dừng đột ngột)
            if (file_exists($filePath)) {
                unlink($filePath); 
            }
            
            // 2. Xóa VĨNH VIỄN trong Database (Dùng DELETE thay vì UPDATE is_deleted)
            $stmt = $db->prepare("DELETE FROM documents WHERE id = ?");
            $result = $stmt->execute([$docId]);
            
            if ($result) {
                // Ghi log hành động
                $logStmt = $db->prepare("INSERT INTO admin_logs (admin_id, action, target_type, target_id, reason) VALUES (?, 'delete_document', 'document', ?, ?)");
                $logStmt->execute([$currentUser['user_id'], $docId, $reason]);
                
                echo json_encode(['success' => true, 'message' => '']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Lỗi khi xóa dữ liệu trong DB']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Tài liệu không tồn tại']);
        }
        break;
        
    case 'logs':
        $page = $_GET['page'] ?? 1;
        $limit = $_GET['limit'] ?? 50;
        $offset = ($page - 1) * $limit;
        
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM admin_logs");
        $stmt->execute();
        $total = $stmt->fetch()['total'];
        
        $stmt = $db->prepare("SELECT l.*, u.full_name as admin_name 
                              FROM admin_logs l 
                              LEFT JOIN users u ON l.admin_id = u.id 
                              ORDER BY l.created_at DESC 
                              LIMIT ? OFFSET ?");
        $stmt->execute([(int)$limit, (int)$offset]);
        $logs = $stmt->fetchAll();
        
        echo json_encode([
            'success' => true,
            'data' => $logs,
            'pagination' => [
                'total' => $total,
                'page' => (int)$page,
                'limit' => (int)$limit,
                'pages' => ceil($total / $limit)
            ]
        ]);
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
?>