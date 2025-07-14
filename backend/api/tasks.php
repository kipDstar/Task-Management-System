<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    require_once 'database.php';
require_once 'auth.php';
require_once 'email_service.php';
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server configuration error: ' . $e->getMessage()
    ]);
    exit();
}

class TasksAPI {
    private $db;
    private $auth;
    
    public function __construct() {
        try {
            $this->db = getDatabase();
            $this->auth = getAuth();
        } catch (Exception $e) {
            throw new Exception('Failed to initialize API: ' . $e->getMessage());
        }
    }
    
    public function handleRequest() {
        $method = $_SERVER['REQUEST_METHOD'];
        
        try {
            switch ($method) {
                case 'GET':
                    $this->getTasks();
                    break;
                case 'POST':
                    $this->createTask();
                    break;
                case 'PUT':
                    $this->updateTask();
                    break;
                case 'DELETE':
                    $this->deleteTask();
                    break;
                default:
                    $this->sendError('Method not allowed', 405);
            }
        } catch (Exception $e) {
            $this->sendError($e->getMessage(), 500);
        }
    }
    
    private function getTasks() {
        $user = $this->auth->requireAuth();
        
        // Build query based on user role
        if ($user['role'] === 'admin') {
            // Admin can see all tasks
            $sql = "
                SELECT 
                    t.*,
                    p.name as project_name,
                    p.color as project_color,
                    u.username as assigned_to_name,
                    c.username as created_by_name
                FROM tasks t
                LEFT JOIN projects p ON t.project_id = p.id
                LEFT JOIN users u ON t.assigned_to = u.id
                LEFT JOIN users c ON t.created_by = c.id
                ORDER BY t.created_at DESC
            ";
            $params = [];
        } else {
            // Regular users can only see tasks assigned to them
            $sql = "
                SELECT 
                    t.*,
                    p.name as project_name,
                    p.color as project_color,
                    u.username as assigned_to_name,
                    c.username as created_by_name
                FROM tasks t
                LEFT JOIN projects p ON t.project_id = p.id
                LEFT JOIN users u ON t.assigned_to = u.id
                LEFT JOIN users c ON t.created_by = c.id
                WHERE t.assigned_to = ?
                ORDER BY t.created_at DESC
            ";
            $params = [$user['id']];
        }
        
        $stmt = $this->db->query($sql, $params);
        $tasks = $stmt->fetchAll();
        
        $this->sendResponse(['tasks' => $tasks]);
    }
    
    private function createTask() {
        $user = $this->auth->requireAuth();
        $input = $this->getJsonInput();
        
        // Validate required fields
        if (empty($input['taskTitle'])) {
            $this->sendError('Task title is required', 400);
            return;
        }
        
        // Only admins can assign tasks to other users
        $assigned_to = null;
        if ($user['role'] === 'admin' && !empty($input['assignedTo'])) {
            $assigned_to = (int)$input['assignedTo'];
        } else {
            // Regular users can only assign tasks to themselves
            $assigned_to = $user['id'];
        }
        
        // Prepare data
        $data = [
            'title' => trim($input['taskTitle']),
            'description' => isset($input['taskDescription']) ? trim($input['taskDescription']) : null,
            'priority' => isset($input['taskPriority']) ? $input['taskPriority'] : 'medium',
            'due_date' => !empty($input['taskDueDate']) ? $input['taskDueDate'] : null,
            'due_time' => !empty($input['taskDueTime']) ? $input['taskDueTime'] : null,
            'project_id' => !empty($input['taskProject']) ? (int)$input['taskProject'] : null,
            'tags' => isset($input['taskTags']) ? trim($input['taskTags']) : null,
            'status' => 'pending',
            'assigned_to' => $assigned_to,
            'created_by' => $user['id']
        ];
        
        // Validate priority
        if (!in_array($data['priority'], ['low', 'medium', 'high'])) {
            $data['priority'] = 'medium';
        }
        
        $sql = "
            INSERT INTO tasks (title, description, priority, due_date, due_time, project_id, tags, status, assigned_to, created_by)
            VALUES (:title, :description, :priority, :due_date, :due_time, :project_id, :tags, :status, :assigned_to, :created_by)
        ";
        
        $this->db->query($sql, $data);
        $taskId = $this->db->lastInsertId();
        
        // Send email notification if task is assigned to someone else
        if ($assigned_to && $assigned_to !== $user['id']) {
            $this->sendTaskAssignmentEmail($taskId, $assigned_to);
        }
        
        $this->sendResponse([
            'message' => 'Task created successfully',
            'task_id' => $taskId
        ]);
    }
    
    private function updateTask() {
        $user = $this->auth->requireAuth();
        $input = $this->getJsonInput();
        
        if (empty($input['taskId'])) {
            $this->sendError('Task ID is required', 400);
            return;
        }
        
        $taskId = (int)$input['taskId'];
        
        // Check if user has permission to update this task
        if (!$this->canUpdateTask($user, $taskId)) {
            $this->sendError('You do not have permission to update this task', 403);
            return;
        }
        
        // Check if it's a status-only update
        if (isset($input['status']) && count($input) === 2) { // taskId + status
            $this->updateTaskStatus($taskId, $input['status']);
            return;
        }
        
        // Full task update
        if (empty($input['taskTitle'])) {
            $this->sendError('Task title is required', 400);
            return;
        }
        
        $data = [
            'id' => $taskId,
            'title' => trim($input['taskTitle']),
            'description' => isset($input['taskDescription']) ? trim($input['taskDescription']) : null,
            'priority' => isset($input['taskPriority']) ? $input['taskPriority'] : 'medium',
            'due_date' => !empty($input['taskDueDate']) ? $input['taskDueDate'] : null,
            'due_time' => !empty($input['taskDueTime']) ? $input['taskDueTime'] : null,
            'project_id' => !empty($input['taskProject']) ? (int)$input['taskProject'] : null,
            'tags' => isset($input['taskTags']) ? trim($input['taskTags']) : null
        ];
        
        // Only admins can change assignment
        if ($user['role'] === 'admin' && !empty($input['assignedTo'])) {
            $data['assigned_to'] = (int)$input['assignedTo'];
        }
        
        // Validate priority
        if (!in_array($data['priority'], ['low', 'medium', 'high'])) {
            $data['priority'] = 'medium';
        }
        
        $sql = "
            UPDATE tasks 
            SET title = :title, 
                description = :description, 
                priority = :priority, 
                due_date = :due_date, 
                due_time = :due_time, 
                project_id = :project_id, 
                tags = :tags,
                updated_at = CURRENT_TIMESTAMP
        ";
        
        if (isset($data['assigned_to'])) {
            $sql .= ", assigned_to = :assigned_to";
        }
        
        $sql .= " WHERE id = :id";
        
        $stmt = $this->db->query($sql, $data);
        
        if ($stmt->rowCount() === 0) {
            $this->sendError('Task not found', 404);
            return;
        }
        
        $this->sendResponse(['message' => 'Task updated successfully']);
    }
    
    private function updateTaskStatus($taskId, $status) {
        $user = $this->auth->requireAuth();
        
        // Check if user has permission to update this task
        if (!$this->canUpdateTask($user, $taskId)) {
            $this->sendError('You do not have permission to update this task', 403);
            return;
        }
        
        // Validate status
        if (!in_array($status, ['pending', 'in_progress', 'completed'])) {
            $this->sendError('Invalid status', 400);
            return;
        }
        
        $sql = "UPDATE tasks SET status = :status, updated_at = CURRENT_TIMESTAMP WHERE id = :id";
        $stmt = $this->db->query($sql, ['id' => $taskId, 'status' => $status]);
        
        if ($stmt->rowCount() === 0) {
            $this->sendError('Task not found', 404);
            return;
        }
        
        $this->sendResponse(['message' => 'Task status updated successfully']);
    }
    
    private function deleteTask() {
        $user = $this->auth->requireAuth();
        $taskId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        
        if ($taskId <= 0) {
            $this->sendError('Valid task ID is required', 400);
            return;
        }
        
        // Only admins can delete tasks
        if ($user['role'] !== 'admin') {
            $this->sendError('You do not have permission to delete tasks', 403);
            return;
        }
        
        $sql = "DELETE FROM tasks WHERE id = :id";
        $stmt = $this->db->query($sql, ['id' => $taskId]);
        
        if ($stmt->rowCount() === 0) {
            $this->sendError('Task not found', 404);
            return;
        }
        
        $this->sendResponse(['message' => 'Task deleted successfully']);
    }
    
    private function canUpdateTask($user, $taskId) {
        if ($user['role'] === 'admin') {
            return true;
        }
        
        // Check if task is assigned to the user
        $stmt = $this->db->query(
            "SELECT id FROM tasks WHERE id = ? AND assigned_to = ?",
            [$taskId, $user['id']]
        );
        
        return $stmt->fetch() !== false;
    }
    
    private function sendTaskAssignmentEmail($taskId, $userId) {
        // Get task and user details with project info
        $stmt = $this->db->query(
            "SELECT t.*, p.name as project_name, u.email, u.first_name, u.last_name 
             FROM tasks t 
             JOIN users u ON t.assigned_to = u.id 
             LEFT JOIN projects p ON t.project_id = p.id
             WHERE t.id = ?",
            [$taskId]
        );
        
        $task = $stmt->fetch();
        if (!$task) {
            return;
        }
        
        $user = [
            'email' => $task['email'],
            'first_name' => $task['first_name'],
            'last_name' => $task['last_name']
        ];
        
        // Use the email service to send the notification
        $emailService = getEmailService();
        $emailService->sendTaskAssignment($task, $user);
    }
    
    private function getJsonInput() {
        $input = json_decode(file_get_contents('php://input'), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->sendError('Invalid JSON input', 400);
        }
        return $input ?: [];
    }
    
    private function sendResponse($data, $statusCode = 200) {
        http_response_code($statusCode);
        echo json_encode([
            'success' => true,
            'data' => $data
        ], JSON_PRETTY_PRINT);
        exit();
    }
    
    private function sendError($message, $statusCode = 400) {
        http_response_code($statusCode);
        echo json_encode([
            'success' => false,
            'message' => $message
        ], JSON_PRETTY_PRINT);
        exit();
    }
}

// Handle the request
try {
    $api = new TasksAPI();
    $api->handleRequest();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
    exit();
}

?>