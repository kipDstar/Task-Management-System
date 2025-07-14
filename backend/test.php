<?php

require_once 'config.php';
require_once 'api/database.php';

echo "<h1>TaskFlow Installation Test</h1>";

// Test 1: PHP Version
echo "<h2>1. PHP Version Check</h2>";
$phpVersion = phpversion();
echo "PHP Version: " . $phpVersion;
if (version_compare($phpVersion, '7.4.0', '>=')) {
    echo " ✅ (Compatible)<br>";
} else {
    echo " ❌ (Requires PHP 7.4 or higher)<br>";
}

// Test 2: Required Extensions
echo "<h2>2. Required Extensions</h2>";
$extensions = ['pdo', 'pdo_sqlite', 'json'];
foreach ($extensions as $ext) {
    echo "Extension {$ext}: ";
    if (extension_loaded($ext)) {
        echo "✅ Loaded<br>";
    } else {
        echo "❌ Missing<br>";
    }
}

// Test 3: Directory Permissions
echo "<h2>3. Directory Permissions</h2>";
$directories = [
    'data' => './data',
    'assets' => './assets',
    'api' => './api'
];

foreach ($directories as $name => $path) {
    echo "Directory {$name}: ";
    if (is_dir($path)) {
        if (is_writable($path)) {
            echo "✅ Exists and writable<br>";
        } else {
            echo "⚠️ Exists but not writable<br>";
        }
    } else {
        echo "❌ Does not exist<br>";
    }
}

// Test 4: Database Connection
echo "<h2>4. Database Connection</h2>";
try {
    $db = new Database();
    echo "Database connection: ✅ Success<br>";
    
    // Test database queries
    $tasksCount = $db->query("SELECT COUNT(*) FROM tasks")->fetchColumn();
    $projectsCount = $db->query("SELECT COUNT(*) FROM projects")->fetchColumn();
    
    echo "Tasks in database: {$tasksCount}<br>";
    echo "Projects in database: {$projectsCount}<br>";
    
} catch (Exception $e) {
    echo "Database connection: ❌ Failed - " . $e->getMessage() . "<br>";
}

// Test 5: API Endpoints
echo "<h2>5. API Endpoints Test</h2>";

// Test tasks API
echo "Testing Tasks API: ";
if (file_exists('api/tasks.php')) {
    echo "✅ File exists<br>";
} else {
    echo "❌ File missing<br>";
}

// Test projects API
echo "Testing Projects API: ";
if (file_exists('api/projects.php')) {
    echo "✅ File exists<br>";
} else {
    echo "❌ File missing<br>";
}

// Test 6: Frontend Files
echo "<h2>6. Frontend Files</h2>";
$frontendFiles = [
    'index.php' => 'Main application file',
    'assets/css/style.css' => 'Stylesheet',
    'assets/js/script.js' => 'JavaScript functionality'
];

foreach ($frontendFiles as $file => $description) {
    echo "{$description}: ";
    if (file_exists($file)) {
        echo "✅ Found<br>";
    } else {
        echo "❌ Missing<br>";
    }
}

echo "<h2>Installation Status</h2>";
echo "<p>If all tests show ✅, your TaskFlow installation is ready!</p>";
echo "<p><a href='index.php' style='background: #667eea; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Launch TaskFlow</a></p>";

?>