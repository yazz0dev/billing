<?php // api/index.php

declare(strict_types=1);

if (!defined('PROJECT_ROOT')) {
    define('PROJECT_ROOT', dirname(__DIR__));
}

require PROJECT_ROOT . '/vendor/autoload.php';

// Define the base path if not already defined in the main index.php
if (!defined('BASE_PATH')) {
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    $baseDir = dirname($scriptName);
    if ($baseDir === '/' || $baseDir === '\\') {
        $baseDir = '';
    }
    define('BASE_PATH', $baseDir);
}

use App\Auth\AuthController;
use App\Admin\AdminController;
use App\Product\ProductController;
use App\Staff\StaffController;
use App\Staff\MobileScannerController;
use App\Billing\BillController;
use App\Notification\NotificationController;
use App\Core\Router;
use App\Core\View;
use App\Core\Response as CoreResponse; // Alias to avoid conflict if Response is used locally
use Core\Exception\RouteNotFoundException;
use Core\Exception\AccessDeniedException;

if (file_exists(PROJECT_ROOT . '/.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(PROJECT_ROOT);
    $dotenv->safeLoad(); // Use safeLoad to not error if .env is missing (e.g. in Vercel prod)
}

$appConfig = require PROJECT_ROOT . '/config/app.php';

if ($appConfig['env'] === 'development' || $appConfig['debug']) {
    ini_set('display_errors', '1'); error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0'); error_reporting(0);
    // Setup production error logging here (e.g., Monolog to a file or service)
}

if (session_status() === PHP_SESSION_NONE) {
    session_name($appConfig['session_name'] ?? 'APP_SESSION');
    session_start([
        'cookie_httponly' => true,
        'cookie_secure' => $appConfig['env'] === 'production',
        'cookie_samesite' => 'Lax',
    ]);
}

// Get the request URI and remove the base path if needed
$requestMethod = $_SERVER['REQUEST_METHOD'];
$requestUri = $_SERVER['REQUEST_URI'];

// Remove base path from request URI if it exists
if (!empty(BASE_PATH) && strpos($requestUri, BASE_PATH) === 0) {
    $requestUri = substr($requestUri, strlen(BASE_PATH));
}

$router = new Router();

// --- Define Routes ---
// Public pages
$router->addRoute('GET', '/',           [AuthController::class, 'showHomePage']);
$router->addRoute('GET', '/billing',    [AuthController::class, 'showHomePage']); // Add explicit route for /billing
$router->addRoute('GET', '/login',      [AuthController::class, 'showLoginForm']);
$router->addRoute('POST', '/login',     [AuthController::class, 'handleLogin']);
$router->addRoute('GET', '/logout',     [AuthController::class, 'logout']);

// Admin Pages (example with middleware placeholder)
$router->addRoute('GET', '/admin/dashboard',  ['handler' => [AdminController::class, 'dashboard'], 'middleware' => 'auth:admin']);
$router->addRoute('GET', '/admin/products',   ['handler' => [ProductController::class, 'index'], 'middleware' => 'auth:admin']);

// Staff Pages
$router->addRoute('GET', '/staff/pos',        ['handler' => [StaffController::class, 'pos'], 'middleware' => 'auth:staff,admin']);
$router->addRoute('GET', '/staff/bills',      ['handler' => [StaffController::class, 'billView'], 'middleware' => 'auth:staff,admin']);
$router->addRoute('GET', '/staff/mobile-scanner', ['handler' => [MobileScannerController::class, 'showScannerPage'], 'middleware' => 'auth:staff,admin']);


// API Routes (No layout, controllers will use $response->json())
// Product API
$router->addRoute('GET', '/api/products',       ['handler' => [ProductController::class, 'apiGetProducts'], 'middleware' => 'auth:admin']);
$router->addRoute('POST', '/api/products',      ['handler' => [ProductController::class, 'apiAddProduct'], 'middleware' => 'auth:admin']);
$router->addRoute('GET', '/api/products/{id}',  ['handler' => [ProductController::class, 'apiGetProductById'], 'middleware' => 'auth:admin']);
$router->addRoute('PUT', '/api/products/{id}',  ['handler' => [ProductController::class, 'apiUpdateProduct'], 'middleware' => 'auth:admin']);
$router->addRoute('DELETE', '/api/products/{id}',['handler' => [ProductController::class, 'apiDeleteProduct'], 'middleware' => 'auth:admin']);

// Billing API
$router->addRoute('POST', '/api/bills/generate', ['handler' => [BillController::class, 'apiGenerateBill'], 'middleware' => 'auth:staff,admin']);
$router->addRoute('GET', '/api/bills',          ['handler' => [BillController::class, 'apiGetBills'], 'middleware' => 'auth:staff,admin']);
$router->addRoute('GET', '/api/sales',          ['handler' => [AdminController::class, 'apiGetSales'], 'middleware' => 'auth:admin']);

// Notification API
$router->addRoute('POST', '/api/notifications/fetch', ['handler' => [NotificationController::class, 'apiFetch'], 'middleware' => 'auth']); // any authenticated user
$router->addRoute('POST', '/api/notifications/mark-seen', ['handler' => [NotificationController::class, 'apiMarkSeen'], 'middleware' => 'auth']);

// Mobile Scanner API
$router->addRoute('POST', '/api/scanner/activate-pos', ['handler' => [MobileScannerController::class, 'apiActivateDesktopScanning'], 'middleware' => 'auth:staff,admin']);
$router->addRoute('POST', '/api/scanner/deactivate-pos', ['handler' => [MobileScannerController::class, 'apiDeactivateDesktopScanning'], 'middleware' => 'auth:staff,admin']);
$router->addRoute('GET', '/api/scanner/check-pos-activation', ['handler' => [MobileScannerController::class, 'apiCheckDesktopActivation'], 'middleware' => 'auth:staff,admin']);
$router->addRoute('POST', '/api/scanner/activate-mobile', ['handler' => [MobileScannerController::class, 'apiActivateMobileSession'], 'middleware' => 'auth:staff,admin']); // mobile is logged in as staff/admin
$router->addRoute('POST', '/api/scanner/submit-scan', ['handler' => [MobileScannerController::class, 'apiSubmitScannedProduct'], 'middleware' => 'auth:staff,admin']);
$router->addRoute('GET', '/api/scanner/items', ['handler' => [MobileScannerController::class, 'apiGetScannedItemsForDesktop'], 'middleware' => 'auth:staff,admin']);


// Dispatch
try {
    $router->dispatch($requestMethod, $requestUri);
} catch (RouteNotFoundException $e) {
    http_response_code(404);
    $view = new View(PROJECT_ROOT . '/templates');
    echo $view->render('error/404.php', ['pageTitle' => '404 - Not Found', 'message' => $e->getMessage()], 'layouts/minimal.php');
} catch (AccessDeniedException $e) {
    http_response_code(403);
    // If AJAX request, return JSON error, else render 403 page or redirect
    if (strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest' || str_contains(strtolower($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json')) {
        (new CoreResponse())->json(['success' => false, 'message' => $e->getMessage() ?: 'Access Denied.'], 403);
    } else {
        $_SESSION['error_message'] = $e->getMessage() ?: 'You do not have permission to access this page.';
        // Check if user is logged in at all
        if (!isset($_SESSION['user_id'])) {
            $_SESSION['redirect_after_login'] = $requestUri; // Store intended URL
            (new CoreResponse())->redirect('/login');
        } else {
            // User is logged in but lacks role, show a 403 page
            $view = new View(PROJECT_ROOT . '/templates');
            echo $view->render('error/403.php', ['pageTitle' => '403 - Access Denied', 'message' => $e->getMessage()], 'layouts/minimal.php');
        }
    }
} catch (\Throwable $e) { // Catch all other throwables
    error_log("Unhandled Exception: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    http_response_code(500);
    $view = new View(PROJECT_ROOT . '/templates');
    $errorMessage = $appConfig['debug'] ? nl2br(htmlspecialchars($e->getMessage() . "\n\nTrace:\n" . $e->getTraceAsString())) : 'An unexpected server error occurred.';
    echo $view->render('error/500.php', ['pageTitle' => '500 - Server Error', 'message' => $errorMessage], 'layouts/minimal.php');
}
