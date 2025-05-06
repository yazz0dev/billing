//billing/router.php
<?php
/**
 * Router for Billing System
 */

error_reporting(E_ALL);
ini_set('display_errors', 1); // For development, 0 for production

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
    require_once __DIR__ . '/notification.php';
    exit;
}
if ($relative_path === '/db-check.php') { // db-check needs layout
    $pageTitle = "Database Connection Check";
    include __DIR__ . '/layout_header.php';
    include __DIR__ . '/db-check.php';
    include __DIR__ . '/layout_footer.php';
    exit;
}

// --- Static Asset Routes (CSS, JS, Images etc.) ---
// It's generally better to let Apache/Nginx handle static files directly for performance.
// If using PHP to serve, ensure paths are secure.
if (preg_match('/^\/global\.css$/', $relative_path)) {
    if (serve_static_file(__DIR__ . '/global.css')) exit;
} elseif (preg_match('/^\/js\/(.+)$/', $relative_path, $matches)) {
    if (serve_static_file(__DIR__ . '/js/' . $matches[1])) exit;
} elseif (preg_match('/^\/ui\/(.+)$/', $relative_path, $matches)) {
    // Serve files from /ui like topbar.html if directly requested (though it's included by PHP now)
    // This might be useful if topbar.html had images or other assets it referenced relatively.
    // However, it's better to put such assets in a dedicated /assets or /images folder.
    // For now, this keeps the topbar.html working if its contents are complex.
    if (serve_static_file(__DIR__ . '/ui/' . $matches[1])) exit;
}


// --- Page Routes ---
$page_routes = [
    // Route path       => [File path, Page Title, Role required (null for public)]
    '/index'            => [__DIR__ . '/index.html', 'Homepage', null],
    '/login'            => [__DIR__ . '/login/index.html', 'Login', null],

    '/admin'            => [__DIR__ . '/admin/index.html', 'Admin Dashboard', 'admin'],
    '/admin/dashboard'  => [__DIR__ . '/admin/index.html', 'Admin Dashboard', 'admin'], // Alias or specific file

    '/staff'            => [__DIR__ . '/staff/index.html', 'Staff Billing', ['admin', 'staff']],
    '/staff/billview'   => [__DIR__ . '/staff/billview.html', 'Bill History', ['admin', 'staff']],

    '/product'          => [__DIR__ . '/product/index.html', 'Product Management', 'admin'], // Typically admin
    '/logout'           => [__DIR__ . '/logout.php', 'Logout', null], // logout.php will handle redirect
];

if (isset($page_routes[$relative_path])) {
    list($file_to_include, $pageTitle, $required_role) = $page_routes[$relative_path];
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
            // For .php files like logout.php, include them directly without layout
            include $file_to_include;
        } else {
            // For .html content files, wrap with layout
            include __DIR__ . '/layout_header.php'; // $pageTitle is used here
            include $file_to_include;
            include __DIR__ . '/layout_footer.php';
        }
        exit;
    } else {
        route_log("File not found for route {$relative_path}: {$file_to_include}");
    }
}

// --- 404 Not Found ---
route_log("404 Not Found for relative path: {$relative_path}");
header("HTTP/1.0 404 Not Found");
$pageTitle = "404 Not Found"; // For the layout
include __DIR__ . '/layout_header.php';
echo "<div class='container text-center glass mt-5'>";
echo "<h1 class='page-title' style='color:var(--error);'>404 Not Found</h1>"; // Override H1 style for error
echo "<p>The page you requested at <code>" . htmlspecialchars($request_uri) . "</code> could not be found.</p>";
echo "<p>We apologize for the inconvenience.</p>";
echo "<a href='{$base_path}/index' class='btn mt-3'>Go to Homepage</a>";
echo "</div>";
include __DIR__ . '/layout_footer.php';
exit;
?>