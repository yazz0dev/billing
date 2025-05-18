<?php // ./index.php
// This is the main entry point for the application.
// Ensure your web server is configured to use this file as the front controller.

declare(strict_types=1);

// Define PROJECT_ROOT as the directory containing 'src', 'config', 'vendor', etc.
// If public/index.php is the entry point, PROJECT_ROOT is one level up.
define('PROJECT_ROOT', dirname(__DIR__));

// Include composer autoloader
if (file_exists(PROJECT_ROOT . '/vendor/autoload.php')) {
    require PROJECT_ROOT . '/vendor/autoload.php';
} else {
    http_response_code(500);
    echo '<h1>Server Error</h1><p>Dependencies not installed. Please run <code>composer install</code>.</p>';
    exit;
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
$scriptName = $_SERVER['SCRIPT_NAME'] ?? ''; // e.g., /index.php or /billing_app/index.php
$baseDir = dirname($scriptName); // e.g., / or /billing_app
if ($baseDir === '/' || $baseDir === '\\') {
    $baseDir = ''; // If script is in webroot, baseDir is empty
}
define('BASE_PATH', $baseDir); // Will be '' or '/billing_app'


// --- Attempt database connection early ---
// This needs View and Database classes.
use App\Core\Database;
use App\Core\View;

try {
    Database::connect();
} catch (\MongoDB\Driver\Exception\Exception $e) { // More specific MongoDB exception
    http_response_code(503); // Service Unavailable
    error_log("Database Connection Error from public/index.php: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    // Ensure View can be instantiated if PROJECT_ROOT is correctly set
    $view = new View(PROJECT_ROOT . '/templates');
    $errorMessage = ($appConfig['debug'] ?? false) ? nl2br(htmlspecialchars("Database Connection Failed: " . $e->getMessage() . "\n\nTrace:\n" . $e->getTraceAsString())) : 'Database service is currently unavailable. Please try again later.';
    // Ensure error templates exist and paths are correct
    echo $view->render('error/500.php', ['pageTitle' => 'Database Error', 'message' => $errorMessage], 'layouts/minimal.php');
    exit;
} catch (\Throwable $e) { // Catch any other throwable during early DB connection
    http_response_code(500);
    error_log("Generic Early Setup Error from public/index.php: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    $view = new View(PROJECT_ROOT . '/templates');
    $errorMessage = ($appConfig['debug'] ?? false) ? nl2br(htmlspecialchars("Initialization Error: " . $e->getMessage() . "\n\nTrace:\n" . $e->getTraceAsString())) : 'An unexpected error occurred during application startup.';
    echo $view->render('error/500.php', ['pageTitle' => 'Initialization Error', 'message' => $errorMessage], 'layouts/minimal.php');
    exit;
}


// Include the router (api/index.php)
// It will take over from here and handle dispatching
$routerFile = PROJECT_ROOT . '/api/index.php';
if (!file_exists($routerFile)) {
    http_response_code(500);
    // Use View for error message if available, otherwise plain text
    if (class_exists(View::class)) {
        $view = new View(PROJECT_ROOT . '/templates');
        echo $view->render('error/500.php', ['pageTitle' => 'Server Configuration Error', 'message' => 'Application router not found.'], 'layouts/minimal.php');
    } else {
        echo '<h1>Server Configuration Error</h1><p>Application router not found.</p>';
    }
    exit;
}

require $routerFile;

// The script should exit within the dispatched controller action or router exception handler