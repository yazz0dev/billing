//billing/db-check.php
<?php
/**
 * MongoDB Connection Check Tool
 * This script verifies the MongoDB connection and provides diagnostic information
 * It will be wrapped by layout_header.php and layout_footer.php by the router.
 */

// Display errors for troubleshooting if not in production
// ini_set('display_errors', 1); // Router handles this
// error_reporting(E_ALL);

$mongoConnectionInfo = null; // Initialize

// Attempt to load composer autoloader
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';

    if (extension_loaded('mongodb') && class_exists('MongoDB\Client')) {
        try {
            $client = new MongoDB\Client("mongodb://localhost:27017", [], ['serverSelectionTimeoutMS' => 3000]); // 3s timeout
            
            $serverInfoCmd = new MongoDB\Driver\Command(['serverStatus' => 1]);
            $serverInfoCursor = $client->getManager()->executeCommand('admin', $serverInfoCmd);
            $serverInfo = current($serverInfoCursor->toArray());

            $version = isset($serverInfo->version) ? $serverInfo->version : 'N/A';
            $uptime = isset($serverInfo->uptime) ? round($serverInfo->uptime) : 'N/A';
            $connections = isset($serverInfo->connections->current) ? $serverInfo->connections->current : 'N/A';
            
            $databaseNames = [];
            foreach ($client->listDatabases() as $dbInfo) {
                $databaseNames[] = $dbInfo->getName();
            }
            
            $billingDbCollections = [];
            if (in_array('billing', $databaseNames)) {
                $billingDb = $client->selectDatabase('billing');
                foreach ($billingDb->listCollections() as $collectionInfo) {
                    $billingDbCollections[] = $collectionInfo->getName();
                }
            }
            
            $mongoConnectionInfo = [
                'status' => 'connected',
                'version' => $version,
                'uptimeSeconds' => $uptime,
                'currentConnections' => $connections,
                'availableDatabases' => $databaseNames,
                'billingDatabaseCollections' => $billingDbCollections
            ];
        } catch (Exception $e) {
            $mongoConnectionInfo = [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    } else {
        $mongoConnectionInfo = [
            'status' => 'error',
            'message' => 'MongoDB PHP extension is not loaded or MongoDB\Client class not found.'
        ];
    }
} else {
     $mongoConnectionInfo = [
        'status' => 'error',
        'message' => "Composer autoloader not found (vendor/autoload.php). Please run 'composer install'."
    ];
}

// Output result as JSON if requested by API
if (isset($_SERVER['HTTP_ACCEPT']) && strpos(strtolower($_SERVER['HTTP_ACCEPT']), 'application/json') !== false && php_sapi_name() !== 'cli') {
    header('Content-Type: application/json');
    echo json_encode($mongoConnectionInfo);
    exit; // Important: exit after JSON output if called as API
}

?>
<h1 class="page-title">MongoDB Connection Status</h1>

<?php if ($mongoConnectionInfo && $mongoConnectionInfo['status'] === 'connected'): ?>
    <div class="content-section glass" style="border-left: 5px solid var(--success);">
        <h2 class="section-title" style="color: var(--success);">✅ Connection Successful</h2>
        <p>Your application is successfully connected to the MongoDB server.</p>
    </div>
    
    <div class="content-section glass mt-4">
        <h2 class="section-title">Server Information</h2>
        <ul>
            <li><strong>MongoDB Version:</strong> <?php echo htmlspecialchars($mongoConnectionInfo['version']); ?></li>
            <li><strong>Server Uptime:</strong> <?php echo htmlspecialchars($mongoConnectionInfo['uptimeSeconds']); ?> seconds</li>
            <li><strong>Current Connections:</strong> <?php echo htmlspecialchars($mongoConnectionInfo['currentConnections']); ?></li>
        </ul>
    </div>
    
    <div class="content-section glass mt-4">
        <h2 class="section-title">Available Databases</h2>
        <?php if (!empty($mongoConnectionInfo['availableDatabases'])): ?>
            <ul>
                <?php foreach ($mongoConnectionInfo['availableDatabases'] as $dbName): ?>
                    <li><?php echo htmlspecialchars($dbName); ?> <?php echo ($dbName === 'billing' ? '<strong>(App DB)</strong>' : ''); ?></li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p>No databases found (this is unusual, check MongoDB server status).</p>
        <?php endif; ?>
    </div>
    
    <div class="content-section glass mt-4">
        <h2 class="section-title">Collections in 'billing' Database</h2>
        <?php if (!empty($mongoConnectionInfo['billingDatabaseCollections'])): ?>
            <div class="card-list">
                <?php foreach ($mongoConnectionInfo['billingDatabaseCollections'] as $collectionName): ?>
                    <div class="card-base p-3"> <!-- Simpler card for list item -->
                       <span style="font-weight:500;"><?php echo htmlspecialchars($collectionName); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
             <p class="mt-3 text-sm text-light">Expected collections: users, product, bill, popup_notifications.</p>
        <?php elseif (in_array('billing', $mongoConnectionInfo['availableDatabases'])): ?>
            <p>The 'billing' database exists but has no collections. This might be an initial setup.</p>
        <?php else: ?>
            <p>The 'billing' database does not seem to exist or is not accessible.</p>
        <?php endif; ?>
    </div>

<?php elseif ($mongoConnectionInfo): // Status is 'error' ?>
    <div class="content-section glass" style="border-left: 5px solid var(--error);">
        <h2 class="section-title" style="color: var(--error);">❌ Connection Failed</h2>
        <p><strong>Error Message:</strong> <?php echo htmlspecialchars($mongoConnectionInfo['message']); ?></p>
    </div>
    
    <div class="content-section glass mt-4">
        <h2 class="section-title">Troubleshooting Tips</h2>
        <ul>
            <li>Ensure the MongoDB service (mongod) is running on your server.</li>
            <li>Verify the connection URI (default: <code>mongodb://localhost:27017</code>) is correct.</li>
            <li>Check if the MongoDB PHP driver/extension is installed and enabled in your <code>php.ini</code>.</li>
            <li>If running MongoDB in Docker or a VM, ensure port 27017 is correctly mapped and accessible.</li>
            <li>Check firewall rules on the server running MongoDB and the web server.</li>
            <li>If authentication is enabled on MongoDB, ensure your application provides valid credentials (not configured in this basic setup).</li>
            <li>Review MongoDB server logs for more detailed error information.</li>
            <li>Make sure <code>composer install</code> has been run in the <code>/billing/</code> directory to install dependencies.</li>
        </ul>
    </div>
<?php else: ?>
     <div class="content-section glass" style="border-left: 5px solid var(--warning);">
        <h2 class="section-title" style="color: var(--warning);">⚠️ Could Not Determine Status</h2>
        <p>There was an issue running the connection check script.</p>
    </div>
<?php endif; ?>

<div class="text-center mt-4">
    <a href="/billing/index" class="btn">Back to Home</a>
</div>