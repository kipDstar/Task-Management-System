<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once 'database.php';
require_once 'auth.php';

class AttachmentsAPI {
    private $db;
    private $auth;
    private $uploadDir;
    
    public function __construct() {
        $this->db = getDatabase();
        $this->auth = getAuth();
        $this->uploadDir = __DIR__ . '/../uploads/';
        
        // Create uploads directory if it doesn't exist
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }
    }
    
    public function handleRequest() {
        $method = $_SERVER['REQUEST_METHOD'];
        
        try {
            switch ($method) {
                case 'GET':
                    $this->getAttachments();
                    break;
                case 'POST':
                    $this->uploadAttachment();
                    break;
                case 'DELETE':
                    $this->deleteAttachment();
                    break;
                default:
                    $this->sendError('Method not allowed', 405);
            }
        } catch (Exception $e) {
            $this->sendError($e->getMessage(), 500);
        }
    }
    
    private function getAttachments() {
        $user = $this->auth->requireAuth();
        $taskId = $_GET['task_id'] ?? null;
        
        if (!$taskId) {
            $this->sendError('Task ID required', 400);
            return;
        }
        
        // Check if user has access to this task
        if (!$this->canAccessTask($user, $taskId)) {
            $this->sendError('Access denied', 403);
            return;
        }
        
        $stmt = $this->db->query(
            "SELECT id, filename, original_name, file_size, mime_type, uploaded_at 
             FROM attachments WHERE task_id = ? ORDER BY uploaded_at DESC",
            [$taskId]
        );
        
        $attachments = $stmt->fetchAll();
        $this->sendResponse(['attachments' => $attachments]);
    }
    
    private function uploadAttachment() {
        $user = $this->auth->requireAuth();
        
        if (!isset($_POST['task_id']) || !isset($_FILES['file'])) {
            $this->sendError('Task ID and file required', 400);
            return;
        }
        
        $taskId = $_POST['task_id'];
        $file = $_FILES['file'];
        
        // Check if user has access to this task
        if (!$this->canAccessTask($user, $taskId)) {
            $this->sendError('Access denied', 403);
            return;
        }
        
        // Validate file
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $this->sendError('File upload failed', 400);
            return;
        }
        
        // Check file size (max 10MB)
        if ($file['size'] > 10 * 1024 * 1024) {
            $this->sendError('File too large (max 10MB)', 400);
            return;
        }
        
        // Check file type
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf', 'text/plain', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        if (!in_array($file['type'], $allowedTypes)) {
            $this->sendError('File type not allowed', 400);
            return;
        }
        
        // Generate unique filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid() . '_' . time() . '.' . $extension;
        $filepath = $this->uploadDir . $filename;
        
        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            $this->sendError('Failed to save file', 500);
            return;
        }
        
        // Save to database
        $stmt = $this->db->query(
            "INSERT INTO attachments (task_id, filename, original_name, file_size, mime_type, uploaded_by) 
             VALUES (?, ?, ?, ?, ?, ?)",
            [$taskId, $filename, $file['name'], $file['size'], $file['type'], $user['id']]
        );
        
        $attachmentId = $this->db->lastInsertId();
        
        $this->sendResponse([
            'message' => 'File uploaded successfully',
            'attachment_id' => $attachmentId,
            'filename' => $filename,
            'original_name' => $file['name']
        ]);
    }
    
    private function deleteAttachment() {
        $user = $this->auth->requireAuth();
        $attachmentId = $_GET['id'] ?? null;
        
        if (!$attachmentId) {
            $this->sendError('Attachment ID required', 400);
            return;
        }
        
        // Get attachment info
        $stmt = $this->db->query(
            "SELECT a.*, t.assigned_to, t.created_by FROM attachments a 
             JOIN tasks t ON a.task_id = t.id WHERE a.id = ?",
            [$attachmentId]
        );
        
        $attachment = $stmt->fetch();
        if (!$attachment) {
            $this->sendError('Attachment not found', 404);
            return;
        }
        
        // Check permissions
        if ($user['role'] !== 'admin' && 
            $attachment['uploaded_by'] !== $user['id'] && 
            $attachment['assigned_to'] !== $user['id'] && 
            $attachment['created_by'] !== $user['id']) {
            $this->sendError('Access denied', 403);
            return;
        }
        
        // Delete file
        $filepath = $this->uploadDir . $attachment['filename'];
        if (file_exists($filepath)) {
            unlink($filepath);
        }
        
        // Delete from database
        $this->db->query("DELETE FROM attachments WHERE id = ?", [$attachmentId]);
        
        $this->sendResponse(['message' => 'Attachment deleted successfully']);
    }
    
    private function canAccessTask($user, $taskId) {
        if ($user['role'] === 'admin') {
            return true;
        }
        
        $stmt = $this->db->query(
            "SELECT id FROM tasks WHERE id = ? AND (assigned_to = ? OR created_by = ?)",
            [$taskId, $user['id'], $user['id']]
        );
        
        return $stmt->fetch() !== false;
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
$api = new AttachmentsAPI();
$api->handleRequest();
?> 