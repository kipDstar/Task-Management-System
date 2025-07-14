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

require_once 'database.php';

class ProjectsAPI {
    private $db;
    
    public function __construct() {
        $this->db = getDatabase();
    }
    
    public function handleRequest() {
        $method = $_SERVER['REQUEST_METHOD'];
        
        try {
            switch ($method) {
                case 'GET':
                    $this->getProjects();
                    break;
                case 'POST':
                    $this->createProject();
                    break;
                case 'PUT':
                    $this->updateProject();
                    break;
                case 'DELETE':
                    $this->deleteProject();
                    break;
                default:
                    $this->sendError('Method not allowed', 405);
            }
        } catch (Exception $e) {
            $this->sendError($e->getMessage(), 500);
        }
    }
    
    private function getProjects() {
        $sql = "
            SELECT 
                p.*,
                COUNT(t.id) as task_count,
                COUNT(CASE WHEN t.status = 'completed' THEN 1 END) as completed_tasks
            FROM projects p
            LEFT JOIN tasks t ON p.id = t.project_id
            GROUP BY p.id
            ORDER BY p.created_at DESC
        ";
        
        $stmt = $this->db->query($sql);
        $projects = $stmt->fetchAll();
        
        $this->sendResponse(['projects' => $projects]);
    }
    
    private function createProject() {
        $input = $this->getJsonInput();
        
        // Validate required fields
        if (empty($input['projectName'])) {
            $this->sendError('Project name is required', 400);
            return;
        }
        
        // Check if project name already exists
        $checkSql = "SELECT COUNT(*) FROM projects WHERE name = :name";
        $count = $this->db->query($checkSql, ['name' => trim($input['projectName'])])->fetchColumn();
        
        if ($count > 0) {
            $this->sendError('Project with this name already exists', 400);
            return;
        }
        
        // Prepare data
        $data = [
            'name' => trim($input['projectName']),
            'color' => isset($input['projectColor']) ? $input['projectColor'] : '#667eea'
        ];
        
        // Validate color format (basic validation)
        if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $data['color'])) {
            $data['color'] = '#667eea';
        }
        
        $sql = "INSERT INTO projects (name, color) VALUES (:name, :color)";
        $this->db->query($sql, $data);
        $projectId = $this->db->lastInsertId();
        
        $this->sendResponse([
            'message' => 'Project created successfully',
            'project_id' => $projectId
        ]);
    }
    
    private function updateProject() {
        $input = $this->getJsonInput();
        
        if (empty($input['projectId'])) {
            $this->sendError('Project ID is required', 400);
            return;
        }
        
        if (empty($input['projectName'])) {
            $this->sendError('Project name is required', 400);
            return;
        }
        
        $projectId = (int)$input['projectId'];
        
        // Check if project name already exists (excluding current project)
        $checkSql = "SELECT COUNT(*) FROM projects WHERE name = :name AND id != :id";
        $count = $this->db->query($checkSql, [
            'name' => trim($input['projectName']),
            'id' => $projectId
        ])->fetchColumn();
        
        if ($count > 0) {
            $this->sendError('Project with this name already exists', 400);
            return;
        }
        
        $data = [
            'id' => $projectId,
            'name' => trim($input['projectName']),
            'color' => isset($input['projectColor']) ? $input['projectColor'] : '#667eea'
        ];
        
        // Validate color format
        if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $data['color'])) {
            $data['color'] = '#667eea';
        }
        
        $sql = "UPDATE projects SET name = :name, color = :color WHERE id = :id";
        $stmt = $this->db->query($sql, $data);
        
        if ($stmt->rowCount() === 0) {
            $this->sendError('Project not found', 404);
            return;
        }
        
        $this->sendResponse(['message' => 'Project updated successfully']);
    }
    
    private function deleteProject() {
        $projectId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        
        if ($projectId <= 0) {
            $this->sendError('Valid project ID is required', 400);
            return;
        }
        
        // Check if project has tasks
        $taskCountSql = "SELECT COUNT(*) FROM tasks WHERE project_id = :id";
        $taskCount = $this->db->query($taskCountSql, ['id' => $projectId])->fetchColumn();
        
        if ($taskCount > 0) {
            // Update tasks to remove project association instead of preventing deletion
            $updateTasksSql = "UPDATE tasks SET project_id = NULL WHERE project_id = :id";
            $this->db->query($updateTasksSql, ['id' => $projectId]);
        }
        
        $sql = "DELETE FROM projects WHERE id = :id";
        $stmt = $this->db->query($sql, ['id' => $projectId]);
        
        if ($stmt->rowCount() === 0) {
            $this->sendError('Project not found', 404);
            return;
        }
        
        $message = $taskCount > 0 
            ? "Project deleted successfully. {$taskCount} tasks were moved to 'No Project'."
            : 'Project deleted successfully';
        
        $this->sendResponse(['message' => $message]);
    }
    
    private function getProjectStats() {
        $projectId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        
        if ($projectId <= 0) {
            $this->sendError('Valid project ID is required', 400);
            return;
        }
        
        $sql = "
            SELECT 
                p.*,
                COUNT(t.id) as total_tasks,
                COUNT(CASE WHEN t.status = 'completed' THEN 1 END) as completed_tasks,
                COUNT(CASE WHEN t.status = 'pending' THEN 1 END) as pending_tasks,
                COUNT(CASE WHEN t.status = 'pending' AND t.due_date < DATE('now') THEN 1 END) as overdue_tasks
            FROM projects p
            LEFT JOIN tasks t ON p.id = t.project_id
            WHERE p.id = :id
            GROUP BY p.id
        ";
        
        $stmt = $this->db->query($sql, ['id' => $projectId]);
        $project = $stmt->fetch();
        
        if (!$project) {
            $this->sendError('Project not found', 404);
            return;
        }
        
        $this->sendResponse(['project' => $project]);
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
$api = new ProjectsAPI();
$api->handleRequest();

?>