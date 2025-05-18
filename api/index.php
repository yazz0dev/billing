<?php // api/index.php

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));

require PROJECT_ROOT . '/vendor/autoload.php';

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

$requestMethod = $_SERVER['REQUEST_METHOD'];
$requestUri = $_SERVER['REQUEST_URI']; // Router will parse path

$router = new App\Core\Router();

// --- Define Routes ---
// Public pages
$router->addRoute('GET', '/',           [App\Auth\AuthController::class, 'showHomePage']);
$router->addRoute('GET', '/login',      [App\Auth\AuthController::class, 'showLoginForm']);
$router->addRoute('POST', '/login',     [App\Auth\AuthController::class, 'handleLogin']);
$router->addRoute('GET', '/logout',     [App\Auth\AuthController::class, 'logout']);

// Admin Pages (example with middleware placeholder)
$router->addRoute('GET', '/admin/dashboard',  ['handler' => [App\Admin\AdminController::class, 'dashboard'], 'middleware' => 'auth:admin']);
$router->addRoute('GET', '/admin/products',   ['handler' => [App\Product\ProductController::class, 'index'], 'middleware' => 'auth:admin']);

// Staff Pages
$router->addRoute('GET', '/staff/pos',        ['handler' => [App\Staff\StaffController::class, 'pos'], 'middleware' => 'auth:staff,admin']);
$router->addRoute('GET', '/staff/bills',      ['handler' => [App\Staff\StaffController::class, 'billView'], 'middleware' => 'auth:staff,admin']);
$router->addRoute('GET', '/staff/mobile-scanner', ['handler' => [App\Staff\MobileScannerController::class, 'showScannerPage'], 'middleware' => 'auth:staff,admin']);


// API Routes (No layout, controllers will use $response->json())
// Product API
$router->addRoute('GET', '/api/products',       ['handler' => [App\Product\ProductController::class, 'apiGetProducts'], 'middleware' => 'auth:admin']);
$router->addRoute('POST', '/api/products',      ['handler' => [App\Product\ProductController::class, 'apiAddProduct'], 'middleware' => 'auth:admin']);
$router->addRoute('GET', '/api/products/{id}',  ['handler' => [App\Product\ProductController::class, 'apiGetProductById'], 'middleware' => 'auth:admin']);
$router->addRoute('PUT', '/api/products/{id}',  ['handler' => [App\Product\ProductController::class, 'apiUpdateProduct'], 'middleware' => 'auth:admin']);
$router->addRoute('DELETE', '/api/products/{id}',['handler' => [App\Product\ProductController::class, 'apiDeleteProduct'], 'middleware' => 'auth:admin']);

// Billing API
$router->addRoute('POST', '/api/bills/generate', ['handler' => [App\Billing\BillController::class, 'apiGenerateBill'], 'middleware' => 'auth:staff,admin']);
$router->addRoute('GET', '/api/bills',          ['handler' => [App\Billing\BillController::class, 'apiGetBills'], 'middleware' => 'auth:staff,admin']);
$router->addRoute('GET', '/api/sales',          ['handler' => [App\Admin\AdminController::class, 'apiGetSales'], 'middleware' => 'auth:admin']);

// Notification API
$router->addRoute('POST', '/api/notifications/fetch', ['handler' => [App\Notification\NotificationController::class, 'apiFetch'], 'middleware' => 'auth']); // any authenticated user
$router->addRoute('POST', '/api/notifications/mark-seen', ['handler' => [App\Notification\NotificationController::class, 'apiMarkSeen'], 'middleware' => 'auth']);

// Mobile Scanner API
$router->addRoute('POST', '/api/scanner/activate-pos', ['handler' => [App\Staff\MobileScannerController::class, 'apiActivateDesktopScanning'], 'middleware' => 'auth:staff,admin']);
$router->addRoute('POST', '/api/scanner/deactivate-pos', ['handler' => [App\Staff\MobileScannerController::class, 'apiDeactivateDesktopScanning'], 'middleware' => 'auth:staff,admin']);
$router->addRoute('GET', '/api/scanner/check-pos-activation', ['handler' => [App\Staff\MobileScannerController::class, 'apiCheckDesktopActivation'], 'middleware' => 'auth:staff,admin']);
$router->addRoute('POST', '/api/scanner/activate-mobile', ['handler' => [App\Staff\MobileScannerController::class, 'apiActivateMobileSession'], 'middleware' => 'auth:staff,admin']); // mobile is logged in as staff/admin
$router->addRoute('POST', '/api/scanner/submit-scan', ['handler' => [App\Staff\MobileScannerController::class, 'apiSubmitScannedProduct'], 'middleware' => 'auth:staff,admin']);
$router->addRoute('GET', '/api/scanner/items', ['handler' => [App\Staff\MobileScannerController::class, 'apiGetScannedItemsForDesktop'], 'middleware' => 'auth:staff,admin']);


// Dispatch
try {
    $router->dispatch($requestMethod, $requestUri);
} catch (Core\Exception\RouteNotFoundException $e) {
    http_response_code(404);
    $view = new App\Core\View(PROJECT_ROOT . '/templates');
    echo $view->render('error/404.php', ['pageTitle' => '404 - Not Found', 'message' => $e->getMessage()], 'layouts/minimal.php');
} catch (Core\Exception\AccessDeniedException $e) {
    http_response_code(403);
    // If AJAX request, return JSON error, else render 403 page or redirect
    if (strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest' || str_contains(strtolower($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json')) {
        (new App\Core\Response())->json(['success' => false, 'message' => $e->getMessage() ?: 'Access Denied.'], 403);
    } else {
        $_SESSION['error_message'] = $e->getMessage() ?: 'You do not have permission to access this page.';
        // Check if user is logged in at all
        if (!isset($_SESSION['user_id'])) {
            $_SESSION['redirect_after_login'] = $requestUri; // Store intended URL
            (new App\Core\Response())->redirect('/login');
        } else {
            // User is logged in but lacks role, show a 403 page
            $view = new App\Core\View(PROJECT_ROOT . '/templates');
            echo $view->render('error/403.php', ['pageTitle' => '403 - Access Denied', 'message' => $e->getMessage()], 'layouts/minimal.php');
        }
    }
} catch (\Throwable $e) { // Catch all other throwables
    error_log("Unhandled Exception: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    http_response_code(500);
    $view = new App\Core\View(PROJECT_ROOT . '/templates');
    $errorMessage = $appConfig['debug'] ? nl2br(htmlspecialchars($e->getMessage() . "\n\nTrace:\n" . $e->getTraceAsString())) : 'An unexpected server error occurred.';
    echo $view->render('error/500.php', ['pageTitle' => '500 - Server Error', 'message' => $errorMessage], 'layouts/minimal.php');
}
