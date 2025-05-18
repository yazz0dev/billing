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
    // The api/index.php script will handle all routing (web and API)
    $routeHandlerFile = PROJECT_ROOT . '/api/index.php';
    if (!file_exists($routeHandlerFile)) {
        throw new Exception("Main route handler file not found: {$routeHandlerFile}");
    }
    require $routeHandlerFile;
    // exit; // api/index.php will handle exit or response sending.

} catch (\MongoDB\Driver\Exception\ConnectionTimeoutException $e) {
    http_response_code(503); // Service Unavailable
    error_log("Database Connection Timeout: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    if (($appConfig['debug'] ?? false)) {
        echo '<h1>Database Connection Error</h1>';
        echo '<p>Could not connect to the database. Please check your connection settings and ensure the database server is running.</p>';
        echo '<h2>Debug Information:</h2>';
        echo '<pre>Type: Connection Timeout</pre>';
        echo '<pre>' . htmlspecialchars($e->getMessage()) . "\n\n" . htmlspecialchars($e->getTraceAsString()) . '</pre>';
    } else {
        echo '<h1>Service Unavailable</h1>';
        echo '<p>We are currently experiencing database issues. Please try again later.</p>';
    }
} catch (\MongoDB\Driver\Exception\ConnectionException $e) { // Catch specific connection errors (e.g., TLS, host not found)
    http_response_code(503); // Service Unavailable
    $errorMessage = $e->getMessage(); // Store message
    error_log("Database Connection Failed: " . $errorMessage . "\n" . $e->getTraceAsString());
    if (($appConfig['debug'] ?? false)) {
        echo '<h1>Database Connection Error</h1>';
        echo '<p>Failed to establish a connection with the database. This could be due to network issues, incorrect credentials, SSL/TLS problems, or the database server being unavailable.</p>';
        echo '<h2>Debug Information:</h2>';
        echo '<pre>Type: Connection Failed</pre>';
        echo '<pre>' . htmlspecialchars($errorMessage) . "\n\n" . htmlspecialchars($e->getTraceAsString()) . '</pre>';
    } else {
        echo '<h1>Service Unavailable</h1>';
        echo '<p>We are currently experiencing issues connecting to our database. Please try again later.</p>';
        echo '<p><strong>Error details for administrators:</strong> ' . htmlspecialchars($errorMessage) . '</p>'; // Show error message
    }
} catch (\MongoDB\Driver\Exception\Exception $e) { // Catch other MongoDB driver exceptions
    http_response_code(500);
    $errorMessage = $e->getMessage(); // Store message
    error_log("Database Error: " . $errorMessage . "\n" . $e->getTraceAsString());
    if (($appConfig['debug'] ?? false)) {
        echo '<h1>Database Error</h1>';
        echo '<p>A database error occurred.</p>';
        echo '<h2>Debug Information:</h2>';
        echo '<pre>Type: MongoDB Driver Exception</pre>';
        echo '<pre>' . htmlspecialchars($errorMessage) . "\n\n" . htmlspecialchars($e->getTraceAsString()) . '</pre>';
    } else {
        echo '<h1>An error occurred</h1>';
        echo '<p>We encountered a problem with the database. Please try again later.</p>';
        // Optionally, you could add htmlspecialchars($errorMessage) here too if appropriate for generic DB errors
    }
} catch (Throwable $e) {
    // Handle other errors
    // Set a generic 500 status code if not already set by a more specific exception
    if (http_response_code() === 200) { // Check if headers not already sent with an error code
        http_response_code(500);
    }
    error_log($e->getMessage() . "\n" . $e->getTraceAsString());
    if (($appConfig['debug'] ?? false)) {
        echo '<h1>Error</h1>';
        echo '<pre>' . $e->getMessage() . "\n\n" . $e->getTraceAsString() . '</pre>';
    } else {
        echo '<h1>An error occurred</h1>';
        echo '<p>Please try again later.</p>';
    }
}
