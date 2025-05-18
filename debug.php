<?php
// billing/debug.php

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// For better readability if run in browser, otherwise remove if CLI only
if (php_sapi_name() !== 'cli') {
    echo "<pre>";
}

echo "MongoDB Connection Debug Script (Using App Core)\n";
echo "=================================================\n\n";

// Define PROJECT_ROOT if not already defined (e.g., if not running through web server context)
if (!defined('PROJECT_ROOT')) {
    define('PROJECT_ROOT', __DIR__); // Assuming debug.php is in the project root
}
echo "INFO: PROJECT_ROOT set to: " . PROJECT_ROOT . "\n";

// Ensure vendor autoload is loaded
$autoloaderPath = PROJECT_ROOT . '/vendor/autoload.php';
if (file_exists($autoloaderPath)) {
    require_once $autoloaderPath;
    echo "SUCCESS: Autoloader loaded successfully from: " . $autoloaderPath . "\n";
} else {
    echo "ERROR: Autoloader not found at " . $autoloaderPath . ".\n";
    echo "Please run 'composer install' in your project root.\n";
    if (php_sapi_name() !== 'cli') echo "</pre>";
    exit;
}

// Load .env file (mimicking api/index.php)
if (file_exists(PROJECT_ROOT . '/.env')) {
    try {
        $dotenv = Dotenv\Dotenv::createImmutable(PROJECT_ROOT);
        $dotenv->safeLoad(); // Use safeLoad to not error if .env is missing
        echo "INFO: .env file loaded if present.\n";
    } catch (Exception $e) {
        echo "WARNING: Could not load .env file: " . $e->getMessage() . "\n";
    }
} else {
    echo "INFO: .env file not found at " . PROJECT_ROOT . "/.env. This is normal for some environments.\n";
}

// Load application configuration (mimicking api/index.php)
$appConfigPath = PROJECT_ROOT . '/config/app.php';
$dbConfigPath = PROJECT_ROOT . '/config/database.php';

if (!file_exists($appConfigPath) || !file_exists($dbConfigPath)) {
    echo "ERROR: Application or Database configuration file not found.\n";
    echo "Ensure config/app.php and config/database.php exist.\n";
    if (php_sapi_name() !== 'cli') echo "</pre>";
    exit;
}

$appConfig = require $appConfigPath;
$dbConfig = require $dbConfigPath;

echo "INFO: Application Name: " . ($appConfig['name'] ?? 'N/A') . "\n";
echo "INFO: Environment: " . ($appConfig['env'] ?? 'N/A') . "\n";
echo "INFO: Debug Mode: " . (isset($appConfig['debug']) ? ($appConfig['debug'] ? 'true' : 'false') : 'N/A') . "\n";

$actual_uri_used = $dbConfig['mongodb']['uri'] ?? 'URI not configured in database.php';
$database_name = $dbConfig['mongodb']['database_name'] ?? 'DB Name not configured';

echo "\nINFO: MongoDB URI from config/database.php:\n";
echo "  URI: " . $actual_uri_used . "\n";
echo "  Database Name: " . $database_name . "\n\n";

echo "IMPORTANT: If connecting to MongoDB Atlas, ensure your server's current IP address (or 0.0.0.0/0 for Vercel) is whitelisted in the Atlas UI (Network Access settings).\n";
echo "           Failure to do so is a common cause of connection timeouts and handshake errors.\n\n";

try {
    echo "INFO: Attempting to connect to MongoDB using App\Core\Database::connect()...\n";

    // Use the application's Database class to connect
    $mongoDb = App\Core\Database::connect();
    echo "SUCCESS: App\Core\Database::connect() successful.\n";
    echo "INFO: Connected to database: " . $mongoDb->getDatabaseName() . "\n";

    // Test connection by pinging the admin database via the client obtained from Database class
    $client = App\Core\Database::getClient();
    if ($client) {
        echo "INFO: Pinging 'admin' database to verify connection...\n";
        $commandResponse = $client->selectDatabase('admin')->command(['ping' => 1]);
        $pingResult = iterator_to_array($commandResponse);

        if (isset($pingResult[0]['ok']) && $pingResult[0]['ok'] == 1) {
            echo "SUCCESS: MongoDB connection confirmed! Ping to 'admin' database was acknowledged.\n\n";
        } else {
            echo "WARNING: Ping command executed but response was not as expected: " . json_encode($pingResult) . "\n\n";
        }

        // Optional: List collections in the connected database as a further test
        echo "INFO: Listing collections in database '" . $mongoDb->getDatabaseName() . "' (requires appropriate permissions)...\n";
        $collections = $mongoDb->listCollectionNames();
        $collectionCount = 0;
        foreach ($collections as $collection) {
            echo "  - " . $collection . "\n";
            $collectionCount++;
        }
        if ($collectionCount == 0) {
            echo "INFO: No collections listed. This could be due to permissions or no collections present in '" . $mongoDb->getDatabaseName() . "'.\n";
        }
    } else {
        echo "ERROR: Could not retrieve MongoDB client from App\Core\Database::getClient().\n";
    }

} catch (MongoDB\Driver\Exception\AuthenticationException $e) {
    echo "ERROR: MongoDB Authentication Failed via App\Core\Database!\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "Common causes:\n";
    echo "  - Incorrect username or password in the MongoDB URI (check .env or config/database.php).\n";
    echo "  - User does not exist or has insufficient permissions for the 'authSource' database.\n";
    echo "  - IP Access List in MongoDB Atlas might not include your server's IP address.\n";
    if ($appConfig['debug']) {
        echo "Trace: \n" . $e->getTraceAsString() . "\n";
    }
} catch (MongoDB\Driver\Exception\ConnectionTimeoutException $e) {
    echo "ERROR: MongoDB Connection Timed Out via App\Core\Database!\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "Common causes:\n";
    echo "  - MongoDB server is not reachable (down, network issue, incorrect host/port).\n";
    echo "  - Firewall blocking outgoing connections on port 27017 (or custom port).\n";
    echo "  - For Atlas, ensure your server's IP is whitelisted in Network Access settings.\n";
    echo "  - 'connectTimeoutMS' or 'serverSelectionTimeoutMS' in config/database.php might be too short.\n";
    if ($appConfig['debug']) {
        echo "Trace: \n" . $e->getTraceAsString() . "\n";
    }
} catch (MongoDB\Driver\Exception\ServerSelectionTimeoutException $e) {
    echo "ERROR: MongoDB Server Selection Timed Out via App\Core\Database!\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "Common causes:\n";
    echo "  - No suitable server found (e.g., all members down or unreachable).\n";
    echo "  - DNS resolution issues for SRV records (if using mongodb+srv:// URI).\n";
    echo "  - SSL/TLS handshake issues. Ensure your PHP has up-to-date CA certificates.\n";
    echo "     (Check `openssl.cafile` in php.ini or if your system's CA bundle is current).\n";
    echo "  - 'serverSelectionTimeoutMS' in config/database.php might be too short.\n";
    if ($appConfig['debug']) {
        echo "Trace: \n" . $e->getTraceAsString() . "\n";
    }
} catch (MongoDB\Driver\Exception\Exception $e) { // General MongoDB Driver Exception
    echo "ERROR: MongoDB Driver Exception Occurred via App\Core\Database!\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "Type: " . get_class($e) . "\n";
    if (method_exists($e, 'hasErrorLabel') && $e->hasErrorLabel('HandshakeError')) {
        echo "INFO: This error has the 'HandshakeError' label. This often relates to TLS/SSL issues.\n";
        echo "  - Check CA certificates (see ServerSelectionTimeoutException notes).\n";
        echo "  - Ensure your MongoDB server/Atlas cluster is configured for TLS if your client expects it (default for Atlas).\n";
    }
    if ($appConfig['debug']) {
        echo "Trace: \n" . $e->getTraceAsString() . "\n";
    }
} catch (Exception $e) { // Catch exceptions from Database::connect() or other generic exceptions
    echo "ERROR: An Exception Occurred!\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "Type: " . get_class($e) . "\n";
    if ($appConfig['debug'] || strpos($e->getMessage(), "Database connection failed:") === 0) { // Show trace if debug or it's our custom db fail message
        echo "Trace: \n" . $e->getTraceAsString() . "\n";
    } else {
        echo "Enable debug mode in config/app.php for more details.\n";
    }
} finally {
    echo "\nINFO: Debug script finished.\n";
    if (php_sapi_name() !== 'cli') {
        echo "</pre>";
    }
}
?>