<?php
//billing/server.php`** (Minor improvements, notification titles)

// Global error handling for fatal errors
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR])) {
        // If no headers sent, and it's likely an API call, send JSON error
        if (!headers_sent() && (isset($_SERVER['HTTP_ACCEPT']) && strpos(strtolower($_SERVER['HTTP_ACCEPT']), 'application/json') !== false || (isset($_GET['action']) || isset($_POST['action'])))) {
            header('Content-Type: application/json');
            http_response_code(500); // Ensure a 500 status code for fatal errors
            echo json_encode([
                'success' => false,
                'status' => 'error', // Keep status for compatibility if other parts use it
                'message' => 'A critical server error occurred.',
                'error_details' => [
                    'type' => $error['type'],
                    'message' => $error['message'],
                    'file' => basename($error['file']), // Keep it brief for client
                    'line' => $error['line']
                ]
            ]);
        }
        // Log the full error to the server's error log
        error_log(sprintf("Fatal error: type %d, Message: %s, File: %s, Line: %d", $error['type'], $error['message'], $error['file'], $error['line']));
    }
});

error_reporting(E_ALL);
ini_set('display_errors', 0); // Do not display errors in output, log them instead

// Ensure vendor autoload is loaded if not already by router
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
} else {
    // Handle missing vendor/autoload.php for API calls
    if (php_sapi_name() !== 'cli' && (!isset($_SERVER['HTTP_ACCEPT']) || strpos(strtolower($_SERVER['HTTP_ACCEPT']), 'application/json') === false)) {
        // This case should ideally not happen if router is entry point
    } else {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Server configuration error: Autoloader not found.']);
        exit;
    }
}

// Include configuration file
if (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
} else {
    if (php_sapi_name() !== 'cli' && (!isset($_SERVER['HTTP_ACCEPT']) || strpos(strtolower($_SERVER['HTTP_ACCEPT']), 'application/json') === false)) {
        // This case should ideally not happen if router is entry point
    } else {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Server configuration error: config.php not found.']);
        exit;
    }
}

// Include notification system (it also loads its own autoloader if needed)
require_once 'notification.php'; // NotificationSystem class

// Import MongoDB Server API
use MongoDB\Driver\ServerApi;

// Initialize MongoDB client and NotificationSystem
$mongoClient = null;
$db = null;
$notificationSystem = null;

try {
    // Ensure vendor autoload is loaded (it should be, but good for robustness)
    if (file_exists(__DIR__ . '/vendor/autoload.php')) {
        require_once __DIR__ . '/vendor/autoload.php';
    } else {
        throw new Exception("Autoloader not found. This is a critical error.");
    }

    // Use the MongoDB URI from config.php
    $uri = defined('MONGODB_URI') ? MONGODB_URI : 'mongodb://localhost:27017';
    
    $uriOptions = [];
    $driverOptions = [
        'serverSelectionTimeoutMS' => 10000, // Reasonable timeout
        'connectTimeoutMS' => 15000,         // Reasonable timeout
        // Add SSL context options for more control over TLS/SSL behavior
        // This is particularly useful for debugging TLS handshake issues.
        'ssl' => true, // Explicitly enable SSL, though often default for srv
        'tlsContext' => stream_context_create([
            'ssl' => [
                // --- IMPORTANT SECURITY NOTE ---
                // 'verify_peer' => false, // DANGER: Disables peer certificate verification.
                                          // ONLY use for temporary debugging in isolated environments.
                                          // DO NOT use in production.
                // 'verify_peer_name' => false, // DANGER: Disables peer name verification.
                                               // ONLY use for temporary debugging.
                                               // DO NOT use in production.

                // If you have a specific CA bundle file, you can specify it:
                // 'cafile' => '/path/to/your/ca-bundle.crt',

                // Attempt to allow self-signed certificates (less secure, for specific scenarios)
                // 'allow_self_signed' => true,

                // You might need to specify a specific TLS version if there are negotiation issues
                // Example: Forcing TLS 1.2 (check constants for your PHP version)
                // 'crypto_method' => STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT,
            ]
        ])
    ];

    // For Atlas SRV URIs, enable retryWrites and ServerApi
    if (strpos($uri, 'mongodb+srv://') === 0 || strpos($uri, '.mongodb.net') !== false) {
        $uriOptions['retryWrites'] = true;
        if (class_exists('MongoDB\\Driver\\ServerApi')) {
            $driverOptions['serverApi'] = new MongoDB\Driver\ServerApi(MongoDB\Driver\ServerApi::V1);
        }
    }
    
    // Create a new client and connect to the server
    $mongoClient = new MongoDB\Client($uri, $uriOptions, $driverOptions);
    
    // Test connection explicitly before proceeding with retry logic
    $connectionSuccessful = false;
    $connectionAttempts = 0;
    $maxAttempts = 3;
    
    while (!$connectionSuccessful && $connectionAttempts < $maxAttempts) {
        try {
            $connectionAttempts++;
            $mongoClient->selectDatabase('admin')->command(['ping' => 1]);
            $connectionSuccessful = true;
            error_log("MongoDB connection established successfully after {$connectionAttempts} attempt(s)");
        } catch (Exception $e) {
            error_log("MongoDB connection attempt {$connectionAttempts} failed: " . $e->getMessage());
            
            if ($connectionAttempts >= $maxAttempts) {
                throw $e; // Re-throw to be caught by the outer try-catch block
            }
            
            // Add exponential backoff delay between attempts
            $delay = pow(2, $connectionAttempts - 1) * 100000; // in microseconds (0.1s, 0.2s, 0.4s)
            usleep($delay);
        }
    }
    
    // Now initialize database and notification system
    $db = $mongoClient->selectDatabase('billing');
    
    // Initialize notification system with the already-established client
    $notificationSystem = null;
    try {
        $notificationSystem = new NotificationSystem($mongoClient); // Pass the client
        
        // Test notification system is working
        if (!$notificationSystem->checkDbConnection()) {
            error_log("Notification system database connection check failed");
            throw new Exception("Notification system database connection check failed");
        }
    } catch (Exception $e) {
        error_log("Failed to initialize notification system: " . $e->getMessage());
        throw $e; // Re-throw to be caught by the outer try-catch block
    }
    
    // Check and create missing collections if needed
    $existingCollections = [];
    foreach ($db->listCollections() as $collection) {
        $existingCollections[] = $collection->getName();
    }
    
    // Check for required collections and create if missing
    $requiredCollections = ['user', 'products', 'bill', 'popup_notifications'];
    foreach ($requiredCollections as $collectionName) {
        if (!in_array($collectionName, $existingCollections)) {
            $db->createCollection($collectionName);
            error_log("Created missing collection: {$collectionName}");
        }
    }
    
} catch (MongoDB\Driver\Exception\AuthenticationException $e) {
    $errorMessage = 'MongoDB authentication failed: ' . $e->getMessage();
    error_log($errorMessage);
    
    if (php_sapi_name() !== 'cli' && (!isset($_SERVER['HTTP_ACCEPT']) || strpos(strtolower($_SERVER['HTTP_ACCEPT']), 'application/json') === false)) {
        // Handle non-JSON context if necessary
    } else {
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'error', 
            'message' => 'Database connection failed: Invalid credentials',
            'error_code' => 'AUTH_FAILED'
        ]);
        exit;
    }
} catch (MongoDB\Driver\Exception\ConnectionTimeoutException $e) {
    $errorMessage = 'MongoDB connection timed out: ' . $e->getMessage();
    error_log($errorMessage);
    
    if (php_sapi_name() !== 'cli' && (!isset($_SERVER['HTTP_ACCEPT']) || strpos(strtolower($_SERVER['HTTP_ACCEPT']), 'application/json') === false)) {
        // Handle non-JSON context
    } else {
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'error',
            'message' => 'Database connection timed out. Please try again later.',
            'error_code' => 'CONNECTION_TIMEOUT'
        ]);
        exit;
    }
} catch (MongoDB\Driver\Exception\ServerSelectionTimeoutException $e) {
    $errorMessage = 'MongoDB server selection timed out: ' . $e->getMessage();
    error_log($errorMessage);
    
    if (php_sapi_name() !== 'cli' && (!isset($_SERVER['HTTP_ACCEPT']) || strpos(strtolower($_SERVER['HTTP_ACCEPT']), 'application/json') === false)) {
        // Handle non-JSON context
    } else {
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'error',
            'message' => 'Could not connect to database server. The service might be temporarily unavailable.',
            'error_code' => 'SERVER_SELECTION_FAILED'
        ]);
        exit;
    }
} catch (MongoDB\Driver\Exception\InvalidArgumentException $e) {
    $errorMessage = 'MongoDB invalid connection string: ' . $e->getMessage();
    error_log($errorMessage);
    
    if (php_sapi_name() !== 'cli' && (!isset($_SERVER['HTTP_ACCEPT']) || strpos(strtolower($_SERVER['HTTP_ACCEPT']), 'application/json') === false)) {
        // Handle non-JSON context
    } else {
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'error',
            'message' => 'Database configuration error. Please contact system administrator.',
            'error_code' => 'INVALID_CONNECTION_STRING'
        ]);
        exit;
    }
} catch (Exception $e) {
    // Generic exception handler for all other cases
    $errorMessage = 'Database connection failed: ' . $e->getMessage();
    error_log($errorMessage);
    
    if (php_sapi_name() !== 'cli' && (!isset($_SERVER['HTTP_ACCEPT']) || strpos(strtolower($_SERVER['HTTP_ACCEPT']), 'application/json') === false)) {
        // Handle non-JSON context if necessary, though API endpoints are primary here
    } else {
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'error', 
            'message' => 'Database connection failed. Please try again later.',
            'error_code' => 'CONNECTION_FAILED'
        ]);
        exit;
    }
}

// Start session if not already started (for user context in notifications)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$currentUserId = isset($_SESSION['user_id']) ? (string)$_SESSION['user_id'] : 'guest_user_' . session_id();
$currentUserRole = isset($_SESSION['user_role']) ? (string)$_SESSION['user_role'] : 'guest';

// --- Request Handling ---
// First, check for JSON POST requests with action in query string
if ($_SERVER['REQUEST_METHOD'] === 'POST' && 
    isset($_GET['action']) && 
    isset($_SERVER['CONTENT_TYPE']) && 
    strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
    
    header('Content-Type: application/json'); // Ensure JSON response
    $action = $_GET['action'];
    $response = ['success' => false, 'message' => 'Invalid action or insufficient data.']; // Default error
    
    // Parse the JSON input
    $jsonInput = file_get_contents('php://input');
    $data = json_decode($jsonInput, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("Invalid JSON received: " . json_last_error_msg() . " - Input: " . $jsonInput);
        echo json_encode(['success' => false, 'message' => 'Invalid JSON payload: ' . json_last_error_msg()]);
        exit;
    }
    
    // Ensure DB is available
    if (!$db || !$notificationSystem) {
        error_log("DB or NotificationSystem not available for JSON POST action: " . $action);
        echo json_encode(['success' => false, 'message' => 'Server error: Database or notification system not available.']);
        exit;
    }
    
    try {
        switch ($action) {
            case 'deleteProduct':
                try {
                    if (isset($data['id'])) {
                        $productIdStr = $data['id'];
                        $query = [];
                        
                        error_log("Attempting to delete product with ID string: " . $productIdStr);
                        
                        $isValidObjectId = false;
                        try {
                            $mongoId = new MongoDB\BSON\ObjectId($productIdStr);
                            $isValidObjectId = true;
                        } catch (MongoDB\Exception\InvalidArgumentException $e) {
                            error_log("InvalidArgumentException when validating ObjectId for delete: " . $productIdStr . " - " . $e->getMessage());
                        } catch (Exception $e) { // Catch other potential errors
                            error_log("Generic Exception when validating ObjectId for delete: " . $productIdStr . " - " . $e->getMessage());
                        }

                        if ($isValidObjectId) {
                            $query['_id'] = $mongoId;
                            error_log("Using ObjectId query for delete: _id = ObjectId('" . $productIdStr . "')");
                        } else {
                            error_log("Invalid ObjectId string for delete: " . $productIdStr);
                            $response = ['success' => false, 'message' => 'Invalid Product ID format for deletion.'];
                            break; 
                        }
                        
                        $product = $db->products->findOne($query);
                        if ($product) {
                            $productName = isset($product->name) ? $product->name : "Unknown";
                            error_log("Found product to delete: " . $productName . " (ID: " . $productIdStr . ")");
                            
                            $result = $db->products->deleteOne($query);
                            
                            if ($result->getDeletedCount() > 0) {
                                error_log("Successfully deleted product: " . $productName);
                                $response = ['success' => true, 'message' => 'Product deleted successfully.'];
                            } else {
                                error_log("Delete operation executed but no documents were deleted for ID: " . $productIdStr);
                                $response = ['success' => false, 'message' => 'Product not deleted - operation failed or product already deleted.'];
                            }
                        } else {
                            error_log("No product found with the provided ObjectId for delete: " . $productIdStr);
                            $response = ['success' => false, 'message' => 'Product not found with the given ID.'];
                        }
                    } else {
                        error_log("Delete request missing product ID in JSON data");
                        $response = ['success' => false, 'message' => 'Missing product ID.'];
                    }
                } catch (MongoDB\Driver\Exception\Exception $e) {
                    error_log("MongoDB error during product deletion (ID: ".(isset($data['id']) ? $data['id'] : 'N/A')."): " . $e->getMessage() . "\nTrace: " . $e->getTraceAsString());
                    $response = ['success' => false, 'message' => 'Database error during deletion. Check server logs.'];
                } catch (Exception $e) {
                    error_log("General error during product deletion (ID: ".(isset($data['id']) ? $data['id'] : 'N/A')."): " . $e->getMessage() . "\nTrace: " . $e->getTraceAsString());
                    $response = ['success' => false, 'message' => 'Server error during deletion. Check server logs.'];
                }
                break;
            
            case 'updateProduct':
                try {
                    if (isset($data['id'], $data['name'], $data['price'], $data['stock'])) {
                        $productIdStr = $data['id'];
                        $name = filter_var($data['name'], FILTER_SANITIZE_STRING);
                        $price = (float)$data['price'];
                        $stock = (int)$data['stock'];

                        $isValidObjectId = false;
                        $mongoId = null;
                        try {
                            $mongoId = new MongoDB\BSON\ObjectId($productIdStr);
                            $isValidObjectId = true;
                        } catch (MongoDB\Exception\InvalidArgumentException $e) {
                            error_log("InvalidArgumentException when validating ObjectId for update: " . $productIdStr . " - " . $e->getMessage());
                        } catch (Exception $e) {
                            error_log("Generic Exception when validating ObjectId for update: " . $productIdStr . " - " . $e->getMessage());
                        }

                        if (!$isValidObjectId) {
                            $response = ['success' => false, 'message' => 'Invalid Product ID format for update.'];
                            break;
                        }
                        if (empty($name) || $price < 0 || $stock < 0) {
                             $response = ['success' => false, 'message' => "Invalid product data provided for update."];
                             break;
                        }

                        $query = ['_id' => $mongoId];
                        $update = ['$set' => ['name' => $name, 'price' => $price, 'stock' => $stock]];
                        
                        $result = $db->products->updateOne($query, $update);

                        if ($result->getMatchedCount() > 0) {
                            if ($result->getModifiedCount() > 0) {
                                $response = ['success' => true, 'message' => 'Product updated successfully.'];
                            } else {
                                $response = ['success' => true, 'message' => 'Product data was the same, no changes made.'];
                            }
                        } else {
                            $response = ['success' => false, 'message' => 'Product not found for update.'];
                        }
                    } else {
                        $response = ['success' => false, 'message' => 'Missing data for product update.'];
                    }
                } catch (MongoDB\Driver\Exception\Exception $e) {
                    error_log("MongoDB error during product update (ID: ".(isset($data['id']) ? $data['id'] : 'N/A')."): " . $e->getMessage());
                    $response = ['success' => false, 'message' => 'Database error during update.'];
                } catch (Exception $e) {
                    error_log("General error during product update (ID: ".(isset($data['id']) ? $data['id'] : 'N/A')."): " . $e->getMessage());
                    $response = ['success' => false, 'message' => 'Server error during update.'];
                }
                break;
                
            case 'generateBill':
                // This action expects a JSON payload, $data should hold it.
                if (!isset($data['items']) || !is_array($data['items']) || empty($data['items'])) {
                    $response = ['success' => false, 'message' => 'No items provided or invalid format for bill generation.'];
                    http_response_code(400);
                    break;
                }

                $items = $data['items'];
                $totalAmount = 0;
                $billItemsDetails = [];
                
                if (!isset($db) || !property_exists($db, 'client') || !$db->client instanceof MongoDB\Client) {
                    $response = ['success' => false, 'message' => 'Database client not available.'];
                    http_response_code(500);
                    error_log("GenerateBill Error: Database client not configured.");
                    break;
                }
                
                $session = null; // Initialize session variable
                try {
                    $session = $db->client->startSession();
                    $session->startTransaction();

                    foreach ($items as $item) {
                        if (!isset($item['product_id']) || !isset($item['quantity']) || !isset($item['price'])) {
                            throw new Exception("Invalid item data in cart. Product ID, quantity, and price are required.");
                        }
                        $productIdStr = $item['product_id'];
                        $quantity = (int)$item['quantity'];
                        $pricePerUnit = (float)$item['price'];

                        if ($quantity <= 0) {
                            throw new Exception("Invalid quantity for product ID: " . $productIdStr);
                        }
                        if ($pricePerUnit < 0) { 
                            throw new Exception("Invalid price for product ID: " . $productIdStr);
                        }

                        $mongoProductId = new MongoDB\BSON\ObjectId($productIdStr);
                        $product = $db->products->findOne(['_id' => $mongoProductId], ['session' => $session]);

                        if (!$product) {
                            throw new Exception("Product not found: ID " . $productIdStr);
                        }

                        if ($product->stock < $quantity) {
                            throw new Exception("Insufficient stock for product: " . htmlspecialchars($product->name) . ". Available: " . $product->stock . ", Requested: " . $quantity);
                        }

                        $newStock = $product->stock - $quantity;
                        $updateResult = $db->products->updateOne(
                            ['_id' => $mongoProductId],
                            ['$set' => ['stock' => $newStock]],
                            ['session' => $session]
                        );

                        if ($updateResult->getModifiedCount() !== 1) {
                            throw new Exception("Failed to update stock for product: " . htmlspecialchars($product->name));
                        }
                        
                        $itemTotal = $quantity * $pricePerUnit;
                        $totalAmount += $itemTotal;
                        $billItemsDetails[] = [
                            'product_id' => $mongoProductId,
                            'product_name' => $product->name,
                            'quantity' => $quantity,
                            'price_per_unit' => $pricePerUnit,
                            'item_total' => $itemTotal
                        ];

                        $lowStockThreshold = $product->low_stock_threshold ?? 5; 
                        if ($newStock > 0 && $newStock <= $lowStockThreshold) {
                            $notificationSystem->saveNotification(
                                "Low stock warning: '".htmlspecialchars($product->name)."' has only {$newStock} units left.",
                                'warning', 'admin', 0, "Low Stock Alert"
                            );
                        } elseif ($newStock == 0) {
                             $notificationSystem->saveNotification(
                                "Out of stock: '".htmlspecialchars($product->name)."' is now out of stock.",
                                'error', 'admin', 0, "Out of Stock"
                            );
                        }
                    }

                    $billData = [
                        'items' => $billItemsDetails,
                        'total_amount' => $totalAmount,
                        'created_at' => new MongoDB\BSON\UTCDateTime(),
                        'user_id' => $_SESSION['user_id'] ?? null, 
                        'username' => $_SESSION['username'] ?? 'N/A' 
                    ];
                    $insertResult = $db->bill_new->insertOne($billData, ['session' => $session]);

                    if (!$insertResult->getInsertedId()) {
                        throw new Exception("Failed to save the bill.");
                    }
                    
                    $session->commitTransaction();
                    
                    $response = [
                        'success' => true,
                        'message' => 'Bill generated successfully.',
                        'bill_id' => (string)$insertResult->getInsertedId()
                    ];
                    
                    $notificationSystem->saveNotification(
                        "New bill #{$insertResult->getInsertedId()} generated. Total: ₹" . number_format($totalAmount, 2),
                        'info', 'admin', 7000, "Bill Generated"
                    );

                } catch (MongoDB\Exception\InvalidArgumentException $e) { 
                    if ($session && $session->isInTransaction()) {
                        $session->abortTransaction();
                    }
                    $response = ['success' => false, 'message' => "Invalid data for MongoDB operation: " . $e->getMessage()];
                    http_response_code(400); 
                    error_log("GenerateBill MongoDB InvalidArgumentException: " . $e->getMessage() . "\nTrace: " . $e->getTraceAsString());
                } catch (MongoDB\Driver\Exception\Exception $e) { 
                    if ($session && $session->isInTransaction()) {
                        $session->abortTransaction();
                    }
                    $response = ['success' => false, 'message' => "MongoDB Driver Error: " . $e->getMessage()];
                    http_response_code(500); 
                    error_log("GenerateBill MongoDB Driver Exception: " . $e->getMessage() . "\nTrace: " . $e->getTraceAsString());
                } catch (Exception $e) { 
                    if ($session && $session->isInTransaction()) {
                        $session->abortTransaction();
                    }
                    $response = ['success' => false, 'message' => "Error generating bill: " . $e->getMessage()];
                    http_response_code(500); 
                    error_log("GenerateBill Exception: " . $e->getMessage() . "\nTrace: " . $e->getTraceAsString());
                } catch (Throwable $t) { // Catch all other throwables (PHP 7+ Errors)
                    if ($session && $session->isInTransaction()) {
                        $session->abortTransaction();
                    }
                    $response = ['success' => false, 'message' => "Critical error generating bill: " . $t->getMessage()];
                    http_response_code(500);
                    error_log("GenerateBill Throwable: " . $t->getMessage() . "\nTrace: " . $t->getTraceAsString());
                } finally {
                    if ($session) { 
                        $session->endSession();
                    }
                }
                break;

            // Add other JSON-based actions here
        }
    } catch (Exception $e) { // Catch errors from json_decode or other early issues
        error_log("Server.php JSON POST Error (action: " . $action . "): " . $e->getMessage() . "\nTrace: " . $e->getTraceAsString());
        $response = ['success' => false, 'message' => 'A server error occurred: ' . $e->getMessage()];
    }
    
    echo json_encode($response);
    exit;
    
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json'); // Ensure JSON response for POST actions
    $action = $_POST['action'];
    $response = ['status' => 'error', 'message' => 'Invalid action or insufficient data.']; // Default error

    // Ensure DB is available for POST actions
    if (!$db || !$notificationSystem) {
        echo json_encode(['status' => 'error', 'message' => 'Server error: Database or notification system not available.']);
        exit;
    }

    try {
        switch ($action) {
            case 'addProduct':
                if (isset($_POST['name'], $_POST['price'], $_POST['stock'])) {
                    $product = [
                        'name' => filter_var($_POST['name'], FILTER_SANITIZE_STRING),
                        'price' => (float)$_POST['price'],
                        'stock' => (int)$_POST['stock']
                    ];
                    if (empty($product['name']) || $product['price'] < 0 || $product['stock'] < 0) {
                         $response['message'] = "Invalid product data provided.";
                         break;
                    }
                    $db->products->insertOne($product);
                    $notificationSystem->saveNotification(
                        "New product '{$product['name']}' (₹{$product['price']}) added.",
                        'success', 
                        'staff', // Changed target from 'all' to 'staff'
                        7000, 
                        "Product Added"
                    );
                    $response = ['status' => 'success', 'message' => 'Product added successfully.'];
                }
                break;

            case 'billProduct':
                $productIdStr = $_POST['productId'] ?? null;
                $isValidObjectId = false;
                $mongoProductId = null;

                if ($productIdStr) {
                    try {
                        $mongoProductId = new MongoDB\BSON\ObjectId($productIdStr);
                        $isValidObjectId = true;
                    } catch (MongoDB\Exception\InvalidArgumentException $e) {
                        error_log("InvalidArgumentException when validating ObjectId for billProduct: " . $productIdStr . " - " . $e->getMessage());
                    } catch (Exception $e) {
                        error_log("Generic Exception when validating ObjectId for billProduct: " . $productIdStr . " - " . $e->getMessage());
                    }
                }

                if (isset($_POST['quantity']) && $isValidObjectId) {
                    $quantity = (int)$_POST['quantity'];
                    if ($quantity <= 0) {
                        $response['message'] = "Invalid quantity.";
                        break;
                    }

                    $product = $db->products->findOne(['_id' => $mongoProductId]);
                    if ($product && $product->stock >= $quantity) {
                        $db->products->updateOne(
                            ['_id' => $mongoProductId],
                            ['$inc' => ['stock' => -$quantity]]
                        );
                        $db->bill->insertOne([
                            'productId' => $mongoProductId,
                            'quantity' => $quantity,
                            'priceAtSale' => $product->price, // Store price at time of sale
                            'total' => $product->price * $quantity,
                            'billedByUserId' => $currentUserId, // Track who billed
                            'billedByRole' => $currentUserRole,
                            'date' => new MongoDB\BSON\UTCDateTime()
                        ]);
                        
                        $remainingStock = $product->stock - $quantity;
                        if ($remainingStock <= 10 && $remainingStock > 0) { // Alert if stock is low but not zero
                            $notificationSystem->saveNotification(
                                "Low stock: '{$product->name}' has only {$remainingStock} units left.",
                                'warning', 'admin', 0, "Low Stock Alert" // Persistent for admin
                            );
                        } elseif ($remainingStock == 0) {
                             $notificationSystem->saveNotification(
                                "Out of stock: '{$product->name}' is now out of stock.",
                                'error', 'admin', 0, "Out of Stock"
                            );
                        }
                        $notificationSystem->saveNotification(
                            "New bill: {$quantity} of '{$product->name}' sold.",
                            'info', // Or 'success'
                            'admin',  // Target admin
                            7000,
                            "Bill Processed"
                        );
                        $response = ['status' => 'success', 'message' => "'{$product->name}' billed successfully."];
                    } else {
                        $response['message'] = $product ? 'Insufficient stock for ' . $product->name . '.' : 'Product not found.';
                    }
                } else {
                    $response['message'] = 'Invalid product ID or quantity.';
                }
                break;

            // addUser action removed as it's better handled by a dedicated admin panel or registration flow
            // If needed, it should include more robust validation and permission checks.

            case 'authenticateUser':
                if (isset($_POST['username'], $_POST['password'])) {
                    $username = filter_var(trim($_POST['username']), FILTER_SANITIZE_STRING);
                    $password = $_POST['password']; // Plain text from form

                    $user = $db->user->findOne(['username' => $username]);

                    // SECURITY WARNING: This is for plain-text password comparison.
                    // In a production environment, passwords MUST be hashed using password_hash()
                    // and verified using password_verify().
                    if ($user && isset($user->password) && $password === $user->password) {
                        $_SESSION['user_id'] = (string) $user->_id;
                        $_SESSION['username'] = $user->username;
                        $_SESSION['user_role'] = $user->role;
                        $_SESSION['user_email'] = isset($user->email) ? $user->email : $user->username . '@example.com'; // Example email

                        $notificationSystem->saveNotification(
                            "Welcome back, {$user->username}! You logged in successfully.",
                            'success', 'all', 5000, "Login Successful"
                        );
                        $response = ['status' => 'success', 'role' => $user->role, 'username' => $user->username];
                    } else {
                        $response['message'] = 'Invalid username or password.';
                    }
                }
                break;

            // case 'generateBill': // This case is now moved to the JSON POST handling block above
            //     // ... (logic was here) ...
            //     break;

            default:
                // $response is already set to the default error message for unknown/unmatched action
                http_response_code(400); // Bad Request
                break;
        }
    } catch (MongoDB\Exception\InvalidArgumentException $e) {
        $response = ['status' => 'error', 'message' => 'Invalid data format for database operation. ' . $e->getMessage()];
    } catch (Exception $e) {
        error_log("Server.php POST Error: " . $e->getMessage() . " for action: " . $action);
        $response = ['status' => 'error', 'message' => 'A server error occurred: ' . $e->getMessage()];
    }
    echo json_encode($response);
    exit;

} elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
    header('Content-Type: application/json'); // Ensure JSON response for GET actions
    $action = $_GET['action'];
    $response = null; // Default null, will be populated

    if (!$db) { // DB check for GET actions
        error_log("DB not available for GET action: " . $action);
        echo json_encode(['status' => 'error', 'message' => 'Server error: Database not available for GET request.']);
        exit;
    }

    try {
        switch ($action) {
            case 'getProducts':
                $products = $db->products->find([], ['sort' => ['name' => 1]])->toArray();
                // MongoDB PHP library automatically converts BSON to appropriate PHP types,
                // including ObjectIds to MongoDB\BSON\ObjectId objects.
                // json_encode will then typically serialize MongoDB\BSON\ObjectId as {"$oid": "id_string"}
                $response = $products; 
                break;

            case 'getProduct': // For editing a single product
                if (isset($_GET['id'])) {
                    $productIdStr = $_GET['id'];
                    $isValidObjectId = false;
                    $mongoId = null;
                    try {
                        $mongoId = new MongoDB\BSON\ObjectId($productIdStr);
                        $isValidObjectId = true;
                    } catch (MongoDB\Exception\InvalidArgumentException $e) {
                        error_log("InvalidArgumentException when validating ObjectId for getProduct: " . $productIdStr . " - " . $e->getMessage());
                    } catch (Exception $e) {
                        error_log("Generic Exception when validating ObjectId for getProduct: " . $productIdStr . " - " . $e->getMessage());
                    }

                    if ($isValidObjectId) {
                        $query = ['_id' => $mongoId];
                        $product = $db->products->findOne($query);
                        if ($product) {
                            $response = $product; // Send the single product document
                        } else {
                            $response = ['status' => 'error', 'message' => 'Product not found.'];
                            http_response_code(404);
                        }
                    } else {
                        $response = ['status' => 'error', 'message' => 'Invalid Product ID format.'];
                        http_response_code(400);
                    }
                } else {
                    $response = ['status' => 'error', 'message' => 'Product ID not provided.'];
                    http_response_code(400);
                }
                break;

            case 'getBills':
                // Consider adding pagination or filtering for large bill sets
                $bills = $db->bill->find([], ['sort' => ['date' => -1]])->toArray();
                $response = $bills; // Direct array response
                break;
            
            case 'getSales': // Alias for getBills if intended for admin sales view
                $sales = $db->bill->find([], ['sort' => ['date' => -1]])->toArray();
                // Potentially aggregate sales data here for a summary view
                $response = $sales;
                break;

            default:
                $response = ['status' => 'error', 'message' => 'Unknown GET action.'];
        }
    } catch (Exception $e) {
        error_log("Server.php GET Error: " . $e->getMessage() . " for action: " . $action);
        $response = ['status' => 'error', 'message' => 'A server error occurred during data retrieval: ' . $e->getMessage()];
    }
    
    if (is_array($response) && !isset($response['status'])) { // If it's a direct data array, no status wrapper needed
        echo json_encode($response);
    } elseif ($response !== null) { // If it's an error object or status object
        echo json_encode($response);
    } else { // Should not happen if default error is set for unknown action
        echo json_encode(['status' => 'error', 'message' => 'No data or invalid action.']);
    }
    exit;
} else {
    // Fallback for invalid requests not matching POST or GET with an action
    header('Content-Type: application/json');
    http_response_code(400); // Bad Request
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method or action not specified.']);
    exit;
}

// If no action matched and not a direct script call handled by router:
if (basename(__FILE__) == basename($_SERVER["SCRIPT_FILENAME"])) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['status' => 'error', 'message' => 'No action specified or invalid request method.']);
    exit;
}
?>
