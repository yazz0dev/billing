<?php
/**
 * Notification System Diagnostics
 * This file helps debug issues with the notification system
 */

// Disable error output for production
ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json');

// Create a diagnostics response
$diagnostics = [
    'status' => 'success',
    'timestamp' => date('Y-m-d H:i:s'),
    'php_version' => phpversion(),
    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
    'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'Unknown',
    'request_headers' => [],
    'session_status' => session_status() == PHP_SESSION_ACTIVE ? 'Active' : 'Inactive',
    'mongodb_extension' => extension_loaded('mongodb') ? 'Loaded' : 'Not loaded',
    'mongodb_client_class' => class_exists('MongoDB\\Client') ? 'Available' : 'Not available',
    'expected_collections' => ['user', 'products', 'bill', 'popup_notifications']
];

// Get request headers
foreach (getallheaders() as $name => $value) {
    $diagnostics['request_headers'][$name] = $value;
}

// Test MongoDB connection if extension is available
if (extension_loaded('mongodb') && class_exists('MongoDB\\Client')) {
    try {
        // Include config file
        if (file_exists(__DIR__ . '/config.php')) {
            require_once __DIR__ . '/config.php';
            $uri = defined('MONGODB_URI') ? MONGODB_URI : 'mongodb://localhost:27017';
            $diagnostics['config_file'] = 'Found';
        } else {
            $uri = 'mongodb://localhost:27017';
            $diagnostics['config_file'] = 'Not found';
        }
        
        // Try to connect
        $client = new MongoDB\Client($uri, [], ['serverSelectionTimeoutMS' => 2000]);
        $diagnostics['mongodb_connection'] = 'Connected';
        
        // List databases
        $databases = [];
        $listDbs = $client->listDatabases();
        foreach ($listDbs as $db) {
            $databases[] = $db->getName();
        }
        $diagnostics['available_databases'] = $databases;
        
        // Check billing database and collections
        if (in_array('billing', $databases)) {
            $diagnostics['billing_database'] = 'Found';
            $collections = [];
            $db = $client->selectDatabase('billing');
            foreach ($db->listCollections() as $collection) {
                $collections[] = $collection->getName();
            }
            $diagnostics['billing_collections'] = $collections;
            
            // Check if all required collections exist
            $missingCollections = array_diff($diagnostics['expected_collections'], $collections);
            $diagnostics['missing_collections'] = $missingCollections;
        } else {
            $diagnostics['billing_database'] = 'Not found';
        }
        
    } catch (Exception $e) {
        $diagnostics['mongodb_connection'] = 'Failed';
        $diagnostics['error_message'] = $e->getMessage();
    }
}

echo json_encode($diagnostics, JSON_PRETTY_PRINT);
?>
