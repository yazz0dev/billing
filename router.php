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
$base_path = '/billing'; // This should match the path used in Vercel routes and client-side JS


// Non-blocking MongoDB connection check (log only)
set_time_limit(5); 
@logMongoDBConnectionStatus();
set_time_limit(30); // Reset to default


// --- Routing Logic ---
$request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
route_log("Request URI: {$request_uri}");

// Remove the base path prefix if the app is not at the domain root
// For Vercel, if routes are defined like "/billing/(.*)" -> "api/index.php",
// $_SERVER['REQUEST_URI'] might already be relative inside api/index.php.
// We need to normalize based on how Vercel passes the path.
// Assuming Vercel passes the full path /billing/... to api/index.php (router.php)

$relative_path = $request_uri;
if (strpos($request_uri, $base_path) === 0) {
    $relative_path = substr($request_uri, strlen($base_path));
}
// If $base_path is "/" (app at root), $relative_path is $request_uri.
// If $base_path is "/billing" and request is "/billing/login", $relative_path becomes "/login".

// Normalize path: ensure leading slash, remove trailing slash (except for root)
if (empty($relative_path)) { // Handles case where request is exactly $base_path
    $relative_path = '/index'; 
} elseif (substr($relative_path, -1) === '/' && strlen($relative_path) > 1) {
    $relative_path = rtrim($relative_path, '/');
}
if (substr($relative_path, 0, 1) !== '/') {
    $relative_path = '/' . $relative_path;
}

// --- API and Special File Routes ---
// server.php and notification.php are handled as part of the application logic,
// not as separate entry points if router.php is the sole Vercel entry.
// All requests going to /billing/* (including /billing/server.php) are routed to this script by vercel.json.
// So, we check $relative_path for these.

if ($relative_path === '/server.php' || strpos($relative_path, '/server.php?') === 0) {
    route_log("Routing to internal server.php logic");
    require_once __DIR__ . '/server.php';
    exit;
}
if ($relative_path === '/notification.php' || strpos($relative_path, '/notification.php?') === 0) {
    route_log("Routing to internal notification.php logic");
    require_once __DIR__ . '/notification.php';
    exit;
}

// --- Static Asset Routes ---
// Static assets (CSS, JS, images) are handled by Vercel directly due to "handle": "filesystem"
// and explicit static builds in vercel.json. This router should not try to serve them.

// --- Page Routes ---
$page_routes = [
    '/index'            => [__DIR__ . '/index.php', 'Homepage', null, true], // The PHP application homepage
    '/login'            => [__DIR__ . '/login/index.php', 'Login', null, true], 

    '/admin'            => [__DIR__ . '/admin/index.php', 'Admin Dashboard', 'admin', true],
    '/admin/dashboard'  => [__DIR__ . '/admin/index.php', 'Admin Dashboard', 'admin', true],

    '/staff'            => [__DIR__ . '/staff/index.php', 'Staff Billing', ['admin', 'staff'], true],
    '/staff/billview'   => [__DIR__ . '/staff/billview.php', 'Bill History', ['admin', 'staff'], true],

    '/product'          => [__DIR__ . '/product/index.php', 'Product Management', 'admin', true],
    '/logout'           => [__DIR__ . '/logout.php', 'Logout', null, false], 
];

if (isset($page_routes[$relative_path])) {
    list($file_to_include, $pageTitle, $required_role, $use_layout) = $page_routes[$relative_path];
    route_log("Matched page route: {$relative_path} -> {$file_to_include}");

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
            // Redirect to login, ensuring $base_path is prefixed
            header('Location: ' . $base_path . '/login'); 
            exit;
        }
    }

    if (file_exists($file_to_include)) {
        if (pathinfo($file_to_include, PATHINFO_EXTENSION) === 'php') {
            if ($use_layout) {
                ob_start();
                include $file_to_include;
                $page_content = ob_get_clean();

                if (file_exists(__DIR__ . '/includes/header.php')) {
                    include __DIR__ . '/includes/header.php';
                } else {
                    route_log("Layout Error: header.php not found."); echo "Error: Header missing.";
                }
                echo $page_content; 
                if (file_exists(__DIR__ . '/includes/footer.php')) {
                    include __DIR__ . '/includes/footer.php';
                } else {
                    route_log("Layout Error: footer.php not found."); echo "Error: Footer missing.";
                }
            } else {
                include $file_to_include;
            }
        }
        exit;
    } else {
        route_log("File not found for route {$relative_path}: {$file_to_include}");
    }
}


// --- 404 Not Found ---
// This part is reached if no routes above matched for $relative_path
route_log("404 Not Found for relative path (within app context): {$relative_path}. Original request URI: {$request_uri}");
header("HTTP/1.0 404 Not Found");
$pageTitle = "404 Not Found"; 

if (file_exists(__DIR__ . '/includes/header.php')) {
    include __DIR__ . '/includes/header.php';
}

echo "<div class='container text-center glass mt-5 p-4'>";
echo "<h1 class='page-title' style='color:var(--error); background:none; -webkit-background-clip:unset; background-clip:unset;'>404 Not Found</h1>";
echo "<p class='text-secondary'>The page you requested at <code>" . htmlspecialchars($request_uri) . "</code> could not be found within the application.</p>";
echo "<p class='text-secondary'>We apologize for the inconvenience.</p>";
echo "<a href='{$base_path}/index' class='btn mt-3'>Go to App Homepage</a>"; // Link to app's index
echo "</div>";

if (file_exists(__DIR__ . '/includes/footer.php')) {
    include __DIR__ . '/includes/footer.php';
}
exit;
?>
