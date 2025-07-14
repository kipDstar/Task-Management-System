<?php

class Database {
    private $connection;
    private $db_file;
    
    public function __construct() {
        $this->db_file = __DIR__ . '/../data/tasks.db';
        $this->ensureDataDirectory();
        $this->connect();
        $this->createTables();
    }
    
    private function ensureDataDirectory() {
        $data_dir = dirname($this->db_file);
        if (!is_dir($data_dir)) {
            mkdir($data_dir, 0755, true);
        }
    }
    
    private function connect() {
        try {
            $this->connection = new PDO('sqlite:' . $this->db_file);
            $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new Exception('Database connection failed: ' . $e->getMessage());
        }
    }
    
    private function createTables() {
        $sql_users = "
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT UNIQUE NOT NULL,
                email TEXT UNIQUE NOT NULL,
                password_hash TEXT NOT NULL,
                role TEXT DEFAULT 'user' CHECK (role IN ('admin', 'user')),
                first_name TEXT,
                last_name TEXT,
                is_active INTEGER DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ";
        
        $sql_sessions = "
            CREATE TABLE IF NOT EXISTS sessions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                session_token TEXT UNIQUE NOT NULL,
                expires_at DATETIME NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )
        ";
        
        $sql_projects = "
            CREATE TABLE IF NOT EXISTS projects (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                color TEXT DEFAULT '#667eea',
                created_by INTEGER,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
            )
        ";
        
        $sql_tasks = "
            CREATE TABLE IF NOT EXISTS tasks (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title TEXT NOT NULL,
                description TEXT,
                status TEXT DEFAULT 'pending' CHECK (status IN ('pending', 'in_progress', 'completed')),
                priority TEXT DEFAULT 'medium' CHECK (priority IN ('low', 'medium', 'high')),
                due_date DATE,
                due_time TIME,
                project_id INTEGER,
                assigned_to INTEGER,
                created_by INTEGER,
                tags TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL,
                FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
                FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
            )
        ";
        
        try {
            $this->connection->exec($sql_users);
            $this->connection->exec($sql_sessions);
            $this->connection->exec($sql_projects);
            $this->connection->exec($sql_tasks);
            $this->insertSampleData();
        } catch (PDOException $e) {
            throw new Exception('Error creating tables: ' . $e->getMessage());
        }
    }
    
    private function insertSampleData() {
        // Check if data already exists
        $count = $this->connection->query("SELECT COUNT(*) FROM users")->fetchColumn();
        if ($count > 0) {
            return; // Data already exists
        }
        
        // Insert default admin user
        $admin_password = password_hash('admin123', PASSWORD_DEFAULT);
        $users_sql = "
            INSERT INTO users (username, email, password_hash, role, first_name, last_name) VALUES 
            ('admin', 'admin@taskflow.com', '$admin_password', 'admin', 'Admin', 'User'),
            ('john', 'john@example.com', '" . password_hash('user123', PASSWORD_DEFAULT) . "', 'user', 'John', 'Doe'),
            ('jane', 'jane@example.com', '" . password_hash('user123', PASSWORD_DEFAULT) . "', 'user', 'Jane', 'Smith')
        ";
        
        // Insert sample projects
        $projects_sql = "
            INSERT INTO projects (name, color, created_by) VALUES 
            ('Work Projects', '#667eea', 1),
            ('Personal', '#48bb78', 1),
            ('Health & Fitness', '#f56565', 1),
            ('Learning', '#ed8936', 1)
        ";
        
        // Insert sample tasks
        $tasks_sql = "
            INSERT INTO tasks (title, description, status, priority, due_date, due_time, project_id, assigned_to, created_by, tags) VALUES 
            ('Complete project proposal', 'Finish the quarterly project proposal for the management team', 'pending', 'high', '2025-01-15', '17:00', 1, 2, 1, 'work, urgent'),
            ('Review code changes', 'Review and approve the latest code changes from the development team', 'pending', 'medium', '2025-01-14', '10:00', 1, 3, 1, 'development, review'),
            ('Buy groceries', 'Weekly grocery shopping', 'completed', 'low', '2025-01-13', NULL, 2, 2, 1, 'personal'),
            ('Team meeting preparation', 'Prepare agenda and materials for the weekly team meeting', 'pending', 'medium', '2025-01-16', '09:00', 1, 2, 1, 'work, meeting'),
            ('Exercise routine', 'Complete 30-minute workout', 'pending', 'medium', '2025-01-14', '07:00', 3, 3, 1, 'fitness, health'),
            ('Read chapter 5', 'Read and take notes on chapter 5 of JavaScript book', 'pending', 'low', '2025-01-17', NULL, 4, 2, 1, 'learning, javascript'),
            ('Call dentist', 'Schedule dental cleaning appointment', 'pending', 'medium', '2025-01-15', NULL, 2, 3, 1, 'health, appointment'),
            ('Update resume', 'Update resume with recent projects and skills', 'completed', 'medium', '2025-01-12', NULL, 1, 2, 1, 'career, professional')
        ";
        
        try {
            $this->connection->exec($users_sql);
            $this->connection->exec($projects_sql);
            $this->connection->exec($tasks_sql);
        } catch (PDOException $e) {
            // Ignore errors if data already exists
        }
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    public function query($sql, $params = []) {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            throw new Exception('Query failed: ' . $e->getMessage());
        }
    }
    
    public function lastInsertId() {
        return $this->connection->lastInsertId();
    }
    
    public function beginTransaction() {
        return $this->connection->beginTransaction();
    }
    
    public function commit() {
        return $this->connection->commit();
    }
    
    public function rollback() {
        return $this->connection->rollback();
    }
}

// Global database instance
function getDatabase() {
    static $db = null;
    if ($db === null) {
        $db = new Database();
    }
    return $db;
}

?>