<?php // api/index.php

declare(strict_types=1);

// PROJECT_ROOT and BASE_PATH are now defined in the including script (./index.php)
// Environment variables, app config ($appConfig), and session are also handled there.

// Use classes from the App namespace - Keep these uses
use App\Auth\AuthController;
use App\Admin\AdminController;
use App\Product\ProductController;
use App\Staff\StaffController;
use App\Staff\MobileScannerController;
use App\Billing\BillController;
use App\Notification\NotificationController;
use App\Core\Router;
use App\Core\View;
use App\Core\Response as CoreResponse;
use App\Core\Exception\RouteNotFoundException;
use App\Core\Exception\AccessDeniedException;

// Get the request URI and method from $_SERVER - Keep this
$requestMethod = $_SERVER['REQUEST_METHOD'];
$requestUri = $_SERVER['REQUEST_URI'];

// Remove base path from request URI if it exists - Keep this, BASE_PATH is defined externally
if (!empty(BASE_PATH) && strpos($requestUri, BASE_PATH) === 0) {
    $requestUri = substr($requestUri, strlen(BASE_PATH));
}
// If the result is empty, set it to root path '/'
if ($requestUri === '' || $requestUri === '/') {
    $requestUri = '/';
} else {
    // Ensure it starts with a slash if it doesn't (e.g. after removing BASE_PATH)
    if (strpos($requestUri, '/') !== 0) {
         $requestUri = '/' . $requestUri;
    }
}


$router = new Router(); // Router constructor correctly uses BASE_PATH if defined

// --- Define Routes --- (Keep all route definitions as they are correct)
// ... (Your existing route definitions here) ...
// Public pages
$router->addRoute('GET', '/',           [AuthController::class, 'showHomePage']);
// The /billing route should also show the home page if not logged in,
// and will redirect if logged in, same as '/'.
$router->addRoute('GET', '/billing',    [AuthController::class, 'showHomePage']);
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


// Dispatch - Keep this try/catch block here for centralized handling
try {
    $router->dispatch($requestMethod, $requestUri);
} catch (RouteNotFoundException $e) {
    http_response_code(404);
    // Access $appConfig defined in index.php
    $view = new View(PROJECT_ROOT . '/templates');
    echo $view->render('error/404.php', ['pageTitle' => '404 - Not Found', 'message' => $e->getMessage(), 'appConfig' => $appConfig], 'layouts/minimal.php');
    exit; // Ensure script stops
} catch (AccessDeniedException $e) {
    http_response_code(403);
    // If AJAX request, return JSON error, else render 403 page or redirect
    if (strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest' || str_contains(strtolower($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json')) {
        (new CoreResponse())->json(['success' => false, 'message' => $e->getMessage() ?: 'Access Denied.'], 403);
    } else {
        // Store initial message in session to be picked up by minimal layout JS
        $_SESSION['initial_page_message'] = ['type' => 'error', 'text' => $e->getMessage() ?: 'You do not have permission to access this page.'];

        // Check if user is logged in at all
        if (!isset($_SESSION['user_id'])) {
            // Store intended URL only if they were trying to access something restricted while logged out
             if (!str_contains($requestUri, '/login') && !str_contains($requestUri, '/logout') && $requestUri !== '/') {
                 $_SESSION['redirect_after_login'] = $requestUri; // Store intended URL
             }
            (new CoreResponse())->redirect('/login');
        } else {
            // User is logged in but lacks role, show a 403 page
            $view = new View(PROJECT_ROOT . '/templates');
            // Access $appConfig and $_SESSION defined in index.php
            echo $view->render('error/403.php', ['pageTitle' => '403 - Access Denied', 'message' => $e->getMessage(), 'appConfig' => $appConfig, 'session' => $_SESSION], 'layouts/minimal.php');
        }
    }
     exit; // Ensure script stops after rendering or redirecting
} catch (\Throwable $e) { // Catch all other throwables
    error_log("Unhandled Exception: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    http_response_code(500);
    $view = new View(PROJECT_ROOT . '/templates');
     // Access $appConfig defined in index.php
    $errorMessage = ($appConfig['debug'] ?? false) ? nl2br(htmlspecialchars($e->getMessage() . "\n\nTrace:\n" . $e->getTraceAsString())) : 'An unexpected server error occurred.';
    echo $view->render('error/500.php', ['pageTitle' => '500 - Server Error', 'message' => $errorMessage, 'appConfig' => $appConfig], 'layouts/minimal.php');
     exit; // Ensure script stops
}