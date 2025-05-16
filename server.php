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
            usleep(pow(2, $connectionAttempts - 1) * 100000);
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
                $db->pairing_sessions->createIndex(['staff_user_id' => 1, 'status' => 1]);
                $db->pairing_sessions->createIndex(['pairing_token' => 1], ['unique' => true, 'partialFilterExpression' => ['pairing_token' => ['$exists' => true]]]);
                $db->pairing_sessions->createIndex(['token_expires_at' => 1], ['expireAfterSeconds' => 0]); // TTL for tokens
                $db->pairing_sessions->createIndex(['session_expires_at' => 1], ['expireAfterSeconds' => 0]); // TTL for sessions
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
    $action = $_POST['action'] ?? ($_GET['action'] ?? null); // Allow action in GET for JSON POST
    $isJsonRequest = isset($_SERVER['CONTENT_TYPE']) && stripos($_SERVER['CONTENT_TYPE'], 'application/json') !== false;
    $data = [];

    if ($isJsonRequest) {
        $jsonInput = file_get_contents('php://input');
        $data = json_decode($jsonInput, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            echo json_encode(['success' => false, 'message' => 'Invalid JSON: ' . json_last_error_msg()]);
            exit;
        }
    } else {
        $data = $_POST; // Use $_POST for form-data
    }
    
    if (!$action) {
        echo json_encode(['success' => false, 'message' => 'Action not specified.']);
        exit;
    }

    try {
        switch ($action) {
            case 'addProduct': // From Admin or Product page form
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

            case 'deleteProduct': // From Product page (JSON POST via GET action)
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

            case 'updateProduct': // From Product page (JSON POST via GET action)
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
            
            case 'generateBill': // From POS (JSON POST via GET action)
                 if (!isset($data['items']) || !is_array($data['items']) || empty($data['items'])) {
                    $response = ['message' => 'No items for bill.']; http_response_code(400); break;
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
                        if ($product->stock < $quantity) throw new Exception("Stock issue for {$product->name}.");
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

            case 'authenticateUser': // From Login page form
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

            case 'initiateMobilePairing': // Desktop POS calls this
                if (!isset($_SESSION['user_id'])) { $response['message'] = 'User not authenticated.'; break; }
                $staff_user_id = new MongoDB\BSON\ObjectId($_SESSION['user_id']);
                $db->pairing_sessions->updateMany(
                    ['staff_user_id' => $staff_user_id, '$or' => [['status' => 'awaiting_mobile_scan'], ['status' => 'mobile_paired_active']]],
                    ['$set' => ['status' => 'superseded', 'pairing_token' => null]]
                );
                $pairing_token = bin2hex(random_bytes(16));
                $token_expires_at = new MongoDB\BSON\UTCDateTime((time() + 3 * 60) * 1000); // Token valid for 3 mins
                $session_expires_at = new MongoDB\BSON\UTCDateTime((time() + 8 * 3600) * 1000); // Pairing session 8 hours
                $pairingSessionData = ['staff_user_id' => $staff_user_id, 'staff_username' => $_SESSION['username'], 'pairing_token' => $pairing_token, 'created_at' => new MongoDB\BSON\UTCDateTime(), 'token_expires_at' => $token_expires_at, 'session_expires_at' => $session_expires_at, 'status' => 'awaiting_mobile_scan', 'mobile_device_identifier' => null, 'scanned_items' => []];
                try {
                    $insertResult = $db->pairing_sessions->insertOne($pairingSessionData);
                    if ($insertResult->getInsertedId()) {
                        $response = ['success' => true, 'pairing_token' => $pairing_token];
                    } else { $response['message'] = 'Failed to create pairing session.'; }
                } catch (MongoDB\Driver\Exception\BulkWriteException $e) { $response['message'] = 'Pairing token generation error. Try again.'; error_log("Pairing token insert error: " . $e->getMessage()); }
                break;

            case 'confirmMobilePairing': // Mobile client calls after QR scan
                if (!isset($_SESSION['user_id'], $data['pairing_token'])) { $response['message'] = 'Mobile auth/token missing.'; break; }
                $mobile_staff_user_id = new MongoDB\BSON\ObjectId($_SESSION['user_id']);
                $pairing_token = $data['pairing_token'];
                $sessionDoc = $db->pairing_sessions->findOne(['pairing_token' => $pairing_token, 'status' => 'awaiting_mobile_scan']);
                if (!$sessionDoc) { $response['message'] = 'Invalid or used pairing token.'; break; }
                // $sessionDoc->token_expires_at is handled by TTL index now
                if (!isset($sessionDoc->staff_user_id) || $sessionDoc->staff_user_id != $mobile_staff_user_id) {
                     $response['message'] = 'User mismatch. Login with same account on both devices.';
                     error_log("Pairing user mismatch: Desktop by " . (string)($sessionDoc->staff_user_id ?? 'N/A') . ", mobile by " . (string)$mobile_staff_user_id);
                     break;
                }
                $updateResult = $db->pairing_sessions->updateOne(
                    ['_id' => $sessionDoc->_id],
                    ['$set' => ['status' => 'mobile_paired_active', 'mobile_device_identifier' => session_id(), 'pairing_token' => null /* Token consumed */]]
                );
                if ($updateResult->getModifiedCount() > 0) {
                    $response = ['success' => true, 'message' => 'Mobile paired.'];
                    $notificationSystem->saveNotification("Scanner paired by {$_SESSION['username']}", 'info', (string)$mobile_staff_user_id, 5000, "Scanner Paired");
                } else { $response['message'] = 'Failed to update pairing status.'; }
                break;

            case 'submitScannedProduct': // Mobile scanner calls this
                if (!isset($_SESSION['user_id'], $data['scanned_product_id'])) { $response['message'] = 'Mobile auth/product ID missing.'; break; }
                $staff_user_id_obj = new MongoDB\BSON\ObjectId($_SESSION['user_id']);
                $scanned_product_id = trim($data['scanned_product_id']);
                $quantity = isset($data['quantity']) ? (int)$data['quantity'] : 1;
                $activePairingSession = $db->pairing_sessions->findOne(['staff_user_id' => $staff_user_id_obj, 'status' => 'mobile_paired_active']);
                if (!$activePairingSession) { $response['message'] = 'No active pairing. Re-pair.'; break; }
                // $activePairingSession->session_expires_at is handled by TTL index
                $product = null; $mongoProdId = null;
                try { $mongoProdId = new MongoDB\BSON\ObjectId($scanned_product_id); } catch (Exception $e) { /* Not ObjectId */ }
                if ($mongoProdId) $product = $db->products->findOne(['_id' => $mongoProdId]);
                // else { /* Query by other barcode field if supported */ }
                if (!$product) { $response['message'] = "Product '{$scanned_product_id}' not found."; break; }
                $newItem = ['product_id' => (string)$product->_id, 'product_name' => $product->name, 'price' => (float)$product->price, 'quantity' => $quantity, 'scanned_at' => new MongoDB\BSON\UTCDateTime(), 'processed' => false];
                $updateResult = $db->pairing_sessions->updateOne(['_id' => $activePairingSession->_id], ['$push' => ['scanned_items' => $newItem]]);
                if ($updateResult->getModifiedCount() > 0) {
                    $response = ['success' => true, 'message' => 'Scan recorded.', 'product_name' => $product->name];
                } else { $response['message'] = 'Failed to record scan.'; }
                break;

            case 'endMobilePairing': // Desktop calls this
                 if (!isset($_SESSION['user_id'])) { $response['message'] = 'User not authenticated.'; break; }
                $staff_user_id_obj = new MongoDB\BSON\ObjectId($_SESSION['user_id']);
                $db->pairing_sessions->updateMany(['staff_user_id' => $staff_user_id_obj, 'status' => 'mobile_paired_active'], ['$set' => ['status' => 'completed']]);
                $response = ['success' => true, 'message' => 'Pairing ended.'];
                break;

            default: $response['message'] = 'Unknown POST action.'; http_response_code(400); break;
        }
    } catch (Exception $e) {
        error_log("Server.php POST Action '{$action}' Error: " . $e->getMessage());
        $response = ['success' => false, 'message' => 'Server error processing request: ' . $e->getMessage()];
        http_response_code(500);
    }

} elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
    header('Content-Type: application/json');
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

            case 'getBills': // Using bill_new as per generateBill logic
                $bills = $db->bill_new->find([], ['sort' => ['created_at' => -1]])->toArray();
                echo json_encode($bills); exit;

            case 'getSales': // Using bill_new
                $sales = $db->bill_new->find([], ['sort' => ['created_at' => -1]])->toArray();
                echo json_encode($sales); exit;

            case 'getScannedItems': // Desktop POS polls this
                if (!isset($_SESSION['user_id'])) { $response['message'] = 'User not authenticated.'; break; }
                $staff_user_id_obj = new MongoDB\BSON\ObjectId($_SESSION['user_id']);
                $activePairingSession = $db->pairing_sessions->findOne(['staff_user_id' => $staff_user_id_obj, 'status' => 'mobile_paired_active']);
                if (!$activePairingSession) { $response = ['success' => true, 'items' => [], 'message' => 'No active mobile pairing.']; break; }
                // $activePairingSession->session_expires_at handled by TTL
                $unprocessedItems = []; $itemUpdateQueries = [];
                if (isset($activePairingSession->scanned_items) && (is_array($activePairingSession->scanned_items) || $activePairingSession->scanned_items instanceof Traversable)) {
                    foreach ($activePairingSession->scanned_items as $item) {
                        if (isset($item->processed) && $item->processed === false) {
                            $unprocessedItems[] = $item;
                            $itemUpdateQueries[] = new MongoDB\UpdateOne(['_id' => $activePairingSession->_id, "scanned_items.scanned_at" => $item->scanned_at], ['$set' => ["scanned_items.$.processed" => true]]);
                        }
                    }
                }
                if (!empty($itemUpdateQueries)) { $db->pairing_sessions->bulkWrite($itemUpdateQueries); }
                $response = ['success' => true, 'items' => $unprocessedItems];
                break;

            case 'checkMobilePairingStatus': // Mobile scanner page calls on load
                if (!isset($_SESSION['user_id'])) { $response = ['is_paired' => false, 'message' => 'Mobile user not authenticated.']; break; }
                $staff_user_id_obj = new MongoDB\BSON\ObjectId($_SESSION['user_id']);
                $activeSession = $db->pairing_sessions->findOne(['staff_user_id' => $staff_user_id_obj, 'status' => 'mobile_paired_active' /* session_expires_at handled by TTL */]);
                $response = ['success' => true, 'is_paired' => (bool)$activeSession, 'message' => ($activeSession ? 'Device paired.' : 'Device not paired.')];
                break;

            case 'checkDesktopPairingStatus': // Desktop POS calls on load
                if (!isset($_SESSION['user_id'])) { $response = ['is_paired' => false, 'message' => 'Desktop user not authenticated.']; break; }
                $staff_user_id_obj = new MongoDB\BSON\ObjectId($_SESSION['user_id']);
                $activeSession = $db->pairing_sessions->findOne(['staff_user_id' => $staff_user_id_obj, 'status' => 'mobile_paired_active' /* session_expires_at handled by TTL */]);
                $response = ['success' => true, 'is_paired' => (bool)$activeSession, 'staff_username' => $activeSession->staff_username ?? null, 'message' => ($activeSession ? 'Active pairing exists.' : 'No active pairing.')];
                break;

            default: $response['message'] = 'Unknown GET action.'; http_response_code(400); break;
        }
    } catch (Exception $e) {
        error_log("Server.php GET Action '{$action}' Error: " . $e->getMessage());
        $response = ['success' => false, 'message' => 'Server error: ' . $e->getMessage()];
        http_response_code(500);
    }
} else {
    // Not a POST or GET with action, or invalid combination
    http_response_code(400); // Bad Request
    $response['message'] = 'Invalid request method or parameters.';
}

if (!headers_sent()) {
    header('Content-Type: application/json');
}
echo json_encode($response);
exit;
?>