<?php
declare(strict_types=1);

// --- WARNING ---
// THIS SCRIPT PROVIDES POWERFUL DEBUGGING CAPABILITIES AND DIRECT DATABASE ACCESS.
// IT SHOULD NEVER BE ACCESSIBLE IN A PRODUCTION ENVIRONMENT.
// SECURE IT APPROPRIATELY (E.G., IP WHITELISTING, HTTP BASIC AUTH, OR REMOVE IT).

define('PROJECT_ROOT', __DIR__);
require PROJECT_ROOT . '/vendor/autoload.php';

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
}

// Load Application Configuration
$appConfig = require PROJECT_ROOT . '/config/app.php';

// --- SECURITY CHECK ---
// Simple IP check (replace with your development IP or remove if locally hosted and secured)
$allowed_ips = ['127.0.0.1', '::1']; // Add your development machine's IP if needed
if ($appConfig['env'] !== 'development' && !in_array($_SERVER['REMOTE_ADDR'], $allowed_ips)) {
    http_response_code(403);
    die('Access Denied. This script is for development purposes only.');
}

// Start session if not already started (some services might need it)
if (session_status() === PHP_SESSION_NONE) {
    session_name($appConfig['session_name'] ?? 'APP_SESSION');
    session_start([
        'cookie_httponly' => true,
        'cookie_secure' => $appConfig['env'] === 'production', // Should be false in dev
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
                debug_output('Database Connection Status', 'MongoDB PHP Driver not found or not enabled. Please install/enable the `mongodb` extension.');
                break;
            }
            try {
                $client = App\Core\Database::connect(); // Uses the static connect method
                $client->listDatabases(); // Simple command to check connection
                debug_output('Database Connection Status', 'Successfully connected to MongoDB.');
            } catch (Exception $e) {
                debug_output('Database Connection Status', 'Failed to connect: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
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
            // Re-add routes (simplified from api/index.php for brevity in debug)
            // --- Define Routes (Copied and simplified from api/index.php) ---
            // Public pages
            $router->addRoute('GET', '/',           [\App\Auth\AuthController::class, 'showHomePage']);
            $router->addRoute('GET', '/login',      [\App\Auth\AuthController::class, 'showLoginForm']);
            $router->addRoute('POST', '/login',     [\App\Auth\AuthController::class, 'handleLogin']);
            $router->addRoute('GET', '/logout',     [\App\Auth\AuthController::class, 'logout']);

            // Admin Pages
            $router->addRoute('GET', '/admin/dashboard',  ['handler' => [\App\Admin\AdminController::class, 'dashboard'], 'middleware' => 'auth:admin']);
            $router->addRoute('GET', '/admin/products',   ['handler' => [\App\Product\ProductController::class, 'index'], 'middleware' => 'auth:admin']);

            // Staff Pages
            $router->addRoute('GET', '/staff/pos',        ['handler' => [\App\Staff\StaffController::class, 'pos'], 'middleware' => 'auth:staff,admin']);
            $router->addRoute('GET', '/staff/bills',      ['handler' => [\App\Staff\StaffController::class, 'billView'], 'middleware' => 'auth:staff,admin']);
            $router->addRoute('GET', '/staff/mobile-scanner', ['handler' => [\App\Staff\MobileScannerController::class, 'showScannerPage'], 'middleware' => 'auth:staff,admin']);

            // API Routes
            $router->addRoute('GET', '/api/products',       ['handler' => [\App\Product\ProductController::class, 'apiGetProducts'], 'middleware' => 'auth:admin']);
            $router->addRoute('POST', '/api/products',      ['handler' => [\App\Product\ProductController::class, 'apiAddProduct'], 'middleware' => 'auth:admin']);
            $router->addRoute('GET', '/api/products/{id}',  ['handler' => [\App\Product\ProductController::class, 'apiGetProductById'], 'middleware' => 'auth:admin']);
            $router->addRoute('PUT', '/api/products/{id}',  ['handler' => [\App\Product\ProductController::class, 'apiUpdateProduct'], 'middleware' => 'auth:admin']);
            $router->addRoute('DELETE', '/api/products/{id}',['handler' => [\App\Product\ProductController::class, 'apiDeleteProduct'], 'middleware' => 'auth:admin']);

            $router->addRoute('POST', '/api/bills/generate', ['handler' => [\App\Billing\BillController::class, 'apiGenerateBill'], 'middleware' => 'auth:staff,admin']);
            $router->addRoute('GET', '/api/bills',          ['handler' => [\App\Billing\BillController::class, 'apiGetBills'], 'middleware' => 'auth:staff,admin']);
            $router->addRoute('GET', '/api/sales',          ['handler' => [\App\Admin\AdminController::class, 'apiGetSales'], 'middleware' => 'auth:admin']);

            $router->addRoute('POST', '/api/notifications/fetch', ['handler' => [\App\Notification\NotificationController::class, 'apiFetch'], 'middleware' => 'auth']);
            $router->addRoute('POST', '/api/notifications/mark-seen', ['handler' => [\App\Notification\NotificationController::class, 'apiMarkSeen'], 'middleware' => 'auth']);

            $router->addRoute('POST', '/api/scanner/activate-pos', ['handler' => [\App\Staff\MobileScannerController::class, 'apiActivateDesktopScanning'], 'middleware' => 'auth:staff,admin']);
            $router->addRoute('POST', '/api/scanner/deactivate-pos', ['handler' => [\App\Staff\MobileScannerController::class, 'apiDeactivateDesktopScanning'], 'middleware' => 'auth:staff,admin']);
            $router->addRoute('GET', '/api/scanner/check-pos-activation', ['handler' => [\App\Staff\MobileScannerController::class, 'apiCheckDesktopActivation'], 'middleware' => 'auth:staff,admin']);
            $router->addRoute('POST', '/api/scanner/activate-mobile', ['handler' => [\App\Staff\MobileScannerController::class, 'apiActivateMobileSession'], 'middleware' => 'auth:staff,admin']);
            $router->addRoute('POST', '/api/scanner/submit-scan', ['handler' => [\App\Staff\MobileScannerController::class, 'apiSubmitScannedProduct'], 'middleware' => 'auth:staff,admin']);
            $router->addRoute('GET', '/api/scanner/items', ['handler' => [\App\Staff\MobileScannerController::class, 'apiGetScannedItemsForDesktop'], 'middleware' => 'auth:staff,admin']);


            // Access protected property $routes using Reflection
            $reflectionRouter = new ReflectionClass(App\Core\Router::class);
            $routesProperty = $reflectionRouter->getProperty('routes');
            $routesProperty->setAccessible(true);
            $definedRoutes = $routesProperty->getValue($router);

            foreach ($definedRoutes as $method => $paths) {
                foreach ($paths as $path => $config) {
                    $handlerDisplay = is_array($config['handler'] ?? $config) ? (is_string(($config['handler'] ?? $config)[0]) ? (($config['handler'] ?? $config)[0] . '::' . ($config['handler'] ?? $config)[1]) : 'Closure/Callable') : 'Invalid Handler';
                    $middlewareDisplay = isset($config['middleware']) ? (is_array($config['middleware']) ? implode(', ', $config['middleware']) : $config['middleware']) : 'None';
                    echo "<tr><td>{$method}</td><td>" . htmlspecialchars($path) . "</td><td>" . htmlspecialchars($handlerDisplay) . "</td><td>" . htmlspecialchars($middlewareDisplay) . "</td></tr>";
                }
            }
            echo "</tbody></table>";
            debug_output('Registered Routes', ob_get_clean(), true);
            break;

        case 'list_products':
            try {
                $productService = new App\Product\ProductService();
                $products = $productService->getAllProducts();
                debug_output('All Products', $products);
            } catch (Error $e) { // Catch Error for class not found issues
                debug_output('Error Listing Products', "Critical Error: " . $e->getMessage() . "\nThis might be due to a missing extension (like MongoDB driver) or a class not found.\nTrace:\n" . $e->getTraceAsString());
            } catch (Exception $e) {
                debug_output('Error Listing Products', $e->getMessage() . "\nTrace:\n" . $e->getTraceAsString());
            }
            break;

        case 'get_product_form':
            echo '<h2>Get Product by ID</h2>';
            echo '<form method="GET">';
            echo '<input type="hidden" name="action" value="get_product">';
            echo 'Product ID: <input type="text" name="id" required> ';
            echo '<input type="submit" value="Fetch Product">';
            echo '</form>';
            break;

        case 'get_product':
            $productId = $_GET['id'] ?? null;
            if ($productId) {
                try {
                    $productService = new App\Product\ProductService();
                    $product = $productService->getProductById((string)$productId);
                    debug_output('Product Details (ID: ' . htmlspecialchars((string)$productId) . ')', $product ?: 'Product not found.');
                } catch (Error $e) {
                    debug_output('Error Fetching Product', "Critical Error: " . $e->getMessage() . "\nTrace:\n" . $e->getTraceAsString());
                } catch (Exception $e) {
                    debug_output('Error Fetching Product', $e->getMessage() . "\nTrace:\n" . $e->getTraceAsString());
                }
            } else {
                debug_output('Get Product', 'Product ID is required.');
            }
            break;

        case 'create_test_notification':
            try {
                $notificationService = new App\Notification\NotificationService();
                // Example: Create a notification for 'admin' role, or a specific user_id if your system supports it.
                // For simplicity, targeting 'admin' role.
                $notificationId = $notificationService->create(
                    'This is a test notification from debug.php at ' . date('Y-m-d H:i:s'),
                    'info',
                    'admin', // Target role or user_id
                    10000,   // Duration in ms (0 for indefinite)
                    'Test Event'
                );
                debug_output('Create Test Notification', $notificationId ? "Notification created with ID: {$notificationId}" : "Failed to create notification.");
            } catch (Error $e) {
                 debug_output('Error Creating Test Notification', "Critical Error: " . $e->getMessage() . "\nTrace:\n" . $e->getTraceAsString());
            } catch (Exception $e) {
                debug_output('Error Creating Test Notification', $e->getMessage() . "\nTrace:\n" . $e->getTraceAsString());
            }
            break;

        case 'list_notifications_form':
            echo '<h2>List Notifications for User</h2>';
            echo '<form method="GET">';
            echo '<input type="hidden" name="action" value="list_notifications">';
            echo 'User ID (MongoDB ObjectId string): <input type="text" name="user_id" placeholder="e.g., 60c72b2f9b1e8a3f9c8b4567" required> ';
            echo '<input type="submit" value="List Notifications">';
            echo '</form>';
            break;

        case 'list_notifications':
            $userId = $_GET['user_id'] ?? null;
             if ($userId) {
                try {
                    $notificationService = new App\Notification\NotificationService();
                    // Assuming fetchForUser method exists and can take a user ID.
                    // If it expects a role, you might need to adjust.
                    // For this example, let's assume it can fetch by user ID if your NotificationRepository supports it.
                    // This part is highly dependent on your NotificationService implementation.
                    // A more generic way might be to fetch all and filter, or add a specific debug method.
                    // For now, let's assume a direct fetch or adapt if needed.
                    $repo = new App\Notification\NotificationRepository();
                    $notifications = $repo->findByUserIdOrRole($userId); // Or a more specific method
                    debug_output('Notifications for User ID: ' . htmlspecialchars($userId), $notifications);
                } catch (Error $e) {
                    debug_output('Error Listing Notifications', "Critical Error: " . $e->getMessage() . "\nTrace:\n" . $e->getTraceAsString());
                } catch (Exception $e) {
                    debug_output('Error Listing Notifications', $e->getMessage() . "\nTrace:\n" . $e->getTraceAsString());
                }
            } else {
                debug_output('List Notifications', 'User ID is required.');
            }
            break;

        case 'clear_notifications':
            try {
                $repo = new App\Notification\NotificationRepository();
                $result = $repo->deleteAll();
                debug_output('Clear All Notifications', "Deleted {$result->getDeletedCount()} notifications.");
            } catch (Error $e) {
                debug_output('Error Clearing Notifications', "Critical Error: " . $e->getMessage() . "\nTrace:\n" . $e->getTraceAsString());
            } catch (Exception $e) {
                debug_output('Error Clearing Notifications', $e->getMessage() . "\nTrace:\n" . $e->getTraceAsString());
            }
            break;

        case 'generate_test_bill':
            try {
                $productService = new App\Product\ProductService();
                $billService = new App\Billing\BillService();

                // Fetch a couple of products to add to the cart
                $products = $productService->getAllProducts();
                if (count($products) < 2) {
                    throw new Exception("Not enough products in DB to generate a test bill. Add at least 2 products.");
                }

                $product1 = (array) $products[0]->getArrayCopy();
                $product2 = (array) $products[1]->getArrayCopy();

                $mockCartItems = [
                    [
                        'product_id' => (string)$product1['_id'],
                        'product_name' => $product1['name'],
                        'quantity' => 2,
                        'price' => (float)$product1['price']
                    ],
                    [
                        'product_id' => (string)$product2['_id'],
                        'product_name' => $product2['name'],
                        'quantity' => 1,
                        'price' => (float)$product2['price']
                    ]
                ];
                // Mock user (replace with actual user ID if testing for a specific user)
                $mockUserId = '000000000000000000000000'; // Placeholder BSON ID like string
                $mockUsername = 'DebugUser';

                $result = $billService->generateBill($mockCartItems, $mockUserId, $mockUsername);
                debug_output('Generate Test Bill', $result);

            } catch (Error $e) {
                debug_output('Error Generating Test Bill', "Critical Error: " . $e->getMessage() . "\nTrace:\n" . $e->getTraceAsString());
            } catch (Exception $e) {
                debug_output('Error Generating Test Bill', $e->getMessage() . "\nTrace:\n" . $e->getTraceAsString());
            }
            break;

        case 'list_bills':
            try {
                $billService = new App\Billing\BillService();
                $bills = $billService->getAllBills();
                debug_output('All Bills', $bills);
            } catch (Error $e) {
                debug_output('Error Listing Bills', "Critical Error: " . $e->getMessage() . "\nTrace:\n" . $e->getTraceAsString());
            } catch (Exception $e) {
                debug_output('Error Listing Bills', $e->getMessage() . "\nTrace:\n" . $e->getTraceAsString());
            }
            break;
        
        case 'clear_bills':
            try {
                if (!class_exists('MongoDB\Client')) {
                    debug_output('Error Clearing Bills', 'MongoDB PHP Driver not found or not enabled.');
                    break;
                }
                $repo = new App\Billing\BillRepository();
                $collection = App\Core\Database::connect()->selectCollection('bills_new');
                $result = $collection->deleteMany([]);
                debug_output('Clear All Bills', "Deleted {$result->getDeletedCount()} bills from 'bills_new' collection.");
            } catch (Error $e) {
                debug_output('Error Clearing Bills', "Critical Error: " . $e->getMessage() . "\nTrace:\n" . $e->getTraceAsString());
            } catch (Exception $e) {
                debug_output('Error Clearing Bills', $e->getMessage() . "\nTrace:\n" . $e->getTraceAsString());
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
                        ob_end_clean(); // Clean buffer if error occurred during ob_start
                        echo "Error executing code: " . htmlspecialchars($t->getMessage()) . "\n";
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
            echo "<p><strong>Current Environment:</strong> " . htmlspecialchars($appConfig['env']) . "</p>";
            echo "<p><strong>Debug Mode:</strong> " . ($appConfig['debug'] ? 'Enabled' : 'Disabled') . "</p>";
            break;
    }
    ?>
    <hr>
    <footer>
        <p>Debug console loaded at: <?php echo date('Y-m-d H:i:s'); ?></p>
    </footer>
</body>
</html>
