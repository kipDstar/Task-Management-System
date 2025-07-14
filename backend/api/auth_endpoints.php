<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once 'auth.php';

$auth = getAuth();
$method = $_SERVER['REQUEST_METHOD'];
$path = $_GET['action'] ?? '';

try {
    switch ($path) {
        case 'login':
            if ($method === 'POST') {
                $data = json_decode(file_get_contents('php://input'), true);
                $result = $auth->login($data['username'] ?? '', $data['password'] ?? '');
                echo json_encode($result);
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
            }
            break;
            
        case 'logout':
            if ($method === 'POST') {
                $headers = getallheaders();
                $session_token = $headers['Authorization'] ?? $headers['authorization'] ?? '';
                $session_token = str_replace('Bearer ', '', $session_token);
                $result = $auth->logout($session_token);
                echo json_encode($result);
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
            }
            break;
            
        case 'register':
            if ($method === 'POST') {
                $data = json_decode(file_get_contents('php://input'), true);
                $result = $auth->register($data);
                echo json_encode($result);
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
            }
            break;
            
        case 'me':
            if ($method === 'GET') {
                $user = $auth->requireAuth();
                echo json_encode(['success' => true, 'user' => $user]);
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
            }
            break;
            
        case 'users':
            if ($method === 'GET') {
                $user = $auth->requireRole('admin');
                $users = $auth->getAllUsers();
                echo json_encode(['success' => true, 'users' => $users]);
            } elseif ($method === 'POST') {
                $user = $auth->requireRole('admin');
                $data = json_decode(file_get_contents('php://input'), true);
                $result = $auth->register($data);
                echo json_encode($result);
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
            }
            break;
            
        case 'users/update':
            if ($method === 'PUT') {
                $user = $auth->requireRole('admin');
                $data = json_decode(file_get_contents('php://input'), true);
                $userId = $data['id'] ?? null;
                if (!$userId) {
                    http_response_code(400);
                    echo json_encode(['error' => 'User ID required']);
                    break;
                }
                unset($data['id']);
                $result = $auth->updateUser($userId, $data);
                echo json_encode($result);
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
            }
            break;
            
        case 'users/delete':
            if ($method === 'DELETE') {
                $user = $auth->requireRole('admin');
                $data = json_decode(file_get_contents('php://input'), true);
                $userId = $data['id'] ?? null;
                if (!$userId) {
                    http_response_code(400);
                    echo json_encode(['error' => 'User ID required']);
                    break;
                }
                $result = $auth->deleteUser($userId);
                echo json_encode($result);
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
            }
            break;
            
        default:
            http_response_code(404);
            echo json_encode(['error' => 'Endpoint not found']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error: ' . $e->getMessage()]);
}
?> 