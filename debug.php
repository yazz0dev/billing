<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// For better readability if run in browser, otherwise remove if CLI only
if (php_sapi_name() !== 'cli') {
    echo "<pre>";
}

echo "MongoDB Connection Debug Script\n";
echo "===============================\n\n";

// Ensure vendor autoload is loaded
$autoloaderPath = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoloaderPath)) {
    require_once $autoloaderPath;
    echo "SUCCESS: Autoloader loaded successfully from: " . $autoloaderPath . "\n";
} else {
    echo "ERROR: Autoloader not found at " . $autoloaderPath . ".\n";
    echo "Please run 'composer install' in your project root.\n";
    if (php_sapi_name() !== 'cli') echo "</pre>";
    exit;
}

// Include configuration file
$configPath = __DIR__ . '/config.php';
if (file_exists($configPath)) {
    require_once $configPath;
    echo "SUCCESS: Config.php loaded successfully from: " . $configPath . "\n";
} else {
    echo "ERROR: config.php not found at " . $configPath . ".\n";
    echo "Please ensure it exists and defines MONGODB_URI.\n";
    if (php_sapi_name() !== 'cli') echo "</pre>";
    exit;
}

// Check if MONGODB_URI is defined
if (!defined('MONGODB_URI')) {
    echo "ERROR: MONGODB_URI is not defined in config.php.\n";
    echo "Please define it in " . $configPath . " (e.g., define('MONGODB_URI', 'mongodb://user:pass@host:port/db');)\n";
    if (php_sapi_name() !== 'cli') echo "</pre>";
    exit;
}

echo "INFO: MONGODB_URI found: " . MONGODB_URI . "\n\n";

// Import MongoDB classes
use MongoDB\Client;
use MongoDB\Driver\ServerApi;
use MongoDB\Driver\Exception\Exception as MongoDBDriverException;
use MongoDB\Driver\Exception\ConnectionTimeoutException;
use MongoDB\Driver\Exception\AuthenticationException;
use MongoDB\Driver\Exception\ServerSelectionTimeoutException;

$mongoClient = null;

try {
    echo "INFO: Attempting to connect to MongoDB...\n\n";

    echo "IMPORTANT: If connecting to MongoDB Atlas, ensure your server's current IP address is whitelisted in the Atlas UI (Network Access settings).\n";
    echo "           Failure to do so is a common cause of connection timeouts and handshake errors.\n\n";

    $uri = MONGODB_URI;
    $uriOptions = []; // URI options, e.g., ['replicaSet' => 'myReplicaSet']
    $driverOptions = [ // Driver options
        'serverSelectionTimeoutMS' => 5000, // How long to try selecting a server (milliseconds)
        'connectTimeoutMS' => 10000,       // How long to wait for a connection to be established (milliseconds)
    ];

    // For Atlas SRV URIs, enable retryWrites and ServerApi (mimicking server.php logic)
    if (strpos($uri, 'mongodb+srv://') === 0 || strpos($uri, '.mongodb.net') !== false) {
        echo "INFO: Atlas SRV URI detected. Applying specific options (retryWrites=true, ServerApi=V1).\n";
        $uriOptions['retryWrites'] = true;
        if (class_exists('MongoDB\\Driver\\ServerApi')) {
            $driverOptions['serverApi'] = new ServerApi(ServerApi::V1);
            echo "INFO: MongoDB ServerApi V1 configured.\n";
        } else {
            echo "WARNING: MongoDB\Driver\ServerApi class not found. ServerApi will not be configured. This might be an issue for Atlas connections if your driver version is older and doesn't support it implicitly.\n";
        }
    } else {
        echo "INFO: Non-Atlas URI detected. Standard options will be used.\n";
    }

    echo "INFO: URI Options being used: " . json_encode($uriOptions) . "\n";
    echo "INFO: Driver Options being used: " . json_encode(array_keys($driverOptions)) . " (ServerApi object not shown fully in json_encode)\n\n";
    
    // Create a new client instance
    $mongoClient = new Client($uri, $uriOptions, $driverOptions);
    echo "INFO: MongoDB client instantiated.\n";

    // Test connection by pinging the admin database
    echo "INFO: Pinging 'admin' database to verify connection...\n";
    $commandResponse = $mongoClient->selectDatabase('admin')->command(['ping' => 1]);
    $pingResult = iterator_to_array($commandResponse); // Get the first document from the command response cursor

    if (isset($pingResult[0]['ok']) && $pingResult[0]['ok'] == 1) {
        echo "SUCCESS: MongoDB connection successful! Ping to 'admin' database was acknowledged.\n\n";
    } else {
        echo "WARNING: Ping command executed but response was not as expected: " . json_encode($pingResult) . "\n\n";
    }

    // Optional: List databases as a further test
    echo "INFO: Listing databases (requires appropriate permissions)...\n";
    $databases = $mongoClient->listDatabases();
    $dbCount = 0;
    foreach ($databases as $database) {
        printf("  - %s (Size on disk: %.2f MB)\n", $database->getName(), $database->getSizeOnDisk() / (1024 * 1024));
        $dbCount++;
    }
    if ($dbCount == 0) {
        echo "INFO: No databases listed. This could be due to permissions or no databases present.\n";
    }

} catch (AuthenticationException $e) {
    echo "ERROR: MongoDB Authentication Failed!\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "Common causes:\n";
    echo "  - Incorrect username or password in MONGODB_URI.\n";
    echo "  - User does not exist or has insufficient permissions for the 'authSource' database (usually 'admin' or the specific database).\n";
    echo "  - IP Access List in MongoDB Atlas might not include your server's IP address.\n";
    echo "Trace: \n" . $e->getTraceAsString() . "\n";
} catch (ConnectionTimeoutException $e) {
    echo "ERROR: MongoDB Connection Timed Out!\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "Common causes:\n";
    echo "  - MongoDB server is not reachable (down, network issue, incorrect host/port).\n";
    echo "  - Firewall blocking outgoing connections on port 27017 (or custom port).\n";
    echo "  - For Atlas, ensure your server's IP is whitelisted in Network Access settings.\n";
    echo "  - 'connectTimeoutMS' (" . $driverOptions['connectTimeoutMS'] . "ms) might be too short for your network latency.\n";
    echo "Trace: \n" . $e->getTraceAsString() . "\n";
} catch (ServerSelectionTimeoutException $e) {
    echo "ERROR: MongoDB Server Selection Timed Out!\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "Common causes:\n";
    echo "  - No suitable server found in the replica set or sharded cluster (e.g., all members down or unreachable).\n";
    echo "  - DNS resolution issues for SRV records (if using mongodb+srv:// URI).\n";
    echo "  - SSL/TLS handshake issues. Ensure your PHP has up-to-date CA certificates.\n";
    echo "     (Check `openssl.cafile` in php.ini or if your system's CA bundle is current).\n";
    echo "  - 'serverSelectionTimeoutMS' (" . $driverOptions['serverSelectionTimeoutMS'] . "ms) might be too short.\n";
    echo "Trace: \n" . $e->getTraceAsString() . "\n";
} catch (MongoDBDriverException $e) {
    echo "ERROR: MongoDB Driver Exception Occurred!\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "Type: " . get_class($e) . "\n";
    if ($e->hasErrorLabel('HandshakeError')) {
        echo "INFO: This error has the 'HandshakeError' label. This often relates to TLS/SSL issues.\n";
        echo "  - Check CA certificates (see ServerSelectionTimeoutException notes).\n";
        echo "  - Ensure your MongoDB server/Atlas cluster is configured for TLS if your client expects it (default for Atlas).\n";
    }
    echo "Trace: \n" . $e->getTraceAsString() . "\n";
} catch (Exception $e) {
    echo "ERROR: A Generic Exception Occurred!\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "Type: " . get_class($e) . "\n";
    echo "Trace: \n" . $e->getTraceAsString() . "\n";
} finally {
    echo "\nINFO: Debug script finished.\n";
    if (php_sapi_name() !== 'cli') {
        echo "</pre>";
    }
}
?>
