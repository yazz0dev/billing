<?php
declare(strict_types=1);

define('PROJECT_ROOT', __DIR__);

// Include composer autoloader
if (file_exists(PROJECT_ROOT . '/vendor/autoload.php')) {
    require PROJECT_ROOT . '/vendor/autoload.php';
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
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$baseDir = dirname($scriptName);
if ($baseDir === '/' || $baseDir === '\\') {
    $baseDir = '';
}
define('BASE_PATH', $baseDir);

// Simple front controller
try {
    // Include your routing logic or framework bootstrap here
    $requestUri = $_SERVER['REQUEST_URI'];
    
    // Remove base path from request URI if it exists
    if (!empty($baseDir) && strpos($requestUri, $baseDir) === 0) {
        $requestUri = substr($requestUri, strlen($baseDir));
    }
    
    $requestPath = parse_url($requestUri, PHP_URL_PATH);
    
    // Normalize empty path to "/"
    if (empty($requestPath)) {
        $requestPath = '/';
    }
    
    // If this is an API request, delegate to the API front controller
    if (strpos($requestPath, '/api/') === 0) {
        // API routes are handled by /api/index.php which includes all endpoints
        try {
            $apiFile = PROJECT_ROOT . '/api/index.php';
            if (!file_exists($apiFile)) {
                throw new Exception("API controller file not found");
            }
            require $apiFile;
            exit; // Prevent further execution after API handling
        } catch (Throwable $apiError) {
            // Log API delegation error and continue to error handling
            error_log("API delegation error: " . $apiError->getMessage());
            http_response_code(500);
            if ($appConfig['debug'] ?? false) {
                echo json_encode([
                    'error' => 'API Error',
                    'message' => $apiError->getMessage(),
                    'file' => $apiError->getFile(),
                    'line' => $apiError->getLine()
                ]);
            } else {
                echo json_encode(['error' => 'Internal Server Error']);
            }
            exit;
        }
    }
    
    // For all other requests, delegate to API index.php which handles all routes
    // This ensures consistent routing for both web pages and API
    try {
        $apiFile = PROJECT_ROOT . '/api/index.php';
        if (!file_exists($apiFile)) {
            throw new Exception("Route handler file not found");
        }
        require $apiFile;
        exit;
    } catch (Throwable $error) {
        // Log delegation error and continue to error handling
        error_log("Route handling error: " . $error->getMessage());
        throw $error;
    }
    
} catch (Throwable $e) {
    // Handle errors
    if (($appConfig['debug'] ?? false)) {
        echo '<h1>Error</h1>';
        echo '<pre>' . $e->getMessage() . "\n\n" . $e->getTraceAsString() . '</pre>';
    } else {
        echo '<h1>An error occurred</h1>';
        echo '<p>Please try again later.</p>';
        error_log($e->getMessage() . "\n" . $e->getTraceAsString());
    }
}
