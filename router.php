<?php
/**
 * Router for Billing System
 * 
 * This router handles all incoming requests and routes them to the appropriate files.
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session for authentication
session_start();

// Get the request URI and remove query string
$request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Remove the '/billing' prefix to get the relative path
$base_path = '/billing';
$relative_path = substr($request_uri, strlen($base_path));

// Normalize path: remove trailing slash except for root path
if ($relative_path !== '/' && substr($relative_path, -1) === '/') {
    $relative_path = rtrim($relative_path, '/');
}

// Default to index if the path is just the base
if ($relative_path === '' || $relative_path === '/') {
    $relative_path = '/index.html';
}

// Handle API requests to server.php
if (strpos($relative_path, '/server.php') === 0) {
    require_once __DIR__ . '/server.php';
    exit;
}

// Handle notification.php requests
if (strpos($relative_path, '/notification.php') === 0) {
    require_once __DIR__ . '/notification.php';
    exit;
}

// Define routes for HTML pages
$routes = [
    '/index.html' => __DIR__ . '/index.html',
    '/login' => __DIR__ . '/login/index.html',
    '/login/index.html' => __DIR__ . '/login/index.html',
    
    // Admin routes
    '/admin' => __DIR__ . '/admin/index.html',
    '/admin/index.html' => __DIR__ . '/admin/index.html',
    '/admin/dashboard' => __DIR__ . '/admin/dashboard.html',
    
    // Staff routes
    '/staff' => __DIR__ . '/staff/index.html',
    '/staff/index.html' => __DIR__ . '/staff/index.html',
    '/staff/bill' => __DIR__ . '/staff/bill.html',
    '/staff/billview' => __DIR__ . '/staff/billview.html',
    '/staff/billview.html' => __DIR__ . '/staff/billview.html',
    
    // Product routes
    '/product' => __DIR__ . '/product/index.html',
    '/product/index.html' => __DIR__ . '/product/index.html',
];

// Log MongoDB connection status
function logMongoDBConnection() {
    $log_file = __DIR__ . '/logs/mongodb.log';
    $log_dir = dirname($log_file);
    
    // Create logs directory if it doesn't exist
    if (!file_exists($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    try {
        $client = new MongoDB\Client("mongodb://localhost:27017");
        $databases = iterator_to_array($client->listDatabases());
        $status = "Connected successfully to MongoDB at " . date('Y-m-d H:i:s');
        error_log($status . PHP_EOL, 3, $log_file);
        return true;
    } catch (Exception $e) {
        $error = "MongoDB Connection Error: " . $e->getMessage() . " at " . date('Y-m-d H:i:s');
        error_log($error . PHP_EOL, 3, $log_file);
        return false;
    }
}

// Non-blocking MongoDB connection check
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    // Only check MongoDB if the Composer autoloader exists
    require_once __DIR__ . '/vendor/autoload.php';
    if (class_exists('MongoDB\Client')) {
        // Run connection check in background or with short timeout
        set_time_limit(5); // Limit to 5 seconds max
        @logMongoDBConnection();
        // Reset time limit to default
        set_time_limit(30);
    }
}

// Debug info - comment out in production
echo "<!-- Debug: Requested path: {$relative_path} -->\n";

// Check if route exists
if (isset($routes[$relative_path])) {
    $file_path = $routes[$relative_path];
    
    // Debug info - comment out in production
    echo "<!-- Debug: Mapped to file: {$file_path} -->\n";
    
    // Security check for protected routes
    if (strpos($relative_path, '/admin/') === 0) {
        // Check if user is logged in as admin
        if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
            header('Location: /billing/login');
            exit;
        }
    } elseif (strpos($relative_path, '/staff/') === 0) {
        // Check if user is logged in as staff or admin
        if (!isset($_SESSION['user_role']) || ($_SESSION['user_role'] !== 'staff' && $_SESSION['user_role'] !== 'admin')) {
            header('Location: /billing/login');
            exit;
        }
    }
    
    // Check if file exists before trying to serve it
    if (!file_exists($file_path)) {
        echo "Error: File not found: {$file_path}";
        exit;
    }
    
    // Serve the file based on extension
    $extension = pathinfo($file_path, PATHINFO_EXTENSION);
    
    if ($extension === 'html') {
        header('Content-Type: text/html');
        include($file_path); // Changed from readfile to include
    } elseif ($extension === 'php') {
        include($file_path);
    } elseif ($extension === 'css') {
        header('Content-Type: text/css');
        readfile($file_path);
    } elseif ($extension === 'js') {
        header('Content-Type: application/javascript');
        readfile($file_path);
    } else {
        // Handle other file types as needed
        readfile($file_path);
    }
} elseif (strpos($relative_path, '/global.css') !== false) {
    // Special handling for global CSS
    $css_path = __DIR__ . '/global.css';
    
    // Debug info - comment out in production
    echo "<!-- Debug: Serving CSS: {$css_path} -->\n";
    
    if (file_exists($css_path)) {
        header('Content-Type: text/css');
        readfile($css_path);
    } else {
        echo "Error: CSS file not found: {$css_path}";
    }
} elseif (strpos($relative_path, '/js/') === 0) {
    // Handle JavaScript files in the js directory
    $js_path = __DIR__ . $relative_path;
    
    // Debug info - comment out in production
    echo "<!-- Debug: Serving JavaScript: {$js_path} -->\n";
    
    if (file_exists($js_path)) {
        header('Content-Type: application/javascript');
        readfile($js_path);
    } else {
        echo "Error: JavaScript file not found: {$js_path}";
    }
} else {
    // 404 Not Found
    header("HTTP/1.0 404 Not Found");
    echo "<h1>404 Not Found</h1>";
    echo "<p>The requested URL {$request_uri} was not found on this server.</p>";
    echo "<p>Debug: Relative path '{$relative_path}' has no matching route.</p>";
    echo "<a href='/billing/'>Go to homepage</a>";
}
?>
