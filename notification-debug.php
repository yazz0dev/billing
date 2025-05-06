<?php
/**
 * Notification System Diagnostics
 * This file helps debug issues with the notification system
 */

// Disable error output for production
ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json');

$diagnostics = [
    'script_name' => basename(__FILE__),
    'timestamp' => date('c'),
    'php_version' => phpversion(),
    'server_api' => php_sapi_name(),
    'session_status' => session_status() === PHP_SESSION_ACTIVE ? 'Active' : (session_status() === PHP_SESSION_NONE ? 'None' : 'Disabled'),
    'autoload_status' => 'Not checked yet',
    'config_file' => 'Not checked yet',
    'mongodb_extension' => extension_loaded('mongodb') ? 'Loaded' : 'Not loaded',
    'mongodb_client_class' => 'Initial state: Not checked',
    'mongodb_connection' => 'Not tested yet',
    'request_headers' => [],
    'expected_collections' => ['user', 'products', 'bill', 'popup_notifications']
];

// Ensure vendor autoload is loaded
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
    $diagnostics['autoload_status'] = 'Loaded successfully.';

    // Determine MongoDB\Client class availability now that autoloader is processed
    if (extension_loaded('mongodb')) {
        if (class_exists('MongoDB\\Client')) {
            $diagnostics['mongodb_client_class'] = 'Available (extension loaded, class found by autoloader)';
        } else {
            $diagnostics['mongodb_client_class'] = 'Not available (MongoDB extension loaded, but MongoDB\\Client class NOT found by autoloader. Ensure "mongodb/mongodb" is in composer.json and installed.)';
        }
    } else {
        $diagnostics['mongodb_client_class'] = 'Not available (MongoDB extension NOT loaded)';
    }
} else {
    $diagnostics['autoload_status'] = 'Error: vendor/autoload.php not found. Key functionalities (like MongoDB client) will fail.';
    $diagnostics['mongodb_client_class'] = 'Not available (autoloader missing)';
    $diagnostics['mongodb_connection'] = 'Skipped (autoloader missing)';
}

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
        
    } catch (MongoDB\Driver\Exception\ConnectionTimeoutException $e) {
        $diagnostics['mongodb_connection'] = 'Error: Connection Timeout - ' . $e->getMessage();
    } catch (MongoDB\Driver\Exception\AuthenticationException $e) {
        $diagnostics['mongodb_connection'] = 'Error: Authentication Failed - ' . $e->getMessage();
    } catch (Exception $e) {
        $diagnostics['mongodb_connection'] = 'Error: ' . $e->getMessage();
    }
} else if ($diagnostics['mongodb_connection'] === 'Not tested yet') {
    if (!extension_loaded('mongodb')) {
        $diagnostics['mongodb_connection'] = 'Skipped (MongoDB extension not loaded)';
    } else {
        $diagnostics['mongodb_connection'] = 'Skipped (MongoDB\\Client class not found by autoloader)';
    }
}

echo json_encode($diagnostics, JSON_PRETTY_PRINT);
?>
