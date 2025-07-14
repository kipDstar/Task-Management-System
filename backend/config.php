<?php

// Application Configuration
define('APP_NAME', 'TaskFlow');
define('APP_VERSION', '1.0.0');
define('APP_DESCRIPTION', 'A modern task management application');

// Database Configuration
define('DB_TYPE', 'sqlite');
define('DB_PATH', __DIR__ . '/data/tasks.db');

// Application Settings
define('TIMEZONE', 'UTC');
define('DATE_FORMAT', 'Y-m-d');
define('TIME_FORMAT', 'H:i');
define('DATETIME_FORMAT', 'Y-m-d H:i:s');

// Security Settings
define('ENABLE_CORS', true);
define('ALLOWED_ORIGINS', '*'); // Change to specific domains in production

// Feature Flags
define('ENABLE_API_LOGGING', false);
define('ENABLE_DEBUG_MODE', true);

// Set timezone
date_default_timezone_set(TIMEZONE);

// Error reporting
if (ENABLE_DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

?>