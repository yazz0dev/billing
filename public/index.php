<?php // ./index.php
declare(strict_types=1);

define('PROJECT_ROOT', __DIR__); // Project root is here

// Include composer autoloader
if (file_exists(PROJECT_ROOT . '/vendor/autoload.php')) {
    require PROJECT_ROOT . '/vendor/autoload.php';
} else {
     // Fallback or error handling if vendor is not found
     http_response_code(500);
     die("Composer autoload not found. Please run 'composer install'.");
}

// Load environment variables
if (file_exists(PROJECT_ROOT . '/.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(PROJECT_ROOT);
    $dotenv->safeLoad(); // Won't error if .env is missing
}

// Load application configuration
$appConfig = require PROJECT_ROOT . '/config/app.php';

// Set error display based on environment
if (($appConfig['env'] ?? 'production') === 'development' || ($appConfig['debug'] ?? false)) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(0);
}

// Initialize session
if (session_status() === PHP_SESSION_NONE) {
    session_name($appConfig['session_name'] ?? 'APP_SESSION');
    session_start([
        'cookie_httponly' => true,
        'cookie_secure' => ($appConfig['env'] ?? 'production') === 'production',
        'cookie_samesite' => 'Lax',
    ]);
}

// Define the base path for the application
// This is crucial if your app lives in a subdirectory (e.g., http://localhost/billing_app)
// If it lives directly in the webroot (e.g., http://localhost/), this should be empty.
$scriptName = $_SERVER['SCRIPT_NAME'] ?? ''; // e.g., /index.php or /billing_app/index.php
$baseDir = dirname($scriptName); // e.g., / or /billing_app
if ($baseDir === '/' || $baseDir === '\\') {
    $baseDir = ''; // If script is in webroot, baseDir is empty
}
define('BASE_PATH', $baseDir); // Will be '' or '/billing_app'

// Include the main route handler (api/index.php)
// It will take over from here and handle dispatching
$routeHandlerFile = PROJECT_ROOT . '/api/index.php';
if (!file_exists($routeHandlerFile)) {
    // Fallback or error handling if api/index.php is not found
    http_response_code(500);
    die("Application entry point not found: {$routeHandlerFile}");
}

// Error Handling (Centralized in api/index.php's dispatch block)
// Include the route handler script. It will perform routing and handle exceptions.
require $routeHandlerFile;

// The script should exit within the dispatched controller action or router exception handler
// No explicit exit needed here.