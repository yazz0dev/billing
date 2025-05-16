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
        // 'ssl' => true, // SSL should be part of the MONGODB_URI for Atlas (e.g., ?ssl=true)
        // 'tlsContext' => stream_context_create([ // Not typically needed if URI handles SSL
        //     'ssl' => [] 
        // ])
    ];

    if (strpos($uri, 'mongodb+srv://') === 0 || strpos($uri, '.mongodb.net') !== false) {
        $uriOptions['retryWrites'] = true;
        if (class_exists('MongoDB\\Driver\\ServerApi')) {
            $driverOptions['serverApi'] = new MongoDB\Driver\ServerApi(MongoDB\Driver\ServerApi::V1);
        }
    }
    
    $mongoClient = new MongoDB\Client($uri, $uriOptions, $driverOptions);
    
    $connectionSuccessful = false;
    $connectionAttempts = 0;
    $maxAttempts = 3;
    
    while (!$connectionSuccessful && $connectionAttempts < $maxAttempts) {
        try {
            $connectionAttempts++;
            $mongoClient->selectDatabase('admin')->command(['ping' => 1]);
            $connectionSuccessful = true;
            // error_log("MongoDB connection established successfully after {$connectionAttempts} attempt(s)");
        } catch (Exception $e) {
            error_log("MongoDB connection attempt {$connectionAttempts} failed: " . $e->getMessage());
            if ($connectionAttempts >= $maxAttempts) throw $e; 
            usleep(pow(2, $connectionAttempts - 1) * 100000);
        }
    }
    
    $db = $mongoClient->selectDatabase('billing');
    
    try {
        $notificationSystem = new NotificationSystem($mongoClient); 
        if (!$notificationSystem->checkDbConnection()) {
            error_log("Notification system database connection check failed");
            throw new Exception("Notification system database connection check failed");
        }
    } catch (Exception $e) {
        error_log("Failed to initialize notification system: " . $e->getMessage());
        throw $e;
    }
    
    $existingCollections = [];
    foreach ($db->listCollections() as $collectionInfo) { 
        $existingCollections[] = $collectionInfo->getName();
    }
    
    $requiredCollections = ['user', 'products', 'bill', 'bill_new', 'popup_notifications', 'pairing_sessions'];
    foreach ($requiredCollections as $collectionName) {
        if (!in_array($collectionName, $existingCollections)) {
            $db->createCollection($collectionName);
            if ($collectionName === 'pairing_sessions') {
                $db->pairing_sessions->createIndex(['pairing_id' => 1], ['unique' => true]);
                $db->pairing_sessions->createIndex(['expires_at' => 1]); 
                $db->pairing_sessions->createIndex(['staff_user_id' => 1]);
            }
            // error_log("Created missing collection: {$collectionName}");
        }
    }
    
} catch (MongoDB\Driver\Exception\AuthenticationException $e) {
    $errorMessage = 'MongoDB authentication failed: ' . $e->getMessage();
    error_log($errorMessage);
    if (php_sapi_name() !== 'cli' && (!isset($_SERVER['HTTP_ACCEPT']) || strpos(strtolower($_SERVER['HTTP_ACCEPT']), 'application/json') === false)) { } else {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Database connection failed: Invalid credentials', 'error_code' => 'AUTH_FAILED']);
        exit;
    }
} catch (MongoDB\Driver\Exception\ConnectionTimeoutException $e) {
    $errorMessage = 'MongoDB connection timed out: ' . $e->getMessage();
    error_log($errorMessage);
    if (php_sapi_name() !== 'cli' && (!isset($_SERVER['HTTP_ACCEPT']) || strpos(strtolower($_SERVER['HTTP_ACCEPT']), 'application/json') === false)) { } else {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Database connection timed out. Please try again later.', 'error_code' => 'CONNECTION_TIMEOUT']);
        exit;
    }
} catch (MongoDB\Driver\Exception\ServerSelectionTimeoutException $e) {
    $errorMessage = 'MongoDB server selection timed out: ' . $e->getMessage();
    error_log($errorMessage);
    if (php_sapi_name() !== 'cli' && (!isset($_SERVER['HTTP_ACCEPT']) || strpos(strtolower($_SERVER['HTTP_ACCEPT']), 'application/json') === false)) { } else {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Could not connect to database server. The service might be temporarily unavailable.', 'error_code' => 'SERVER_SELECTION_FAILED']);
        exit;
    }
} catch (MongoDB\Driver\Exception\InvalidArgumentException $e) {
    $errorMessage = 'MongoDB invalid connection string: ' . $e->getMessage();
    error_log($errorMessage);
    if (php_sapi_name() !== 'cli' && (!isset($_SERVER['HTTP_ACCEPT']) || strpos(strtolower($_SERVER['HTTP_ACCEPT']), 'application/json') === false)) { } else {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Database configuration error. Please contact system administrator.', 'error_code' => 'INVALID_CONNECTION_STRING']);
        exit;
    }
} catch (Exception $e) {
    $errorMessage = 'Database connection failed: ' . $e->getMessage();
    error_log($errorMessage);
    if (php_sapi_name() !== 'cli' && (!isset($_SERVER['HTTP_ACCEPT']) || strpos(strtolower($_SERVER['HTTP_ACCEPT']), 'application/json') === false)) { } else {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Database connection failed. Please try again later.', 'error_code' => 'CONNECTION_FAILED']);
        exit;
    }
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$currentUserId = isset($_SESSION['user_id']) ? (string)$_SESSION['user_id'] : 'guest_user_' . session_id();
$currentUserRole = isset($_SESSION['user_role']) ? (string)$_SESSION['user_role'] : 'guest';

// --- Request Handling ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && 
    isset($_GET['action']) && 
    isset($_SERVER['CONTENT_TYPE']) && 
    strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
    
    header('Content-Type: application/json');
    $action = $_GET['action'];
    $response = ['success' => false, 'message' => 'Invalid action or insufficient data.']; 
    
    $jsonInput = file_get_contents('php://input');
    $data = json_decode($jsonInput, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("Invalid JSON received: " . json_last_error_msg() . " - Input: " . $jsonInput);
        echo json_encode(['success' => false, 'message' => 'Invalid JSON payload: ' . json_last_error_msg()]);
        exit;
    }
    
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
                        
                        $isValidObjectId = false;
                        try {
                            $mongoId = new MongoDB\BSON\ObjectId($productIdStr);
                            $isValidObjectId = true;
                        } catch (MongoDB\Exception\InvalidArgumentException $e) {
                            // error_log("InvalidArgumentException when validating ObjectId for delete: " . $productIdStr . " - " . $e->getMessage());
                        } catch (Exception $e) { 
                            // error_log("Generic Exception when validating ObjectId for delete: " . $productIdStr . " - " . $e->getMessage());
                        }

                        if ($isValidObjectId) {
                            $query['_id'] = $mongoId;
                        } else {
                            $response = ['success' => false, 'message' => 'Invalid Product ID format for deletion.'];
                            break; 
                        }
                        
                        $product = $db->products->findOne($query);
                        if ($product) {
                            $productName = isset($product->name) ? $product->name : "Unknown";
                            
                            $result = $db->products->deleteOne($query);
                            
                            if ($result->getDeletedCount() > 0) {
                                $response = ['success' => true, 'message' => 'Product deleted successfully.'];
                            } else {
                                $response = ['success' => false, 'message' => 'Product not deleted - operation failed or product already deleted.'];
                            }
                        } else {
                            $response = ['success' => false, 'message' => 'Product not found with the given ID.'];
                        }
                    } else {
                        $response = ['success' => false, 'message' => 'Missing product ID.'];
                    }
                } catch (MongoDB\Driver\Exception\Exception $e) {
                    error_log("MongoDB error during product deletion (ID: ".(isset($data['id']) ? $data['id'] : 'N/A')."): " . $e->getMessage());
                    $response = ['success' => false, 'message' => 'Database error during deletion. Check server logs.'];
                } catch (Exception $e) {
                    error_log("General error during product deletion (ID: ".(isset($data['id']) ? $data['id'] : 'N/A')."): " . $e->getMessage());
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
                            // error_log("InvalidArgumentException when validating ObjectId for update: " . $productIdStr . " - " . $e->getMessage());
                        } catch (Exception $e) {
                            // error_log("Generic Exception when validating ObjectId for update: " . $productIdStr . " - " . $e->getMessage());
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
                
                $session = null; 
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

                        if ($quantity <= 0) throw new Exception("Invalid quantity for product ID: " . $productIdStr);
                        if ($pricePerUnit < 0) throw new Exception("Invalid price for product ID: " . $productIdStr);
                        
                        $mongoProductId = new MongoDB\BSON\ObjectId($productIdStr);
                        $product = $db->products->findOne(['_id' => $mongoProductId], ['session' => $session]);

                        if (!$product) throw new Exception("Product not found: ID " . $productIdStr);
                        if ($product->stock < $quantity) throw new Exception("Insufficient stock for product: " . htmlspecialchars($product->name) . ". Available: " . $product->stock . ", Requested: " . $quantity);

                        $newStock = $product->stock - $quantity;
                        $updateResult = $db->products->updateOne(
                            ['_id' => $mongoProductId],
                            ['$set' => ['stock' => $newStock]],
                            ['session' => $session]
                        );

                        if ($updateResult->getModifiedCount() !== 1) throw new Exception("Failed to update stock for product: " . htmlspecialchars($product->name));
                        
                        $itemTotal = $quantity * $pricePerUnit;
                        $totalAmount += $itemTotal;
                        $billItemsDetails[] = [
                            'product_id' => $mongoProductId, 'product_name' => $product->name,
                            'quantity' => $quantity, 'price_per_unit' => $pricePerUnit, 'item_total' => $itemTotal
                        ];

                        $lowStockThreshold = $product->low_stock_threshold ?? 5; 
                        if ($newStock > 0 && $newStock <= $lowStockThreshold) {
                            $notificationSystem->saveNotification("Low stock warning: '".htmlspecialchars($product->name)."' has only {$newStock} units left.", 'warning', 'admin', 0, "Low Stock Alert");
                        } elseif ($newStock == 0) {
                             $notificationSystem->saveNotification("Out of stock: '".htmlspecialchars($product->name)."' is now out of stock.",'error', 'admin', 0, "Out of Stock");
                        }
                    }

                    $billData = [
                        'items' => $billItemsDetails, 'total_amount' => $totalAmount,
                        'created_at' => new MongoDB\BSON\UTCDateTime(),
                        'user_id' => $_SESSION['user_id'] ?? null, 'username' => $_SESSION['username'] ?? 'N/A' 
                    ];
                    $insertResult = $db->bill_new->insertOne($billData, ['session' => $session]);

                    if (!$insertResult->getInsertedId()) throw new Exception("Failed to save the bill.");
                    
                    $session->commitTransaction();
                    $response = ['success' => true, 'message' => 'Bill generated successfully.', 'bill_id' => (string)$insertResult->getInsertedId()];
                    $notificationSystem->saveNotification("New bill #{$insertResult->getInsertedId()} generated. Total: ₹" . number_format($totalAmount, 2), 'info', 'admin', 7000, "Bill Generated");

                } catch (MongoDB\Exception\InvalidArgumentException $e) { 
                    if ($session && $session->isInTransaction()) $session->abortTransaction();
                    $response = ['success' => false, 'message' => "Invalid data for MongoDB operation: " . $e->getMessage()];
                    http_response_code(400); 
                    error_log("GenerateBill MongoDB InvalidArgumentException: " . $e->getMessage());
                } catch (MongoDB\Driver\Exception\Exception $e) { 
                    if ($session && $session->isInTransaction()) $session->abortTransaction();
                    $response = ['success' => false, 'message' => "MongoDB Driver Error: " . $e->getMessage()];
                    http_response_code(500); 
                    error_log("GenerateBill MongoDB Driver Exception: " . $e->getMessage());
                } catch (Exception $e) { 
                    if ($session && $session->isInTransaction()) $session->abortTransaction();
                    $response = ['success' => false, 'message' => "Error generating bill: " . $e->getMessage()];
                    http_response_code(500); 
                    error_log("GenerateBill Exception: " . $e->getMessage());
                } catch (Throwable $t) { 
                    if ($session && $session->isInTransaction()) $session->abortTransaction();
                    $response = ['success' => false, 'message' => "Critical error generating bill: " . $t->getMessage()];
                    http_response_code(500);
                    error_log("GenerateBill Throwable: " . $t->getMessage());
                } finally {
                    if ($session) $session->endSession();
                }
                break;
        }
    } catch (Exception $e) { 
        error_log("Server.php JSON POST Error (action: " . ($action ?? 'unknown') . "): " . $e->getMessage());
        $response = ['success' => false, 'message' => 'A server error occurred: ' . $e->getMessage()];
    }
    echo json_encode($response);
    exit;
    
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json'); 
    $action = $_POST['action'];
    $response = ['success' => false, 'status' => 'error', 'message' => 'Invalid action or insufficient data.'];

    if (!$db || !$notificationSystem) {
        echo json_encode(['success' => false, 'status' => 'error', 'message' => 'Server error: Database or notification system not available.']);
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
                        'success', 'staff', 7000, "Product Added"
                    );
                    $response = ['success' => true, 'status' => 'success', 'message' => 'Product added successfully.'];
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
                    } catch (MongoDB\Exception\InvalidArgumentException $e) { /* log */ } 
                    catch (Exception $e) { /* log */ }
                }

                if (isset($_POST['quantity']) && $isValidObjectId) {
                    $quantity = (int)$_POST['quantity'];
                    if ($quantity <= 0) { $response['message'] = "Invalid quantity."; break; }

                    $product = $db->products->findOne(['_id' => $mongoProductId]);
                    if ($product && $product->stock >= $quantity) {
                        $db->products->updateOne(['_id' => $mongoProductId], ['$inc' => ['stock' => -$quantity]]);
                        // $db->bill->insertOne([ ... ]); // Original collection 'bill'
                        // ... (stock alerts & notifications logic) ...
                        $response = ['success' => true, 'status' => 'success', 'message' => "'{$product->name}' billed successfully."];
                    } else { $response['message'] = 'Product not found or insufficient stock.'; }
                } else { $response['message'] = 'Missing or invalid product ID or quantity.'; }
                break;

            case 'authenticateUser':
                 if (isset($_POST['username'], $_POST['password'])) {
                    $username = filter_var(trim($_POST['username']), FILTER_SANITIZE_STRING);
                    $password = $_POST['password']; 

                    $user = $db->user->findOne(['username' => $username]);

                    if ($user && isset($user->password) && $password === $user->password) { // PLAIN TEXT - HASH IN PRODUCTION
                        $_SESSION['user_id'] = (string) $user->_id;
                        $_SESSION['username'] = $user->username;
                        $_SESSION['user_role'] = $user->role;
                        $_SESSION['user_email'] = isset($user->email) ? $user->email : $user->username . '@example.com';

                        $notificationSystem->saveNotification("Welcome back, {$user->username}! You logged in successfully.", 'success', 'all', 5000, "Login Successful");
                        $response = ['success' => true, 'status' => 'success', 'role' => $user->role, 'username' => $user->username];
                    } else {
                        $response['message'] = 'Invalid username or password.';
                    }
                }
                break;
            
            case 'requestPairingId':
                if (!isset($_SESSION['user_id'])) {
                    $response = ['success' => false, 'message' => 'User not authenticated.'];
                    break;
                }
                $pairing_id = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6)); 

                $expires_at = new MongoDB\BSON\UTCDateTime((time() + 15 * 60) * 1000);
                $sessionData = [
                    'pairing_id' => $pairing_id,
                    'staff_user_id' => $_SESSION['user_id'],
                    'staff_username' => $_SESSION['username'],
                    'created_at' => new MongoDB\BSON\UTCDateTime(),
                    'expires_at' => $expires_at,
                    'status' => 'pending', 
                    'scanned_items' => [] 
                ];
                try {
                    $db->pairing_sessions->insertOne($sessionData);
                    $response = ['success' => true, 'pairing_id' => $pairing_id];
                } catch (MongoDB\Driver\Exception\BulkWriteException $e) {
                    $writeConcernError = $e->getWriteResult()->getWriteConcernError();
                    if ($writeConcernError && $writeConcernError->getCode() === 11000) { 
                        $response = ['success' => false, 'message' => 'Could not generate a unique pairing ID. Please try again.'];
                    } else {
                        throw $e; 
                    }
                }
                break;

            case 'submitScannedProduct':
                if (isset($_POST['pairing_id'], $_POST['scanned_product_id'])) {
                    $pairing_id = trim(strtoupper($_POST['pairing_id']));
                    $scanned_product_id = trim($_POST['scanned_product_id']);
                    $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;

                    $session = $db->pairing_sessions->findOne(['pairing_id' => $pairing_id]);

                    if (!$session) {
                        $response = ['success' => false, 'message' => 'Pairing ID not found.'];
                        break;
                    }
                    if ($session->expires_at->toDateTime() < new DateTimeImmutable()) {
                        $db->pairing_sessions->updateOne(['_id' => $session->_id], ['$set' => ['status' => 'expired']]);
                        $response = ['success' => false, 'message' => 'Pairing session expired.'];
                        break;
                    }
                    $product = null;
                    $isValidObjectId = false;
                    try {
                        $mongoProdId = new MongoDB\BSON\ObjectId($scanned_product_id);
                        $isValidObjectId = true;
                    } catch (MongoDB\Exception\InvalidArgumentException $e) {
                        // Not a valid ObjectId, might be a different type of barcode (e.g. EAN)
                        // For now, we require ObjectId string. This can be extended.
                    }

                    if ($isValidObjectId) {
                        $product = $db->products->findOne(['_id' => $mongoProdId]);
                    } else {
                        // If you support other barcode types, query by that field here.
                        // e.g., $product = $db->products->findOne(['barcode_ean' => $scanned_product_id]);
                    }
                     
                    if (!$product) {
                        $response = ['success' => false, 'message' => "Scanned Product ID/Barcode '{$scanned_product_id}' not found in database."];
                        break;
                    }

                    $newItem = [
                        'product_id' => (string)$product->_id, // Store the canonical DB ID (ObjectId string)
                        'product_name' => $product->name, 
                        'price' => $product->price,       
                        'scanned_at' => new MongoDB\BSON\UTCDateTime(),
                        'processed' => false,
                        'quantity' => $quantity
                    ];

                    $updateResult = $db->pairing_sessions->updateOne(
                        ['_id' => $session->_id],
                        [
                            '$push' => ['scanned_items' => $newItem],
                            '$set' => ['status' => 'active'] 
                        ]
                    );

                    if ($updateResult->getModifiedCount() > 0) {
                        $response = ['success' => true, 'message' => 'Product scan received.', 'product_name' => $product->name];
                    } else {
                        $response = ['success' => false, 'message' => 'Failed to record scanned product.'];
                    }
                } else {
                     $response = ['success' => false, 'message' => 'Missing pairing ID or scanned product ID.'];
                }
                break;

            default:
                http_response_code(400); 
                break;
        }
    } catch (MongoDB\Exception\InvalidArgumentException $e) {
        $response = ['success' => false, 'status' => 'error', 'message' => 'Invalid data format for database operation. ' . $e->getMessage()];
    } catch (Exception $e) {
        error_log("Server.php POST Error: " . $e->getMessage() . " for action: " . $action);
        $response = ['success' => false, 'status' => 'error', 'message' => 'A server error occurred: ' . $e->getMessage()];
    }
    echo json_encode($response);
    exit;

} elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
    header('Content-Type: application/json'); 
    $action = $_GET['action'];
    $response = ['success' => false, 'message' => 'Unknown GET action or error.']; 

    if (!$db) { 
        error_log("DB not available for GET action: " . $action);
        echo json_encode(['success' => false, 'message' => 'Server error: Database not available for GET request.']);
        exit;
    }

    try {
        switch ($action) {
            case 'getProducts':
                $products = $db->products->find([], ['sort' => ['name' => 1]])->toArray();
                echo json_encode($products);
                exit; 

            case 'getProduct':
                if (isset($_GET['id'])) {
                    $productIdStr = $_GET['id'];
                    $isValidObjectId = false; $mongoId = null;
                    try { $mongoId = new MongoDB\BSON\ObjectId($productIdStr); $isValidObjectId = true; } catch (Exception $e) {/*log*/}

                    if ($isValidObjectId) {
                        $product = $db->products->findOne(['_id' => $mongoId]);
                        if ($product) { echo json_encode($product); exit; }
                        else { $response = ['success' => false, 'message' => 'Product not found.']; http_response_code(404); }
                    } else { $response = ['success' => false, 'message' => 'Invalid Product ID format.']; http_response_code(400); }
                } else { $response = ['success' => false, 'message' => 'Product ID not provided.']; http_response_code(400); }
                break;

            case 'getBills':
                $bills = $db->bill->find([], ['sort' => ['date' => -1]])->toArray(); // or bill_new
                echo json_encode($bills); 
                exit;
            
            case 'getSales': 
                $sales = $db->bill_new->find([], ['sort' => ['created_at' => -1]])->toArray(); // or bill
                echo json_encode($sales);
                exit;
            
            case 'getScannedItems':
                if (isset($_GET['pairing_id'])) {
                    $pairing_id = trim(strtoupper($_GET['pairing_id']));
                    $session = $db->pairing_sessions->findOne([
                        'pairing_id' => $pairing_id,
                        'staff_user_id' => $_SESSION['user_id'] 
                    ]);

                    if (!$session) {
                        $response = ['success' => false, 'message' => 'Pairing session not found or not authorized.'];
                        break;
                    }
                    if ($session->expires_at->toDateTime() < new DateTimeImmutable()) {
                        $db->pairing_sessions->updateOne(['_id' => $session->_id], ['$set' => ['status' => 'expired']]);
                        $response = ['success' => false, 'message' => 'Pairing session expired.'];
                        break;
                    }

                    $unprocessedItems = [];
                    $itemUpdateQueries = []; // For bulkWrite
                    
                    foreach ($session->scanned_items as $index => $item) { // Need index for positional operator
                        if (isset($item->processed) && $item->processed === false) {
                            $unprocessedItems[] = $item; 
                             // Mark as processed using positional operator $
                            $itemUpdateQueries[] = new MongoDB\UpdateOne(
                                ['_id' => $session->_id, "scanned_items.scanned_at" => $item->scanned_at], // More robust match
                                ['$set' => ["scanned_items.$.processed" => true]]
                            );
                        }
                    }

                    if (!empty($itemUpdateQueries)) {
                        $bulkWriteResult = $db->pairing_sessions->bulkWrite($itemUpdateQueries);
                        if ($bulkWriteResult->getModifiedCount() !== count($unprocessedItems)) {
                            error_log("Mismatch processing scanned items for pairing_id: {$pairing_id}. Expected: " . count($unprocessedItems) . ", Modified: " . $bulkWriteResult->getModifiedCount());
                        }
                    }
                    $response = ['success' => true, 'items' => $unprocessedItems];

                } else {
                    $response = ['success' => false, 'message' => 'Pairing ID not provided.'];
                }
                break;

            default:
                $response = ['success' => false, 'message' => 'Unknown GET action.'];
        }
    } catch (Exception $e) {
        error_log("Server.php GET Error: " . $e->getMessage() . " for action: " . $action);
        $response = ['success' => false, 'message' => 'A server error occurred during data retrieval: ' . $e->getMessage()];
    }
    
    echo json_encode($response); 
    exit;
} else {
    header('Content-Type: application/json');
    http_response_code(400); 
    echo json_encode(['success' => false, 'status' => 'error', 'message' => 'Invalid request method or action not specified.']);
    exit;
}

// Fallback if no action matched (should not be reached if POST/GET with action is handled)
if (basename(__FILE__) == basename($_SERVER["SCRIPT_FILENAME"])) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['success' => false, 'status' => 'error', 'message' => 'No action specified or invalid request method.']);
    exit;
}
?>