<?php

require 'vendor/autoload.php'; // Ensure MongoDB library is loaded

$client = new MongoDB\Client("mongodb://localhost:27017");
$db = $client->billing;

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
        echo json_encode(['status' => 'success', 'message' => 'User added successfully']);
    }

    // Authenticate a user
    elseif ($action === 'authenticateUser') {
        $username = $_POST['username'];
        $password = $_POST['password'];

        $user = $db->users->findOne(['username' => $username]);
        if ($user && password_verify($password, $user->password)) {
            echo json_encode(['status' => 'success', 'role' => $user->role]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Invalid credentials']);
        }
    }
}
?>
