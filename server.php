<?php

require 'vendor/autoload.php'; // Ensure MongoDB library is loaded

$client = new MongoDB\Client("mongodb://localhost:27017");
$db = $client->billing;

// Include notification system
require_once 'notification.php';
$notificationSystem = new NotificationSystem();

// Handle requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    // Add a product
    if ($action === 'addProduct') {
        $product = [
            'name' => $_POST['name'],
            'price' => (float)$_POST['price'],
            'stock' => (int)$_POST['stock']
        ];
        $db->product->insertOne($product);
        
        // Create notification for product addition
        $notificationSystem->saveNotification(
            "New product added: {$_POST['name']} (₹{$_POST['price']})",
            'success',
            'all',
            7000
        );
        
        echo json_encode(['status' => 'success', 'message' => 'Product added successfully']);
    }

    // Bill a product
    elseif ($action === 'billProduct') {
        $productId = new MongoDB\BSON\ObjectId($_POST['productId']);
        $quantity = (int)$_POST['quantity'];

        $product = $db->product->findOne(['_id' => $productId]);
        if ($product && $product->stock >= $quantity) {
            $db->product->updateOne(
                ['_id' => $productId],
                ['$inc' => ['stock' => -$quantity]]
            );
            $db->bill->insertOne([
                'productId' => $productId,
                'quantity' => $quantity,
                'total' => $product->price * $quantity,
                'date' => new MongoDB\BSON\UTCDateTime()
            ]);
            
            // Check for low stock and create warning notification
            if ($product->stock - $quantity <= 10) {
                $notificationSystem->saveNotification(
                    "Low stock alert: {$product->name} has only " . ($product->stock - $quantity) . " units left",
                    'warning',
                    'admin',
                    0 // 0 means requires manual dismissal
                );
            }
            
            echo json_encode(['status' => 'success', 'message' => 'Product billed successfully']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Insufficient stock']);
        }
    }

    // Get sales data
    elseif ($action === 'getSales') {
        $sales = $db->bill->find()->toArray();
        echo json_encode($sales);
    }

    // Add a user
    elseif ($action === 'addUser') {
        $user = [
            'username' => $_POST['username'],
            'password' => password_hash($_POST['password'], PASSWORD_BCRYPT),
            'role' => $_POST['role']
        ];
        $db->users->insertOne($user);
        
        // Create notification about new user
        $notificationSystem->saveNotification(
            "New {$_POST['role']} user added: {$_POST['username']}",
            'info',
            'admin'
        );
        
        echo json_encode(['status' => 'success', 'message' => 'User added successfully']);
    }

    // Authenticate a user
    elseif ($action === 'authenticateUser') {
        $username = $_POST['username'];
        $password = $_POST['password'];

        $user = $db->users->findOne(['username' => $username]);
        // Only check password if it exists in the document
        if ($user && isset($user->password) && password_verify($password, $user->password)) {
            // Start session and store user info
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $_SESSION['user_id'] = (string) $user->_id;
            $_SESSION['username'] = $user->username;
            $_SESSION['user_role'] = $user->role;
            
            // Create welcome notification
            $notificationSystem->saveNotification(
                "Welcome back, {$user->username}! You have successfully logged in.",
                'success',
                (string) $user->_id,
                5000
            );
            
            echo json_encode(['status' => 'success', 'role' => $user->role]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Invalid credentials']);
        }
    }
}

// Handle GET requests for products and bills
elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
    $action = $_GET['action'];

    // Get all products
    if ($action === 'getProducts') {
        $products = $db->product->find()->toArray();
        echo json_encode($products);
    }

    // Get all bills
    elseif ($action === 'getBills') {
        $bills = $db->bill->find()->toArray();
        echo json_encode($bills);
    }
}
?>
