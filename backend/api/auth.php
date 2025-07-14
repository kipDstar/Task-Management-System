<?php
require_once 'database.php';

class Auth {
    private $db;
    
    public function __construct() {
        $this->db = getDatabase();
    }
    
    public function login($username, $password) {
        try {
            $stmt = $this->db->query(
                "SELECT id, username, email, password_hash, role, first_name, last_name, is_active 
                 FROM users WHERE username = ? AND is_active = 1",
                [$username]
            );
            
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password_hash'])) {
                return $this->createSession($user);
            }
            
            return ['success' => false, 'message' => 'Invalid username or password'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Login failed: ' . $e->getMessage()];
        }
    }
    
    public function register($userData) {
        try {
            // Validate required fields
            if (empty($userData['username']) || empty($userData['email']) || empty($userData['password'])) {
                return ['success' => false, 'message' => 'Username, email, and password are required'];
            }
            
            // Check if username or email already exists
            $stmt = $this->db->query(
                "SELECT id FROM users WHERE username = ? OR email = ?",
                [$userData['username'], $userData['email']]
            );
            
            if ($stmt->fetch()) {
                return ['success' => false, 'message' => 'Username or email already exists'];
            }
            
            // Hash password
            $password_hash = password_hash($userData['password'], PASSWORD_DEFAULT);
            
            // Insert new user
            $stmt = $this->db->query(
                "INSERT INTO users (username, email, password_hash, role, first_name, last_name) 
                 VALUES (?, ?, ?, ?, ?, ?)",
                [
                    $userData['username'],
                    $userData['email'],
                    $password_hash,
                    $userData['role'] ?? 'user',
                    $userData['first_name'] ?? '',
                    $userData['last_name'] ?? ''
                ]
            );
            
            return ['success' => true, 'message' => 'User registered successfully'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Registration failed: ' . $e->getMessage()];
        }
    }
    
    public function logout($session_token) {
        try {
            $this->db->query(
                "DELETE FROM sessions WHERE session_token = ?",
                [$session_token]
            );
            return ['success' => true, 'message' => 'Logged out successfully'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Logout failed: ' . $e->getMessage()];
        }
    }
    
    public function getCurrentUser($session_token) {
        try {
            $stmt = $this->db->query(
                "SELECT u.id, u.username, u.email, u.role, u.first_name, u.last_name, u.is_active
                 FROM users u
                 JOIN sessions s ON u.id = s.user_id
                 WHERE s.session_token = ? AND s.expires_at > datetime('now') AND u.is_active = 1",
                [$session_token]
            );
            
            return $stmt->fetch();
        } catch (Exception $e) {
            return null;
        }
    }
    
    public function createSession($user) {
        try {
            $session_token = bin2hex(random_bytes(32));
            $expires_at = date('Y-m-d H:i:s', strtotime('+24 hours'));
            
            $this->db->query(
                "INSERT INTO sessions (user_id, session_token, expires_at) VALUES (?, ?, ?)",
                [$user['id'], $session_token, $expires_at]
            );
            
            return [
                'success' => true,
                'session_token' => $session_token,
                'user' => [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'email' => $user['email'],
                    'role' => $user['role'],
                    'first_name' => $user['first_name'],
                    'last_name' => $user['last_name']
                ]
            ];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Session creation failed: ' . $e->getMessage()];
        }
    }
    
    public function requireAuth() {
        $headers = getallheaders();
        $session_token = $headers['Authorization'] ?? $headers['authorization'] ?? null;
        
        if (!$session_token) {
            http_response_code(401);
            echo json_encode(['error' => 'Authentication required']);
            exit;
        }
        
        // Remove 'Bearer ' prefix if present
        $session_token = str_replace('Bearer ', '', $session_token);
        
        $user = $this->getCurrentUser($session_token);
        if (!$user) {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid or expired session']);
            exit;
        }
        
        return $user;
    }
    
    public function requireRole($required_role) {
        $user = $this->requireAuth();
        
        if ($user['role'] !== $required_role && $user['role'] !== 'admin') {
            http_response_code(403);
            echo json_encode(['error' => 'Insufficient permissions']);
            exit;
        }
        
        return $user;
    }
    
    public function getAllUsers() {
        try {
            $stmt = $this->db->query(
                "SELECT id, username, email, role, first_name, last_name, is_active, created_at 
                 FROM users ORDER BY created_at DESC"
            );
            return $stmt->fetchAll();
        } catch (Exception $e) {
            return [];
        }
    }
    
    public function updateUser($userId, $userData) {
        try {
            $updates = [];
            $params = [];
            
            if (isset($userData['first_name'])) {
                $updates[] = "first_name = ?";
                $params[] = $userData['first_name'];
            }
            
            if (isset($userData['last_name'])) {
                $updates[] = "last_name = ?";
                $params[] = $userData['last_name'];
            }
            
            if (isset($userData['email'])) {
                $updates[] = "email = ?";
                $params[] = $userData['email'];
            }
            
            if (isset($userData['role'])) {
                $updates[] = "role = ?";
                $params[] = $userData['role'];
            }
            
            if (isset($userData['is_active'])) {
                $updates[] = "is_active = ?";
                $params[] = $userData['is_active'];
            }
            
            if (isset($userData['password']) && !empty($userData['password'])) {
                $updates[] = "password_hash = ?";
                $params[] = password_hash($userData['password'], PASSWORD_DEFAULT);
            }
            
            if (empty($updates)) {
                return ['success' => false, 'message' => 'No fields to update'];
            }
            
            $updates[] = "updated_at = datetime('now')";
            $params[] = $userId;
            
            $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?";
            $this->db->query($sql, $params);
            
            return ['success' => true, 'message' => 'User updated successfully'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Update failed: ' . $e->getMessage()];
        }
    }
    
    public function deleteUser($userId) {
        try {
            $this->db->query("DELETE FROM users WHERE id = ?", [$userId]);
            return ['success' => true, 'message' => 'User deleted successfully'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Delete failed: ' . $e->getMessage()];
        }
    }
}

// Global auth instance
function getAuth() {
    static $auth = null;
    if ($auth === null) {
        $auth = new Auth();
    }
    return $auth;
}

?> 