<?php
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once 'database.php';
require_once 'auth.php';

// Get session token from query parameter since EventSource doesn't support custom headers
$session_token = $_GET['token'] ?? null;

if (!$session_token) {
    http_response_code(401);
    echo "event: error\n";
    echo "data: " . json_encode(['error' => 'No session token provided']) . "\n\n";
    exit;
}

$auth = getAuth();
$user = $auth->getCurrentUser($session_token);

if (!$user) {
    http_response_code(401);
    echo "event: error\n";
    echo "data: " . json_encode(['error' => 'Invalid session token']) . "\n\n";
    exit;
}

$db = getDatabase();

// Function to send SSE data
function sendSSE($event, $data) {
    echo "event: $event\n";
    echo "data: " . json_encode($data) . "\n\n";
    ob_flush();
    flush();
}

// Send initial connection confirmation
sendSSE('connected', ['user_id' => $user['id'], 'timestamp' => time()]);

// Keep connection alive and check for updates
$lastCheck = time();
$userTasks = [];

while (true) {
    // Check for new tasks or updates every 5 seconds
    if (time() - $lastCheck >= 5) {
        $lastCheck = time();
        
        // Get user's tasks
        if ($user['role'] === 'admin') {
            $stmt = $db->query("SELECT COUNT(*) as count FROM tasks WHERE updated_at > datetime('now', '-10 seconds')");
        } else {
            $stmt = $db->query("SELECT COUNT(*) as count FROM tasks WHERE assigned_to = ? AND updated_at > datetime('now', '-10 seconds')", [$user['id']]);
        }
        
        $recentUpdates = $stmt->fetch();
        
        if ($recentUpdates['count'] > 0) {
            // Send task update notification
            sendSSE('task_update', [
                'message' => 'Tasks have been updated',
                'timestamp' => time()
            ]);
        }
        
        // Check for new users (admin only)
        if ($user['role'] === 'admin') {
            $stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE created_at > datetime('now', '-10 seconds')");
            $newUsers = $stmt->fetch();
            
            if ($newUsers['count'] > 0) {
                sendSSE('user_update', [
                    'message' => 'New users have been added',
                    'timestamp' => time()
                ]);
            }
        }
        
        // Send heartbeat to keep connection alive
        sendSSE('heartbeat', ['timestamp' => time()]);
    }
    
    // Check if client is still connected
    if (connection_aborted()) {
        break;
    }
    
    usleep(1000000); // Sleep for 1 second
}
?> 