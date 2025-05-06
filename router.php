<?php
//billing/router.php
/**
 * Router for Billing System
 */

error_reporting(E_ALL);
ini_set('display_errors', 1); // For development, 0 for production

// Ensure vendor autoload is loaded
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
} else {
    // This is a critical failure for the entire application.
    $errorMessage = "CRITICAL: vendor/autoload.php not found. Application cannot start. Please run 'composer install'.";
    error_log($errorMessage);
    http_response_code(500); // Internal Server Error
    echo "<h1>Server Configuration Error</h1>";
    echo "<p>A critical application component (autoloader) is missing. This typically means dependencies are not installed.</p>";
    echo "<p>If you are the administrator, please run <code>composer install</code> in the application directory.</p>";
    echo "<p>Otherwise, please contact the site administrator and report this issue.</p>";
    // Provide path detail for easier debugging if display_errors is on (though it might be caught by error_reporting already)
    if (ini_get('display_errors')) {
        echo "<p><small>Missing file: " . htmlspecialchars(__DIR__ . '/vendor/autoload.php') . "</small></p>";
    }
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- Configuration ---
$base_path = '/billing';
$log_dir = __DIR__ . '/logs';
$mongodb_log_file = $log_dir . '/mongodb.log';
$router_log_file = $log_dir . '/router.log';

// --- Helper Functions ---
function route_log($message) {
    global $router_log_file, $log_dir;
    if (!file_exists($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    error_log(date('[Y-m-d H:i:s] ') . $message . PHP_EOL, 3, $router_log_file);
}

function serve_static_file($file_path) {
    if (!file_exists($file_path) || !is_readable($file_path)) {
        route_log("Static file not found or not readable: {$file_path}");
        return false;
    }

    $mime_types = [
        'css' => 'text/css',
        'js' => 'application/javascript',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'svg' => 'image/svg+xml',
    ];
    $extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
    $content_type = isset($mime_types[$extension]) ? $mime_types[$extension] : 'application/octet-stream';

    header("Content-Type: {$content_type}");
    header("Content-Length: " . filesize($file_path));
    // Consider adding caching headers for static assets in production
    // header("Cache-Control: max-age=31536000, public"); // Cache for 1 year
    // header("Expires: " . gmdate('D, d M Y H:i:s', time() + 31536000) . ' GMT');
    readfile($file_path);
    return true;
}

function logMongoDBConnectionStatus() {
    global $mongodb_log_file, $log_dir;
    if (!file_exists($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    if (file_exists(__DIR__ . '/vendor/autoload.php')) {
        require_once __DIR__ . '/vendor/autoload.php';
        if (class_exists('MongoDB\Client')) {
            try {
                // Use a short timeout for the connection attempt
                $client = new MongoDB\Client("mongodb://localhost:27017", [], ['serverSelectionTimeoutMS' => 2000]);
                $client->listDatabases(); // Actual command to check connection
                $status = "Connected successfully to MongoDB.";
                error_log(date('[Y-m-d H:i:s] ') . $status . PHP_EOL, 3, $mongodb_log_file);
                return true;
            } catch (Exception $e) {
                $error = "MongoDB Connection Error: " . $e->getMessage();
                error_log(date('[Y-m-d H:i:s] ') . $error . PHP_EOL, 3, $mongodb_log_file);
                return false;
            }
        } else {
            error_log(date('[Y-m-d H:i:s] ') . "MongoDB\Client class not found." . PHP_EOL, 3, $mongodb_log_file);
        }
    } else {
         error_log(date('[Y-m-d H:i:s] ') . "Composer autoload.php not found." . PHP_EOL, 3, $mongodb_log_file);
    }
    return false;
}

// Non-blocking MongoDB connection check (log only)
// This is a simple way; for true non-blocking, consider background processes or message queues.
// For this app, a quick check with timeout is probably sufficient.
set_time_limit(5); // Short time limit for this initial check
@logMongoDBConnectionStatus();
set_time_limit(30); // Reset to default


// --- Routing Logic ---
$request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
route_log("Request URI: {$request_uri}");

// Remove the base path prefix
$relative_path = $request_uri;
if (strpos($request_uri, $base_path) === 0) {
    $relative_path = substr($request_uri, strlen($base_path));
}

// Normalize path: ensure leading slash, remove trailing slash (except for root)
if (empty($relative_path) || $relative_path === '/') {
    $relative_path = '/index'; // Default to homepage (will map to index.html)
} elseif (substr($relative_path, -1) === '/' && strlen($relative_path) > 1) {
    $relative_path = rtrim($relative_path, '/');
}
if (substr($relative_path, 0, 1) !== '/') {
    $relative_path = '/' . $relative_path;
}
route_log("Normalized relative path: {$relative_path}");


// --- API and Special File Routes ---
if ($relative_path === '/server.php' || strpos($relative_path, '/server.php?') === 0) {
    route_log("Routing to server.php");
    require_once __DIR__ . '/server.php';
    exit;
}
if ($relative_path === '/notification.php' || strpos($relative_path, '/notification.php?') === 0) {
    route_log("Routing to notification.php");
    // Ensure we're setting proper content type for AJAX responses
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest' ||
        isset($_POST['popup_action'])) {
        // For AJAX requests, we want to catch PHP errors and return them as JSON
        ob_start(); // Start output buffering
        try {
            require_once __DIR__ . '/notification.php';
        } catch (Exception $e) {
            $error = ob_get_clean(); // Get any output so far
            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'error',
                'message' => 'Server error: ' . $e->getMessage(),
                'debug' => $error
            ]);
            exit;
        }
        
        // If we got here and haven't output anything yet, check if the buffer contains errors
        $output = ob_get_clean();
        if (!empty($output) && strpos($output, '{') !== 0) { // Not JSON
            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'error',
                'message' => 'Invalid server response',
                'debug' => $output
            ]);
            exit;
        }
        
        // Otherwise, return whatever was in the buffer
        echo $output;
    } else {
        // For non-AJAX requests
        require_once __DIR__ . '/notification.php';
    }
    exit;
}

// --- Static Asset Routes (CSS, JS, Images etc.) ---
// Static assets like CSS, JS, and images should ideally be served directly by the webserver (Apache/Nginx)
// for better performance. PHP-based serving for these has been removed.
// Ensure your webserver is configured to serve files from /js, /css (if you create it), /images, etc.
// global.css is expected to be served by the webserver.

// --- Page Routes ---
$page_routes = [
    // Route path       => [File path, Page Title, Role required (null for public)]
    // Route path       => [File path, Page Title, Role required (null for public), Use Layout (true/false)]
    '/index'            => [__DIR__ . '/index.php', 'Homepage', null, true],
    '/login'            => [__DIR__ . '/login/index.php', 'Login', null, true], // Login page might have a simpler layout or none

    '/admin'            => [__DIR__ . '/admin/index.php', 'Admin Dashboard', 'admin', true],
    '/admin/dashboard'  => [__DIR__ . '/admin/index.php', 'Admin Dashboard', 'admin', true],

    '/staff'            => [__DIR__ . '/staff/index.php', 'Staff Billing', ['admin', 'staff'], true],
    '/staff/billview'   => [__DIR__ . '/staff/billview.php', 'Bill History', ['admin', 'staff'], true],

    '/product'          => [__DIR__ . '/product/index.php', 'Product Management', 'admin', true],
    '/logout'           => [__DIR__ . '/logout.php', 'Logout', null, false], // Logout script doesn't need layout
];

if (isset($page_routes[$relative_path])) {
    list($file_to_include, $pageTitle, $required_role, $use_layout) = $page_routes[$relative_path];
    route_log("Matched page route: {$relative_path} -> {$file_to_include}");

    // Role-based access control
    if ($required_role !== null) {
        $user_role = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : null;
        $is_allowed = false;
        if (is_array($required_role)) {
            if (in_array($user_role, $required_role)) {
                $is_allowed = true;
            }
        } elseif ($user_role === $required_role) {
            $is_allowed = true;
        }

        if (!$is_allowed) {
            route_log("Access denied for role '{$user_role}' to {$relative_path}. Required: " . (is_array($required_role) ? implode('/', $required_role) : $required_role));
            $_SESSION['login_redirect_message'] = "You do not have permission to access this page.";
            header('Location: ' . $base_path . '/login');
            exit;
        }
    }

    if (file_exists($file_to_include)) {
        if (pathinfo($file_to_include, PATHINFO_EXTENSION) === 'php') {
            if ($use_layout) {
                // Start output buffering to capture page content
                ob_start();
                include $file_to_include;
                $page_content = ob_get_clean();

                // Include header, then page content, then footer
                if (file_exists(__DIR__ . '/includes/header.php')) {
                    include __DIR__ . '/includes/header.php';
                } else {
                    route_log("Layout Error: header.php not found.");
                    echo "Error: Header missing."; // Fallback
                }
                
                echo $page_content; // Output the captured content

                if (file_exists(__DIR__ . '/includes/footer.php')) {
                    include __DIR__ . '/includes/footer.php';
                } else {
                    route_log("Layout Error: footer.php not found.");
                    echo "Error: Footer missing."; // Fallback
                }

            } else {
                // For files like logout.php, include them directly without layout
                include $file_to_include;
            }
        }
        exit;
    } else {
        route_log("File not found for route {$relative_path}: {$file_to_include}");
    }
}

// --- Simple router to handle HTML to PHP conversions ---
// Get the requested URI
$request_uri = $_SERVER['REQUEST_URI'];

// Remove query string if present
if (strpos($request_uri, '?') !== false) {
    $request_uri = substr($request_uri, 0, strpos($request_uri, '?'));
}

// Check if this is an HTML file request
if (preg_match('/\.html$/', $request_uri)) {
    // Convert to PHP equivalent
    $php_file = preg_replace('/\.html$/', '.php', $request_uri);
    
    // Check if PHP file exists
    $file_path = __DIR__ . $php_file;
    if (file_exists($file_path)) {
        // Redirect to PHP version
        header('Location: ' . $php_file . (isset($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : ''));
        exit;
    }
}

// --- 404 Not Found ---
// This part is reached if no routes above matched
route_log("404 Not Found for relative path: {$relative_path}");
header("HTTP/1.0 404 Not Found");
$pageTitle = "404 Not Found"; // For the layout

// Use the layout for the 404 page as well
if (file_exists(__DIR__ . '/includes/header.php')) {
    include __DIR__ . '/includes/header.php';
}

echo "<div class='container text-center glass mt-5 p-4'>"; // Added padding to glass
echo "<h1 class='page-title' style='color:var(--error); background:none; -webkit-background-clip:unset; background-clip:unset;'>404 Not Found</h1>"; // Simpler error title
echo "<p class='text-secondary'>The page you requested at <code>" . htmlspecialchars($request_uri) . "</code> could not be found.</p>";
echo "<p class='text-secondary'>We apologize for the inconvenience.</p>";
echo "<a href='{$base_path}/index' class='btn mt-3'>Go to Homepage</a>";
echo "</div>";

if (file_exists(__DIR__ . '/includes/footer.php')) {
    include __DIR__ . '/includes/footer.php';
}
exit;
?>