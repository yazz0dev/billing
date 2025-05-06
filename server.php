<?php
//billing/server.php`** (Minor improvements, notification titles)

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

// Include notification system (it also loads its own autoloader if needed)
require_once 'notification.php'; // NotificationSystem class

// Initialize MongoDB client and NotificationSystem
$mongoClient = null;
$db = null;
$notificationSystem = null;

try {
    $mongoClient = new MongoDB\Client("mongodb://localhost:27017", [], ['serverSelectionTimeoutMS' => 3000]);
    $db = $mongoClient->selectDatabase('billing');
    $notificationSystem = new NotificationSystem(); // It has its own DB connection logic
} catch (Exception $e) {
    // If DB connection fails at this top level, critical error for most operations
    if (php_sapi_name() !== 'cli' && (!isset($_SERVER['HTTP_ACCEPT']) || strpos(strtolower($_SERVER['HTTP_ACCEPT']), 'application/json') === false)) {
        // Handle non-JSON context if necessary, though API endpoints are primary here
    } else {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Database connection failed: ' . $e->getMessage()]);
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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
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
                    $db->product->insertOne($product);
                    $notificationSystem->saveNotification(
                        "New product '{$product['name']}' (₹{$product['price']}) added.",
                        'success', 'all', 7000, "Product Added"
                    );
                    $response = ['status' => 'success', 'message' => 'Product added successfully.'];
                }
                break;

            case 'billProduct':
                if (isset($_POST['productId'], $_POST['quantity']) && MongoDB\BSON\ObjectId::isValid($_POST['productId'])) {
                    $productId = new MongoDB\BSON\ObjectId($_POST['productId']);
                    $quantity = (int)$_POST['quantity'];
                    if ($quantity <= 0) {
                        $response['message'] = "Invalid quantity.";
                        break;
                    }

                    $product = $db->product->findOne(['_id' => $productId]);
                    if ($product && $product->stock >= $quantity) {
                        $db->product->updateOne(
                            ['_id' => $productId],
                            ['$inc' => ['stock' => -$quantity]]
                        );
                        $db->bill->insertOne([
                            'productId' => $productId,
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
                    $password = $_POST['password'];

                    $user = $db->users->findOne(['username' => $username]);
                    if ($user && isset($user->password) && password_verify($password, $user->password)) {
                        $_SESSION['user_id'] = (string) $user->_id;
                        $_SESSION['username'] = $user->username;
                        $_SESSION['user_role'] = $user->role;
                        $_SESSION['user_email'] = isset($user->email) ? $user->email : $user->username . '@example.com'; // Example email

                        $notificationSystem->saveNotification(
                            "Welcome back, {$user->username}! You logged in successfully.",
                            'success', (string)$user->_id, 5000, "Login Successful"
                        );
                        $response = ['status' => 'success', 'role' => $user->role, 'username' => $user->username];
                    } else {
                        $response['message'] = 'Invalid username or password.';
                    }
                }
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
        echo json_encode(['status' => 'error', 'message' => 'Server error: Database not available for GET request.']);
        exit;
    }

    try {
        switch ($action) {
            case 'getProducts':
                $products = $db->product->find([], ['sort' => ['name' => 1]])->toArray();
                $response = $products; // Direct array response
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
}

// If no action matched and not a direct script call handled by router:
if (basename(__FILE__) == basename($_SERVER["SCRIPT_FILENAME"])) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['status' => 'error', 'message' => 'No action specified or invalid request method.']);
    exit;
}
?>
