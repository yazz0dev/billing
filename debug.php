<?php // ./debug.php
declare(strict_types=1);

// PROJECT_ROOT is the directory where debug.php resides
define('PROJECT_ROOT', __DIR__);

// --- WARNING ---
// THIS SCRIPT PROVIDES POWERFUL DEBUGGING CAPABILITIES AND DIRECT DATABASE ACCESS.
// IT SHOULD NEVER BE ACCESSIBLE IN A PRODUCTION ENVIRONMENT.
// SECURE IT APPROPRIATELY (E.G., IP WHITELISTING, HTTP BASIC AUTH, OR REMOVE IT).

// Include composer autoloader
if (file_exists(PROJECT_ROOT . '/vendor/autoload.php')) {
    require PROJECT_ROOT . '/vendor/autoload.php';
} else {
    http_response_code(500);
    die("Composer autoload not found. Please run 'composer install'.");
}

// Basic Error Handling for Debug Script
ini_set('display_errors', '1');
error_reporting(E_ALL);
set_exception_handler(function ($exception) {
    echo "<h2>Unhandled Exception:</h2>";
    echo "<pre>";
    echo "Message: " . htmlspecialchars($exception->getMessage()) . "\n";
    echo "File: " . htmlspecialchars($exception->getFile()) . ":" . $exception->getLine() . "\n";
    echo "Trace:\n" . htmlspecialchars($exception->getTraceAsString());
    echo "</pre>";
});

// Load Environment Variables
if (file_exists(PROJECT_ROOT . '/.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(PROJECT_ROOT);
    $dotenv->safeLoad();
} else {
     echo '<p class="warning">.env file not found at project root. Using default configurations or environment variables.</p>';
}


// Load Application Configuration
// Use include_once in case this config is required elsewhere
$appConfig = include_once PROJECT_ROOT . '/config/app.php';
if ($appConfig === false) {
     http_response_code(500);
     die("Application configuration file not found or has an error.");
}


// --- SECURITY CHECK ---
// Simple IP check (replace with your development IP or remove if locally hosted and secured)
$allowed_ips = ['127.0.0.1', '::1']; // Add your development machine's IP if needed
// WARNING: $_SERVER['REMOTE_ADDR'] can be spoofed or incorrect behind proxies.
// Use more robust methods if needed, or rely on external security measures.
if (($appConfig['env'] ?? 'production') !== 'development' && !in_array($_SERVER['REMOTE_ADDR'], $allowed_ips)) {
    http_response_code(403);
    die('Access Denied. This script is for development purposes only and should not be publicly accessible in production.');
}

// Start session if not already started (some services might need it)
if (session_status() === PHP_SESSION_NONE) {
    session_name($appConfig['session_name'] ?? 'APP_SESSION');
    session_start([
        'cookie_httponly' => true,
        'cookie_secure' => ($appConfig['env'] ?? 'production') === 'production', // Should be false in dev typically
        'cookie_samesite' => 'Lax',
    ]);
}

// --- Helper Function for Output ---
function debug_output($title, $data, $isHtml = false) {
    echo "<h2>" . htmlspecialchars($title) . "</h2>";
    if ($isHtml) {
        echo $data;
    } else {
        echo "<pre>" . htmlspecialchars(print_r($data, true)) . "</pre>";
    }
    echo "<hr>";
}

// --- Debug Actions ---
$action = $_GET['action'] ?? 'default';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Application Debug Console</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background-color: #f4f4f4; color: #333; }
        h1 { color: #333; border-bottom: 2px solid #333; padding-bottom: 10px; }
        h2 { color: #555; margin-top: 30px; }
        pre { background-color: #fff; border: 1px solid #ddd; padding: 15px; overflow-x: auto; white-space: pre-wrap; word-wrap: break-word; }
        .menu { margin-bottom: 20px; padding: 10px; background-color: #e9e9e9; border: 1px solid #ccc; }
        .menu a { margin-right: 15px; text-decoration: none; color: #007bff; }
        .menu a:hover { text-decoration: underline; }
        .warning { color: red; font-weight: bold; }
        .code-input textarea { width: 100%; min-height: 150px; margin-bottom: 10px; }
        .code-input input[type="submit"] { padding: 10px 15px; background-color: #dc3545; color: white; border: none; cursor: pointer; }
        .code-input input[type="submit"]:hover { background-color: #c82333; }
        table { border-collapse: collapse; width: 100%; margin-bottom: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f0f0f0; }
        .success { color: green; }
        .error { color: red; }
    </style>
</head>
<body>
    <h1>Application Debug Console</h1>
    <p class="warning">Warning: This console provides direct access to application internals. Use with caution.</p>

    <div class="menu">
        <a href="?action=default">Home</a> |
        <a href="?action=phpinfo">PHP Info</a> |
        <a href="?action=session">Session Data</a> |
        <a href="?action=config">App Config</a> |
        <a href="?action=db_status">DB Status</a> |
        <a href="?action=routes">List Routes</a>
        <br>
        <strong>Products:</strong>
        <a href="?action=list_products">List Products</a> |
        <a href="?action=get_product_form">Get Product by ID</a>
        <br>
        <strong>Notifications:</strong>
        <a href="?action=create_test_notification">Create Test Notification</a> |
        <a href="?action=list_notifications_form">List User Notifications</a> |
        <a href="?action=clear_notifications" onclick="return confirm('Are you sure you want to clear ALL notifications?');">Clear All Notifications</a>
        <br>
        <strong>Billing:</strong>
        <a href="?action=generate_test_bill">Generate Test Bill</a> |
        <a href="?action=list_bills">List Bills</a> |
        <a href="?action=clear_bills" onclick="return confirm('DANGER: Are you sure you want to clear ALL bills? This is irreversible!');">Clear All Bills</a>
        <br>
        <strong>Advanced:</strong>
        <a href="?action=eval_code_form">Execute PHP (DANGEROUS)</a>
    </div>

    <?php
    // --- Action Handling ---
    switch ($action) {
        case 'phpinfo':
            ob_start();
            phpinfo();
            $phpinfoOutput = ob_get_clean();
            debug_output('PHP Information', $phpinfoOutput, true);
            break;

        case 'session':
            debug_output('Current Session Data', $_SESSION ?? []);
            break;

        case 'config':
            debug_output('Application Configuration', $appConfig);
            break;

        case 'db_status':
            if (!class_exists('MongoDB\Client')) {
                 debug_output('Database Connection Status', '<span class="error">MongoDB PHP Driver not found or not enabled. Please install/enable the `mongodb` extension.</span>');
                break;
            }
            try {
                // Database connection is handled within the Database class now
                $client = App\Core\Database::getClient(); // Get client via static method
                $client->listDatabases(); // Simple command to check connection
                debug_output('Database Connection Status', '<span class="success">Successfully connected to MongoDB.</span>');
            } catch (\MongoDB\Driver\Exception\Exception $e) {
                debug_output('Database Connection Status', '<span class="error">Failed to connect:</span> ' . htmlspecialchars($e->getMessage()) . "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>", true);
            } catch (\Exception $e) {
                 debug_output('Database Connection Status', '<span class="error">Error during database check:</span> ' . htmlspecialchars($e->getMessage()) . "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>", true);
            }
            break;

        case 'routes':
            // This requires access to the router instance and its routes.
            // For simplicity, we'll re-instantiate and define routes as in api/index.php
            // This is not ideal as it duplicates route definitions but works for debug.
            // A better way would be to have a global registry or service for routes.
            ob_start();
            echo "<p>Note: This is a re-parsed list of routes based on api/index.php definitions. Middleware details might be simplified.</p>";
            echo "<table><thead><tr><th>Method</th><th>Path</th><th>Handler</th><th>Middleware</th></tr></thead><tbody>";
            $router = new App\Core\Router();
            // Re-add routes (Copied from api/index.php)
            // --- Define Routes (Copied from api/index.php) ---
            // Public pages
            $router->addRoute('GET', '/',           [\App\Auth\AuthController::class, 'showHomePage']);
            $router->addRoute('GET', '/billing',    [\App\Auth\AuthController::class, 'showHomePage']); // Add explicit route for /billing
            $router->addRoute('GET', '/login',      [\App\Auth\AuthController::class, 'showLoginForm']);
            $router->addRoute('POST', '/login',     [\App\Auth\AuthController::class, 'handleLogin']);
            $router->addRoute('GET', '/logout',     [\App\Auth\AuthController::class, 'logout']);

            // Admin Pages (example with middleware placeholder)
            $router->addRoute('GET', '/admin/dashboard',  ['handler' => [\App\Admin\AdminController::class, 'dashboard'], 'middleware' => 'auth:admin']);
            $router->addRoute('GET', '/admin/products',   ['handler' => [\App\Product\ProductController::class, 'index'], 'middleware' => 'auth:admin']);

            // Staff Pages
            $router->addRoute('GET', '/staff/pos',        ['handler' => [\App\Staff\StaffController::class, 'pos'], 'middleware' => 'auth:staff,admin']);
            $router->addRoute('GET', '/staff/bills',      ['handler' => [\App\Staff\StaffController::class, 'billView'], 'middleware' => 'auth:staff,admin']);
            $router->addRoute('GET', '/staff/mobile-scanner', ['handler' => [\App\Staff\MobileScannerController::class, 'showScannerPage'], 'middleware' => 'auth:staff,admin']);


            // API Routes (No layout, controllers will use $response->json())
            // Product API
            $router->addRoute('GET', '/api/products',       ['handler' => [\App\Product\ProductController::class, 'apiGetProducts'], 'middleware' => 'auth:admin']);
            $router->addRoute('POST', '/api/products',      ['handler' => [\App\Product\ProductController::class, 'apiAddProduct'], 'middleware' => 'auth:admin']);
            $router->addRoute('GET', '/api/products/{id}',  ['handler' => [\App\Product\ProductController::class, 'apiGetProductById'], 'middleware' => 'auth:admin']);
            $router->addRoute('PUT', '/api/products/{id}',  ['handler' => [\App\Product\ProductController::class, 'apiUpdateProduct'], 'middleware' => 'auth:admin']);
            $router->addRoute('DELETE', '/api/products/{id}',['handler' => [\App\Product\ProductController::class, 'apiDeleteProduct'], 'middleware' => 'auth:admin']);

            // Billing API
            $router->addRoute('POST', '/api/bills/generate', ['handler' => [\App\Billing\BillController::class, 'apiGenerateBill'], 'middleware' => 'auth:staff,admin']);
            $router->addRoute('GET', '/api/bills',          ['handler' => [\App\Billing\BillController::class, 'apiGetBills'], 'middleware' => 'auth:staff,admin']);
            $router->addRoute('GET', '/api/sales',          ['handler' => [\App\Admin\AdminController::class, 'apiGetSales'], 'middleware' => 'auth:admin']);

            // Notification API
            $router->addRoute('POST', '/api/notifications/fetch', ['handler' => [\App\Notification\NotificationController::class, 'apiFetch'], 'middleware' => 'auth']); // any authenticated user
            $router->addRoute('POST', '/api/notifications/mark-seen', ['handler' => [\App\Notification\NotificationController::class, 'apiMarkSeen'], 'middleware' => 'auth']);

            // Mobile Scanner API
            $router->addRoute('POST', '/api/scanner/activate-pos', ['handler' => [\App\Staff\MobileScannerController::class, 'apiActivateDesktopScanning'], 'middleware' => 'auth:staff,admin']);
            $router->addRoute('POST', '/api/scanner/deactivate-pos', ['handler' => [\App\Staff\MobileScannerController::class, 'apiDeactivateDesktopScanning'], 'middleware' => 'auth:staff,admin']);
            $router->addRoute('GET', '/api/scanner/check-pos-activation', ['handler' => [\App\Staff\MobileScannerController::class, 'apiCheckDesktopActivation'], 'middleware' => 'auth:staff,admin']);
            $router->addRoute('POST', '/api/scanner/activate-mobile', ['handler' => [\App\Staff\MobileScannerController::class, 'apiActivateMobileSession'], 'middleware' => 'auth:staff,admin']); // mobile is logged in as staff/admin
            $router->addRoute('POST', '/api/scanner/submit-scan', ['handler' => [\App\Staff\MobileScannerController::class, 'apiSubmitScannedProduct'], 'middleware' => 'auth:staff,admin']);
            $router->addRoute('GET', '/api/scanner/items', ['handler' => [\App\Staff\MobileScannerController::class, 'apiGetScannedItemsForDesktop'], 'middleware' => 'auth:staff,admin']);
            // --- End Copied Routes ---


            // Access protected property $routes using Reflection
            // Need to include Reflection classes


            try {
                 $reflectionRouter = new ReflectionClass(App\Core\Router::class);
                 $routesProperty = $reflectionRouter->getProperty('routes');
                 $routesProperty->setAccessible(true);
                 $definedRoutes = $routesProperty->getValue($router);

                 foreach ($definedRoutes as $method => $paths) {
                     foreach ($paths as $path => $config) {
                         $handlerConfig = $config['handler'] ?? $config;
                         $handlerDisplay = is_array($handlerConfig) && count($handlerConfig) === 2 && is_string($handlerConfig[0]) && is_string($handlerConfig[1])
                             ? ($handlerConfig[0] . '::' . $handlerConfig[1])
                             : 'Invalid Handler';
                         $middlewareDisplay = isset($config['middleware']) ? (is_array($config['middleware']) ? implode(', ', $config['middleware']) : $config['middleware']) : 'None';
                         echo "<tr><td>{$method}</td><td>" . htmlspecialchars($path) . "</td><td>" . htmlspecialchars($handlerDisplay) . "</td><td>" . htmlspecialchars($middlewareDisplay) . "</td></tr>";
                     }
                 }
             } catch (ReflectionException $e) {
                 echo "<tr><td colspan='4'><span class='error'>Error listing routes: {$e->getMessage()}</span></td></tr>";
             }

            echo "</tbody></table>";
            debug_output('Registered Routes', ob_get_clean(), true);
            break;

        case 'list_products':
            try {
                // Need to ensure Database connection is established before calling services
                if (!class_exists('MongoDB\Client')) {
                    debug_output('Error Listing Products', '<span class="error">MongoDB PHP Driver not found or not enabled.</span>');
                    break;
                }
                 App\Core\Database::connect(); // Explicitly connect if needed
                $productService = new App\Product\ProductService();
                $products = $productService->getAllProducts();
                debug_output('All Products', $products);
            } catch (Error $e) { // Catch Error for class not found issues
                debug_output('Error Listing Products', "<span class='error'>Critical Error:</span> " . htmlspecialchars($e->getMessage()) . "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>", true);
            } catch (Exception $e) {
                debug_output('Error Listing Products', "<span class='error'>Error:</span> " . htmlspecialchars($e->getMessage()) . "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>", true);
            }
            break;

        case 'get_product_form':
            echo '<h2>Get Product by ID</h2>';
            echo '<form method="GET">';
            echo '<input type="hidden" name="action" value="get_product">';
            echo 'Product ID (MongoDB ObjectId string): <input type="text" name="id" placeholder="e.g., 60c72b2f9b1e8a3f9c8b4567" required> ';
            echo '<input type="submit" value="Fetch Product">';
            echo '</form>';
            break;

        case 'get_product':
            $productId = $_GET['id'] ?? null;
            if ($productId) {
                try {
                    if (!class_exists('MongoDB\Client')) {
                        debug_output('Error Fetching Product', '<span class="error">MongoDB PHP Driver not found or not enabled.</span>');
                        break;
                    }
                    App\Core\Database::connect();
                    $productService = new App\Product\ProductService();
                    $product = $productService->getProductById((string)$productId);
                    debug_output('Product Details (ID: ' . htmlspecialchars((string)$productId) . ')', $product ?: 'Product not found.');
                } catch (Error $e) {
                    debug_output('Error Fetching Product', "<span class='error'>Critical Error:</span> " . htmlspecialchars($e->getMessage()) . "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>", true);
                } catch (Exception $e) {
                     debug_output('Error Fetching Product', "<span class='error'>Error:</span> " . htmlspecialchars($e->getMessage()) . "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>", true);
                }
            } else {
                debug_output('Get Product', 'Product ID is required.');
            }
            break;

        case 'create_test_notification':
            try {
                if (!class_exists('MongoDB\Client')) {
                    debug_output('Error Creating Test Notification', '<span class="error">MongoDB PHP Driver not found or not enabled.</span>');
                    break;
                }
                App\Core\Database::connect();
                $notificationService = new App\Notification\NotificationService();
                // Example: Create a notification for 'admin' role.
                $notificationId = $notificationService->create(
                    'This is a test notification from debug.php at ' . date('Y-m-d H:i:s'),
                    'info',
                    'admin', // Target role or user_id
                    10000,   // Duration in ms (0 for indefinite)
                    'Test Event'
                );
                debug_output('Create Test Notification', $notificationId ? "<span class='success'>Notification created with ID: {$notificationId}</span>" : "<span class='error'>Failed to create notification.</span>", true);
            } catch (Error $e) {
                 debug_output('Error Creating Test Notification', "<span class='error'>Critical Error:</span> " . htmlspecialchars($e->getMessage()) . "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>", true);
            } catch (Exception $e) {
                debug_output('Error Creating Test Notification', "<span class='error'>Error:</span> " . htmlspecialchars($e->getMessage()) . "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>", true);
            }
            break;

        case 'list_notifications_form':
            echo '<h2>List Notifications for User/Role</h2>';
            echo '<form method="GET">';
            echo '<input type="hidden" name="action" value="list_notifications">';
            echo 'Target (User ID, "admin", "staff", or "all"): <input type="text" name="target" placeholder="e.g., 60c72b2f9b1e8a3f9c8b4567 or admin" required> ';
            echo '<input type="submit" value="List Notifications">';
            echo '</form>';
            break;

        case 'list_notifications':
            $target = $_GET['target'] ?? null;
             if ($target) {
                try {
                    if (!class_exists('MongoDB\Client')) {
                        debug_output('Error Listing Notifications', '<span class="error">MongoDB PHP Driver not found or not enabled.</span>');
                        break;
                    }
                    App\Core\Database::connect();
                    $notificationService = new App\Notification\NotificationService();
                    // Adapt this based on your NotificationService/Repository capabilities
                    // getNotificationsForUser expects user ID and role. Mock them if target is a role or 'all'.
                    $mockUserId = ($target === 'admin' || $target === 'staff' || $target === 'all') ? 'mock_user_id' . uniqid() : $target; // Use a unique mock ID or provided ID
                    $mockUserRole = ($target === 'admin' || $target === 'staff') ? $target : 'user'; // Use provided role or generic

                    $notifications = $notificationService->getNotificationsForUser($mockUserId, $mockUserRole);

                    debug_output('Notifications targeting: ' . htmlspecialchars($target) . " (Fetched with mock user ID: {$mockUserId}, Role: {$mockUserRole})", $notifications);
                } catch (Error $e) {
                    debug_output('Error Listing Notifications', "<span class='error'>Critical Error:</span> " . htmlspecialchars($e->getMessage()) . "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>", true);
                } catch (Exception $e) {
                    debug_output('Error Listing Notifications', "<span class='error'>Error:</span> " . htmlspecialchars($e->getMessage()) . "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>", true);
                }
            } else {
                debug_output('List Notifications', 'Target (User ID, role, or "all") is required.');
            }
            break;

        case 'clear_notifications':
            try {
                if (!class_exists('MongoDB\Client')) {
                    debug_output('Error Clearing Notifications', '<span class="error">MongoDB PHP Driver not found or not enabled.</span>');
                    break;
                }
                App\Core\Database::connect();
                // Instantiate the repository to access collection name etc. if needed, or get collection directly
                 $repo = new App\Notification\NotificationRepository();
                 $reflectionRepo = new ReflectionClass($repo);
                 $collectionProperty = $reflectionRepo->getProperty('collection');
                 $collectionProperty->setAccessible(true);
                 $collection = $collectionProperty->getValue($repo);

                $result = $collection->deleteMany([]);
                debug_output('Clear All Notifications', "<span class='success'>Deleted {$result->getDeletedCount()} notifications from '{$collection->getCollectionName()}' collection.</span>", true);
            } catch (Error $e) {
                debug_output('Error Clearing Notifications', "<span class='error'>Critical Error:</span> " . htmlspecialchars($e->getMessage()) . "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>", true);
            } catch (Exception $e) {
                debug_output('Error Clearing Notifications', "<span class='error'>Error:</span> " . htmlspecialchars($e->getMessage()) . "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>", true);
            }
            break;

        case 'generate_test_bill':
            try {
                if (!class_exists('MongoDB\Client')) {
                    debug_output('Error Generating Test Bill', '<span class="error">MongoDB PHP Driver not found or not enabled.</span>');
                    break;
                }
                App\Core\Database::connect(); // Ensure DB is connected before services
                $productService = new App\Product\ProductService();
                $billService = new App\Billing\BillService();
                 $userRepo = new App\Auth\UserRepository(); // To get a real user ID if available

                // Fetch a couple of products to add to the cart
                $products = $productService->getAllProducts();
                if (count($products) < 2) {
                    throw new Exception("Not enough products in DB to generate a test bill. Add at least 2 products via Admin > Products or debug tool.");
                }

                $product1 = (array) $products[0]->getArrayCopy();
                $product2 = (array) $products[1]->getArrayCopy();

                // Construct mock cart items using fetched product details
                $mockCartItems = [
                    [
                        'product_id' => (string)$product1['_id'],
                        'product_name' => $product1['name'], // Include name
                        'quantity' => rand(1, 5), // Random quantity
                        'price' => (float)$product1['price']
                    ],
                    [
                        'product_id' => (string)$product2['_id'],
                        'product_name' => $product2['name'], // Include name
                        'quantity' => rand(1, 3), // Random quantity
                        'price' => (float)$product2['price']
                    ]
                ];

                 // Find a staff or admin user to associate the bill with
                $userDoc = $userRepo->findByUsername('admin');
                if (!$userDoc) {
                   $userDoc = $userRepo->findByUsername('staff'); // Fallback to staff
                }
                $mockUserId = $userDoc ? (string)$userDoc->_id : 'debug_user_id_fallback';
                $mockUsername = $userDoc ? $userDoc->username : 'DebugUser';

                debug_output('Test Bill Items', $mockCartItems);
                debug_output('Associated User', ['id' => $mockUserId, 'username' => $mockUsername]);


                $result = $billService->generateBill($mockCartItems, $mockUserId, $mockUsername);
                debug_output('Generate Test Bill Result', $result);

            } catch (Error $e) {
                debug_output('Error Generating Test Bill', "<span class='error'>Critical Error:</span> " . htmlspecialchars($e->getMessage()) . "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>", true);
            } catch (Exception $e) {
                debug_output('Error Generating Test Bill', "<span class='error'>Error:</span> " . htmlspecialchars($e->getMessage()) . "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>", true);
            }
            break;

        case 'list_bills':
            try {
                if (!class_exists('MongoDB\Client')) {
                    debug_output('Error Listing Bills', '<span class="error">MongoDB PHP Driver not found or not enabled.</span>');
                    break;
                }
                App\Core\Database::connect();
                $billService = new App\Billing\BillService();
                $bills = $billService->getAllBills();
                debug_output('All Bills', $bills);
            } catch (Error $e) {
                 debug_output('Error Listing Bills', "<span class='error'>Critical Error:</span> " . htmlspecialchars($e->getMessage()) . "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>", true);
            } catch (Exception $e) {
                debug_output('Error Listing Bills', "<span class='error'>Error:</span> " . htmlspecialchars($e->getMessage()) . "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>", true);
            }
            break;

        case 'clear_bills':
            try {
                if (!class_exists('MongoDB\Client')) {
                    debug_output('Error Clearing Bills', '<span class="error">MongoDB PHP Driver not found or not enabled.</span>');
                    break;
                }
                 // Use reflection to get the collection name used by BillRepository
                 $repo = new App\Billing\BillRepository();
                 $reflectionRepo = new ReflectionClass($repo);
                 $collectionProperty = $reflectionRepo->getProperty('billCollection');
                 $collectionProperty->setAccessible(true);
                 $collection = $collectionProperty->getValue($repo);

                $result = $collection->deleteMany([]);
                debug_output('Clear All Bills', "<span class='success'>Deleted {$result->getDeletedCount()} bills from '{$collection->getCollectionName()}' collection.</span>", true);
            } catch (Error $e) {
                 debug_output('Error Clearing Bills', "<span class='error'>Critical Error:</span> " . htmlspecialchars($e->getMessage()) . "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>", true);
            } catch (Exception $e) {
                debug_output('Error Clearing Bills', "<span class='error'>Error:</span> " . htmlspecialchars($e->getMessage()) . "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>", true);
            }
            break;

        case 'eval_code_form':
            echo '<h2>Execute PHP Code (EXTREMELY DANGEROUS)</h2>';
            echo '<p class="warning">Only execute code if you know EXACTLY what you are doing. This can break your application or server.</p>';
            echo '<form method="POST" class="code-input">';
            echo '<input type="hidden" name="action" value="eval_code">';
            echo '<textarea name="php_code" placeholder="Enter PHP code here... e.g., echo \'Hello World\';"></textarea><br>';
            echo '<input type="submit" value="Execute Code">';
            echo '</form>';
            break;

        case 'eval_code':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $code = $_POST['php_code'] ?? '';
                if (!empty($code)) {
                    echo '<h3>Executing Code:</h3>';
                    echo '<pre>' . htmlspecialchars($code) . '</pre>';
                    echo '<h3>Output:</h3>';
                    echo '<pre>';
                    try {
                        ob_start();
                        eval($code); // DANGER: eval is evil
                        $output = ob_get_clean();
                        echo htmlspecialchars($output);
                    } catch (Throwable $t) { // Catch parse errors and exceptions
                        // Check if buffer is active before trying to clean
                        if (ob_get_level() > 0) {
                             ob_end_clean(); // Clean buffer if error occurred during ob_start or eval
                        }
                        echo "<span class='error'>Error executing code:</span> " . htmlspecialchars($t->getMessage()) . "\n";
                        echo "File: " . htmlspecialchars($t->getFile()) . ":" . $t->getLine() . "\n";
                        echo "Trace:\n" . htmlspecialchars($t->getTraceAsString());
                    }
                    echo '</pre>';
                } else {
                    debug_output('Execute PHP Code', 'No code provided.');
                }
            } else {
                debug_output('Execute PHP Code', 'This action requires a POST request.');
            }
            break;

        case 'default':
        default:
            echo "<h2>Welcome to the Debug Console</h2>";
            echo "<p>Select an action from the menu above to inspect different parts of the application.</p>";
            echo "<p><strong>Current Environment:</strong> " . htmlspecialchars($appConfig['env'] ?? 'N/A') . "</p>";
            echo "<p><strong>Debug Mode:</strong> " . (($appConfig['debug'] ?? false) ? 'Enabled' : 'Disabled') . "</p>";
             // Check if PROJECT_ROOT is correct
            echo "<p><strong>PROJECT_ROOT:</strong> " . htmlspecialchars(PROJECT_ROOT) . "</p>";
             // Check if BASE_PATH is correct (only relevant if app is in a subdir)
             if (defined('BASE_PATH')) {
                  echo "<p><strong>Calculated BASE_PATH:</strong> '" . htmlspecialchars(BASE_PATH) . "'</p>";
                  if (!empty(BASE_PATH) && strpos($_SERVER['REQUEST_URI'] ?? '', BASE_PATH) !== 0) {
                       echo "<p class='warning'>Warning: BASE_PATH seems incorrect based on REQUEST_URI. This can cause routing issues.</p>";
                  }
             } else {
                  echo "<p class='warning'>BASE_PATH constant is not defined. This may cause routing issues if the app is in a subdirectory.</p>";
             }


            break;
    }
    ?>
    <hr>
    <footer>
        <p>Debug console loaded at: <?php echo date('Y-m-d H:i:s'); ?></p>
    </footer>
</body>
</html>