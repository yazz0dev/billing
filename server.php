<?php
//billing/server.php

// Global error handling for fatal errors
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR])) {
        if (!headers_sent() && (isset($_SERVER['HTTP_ACCEPT']) && strpos(strtolower($_SERVER['HTTP_ACCEPT']), 'application/json') !== false || (isset($_GET['action']) || isset($_POST['action'])))) {
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode([
                'success' => false, 'status' => 'error', 'message' => 'A critical server error occurred.',
                'error_details' => ['type' => $error['type'], 'message' => $error['message'], 'file' => basename($error['file']), 'line' => $error['line']]
            ]);
        }
        error_log(sprintf("Fatal error: type %d, Message: %s, File: %s, Line: %d", $error['type'], $error['message'], $error['file'], $error['line']));
    }
});

error_reporting(E_ALL);
ini_set('display_errors', 0);

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
} else {
    if (php_sapi_name() !== 'cli' && (!isset($_SERVER['HTTP_ACCEPT']) || strpos(strtolower($_SERVER['HTTP_ACCEPT']), 'application/json') === false)) {
        // This case should ideally not happen if router is entry point
    } else {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Server configuration error: Autoloader not found.']);
        exit;
    }
}

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

require_once 'notification.php';
use MongoDB\Driver\ServerApi;

$mongoClient = null;
$db = null;
$notificationSystem = null;

try {
    if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
        throw new Exception("Autoloader not found. This is a critical error.");
    }
    $uri = defined('MONGODB_URI') ? MONGODB_URI : 'mongodb://localhost:27017';
    $uriOptions = [];
    $driverOptions = ['serverSelectionTimeoutMS' => 10000, 'connectTimeoutMS' => 15000];

    if (strpos($uri, 'mongodb+srv://') === 0 || strpos($uri, '.mongodb.net') !== false) {
        $uriOptions['retryWrites'] = true;
        if (class_exists('MongoDB\\Driver\\ServerApi')) {
            $driverOptions['serverApi'] = new MongoDB\Driver\ServerApi(MongoDB\Driver\ServerApi::V1);
        }
    }
    $mongoClient = new MongoDB\Client($uri, $uriOptions, $driverOptions);
    $connectionSuccessful = false; $connectionAttempts = 0; $maxAttempts = 3;
    while (!$connectionSuccessful && $connectionAttempts < $maxAttempts) {
        try {
            $connectionAttempts++;
            $mongoClient->selectDatabase('admin')->command(['ping' => 1]);
            $connectionSuccessful = true;
        } catch (Exception $e) {
            error_log("MongoDB connection attempt {$connectionAttempts} failed: " . $e->getMessage());
            if ($connectionAttempts >= $maxAttempts) throw $e;
            usleep(pow(2, $connectionAttempts - 1) * 100000); // Exponential backoff
        }
    }
    $db = $mongoClient->selectDatabase('billing');
    $notificationSystem = new NotificationSystem($mongoClient);
    if (!$notificationSystem->checkDbConnection()) {
        throw new Exception("Notification system database connection check failed");
    }
    $existingCollections = [];
    foreach ($db->listCollections() as $collectionInfo) { $existingCollections[] = $collectionInfo->getName(); }
    $requiredCollections = ['user', 'products', 'bill', 'bill_new', 'popup_notifications', 'pairing_sessions'];
    foreach ($requiredCollections as $collectionName) {
        if (!in_array($collectionName, $existingCollections)) {
            $db->createCollection($collectionName);
            if ($collectionName === 'pairing_sessions') {
                // Ensures only one active or pending session per staff user
                $db->pairing_sessions->createIndex(['staff_user_id' => 1, 'status' => 1]); 
                // TTL for automatic cleanup of old sessions
                $db->pairing_sessions->createIndex(['session_expires_at' => 1], ['expireAfterSeconds' => 0]);
            }
        }
    }
} catch (Exception $e) {
    $errorCode = 'CONNECTION_FAILED';
    if ($e instanceof MongoDB\Driver\Exception\AuthenticationException) $errorCode = 'AUTH_FAILED';
    elseif ($e instanceof MongoDB\Driver\Exception\ConnectionTimeoutException) $errorCode = 'CONNECTION_TIMEOUT';
    elseif ($e instanceof MongoDB\Driver\Exception\ServerSelectionTimeoutException) $errorCode = 'SERVER_SELECTION_FAILED';
    elseif ($e instanceof MongoDB\Driver\Exception\InvalidArgumentException) $errorCode = 'INVALID_CONNECTION_STRING';
    
    error_log('Database/Notification System Init Error: ' . $e->getMessage());
    if (!headers_sent() && (isset($_SERVER['HTTP_ACCEPT']) && strpos(strtolower($_SERVER['HTTP_ACCEPT']), 'application/json') !== false )) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Database connection error. Please try again later.', 'error_code' => $errorCode]);
    }
    exit;
}

if (session_status() === PHP_SESSION_NONE) { session_start(); }

$response = ['success' => false, 'message' => 'Invalid request.']; // Default response

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? ($_GET['action'] ?? null); 
    $isJsonRequest = isset($_SERVER['CONTENT_TYPE']) && stripos($_SERVER['CONTENT_TYPE'], 'application/json') !== false;
    $data = [];

    if ($isJsonRequest) {
        $jsonInput = file_get_contents('php://input');
        $data = json_decode($jsonInput, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $response = ['success' => false, 'message' => 'Invalid JSON: ' . json_last_error_msg()];
            if (!headers_sent()) { header('Content-Type: application/json');}
            echo json_encode($response);
            exit;
        }
    } else {
        $data = $_POST; 
    }
    
    if (!$action) {
        $response = ['success' => false, 'message' => 'Action not specified.'];
        if (!headers_sent()) { header('Content-Type: application/json');}
        echo json_encode($response);
        exit;
    }

    try {
        switch ($action) {
            case 'addProduct':
                if (isset($data['name'], $data['price'], $data['stock'])) {
                    $product = [
                        'name' => filter_var($data['name'], FILTER_SANITIZE_STRING),
                        'price' => (float)$data['price'],
                        'stock' => (int)$data['stock']
                    ];
                    if (empty($product['name']) || $product['price'] < 0 || $product['stock'] < 0) {
                         $response['message'] = "Invalid product data."; break;
                    }
                    $db->products->insertOne($product);
                    $notificationSystem->saveNotification("Product '{$product['name']}' added.", 'success', 'staff', 7000, "Product Added");
                    $response = ['success' => true, 'status' => 'success', 'message' => 'Product added.'];
                } else { $response['message'] = 'Missing product data.'; }
                break;

            case 'deleteProduct':
                 if (isset($data['id'])) {
                    $productIdStr = $data['id']; $mongoId = null;
                    try { $mongoId = new MongoDB\BSON\ObjectId($productIdStr); } catch (Exception $e) { /* Invalid ID */ }
                    if ($mongoId) {
                        $result = $db->products->deleteOne(['_id' => $mongoId]);
                        if ($result->getDeletedCount() > 0) {
                            $response = ['success' => true, 'message' => 'Product deleted.'];
                        } else { $response['message'] = 'Product not found or already deleted.'; }
                    } else { $response['message'] = 'Invalid Product ID.'; }
                } else { $response['message'] = 'Product ID missing.'; }
                break;

            case 'updateProduct':
                if (isset($data['id'], $data['name'], $data['price'], $data['stock'])) {
                    $productIdStr = $data['id']; $mongoId = null;
                    try { $mongoId = new MongoDB\BSON\ObjectId($productIdStr); } catch (Exception $e) { /* Invalid ID */ }
                    if ($mongoId) {
                        $name = filter_var($data['name'], FILTER_SANITIZE_STRING);
                        $price = (float)$data['price']; $stock = (int)$data['stock'];
                        if (empty($name) || $price < 0 || $stock < 0) {
                             $response['message'] = "Invalid product data."; break;
                        }
                        $update = ['$set' => ['name' => $name, 'price' => $price, 'stock' => $stock]];
                        $result = $db->products->updateOne(['_id' => $mongoId], $update);
                        if ($result->getMatchedCount() > 0) {
                            $response = ['success' => true, 'message' => $result->getModifiedCount() > 0 ? 'Product updated.' : 'No changes made.'];
                        } else { $response['message'] = 'Product not found.'; }
                    } else { $response['message'] = 'Invalid Product ID.'; }
                } else { $response['message'] = 'Missing product data.'; }
                break;
            
            case 'generateBill':
                 if (!isset($data['items']) || !is_array($data['items']) || empty($data['items'])) {
                    $response['message'] = 'No items for bill.'; http_response_code(400); break;
                }
                $items = $data['items']; $totalAmount = 0; $billItemsDetails = [];
                $dbSession = $mongoClient->startSession();
                try {
                    $dbSession->startTransaction();
                    foreach ($items as $item) {
                        if (!isset($item['product_id'], $item['quantity'], $item['price'])) throw new Exception("Invalid item data.");
                        $productIdStr = $item['product_id']; $quantity = (int)$item['quantity']; $pricePerUnit = (float)$item['price'];
                        if ($quantity <= 0 || $pricePerUnit < 0) throw new Exception("Invalid quantity/price.");
                        $mongoProductId = new MongoDB\BSON\ObjectId($productIdStr);
                        $product = $db->products->findOne(['_id' => $mongoProductId], ['session' => $dbSession]);
                        if (!$product) throw new Exception("Product ID {$productIdStr} not found.");
                        if ($product->stock < $quantity) throw new Exception("Stock issue for {$product->name}. Available: {$product->stock}, Req: {$quantity}");
                        $newStock = $product->stock - $quantity;
                        $updateRes = $db->products->updateOne(['_id' => $mongoProductId],['$set' => ['stock' => $newStock]],['session' => $dbSession]);
                        if ($updateRes->getModifiedCount() !== 1) throw new Exception("Stock update failed for {$product->name}.");
                        $itemTotal = $quantity * $pricePerUnit; $totalAmount += $itemTotal;
                        $billItemsDetails[] = ['product_id' => $mongoProductId, 'product_name' => $product->name, 'quantity' => $quantity, 'price_per_unit' => $pricePerUnit, 'item_total' => $itemTotal];
                        $lowStock = $product->low_stock_threshold ?? 5;
                        if ($newStock <= $lowStock && $newStock > 0) $notificationSystem->saveNotification("Low stock: {$product->name} ({$newStock} left).", 'warning', 'admin', 0, "Low Stock");
                        elseif ($newStock == 0) $notificationSystem->saveNotification("Out of stock: {$product->name}.", 'error', 'admin', 0, "Out of Stock");
                    }
                    $billData = ['items' => $billItemsDetails, 'total_amount' => $totalAmount, 'created_at' => new MongoDB\BSON\UTCDateTime(), 'user_id' => $_SESSION['user_id'] ?? null, 'username' => $_SESSION['username'] ?? 'N/A'];
                    $insertRes = $db->bill_new->insertOne($billData, ['session' => $dbSession]);
                    if (!$insertRes->getInsertedId()) throw new Exception("Failed to save bill.");
                    $dbSession->commitTransaction();
                    $response = ['success' => true, 'message' => 'Bill generated.', 'bill_id' => (string)$insertRes->getInsertedId()];
                    $notificationSystem->saveNotification("Bill #{$insertRes->getInsertedId()} (₹" . number_format($totalAmount, 2) . ") created.", 'info', 'admin', 7000, "Bill Generated");
                } catch (Exception $e) {
                    if ($dbSession->isInTransaction()) $dbSession->abortTransaction();
                    $response = ['message' => "Bill error: " . $e->getMessage()]; http_response_code(500);
                    error_log("GenerateBill Error: " . $e->getMessage());
                } finally { $dbSession->endSession(); }
                break;

            case 'authenticateUser':
                if (isset($data['username'], $data['password'])) {
                    $username = filter_var(trim($data['username']), FILTER_SANITIZE_STRING);
                    $password = $data['password'];
                    $user = $db->user->findOne(['username' => $username]);
                    if ($user && isset($user->password) && $password === $user->password) { // PLAIN TEXT - HASH IN PRODUCTION!
                        $_SESSION['user_id'] = (string) $user->_id; $_SESSION['username'] = $user->username;
                        $_SESSION['user_role'] = $user->role; $_SESSION['user_email'] = $user->email ?? ($user->username . '@example.com');
                        $notificationSystem->saveNotification("Welcome {$user->username}!", 'success', 'all', 5000, "Login OK");
                        $response = ['success' => true, 'status' => 'success', 'role' => $user->role, 'username' => $user->username];
                    } else { $response['message'] = 'Invalid credentials.'; }
                } else { $response['message'] = 'Username/password missing.'; }
                break;

            case 'activateMobileScanning': // Desktop POS calls this
                if (!isset($_SESSION['user_id'])) { $response['message'] = 'User not authenticated.'; break; }
                $staff_user_id = new MongoDB\BSON\ObjectId($_SESSION['user_id']);
                $staff_username = $_SESSION['username'];

                $db->pairing_sessions->updateMany(
                    ['staff_user_id' => $staff_user_id, 'status' => ['$in' => ['desktop_initiated_pairing', 'mobile_active']]],
                    ['$set' => ['status' => 'superseded_by_desktop']]
                );
                $session_expires_at = new MongoDB\BSON\UTCDateTime((time() + 8 * 3600) * 1000);
                $newPairingSession = ['staff_user_id' => $staff_user_id, 'staff_username' => $staff_username, 'desktop_session_id' => session_id(), 'created_at' => new MongoDB\BSON\UTCDateTime(), 'session_expires_at' => $session_expires_at, 'status' => 'desktop_initiated_pairing', 'scanned_items' => []];
                try {
                    $insertResult = $db->pairing_sessions->insertOne($newPairingSession);
                    if ($insertResult->getInsertedId()) {
                        $response = ['success' => true, 'message' => 'Mobile scanner mode activated. Waiting for mobile.', 'staff_username' => $staff_username];
                    } else { $response['message'] = 'Failed to create activation session.'; }
                } catch (Exception $e) { $response['message'] = 'DB error activating session.'; error_log("ActivateMobileScanning Error: ".$e->getMessage());}
                break;

            case 'deactivateMobileScanning': // Desktop POS calls this
                if (!isset($_SESSION['user_id'])) { $response['message'] = 'User not authenticated.'; break; }
                $staff_user_id = new MongoDB\BSON\ObjectId($_SESSION['user_id']);
                $updateResult = $db->pairing_sessions->updateMany(
                    ['staff_user_id' => $staff_user_id, 'status' => ['$in' => ['desktop_initiated_pairing', 'mobile_active']]],
                    ['$set' => ['status' => 'completed_by_desktop']]
                );
                $response = ['success' => true, 'message' => 'Mobile scanner mode deactivated.'];
                if($updateResult->getModifiedCount() > 0) {
                    $notificationSystem->saveNotification("Mobile scanner deactivated by {$_SESSION['username']}", 'info', (string)$staff_user_id, 3000, "Scanner Deactivated");
                }
                break;

            case 'submitScannedProduct': // Mobile scanner calls this
                if (!isset($_SESSION['user_id'], $data['scanned_product_id'])) { $response['message'] = 'Mobile auth/product ID missing.'; break; }
                $staff_user_id_obj = new MongoDB\BSON\ObjectId($_SESSION['user_id']);
                $scanned_product_id = trim($data['scanned_product_id']);
                $quantity = isset($data['quantity']) ? (int)$data['quantity'] : 1;
                $activePairingSession = $db->pairing_sessions->findOne(['staff_user_id' => $staff_user_id_obj, 'status' => 'mobile_active']);
                if (!$activePairingSession) { $response['message'] = 'No active pairing session for scanning. Activate on POS or re-login on mobile.'; break; }
                $product = null; $mongoProdId = null;
                try { $mongoProdId = new MongoDB\BSON\ObjectId($scanned_product_id); } catch (Exception $e) { /* Not ObjectId */ }
                if ($mongoProdId) $product = $db->products->findOne(['_id' => $mongoProdId]);
                if (!$product) { $response['message'] = "Product '{$scanned_product_id}' not found."; break; }
                $newItem = ['product_id' => (string)$product->_id, 'product_name' => $product->name, 'price' => (float)$product->price, 'quantity' => $quantity, 'scanned_at' => new MongoDB\BSON\UTCDateTime(), 'processed' => false];
                $updateResult = $db->pairing_sessions->updateOne(['_id' => $activePairingSession->_id], ['$push' => ['scanned_items' => $newItem]]);
                if ($updateResult->getModifiedCount() > 0) {
                    $response = ['success' => true, 'message' => 'Scan recorded.', 'product_name' => $product->name];
                } else { $response['message'] = 'Failed to record scan to active session.'; }
                break;

            default: $response['message'] = 'Unknown POST action.'; http_response_code(400); break;
        }
    } catch (Exception $e) {
        error_log("Server.php POST Action '{$action}' Error: " . $e->getMessage());
        $response = ['success' => false, 'message' => 'Server error processing request: ' . $e->getMessage()];
        http_response_code(500);
    }

} elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
    header('Content-Type: application/json'); // Ensure this is set for all GET actions
    $action = $_GET['action'];
    try {
        switch ($action) {
            case 'getProducts':
                $products = $db->products->find([], ['sort' => ['name' => 1]])->toArray();
                echo json_encode($products); exit;

            case 'getProduct':
                if (isset($_GET['id'])) {
                    $productIdStr = $_GET['id']; $mongoId = null;
                    try { $mongoId = new MongoDB\BSON\ObjectId($productIdStr); } catch (Exception $e) { /* Invalid ID */ }
                    if ($mongoId) {
                        $product = $db->products->findOne(['_id' => $mongoId]);
                        if ($product) { echo json_encode($product); exit; }
                        else { $response['message'] = 'Product not found.'; http_response_code(404); }
                    } else { $response['message'] = 'Invalid Product ID.'; http_response_code(400); }
                } else { $response['message'] = 'Product ID missing.'; http_response_code(400); }
                break;

            case 'getBills':
                $bills = $db->bill_new->find([], ['sort' => ['created_at' => -1]])->toArray();
                echo json_encode($bills); exit;

            case 'getSales':
                $sales = $db->bill_new->find([], ['sort' => ['created_at' => -1]])->toArray();
                echo json_encode($sales); exit;

            case 'activateMobileScannerSession': // Mobile scanner page calls on load/retry
                if (!isset($_SESSION['user_id'])) { $response = ['success' => false, 'session_activated' => false, 'message' => 'Mobile user not authenticated.']; break; }
                $staff_user_id_obj = new MongoDB\BSON\ObjectId($_SESSION['user_id']);
                $current_user_display = $_SESSION['username'] ?? 'N/A';
                $desktopInitiatedSession = $db->pairing_sessions->findOneAndUpdate(
                    ['staff_user_id' => $staff_user_id_obj, 'status' => 'desktop_initiated_pairing'],
                    ['$set' => ['status' => 'mobile_active', 'mobile_session_id' => session_id(), 'last_mobile_heartbeat' => new MongoDB\BSON\UTCDateTime()]],
                    ['returnDocument' => MongoDB\Operation\FindOneAndUpdate::AFTER]
                );
                if ($desktopInitiatedSession) {
                    $response = ['success' => true, 'session_activated' => true, 'message' => 'Scanner session activated.', 'staff_username' => $desktopInitiatedSession->staff_username];
                } else {
                    $alreadyActiveSession = $db->pairing_sessions->findOne(['staff_user_id' => $staff_user_id_obj, 'status' => 'mobile_active', 'mobile_session_id' => session_id()]);
                     if($alreadyActiveSession){
                        $db->pairing_sessions->updateOne(['_id' => $alreadyActiveSession->_id], ['$set' => ['last_mobile_heartbeat' => new MongoDB\BSON\UTCDateTime()]]);
                        $response = ['success' => true, 'session_activated' => true, 'message' => 'Scanner session already active.', 'staff_username' => $alreadyActiveSession->staff_username, 'current_user' => $current_user_display];
                     } else {
                        $response = ['success' => false, 'session_activated' => false, 'message' => 'POS terminal has not activated scanner mode for your account, or another mobile is active.', 'current_user' => $current_user_display];
                     }
                }
                break;
            
            case 'checkDesktopScannerActivation': // Desktop POS calls on load
                if (!isset($_SESSION['user_id'])) { $response = ['success' => false, 'is_active' => false, 'message' => 'Desktop user not authenticated.']; break; }
                $staff_user_id_obj = new MongoDB\BSON\ObjectId($_SESSION['user_id']);
                $activeSession = $db->pairing_sessions->findOne(['staff_user_id' => $staff_user_id_obj, 'status' => ['$in' => ['desktop_initiated_pairing', 'mobile_active']]]);
                if ($activeSession) {
                    $status_message = $activeSession->status === 'mobile_active' ? 'Mobile scanner is connected and active.' : 'Waiting for your mobile scanner to connect.';
                    $response = ['success' => true, 'is_active' => true, 'status' => $activeSession->status, 'staff_username' => $activeSession->staff_username, 'message' => $status_message];
                } else {
                    $response = ['success' => true, 'is_active' => false, 'message' => 'Scanner mode not active.'];
                }
                break;
            
            case 'getScannedItems': // Desktop POS polls this
                if (!isset($_SESSION['user_id'])) { $response['message'] = 'User not authenticated.'; break; }
                $staff_user_id_obj = new MongoDB\BSON\ObjectId($_SESSION['user_id']);
                $activePairingSession = $db->pairing_sessions->findOne(['staff_user_id' => $staff_user_id_obj, 'status' => 'mobile_active']);
                if (!$activePairingSession) { $response = ['success' => true, 'items' => [], 'message' => 'No active mobile scanner confirmed for this POS session.']; break; }
                $unprocessedItems = []; $itemUpdateQueries = [];
                if (isset($activePairingSession->scanned_items) && (is_array($activePairingSession->scanned_items) || $activePairingSession->scanned_items instanceof Traversable)) {
                    foreach ($activePairingSession->scanned_items as $item) {
                        if (isset($item->processed) && $item->processed === false) {
                            $unprocessedItems[] = $item;
                            // Ensure $item->scanned_at is a BSON\UTCDateTime object before using in query
                            $scannedAtQueryVal = $item->scanned_at;
                            if (!$scannedAtQueryVal instanceof MongoDB\BSON\UTCDateTime && isset($scannedAtQueryVal->{'$date'}->{'$numberLong'})) {
                                $scannedAtQueryVal = new MongoDB\BSON\UTCDateTime($scannedAtQueryVal->{'$date'}->{'$numberLong'});
                            } elseif (is_string($scannedAtQueryVal)) { // Fallback if it's somehow a string
                                 try { $scannedAtQueryVal = new MongoDB\BSON\UTCDateTime(strtotime($scannedAtQueryVal)*1000); } catch (Exception $e) { continue; /* skip if invalid date */ }
                            }
                            if ($scannedAtQueryVal instanceof MongoDB\BSON\UTCDateTime) { // Only add if valid
                                $itemUpdateQueries[] = new MongoDB\UpdateOne(['_id' => $activePairingSession->_id, "scanned_items.scanned_at" => $scannedAtQueryVal], ['$set' => ["scanned_items.$.processed" => true]]);
                            }
                        }
                    }
                }
                if (!empty($itemUpdateQueries)) { $db->pairing_sessions->bulkWrite($itemUpdateQueries); }
                $response = ['success' => true, 'items' => $unprocessedItems];
                break;

            default: $response['message'] = 'Unknown GET action.'; http_response_code(400); break;
        }
    } catch (Exception $e) {
        error_log("Server.php GET Action '{$action}' Error: " . $e->getMessage());
        $response = ['success' => false, 'message' => 'Server error: ' . $e->getMessage()];
        http_response_code(500);
    }
} else {
    http_response_code(400);
    $response['message'] = 'Invalid request method or parameters.';
}

if (!headers_sent()) {
    header('Content-Type: application/json');
}
echo json_encode($response);
exit;
?>