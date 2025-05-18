<?php
declare(strict_types=1);

// Fix: Set PROJECT_ROOT to the actual project root (one level up from /api)
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
    $dotenv->safeLoad();
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

// Define the base path for the application if it's in a subdirectory
// This is important for bramus/router if your app isn't at the domain root.
$scriptName = $_SERVER['SCRIPT_NAME'] ?? ''; // e.g., /index.php or /billing_app/index.php
$baseDir = dirname($scriptName); // e.g., / or /billing_app
if ($baseDir === '/' || $baseDir === '\\') {
    $baseDir = '';
}
define('BASE_PATH', $baseDir); // Will be '' or '/billing_app'

// --- Import necessary classes ---
use App\Auth\AuthController;
use App\Admin\AdminController;
use App\Product\ProductController;
use App\Staff\StaffController;
use App\Staff\MobileScannerController;
use App\Billing\BillController;
use App\Notification\NotificationController;
use App\Core\View;
use App\Core\Response as CoreResponse;
use App\Core\Request as CoreRequest;
use App\Core\Database;
use App\Middleware\AuthMiddleware; // Assuming this is your middleware class

// Bramus Router
use Bramus\Router\Router;

// Attempt database connection early
try {
    Database::connect();
} catch (\MongoDB\Driver\Exception\Exception $e) {
    http_response_code(503);
    error_log("Database Connection Error: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    $view = new View(PROJECT_ROOT . '/templates');
    $errorMessage = $appConfig['debug'] ? nl2br(htmlspecialchars("Database Connection Failed: " . $e->getMessage() . "\n\nTrace:\n" . $e->getTraceAsString())) : 'Database service is currently unavailable.';
    echo $view->render('error/500.php', ['pageTitle' => 'Database Error', 'message' => $errorMessage], 'layouts/minimal.php');
    exit;
} catch (\Throwable $e) {
    http_response_code(500);
    error_log("Early Setup Error: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    $view = new View(PROJECT_ROOT . '/templates');
    $errorMessage = $appConfig['debug'] ? nl2br(htmlspecialchars("Initialization Error: " . $e->getMessage() . "\n\nTrace:\n" . $e->getTraceAsString())) : 'An unexpected error occurred during application startup.';
    echo $view->render('error/500.php', ['pageTitle' => 'Initialization Error', 'message' => $errorMessage], 'layouts/minimal.php');
    exit;
}

// Create Router instance
$router = new Router();

// Custom 404 Handler
$router->set404(function () {
    http_response_code(404);
    $view = new View(PROJECT_ROOT . '/templates');
    echo $view->render('error/404.php', ['pageTitle' => '404 - Not Found', 'message' => 'The requested page could not be found.'], 'layouts/minimal.php');
});

// Global error handler (for exceptions not caught elsewhere or thrown by router)
// Note: Bramus Router's run() method might catch exceptions.
// This is a general fallback.
// It's better to handle AccessDeniedException in the middleware or before calling the controller.
// For now, this will catch unhandled exceptions from controller actions if they bubble up.
// Set a general error handler (optional, depends on how bramus/router handles exceptions)
/*
set_exception_handler(function (\Throwable $e) use ($appConfig) {
    error_log("Unhandled Exception in Router Scope: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    if (http_response_code() === 200) { // If no error code set yet
        http_response_code(500);
    }
    // Simplified error display for now. Adapt from previous index.php if needed.
    $view = new View(PROJECT_ROOT . '/templates');
    $errorMessage = ($appConfig['debug'] ?? false) ? nl2br(htmlspecialchars($e->getMessage() . "\n\nTrace:\n" . $e->getTraceAsString())) : 'An unexpected server error occurred.';
    $errorPage = ($e instanceof \App\Core\Exception\AccessDeniedException) ? 'error/403.php' : 'error/500.php';
    $pageTitle = ($e instanceof \App\Core\Exception\AccessDeniedException) ? '403 - Access Denied' : '500 - Server Error';
    echo $view->render($errorPage, ['pageTitle' => $pageTitle, 'message' => $errorMessage], 'layouts/minimal.php');
});
*/


// --- Helper function to create Request and Response objects ---
// We'll pass these to our controller methods.
function getRequestResponseObjects(): array {
    $request = new CoreRequest($_GET, $_POST, $_COOKIE, $_FILES, $_SERVER, file_get_contents('php://input'));
    $response = new CoreResponse();
    return [$request, $response];
}

// --- Middleware Definitions ---
// Bramus router allows 'before' middleware.
// Let's define a middleware check function that can be used.
$authMiddlewareHandler = function (...$roles) {
    return function () use ($roles) { // This inner function is what Bramus calls
        [$request, ] = getRequestResponseObjects(); // Get request for middleware
        $middleware = new AuthMiddleware();
        try {
            $middleware->handle($request, ...$roles); // Spread roles array
        } catch (\App\Core\Exception\AccessDeniedException $e) {
            http_response_code(403);
            $appConfig = require PROJECT_ROOT . '/config/app.php'; // Re-require if needed
            $isApiRequest = (strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest' || str_contains(strtolower($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json'));

            if ($isApiRequest) {
                (new CoreResponse())->json(['success' => false, 'message' => $e->getMessage() ?: 'Access Denied.'], 403);
            } else {
                if (!isset($_SESSION['user_id'])) {
                    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
                    $_SESSION['error_message'] = $e->getMessage() ?: 'You must be logged in.';
                    (new CoreResponse())->redirect(BASE_PATH . '/login'); // Prepend BASE_PATH
                } else {
                    $view = new View(PROJECT_ROOT . '/templates');
                    echo $view->render('error/403.php', ['pageTitle' => '403 - Access Denied', 'message' => $e->getMessage()], 'layouts/minimal.php');
                }
            }
            exit; // Stop further execution
        }
    };
};


// --- Define Routes ---

// Public pages
$router->get('/', function () {
    [$request, $response] = getRequestResponseObjects();
    (new AuthController())->showHomePage($request, $response);
});
$router->get('/login', function () {
    [$request, $response] = getRequestResponseObjects();
    (new AuthController())->showLoginForm($request, $response);
});
$router->post('/login', function () {
    [$request, $response] = getRequestResponseObjects();
    (new AuthController())->handleLogin($request, $response);
});

// Authenticated routes
$router->before('GET|POST|PUT|DELETE', '/admin/.*|/staff/.*|/api/.*|/logout', $authMiddlewareHandler()); // General auth for these groups

// Logout
$router->get('/logout', function () {
    [$request, $response] = getRequestResponseObjects();
    (new AuthController())->logout($request, $response);
});


// Admin Pages Group - with role-specific middleware
$router->mount('/admin', function () use ($router, $authMiddlewareHandler) {
    $router->before('GET|POST|PUT|DELETE', '/.*', $authMiddlewareHandler('admin'));

    $router->get('/dashboard', function () {
        [$request, $response] = getRequestResponseObjects();
        (new AdminController())->dashboard($request, $response);
    });
    $router->get('/products', function () {
        [$request, $response] = getRequestResponseObjects();
        (new ProductController())->index($request, $response);
    });
});

// Staff Pages Group - with role-specific middleware
$router->mount('/staff', function () use ($router, $authMiddlewareHandler) {
    $router->before('GET|POST|PUT|DELETE', '/.*', $authMiddlewareHandler('staff', 'admin'));

    $router->get('/pos', function () {
        [$request, $response] = getRequestResponseObjects();
        (new StaffController())->pos($request, $response);
    });
    $router->get('/bills', function () {
        [$request, $response] = getRequestResponseObjects();
        (new StaffController())->billView($request, $response);
    });
    $router->get('/mobile-scanner', function () {
        [$request, $response] = getRequestResponseObjects();
        (new MobileScannerController())->showScannerPage($request, $response);
    });
});

// API Routes Group
$router->mount('/api', function () use ($router, $authMiddlewareHandler) {
    // Some API routes might be public, others require auth. Apply middleware specifically.

    // Product API (admin only)
    $router->mount('/products', function() use ($router, $authMiddlewareHandler) {
        $router->before('GET|POST|PUT|DELETE', '/.*', $authMiddlewareHandler('admin'));
        $router->get('/', function () {
            [$request, $response] = getRequestResponseObjects();
            (new ProductController())->apiGetProducts($request, $response);
        });
        $router->post('/', function () {
            [$request, $response] = getRequestResponseObjects();
            (new ProductController())->apiAddProduct($request, $response);
        });
        $router->get('/(\w+)', function ($id) { // \w+ matches alphanumeric and underscore
            [$request, $response] = getRequestResponseObjects();
            (new ProductController())->apiGetProductById($request, $response, $id);
        });
        $router->put('/(\w+)', function ($id) {
            [$request, $response] = getRequestResponseObjects();
            (new ProductController())->apiUpdateProduct($request, $response, $id);
        });
        $router->delete('/(\w+)', function ($id) {
            [$request, $response] = getRequestResponseObjects();
            (new ProductController())->apiDeleteProduct($request, $response, $id);
        });
    });

    // Billing API (staff or admin)
    $router->mount('/bills', function() use ($router, $authMiddlewareHandler) {
        $router->before('GET|POST', '/.*', $authMiddlewareHandler('staff', 'admin'));
        $router->post('/generate', function () {
            [$request, $response] = getRequestResponseObjects();
            (new BillController())->apiGenerateBill($request, $response);
        });
        $router->get('/', function () {
            [$request, $response] = getRequestResponseObjects();
            (new BillController())->apiGetBills($request, $response);
        });
        $router->get('/(\w+)', function ($id) { // For /api/bills/{id}
            [$request, $response] = getRequestResponseObjects();
            // Pass $id to controller method (adapt controller method signature)
            (new BillController())->apiGetBillById($request, $response, ['id' => $id]);
        });
    });

    // Sales API (admin only)
    $router->get('/sales', function () use ($authMiddlewareHandler) {
        $authMiddlewareHandler('admin')(); // Execute middleware check
        [$request, $response] = getRequestResponseObjects();
        (new AdminController())->apiGetSales($request, $response);
    });

    // Notification API (any authenticated user)
    $router->mount('/notifications', function() use ($router, $authMiddlewareHandler) {
        $router->before('POST', '/.*', $authMiddlewareHandler()); // No specific role, just authenticated
        $router->post('/fetch', function () {
            [$request, $response] = getRequestResponseObjects();
            (new NotificationController())->apiFetch($request, $response);
        });
        $router->post('/mark-seen', function () {
            [$request, $response] = getRequestResponseObjects();
            (new NotificationController())->apiMarkSeen($request, $response);
        });
    });

    // Mobile Scanner API (staff or admin)
    $router->mount('/scanner', function() use ($router, $authMiddlewareHandler) {
        $router->before('GET|POST', '/.*', $authMiddlewareHandler('staff', 'admin'));
        $router->post('/activate-pos', function () {
            [$request, $response] = getRequestResponseObjects();
            (new MobileScannerController())->apiActivateDesktopScanning($request, $response);
        });
        $router->post('/deactivate-pos', function () {
            [$request, $response] = getRequestResponseObjects();
            (new MobileScannerController())->apiDeactivateDesktopScanning($request, $response);
        });
        $router->get('/check-pos-activation', function () {
            [$request, $response] = getRequestResponseObjects();
            (new MobileScannerController())->apiCheckDesktopActivation($request, $response);
        });
        $router->post('/activate-mobile', function () {
            [$request, $response] = getRequestResponseObjects();
            (new MobileScannerController())->apiActivateMobileSession($request, $response);
        });
        $router->post('/submit-scan', function () {
            [$request, $response] = getRequestResponseObjects();
            (new MobileScannerController())->apiSubmitScannedProduct($request, $response);
        });
        $router->get('/items', function () {
            [$request, $response] = getRequestResponseObjects();
            (new MobileScannerController())->apiGetScannedItemsForDesktop($request, $response);
        });
    });
});


// --- Run the router ---
// Bramus router might try to handle subfolder installs automatically.
// If your `index.php` is in the root and `.htaccess` in `public` rewrites to `../index.php`,
// the URI Bramus sees should be relative to the `public` folder.
// e.g. URL `http://localhost/project/public/login` -> `.htaccess` -> `../index.php/login`
// Bramus router might see `/login`.
// Test with error_log($router->getCurrentUri());
// error_log("Bramus current URI: " . $router->getCurrentUri());

// The router will output content or exit, so this script implicitly ends after run().
try {
    $router->run();
} catch (\App\Core\Exception\AccessDeniedException $e) { // Catch explicitly if not handled by middleware exit
    http_response_code(403);
    $isApiRequest = (strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest' || str_contains(strtolower($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json'));
    if ($isApiRequest) {
        (new CoreResponse())->json(['success' => false, 'message' => $e->getMessage() ?: 'Access Denied.'], 403);
    } else {
        $view = new View(PROJECT_ROOT . '/templates');
        echo $view->render('error/403.php', ['pageTitle' => '403 - Access Denied', 'message' => $e->getMessage()], 'layouts/minimal.php');
    }
    exit;
} catch (\Throwable $e) {
    error_log("Unhandled Exception during router run: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    if (http_response_code() === 200) http_response_code(500);
    $isApiRequest = (strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest' || str_contains(strtolower($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json'));
    $view = new View(PROJECT_ROOT . '/templates');
    $appConfig = require PROJECT_ROOT . '/config/app.php'; // Ensure $appConfig is available
    $errorMessage = ($appConfig['debug'] ?? false) ? nl2br(htmlspecialchars($e->getMessage() . "\n\nTrace:\n" . $e->getTraceAsString())) : 'An unexpected server error occurred.';

    if ($isApiRequest) {
        (new CoreResponse())->json(['success' => false, 'message' => ($appConfig['debug'] ?? false) ? $e->getMessage() : 'Server error'], 500);
    } else {
        echo $view->render('error/500.php', ['pageTitle' => '500 - Server Error', 'message' => $errorMessage], 'layouts/minimal.php');
    }
    exit;
}