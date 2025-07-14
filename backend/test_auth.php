<?php
require_once 'api/database.php';
require_once 'api/auth.php';

echo "<h1>TaskFlow Authentication Test</h1>";

try {
    // Initialize database
    $db = getDatabase();
    echo "<p>✅ Database connection successful</p>";
    
    // Initialize auth
    $auth = getAuth();
    echo "<p>✅ Authentication system initialized</p>";
    
    // Test user creation
    $testUser = [
        'username' => 'testuser',
        'email' => 'test@example.com',
        'password' => 'testpass123',
        'first_name' => 'Test',
        'last_name' => 'User',
        'role' => 'user'
    ];
    
    $result = $auth->register($testUser);
    if ($result['success']) {
        echo "<p>✅ User registration test successful</p>";
    } else {
        echo "<p>⚠️ User registration test: " . $result['message'] . "</p>";
    }
    
    // Test login
    $loginResult = $auth->login('testuser', 'testpass123');
    if ($loginResult['success']) {
        echo "<p>✅ Login test successful</p>";
        echo "<p>Session token: " . substr($loginResult['session_token'], 0, 20) . "...</p>";
        
        // Test current user
        $currentUser = $auth->getCurrentUser($loginResult['session_token']);
        if ($currentUser) {
            echo "<p>✅ Current user test successful: " . $currentUser['username'] . "</p>";
        } else {
            echo "<p>❌ Current user test failed</p>";
        }
        
        // Test logout
        $logoutResult = $auth->logout($loginResult['session_token']);
        if ($logoutResult['success']) {
            echo "<p>✅ Logout test successful</p>";
        } else {
            echo "<p>❌ Logout test failed</p>";
        }
    } else {
        echo "<p>❌ Login test failed: " . $loginResult['message'] . "</p>";
    }
    
    // Test admin user
    $adminLogin = $auth->login('admin', 'admin123');
    if ($adminLogin['success']) {
        echo "<p>✅ Admin login test successful</p>";
        
        // Test user listing (admin only)
        $users = $auth->getAllUsers();
        if (count($users) > 0) {
            echo "<p>✅ User listing test successful (" . count($users) . " users found)</p>";
        } else {
            echo "<p>❌ User listing test failed</p>";
        }
    } else {
        echo "<p>❌ Admin login test failed: " . $adminLogin['message'] . "</p>";
    }
    
    echo "<h2>Sample Users:</h2>";
    echo "<ul>";
    echo "<li><strong>Admin:</strong> admin / admin123</li>";
    echo "<li><strong>User:</strong> john / user123</li>";
    echo "<li><strong>User:</strong> jane / user123</li>";
    echo "</ul>";
    
    echo "<p><a href='login.php'>Go to Login Page</a></p>";
    
} catch (Exception $e) {
    echo "<p>❌ Error: " . $e->getMessage() . "</p>";
}
?> 