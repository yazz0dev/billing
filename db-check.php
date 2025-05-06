<?php
/**
 * MongoDB Connection Check Tool
 * This script verifies the MongoDB connection and provides diagnostic information
 */

// Display errors for troubleshooting
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Attempt to load composer autoloader
if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
    die("Error: Composer autoloader not found. Please run 'composer install' in the project directory.");
}

require_once __DIR__ . '/vendor/autoload.php';

// Check if MongoDB extension is installed
if (!extension_loaded('mongodb')) {
    die("Error: MongoDB PHP extension is not installed. Please install the MongoDB extension for PHP.");
}

// Function to check MongoDB connection
function checkMongoDBConnection($uri = "mongodb://localhost:27017") {
    try {
        $client = new MongoDB\Client($uri);
        
        // Get server info
        $serverInfo = $client->selectDatabase('admin')->command(['serverStatus' => 1])->toArray()[0];
        $version = $serverInfo->version;
        $uptime = $serverInfo->uptime;
        $connections = $serverInfo->connections->current;
        
        // Get database names
        $databaseNames = [];
        foreach ($client->listDatabases() as $db) {
            $databaseNames[] = $db->getName();
        }
        
        // Check billing database specifically
        $billingDb = $client->selectDatabase('billing');
        $collections = [];
        foreach ($billingDb->listCollections() as $collection) {
            $collections[] = $collection->getName();
        }
        
        return [
            'status' => 'connected',
            'version' => $version,
            'uptime' => $uptime,
            'connections' => $connections,
            'databases' => $databaseNames,
            'collections' => $collections
        ];
    } catch (Exception $e) {
        return [
            'status' => 'error',
            'message' => $e->getMessage()
        ];
    }
}

// Check connection
$connection = checkMongoDBConnection();

// Output result based on request type
if (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
    // API response
    header('Content-Type: application/json');
    echo json_encode($connection);
} else {
    // HTML response
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>MongoDB Connection Status</title>
        <link rel="stylesheet" href="/billing/global.css">
        <style>
            .status-box {
                padding: 1.5rem;
                border-radius: var(--border-radius-md);
                margin-bottom: 1rem;
            }
            .connected {
                background-color: rgba(16, 185, 129, 0.1);
                border: 1px solid var(--success);
            }
            .error {
                background-color: rgba(239, 68, 68, 0.1);
                border: 1px solid var(--error);
            }
            .collection-list {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
                gap: 1rem;
            }
            .collection-item {
                background: var(--glass-bg);
                padding: 1rem;
                border-radius: var(--border-radius-sm);
                border: 1px solid var(--glass-border);
            }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>MongoDB Connection Status</h1>
            
            <?php if ($connection['status'] === 'connected'): ?>
                <div class="status-box connected">
                    <h2>✅ Connection Successful</h2>
                    <p>MongoDB server is running properly.</p>
                </div>
                
                <div class="glass">
                    <h2>Server Information</h2>
                    <ul>
                        <li><strong>Version:</strong> <?= htmlspecialchars($connection['version']) ?></li>
                        <li><strong>Uptime:</strong> <?= htmlspecialchars($connection['uptime']) ?> seconds</li>
                        <li><strong>Current Connections:</strong> <?= htmlspecialchars($connection['connections']) ?></li>
                    </ul>
                </div>
                
                <div class="glass mt-4">
                    <h2>Databases</h2>
                    <ul>
                        <?php foreach ($connection['databases'] as $db): ?>
                            <li><?= htmlspecialchars($db) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                
                <div class="glass mt-4">
                    <h2>Billing Collections</h2>
                    <?php if (count($connection['collections']) > 0): ?>
                        <div class="collection-list">
                            <?php foreach ($connection['collections'] as $collection): ?>
                                <div class="collection-item">
                                    <?= htmlspecialchars($collection) ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p>No collections found in the billing database.</p>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="status-box error">
                    <h2>❌ Connection Failed</h2>
                    <p>Could not connect to MongoDB: <?= htmlspecialchars($connection['message']) ?></p>
                </div>
                
                <div class="glass mt-4">
                    <h2>Troubleshooting</h2>
                    <ul>
                        <li>Make sure MongoDB service is running</li>
                        <li>Check if MongoDB is running on the default port (27017)</li>
                        <li>Verify that the MongoDB PHP extension is properly installed</li>
                        <li>Check firewall settings if MongoDB is running on a different server</li>
                        <li>Confirm authentication details if MongoDB requires authentication</li>
                    </ul>
                </div>
            <?php endif; ?>
            
            <div class="text-center mt-4">
                <a href="/billing/" class="btn">Back to Home</a>
            </div>
        </div>
    </body>
    </html>
    <?php
}
?>
