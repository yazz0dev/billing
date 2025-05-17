<?php
/**
 * Centralized Popup Notification System
 */
//billing/notification.php

// config.php will define MONGODB_URI_CONFIG for local fallback
if (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
}

// Ensure vendor autoload is loaded if not already by router
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
} else {
    // Handle missing vendor/autoload.php for API calls
    if (php_sapi_name() !== 'cli' && (!isset($_SERVER['HTTP_ACCEPT']) || strpos(strtolower($_SERVER['HTTP_ACCEPT']), 'application/json') === false)) {
         // If not an API call, this might be included in an HTML page context somehow, though router should prevent it.
    } else {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Server configuration error: Autoloader not found.']);
        exit;
    }
}


class NotificationSystem {
    private $db;
    private $client; // Store client for potential reuse or graceful shutdown

    /**
     * Constructor allows passing an existing MongoDB client
     * This helps reduce connection overhead and share connections
     * 
     * @param MongoDB\Client|null $existingClient An existing MongoDB client instance
     */
    public function __construct($existingClient = null) {
        try {
            if ($existingClient instanceof MongoDB\Client) {
                $this->client = $existingClient;
                error_log("NotificationSystem: Using provided MongoDB client");
            } else {
                // Prioritize environment variable (from Vercel), then config.php, then default.
                $uri = getenv('MONGODB_URI') ?: (defined('MONGODB_URI_CONFIG') ? MONGODB_URI_CONFIG : 'mongodb://localhost:27017');
                
                if ($uri === 'mongodb://localhost:27017' && !getenv('MONGODB_URI') && !defined('MONGODB_URI_CONFIG')) {
                    error_log("WARNING (NotificationSystem): MongoDB URI is falling back to localhost default. Ensure MONGODB_URI env var is set on Vercel or MONGODB_URI_CONFIG is defined in config.php for local.");
                }

                $this->client = new MongoDB\Client($uri, [], [
                    'serverSelectionTimeoutMS' => 5000,
                    'connectTimeoutMS' => 10000
                ]);
                error_log("NotificationSystem: Created new MongoDB client for URI: " . substr($uri, 0, strpos($uri, '@') ?: strlen($uri)));
            }
            
            $this->db = $this->client->selectDatabase('billing');
            
            // Verify connection is working by running a simple command
            $this->db->command(['ping' => 1]);
            
        } catch (Exception $e) {
            // Log error and potentially rethrow or handle gracefully
            error_log("NotificationSystem: Failed to connect to MongoDB: " . $e->getMessage());
            $this->db = null; // Ensure db is null if connection fails
        }
    }

    public function checkDbConnection() {
        if ($this->db === null) {
            // Attempt to reconnect or throw an error
            try {
                 // Prioritize environment variable (from Vercel), then config.php, then default.
                $uri = getenv('MONGODB_URI') ?: (defined('MONGODB_URI_CONFIG') ? MONGODB_URI_CONFIG : 'mongodb://localhost:27017');
                
                $uriOptions = [];
                $driverOptions = [
                    'serverSelectionTimeoutMS' => 5000, // Shorter timeout for a quick check/reconnect
                    'connectTimeoutMS' => 8000
                ];

                if (strpos($uri, 'mongodb+srv://') === 0 || strpos($uri, '.mongodb.net') !== false) {
                    $uriOptions['retryWrites'] = true;
                    if (class_exists('MongoDB\\Driver\\ServerApi')) {
                         // Ensure ServerApi class is available if vendor/autoload.php was loaded by the main script
                        $driverOptions['serverApi'] = new MongoDB\Driver\ServerApi(MongoDB\Driver\ServerApi::V1);
                    }
                }

                $this->client = new MongoDB\Client($uri, $uriOptions, $driverOptions);
                $this->db = $this->client->selectDatabase('billing');
                
                // Test the connection explicitly
                $this->db->command(['ping' => 1]);
                
                if ($this->db === null) throw new Exception("Reconnection failed.");
                error_log("NotificationSystem: DB Reconnection successful.");
                return true;
            } catch (Exception $e) {
                 error_log("NotificationSystem: DB Reconnection failed: " . $e->getMessage());
                return false;
            }
        }
        
        // Test if the connection is still valid
        try {
            $this->db->command(['ping' => 1]);
            return true;
        } catch (Exception $e) {
            error_log("NotificationSystem: DB connection check failed: " . $e->getMessage());
            $this->db = null; // Mark as disconnected
            return false;
        }
    }
    
    public function saveNotification($message, $type = 'info', $target = 'all', $duration = 5000, $title = null) {
        if (!$this->checkDbConnection()) return false; // Or throw exception

        // Only store notifications explicitly targeted to 'admin' or 'staff' roles.
        // Other notifications are considered transient ("display only") and won't be persisted.
        if ($target !== 'admin' && $target !== 'staff') {
            // error_log("NotificationSystem: Notification not stored due to target policy. Target: " . (is_array($target) ? json_encode($target) : $target));
            return false; // Indicate not stored, won't be picked up by poller.
        }

        $notification = [
            'message' => (string) $message,
            'type' => (string) $type,
            'target' => $target, // Can be string (all, role) or array of user IDs
            'duration' => (int) $duration,
            'created_at' => new MongoDB\BSON\UTCDateTime(),
            'is_active' => true,
            'seen_by' => [] // Array of user IDs who have seen it
        ];
        if ($title) {
            $notification['title'] = (string) $title;
        }
        
        try {
            $result = $this->db->popup_notifications->insertOne($notification);
            return (string)$result->getInsertedId();
        } catch (Exception $e) {
            error_log("NotificationSystem: Failed to save notification: " . $e->getMessage());
            return false;
        }
    }
    
    public function getActiveNotifications($userId, $userRole) {
        if (!$this->checkDbConnection()) return [];

        // Ensure userId and userRole are strings or handle accordingly
        $userIdStr = is_string($userId) ? $userId : 'guest'; // Default to guest if not a string
        $userRoleStr = is_string($userRole) ? $userRole : 'guest';

        $query = [
            'is_active' => true,
            'seen_by' => ['$ne' => $userIdStr], // Exclude if current user has seen it
            '$or' => [
                ['target' => 'all'], // For everyone
                ['target' => $userIdStr], // Specifically for this user ID
                ['target' => $userRoleStr], // For this user's role
                // Potentially add logic for target being an array of user IDs if needed:
                // ['target' => ['$type' => 'array', '$elemMatch' => ['$eq' => $userIdStr]]]
            ]
        ];
        
        $options = [
            'sort' => ['created_at' => -1],
            'limit' => 10 // Increased limit slightly
        ];
        
        try {
            $notificationsCursor = $this->db->popup_notifications->find($query, $options);
            return $notificationsCursor->toArray();
        } catch (Exception $e) {
            error_log("NotificationSystem: Failed to get active notifications: " . $e->getMessage());
            return [];
        }
    }
    
    public function markAsSeen($notificationId, $userId) {
        if (!$this->checkDbConnection() || empty($notificationId) || empty($userId)) return false;

        try {
            $result = $this->db->popup_notifications->updateOne(
                ['_id' => new MongoDB\BSON\ObjectId($notificationId)],
                ['$addToSet' => ['seen_by' => (string)$userId]] // Ensure userId is string
            );
            return $result->getModifiedCount() > 0;
        } catch (MongoDB\Exception\InvalidArgumentException $e) {
            error_log("NotificationSystem: Invalid BSON ObjectId for markAsSeen: " . $notificationId . " Error: " . $e->getMessage());
            return false;
        } catch (Exception $e) {
            error_log("NotificationSystem: Failed to mark notification as seen: " . $e->getMessage());
            return false;
        }
    }

    public function deactivate($notificationId) {
        if (!$this->checkDbConnection() || empty($notificationId)) return false;
        try {
            $result = $this->db->popup_notifications->updateOne(
                ['_id' => new MongoDB\BSON\ObjectId($notificationId)],
                ['$set' => ['is_active' => false]]
            );
            return $result->getModifiedCount() > 0;
        } catch (MongoDB\Exception\InvalidArgumentException $e) {
            error_log("NotificationSystem: Invalid BSON ObjectId for deactivate: " . $notificationId . " Error: " . $e->getMessage());
            return false;
        } catch (Exception $e) {
            error_log("NotificationSystem: Failed to deactivate notification: " . $e->getMessage());
            return false;
        }
    }
}

// Handle AJAX requests if this file is called directly
// Router.php should handle this, but this is a fallback if direct access occurs.
if (basename(__FILE__) == basename($_SERVER["SCRIPT_FILENAME"])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['popup_action'])) {
        header('Content-Type: application/json');
        $notificationSystem = new NotificationSystem();
        $action = $_POST['popup_action'];
        $response = ['status' => 'error', 'message' => 'Invalid action or DB connection issue.'];

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $userId = isset($_SESSION['user_id']) ? (string)$_SESSION['user_id'] : 'guest_user_' . session_id();
        $userRole = isset($_SESSION['user_role']) ? (string)$_SESSION['user_role'] : 'guest';
        
        switch ($action) {
            case 'get':
                $notifications = $notificationSystem->getActiveNotifications($userId, $userRole);
                $response = ['status' => 'success', 'data' => $notifications];
                break;
                
            case 'mark_seen':
                if (isset($_POST['notification_id']) && MongoDB\BSON\ObjectId::isValid($_POST['notification_id'])) {
                    $success = $notificationSystem->markAsSeen($_POST['notification_id'], $userId);
                    $response = ['status' => $success ? 'success' : 'error', 'message' => $success ? 'Marked as seen.' : 'Failed to mark as seen.'];
                } else {
                    $response['message'] = 'Missing or invalid notification_id.';
                }
                break;
                
            case 'save': // This endpoint is more for server-side initiated notifications
                // Example: only admin can save via this specific AJAX action.
                // Other roles might save notifications programmatically from other parts of the backend.
                if (isset($_POST['message']) && (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin')) {
                    $type = isset($_POST['type']) ? $_POST['type'] : 'info';
                    $target = isset($_POST['target']) ? $_POST['target'] : 'all';
                    $duration = isset($_POST['duration']) ? intval($_POST['duration']) : 5000;
                    $title = isset($_POST['title']) ? $_POST['title'] : null;

                    $id = $notificationSystem->saveNotification($_POST['message'], $type, $target, $duration, $title);
                    if ($id) {
                        $response = ['status' => 'success', 'id' => $id, 'message' => 'Notification saved.'];
                    } else {
                        // saveNotification returns false if not stored (e.g., target not admin/staff) or on DB error.
                        if ($target !== 'admin' && $target !== 'staff') {
                            $response['message'] = 'Notification not stored: target must be \'admin\' or \'staff\' for persistence.';
                        } else {
                            $response['message'] = 'Failed to save notification. Check server logs for database errors.';
                        }
                    }
                } else {
                    $response['message'] = 'Missing message or insufficient permissions to save notification via this endpoint.';
                }
                break;
        }
        echo json_encode($response);
        exit;
    }

    // Test endpoint (remove or protect in production)
    if (isset($_GET['create_popup_test']) && !empty($_GET['message'])) {
        header('Content-Type: application/json');
        $notificationSystem = new NotificationSystem();
        $type = isset($_GET['type']) ? $_GET['type'] : 'info';
        $target = isset($_GET['target']) ? $_GET['target'] : 'all'; // e.g., 'admin', 'staff', or a user_id
        $duration = isset($_GET['duration']) ? intval($_GET['duration']) : 5000;
        $title = isset($_GET['title']) ? $_GET['title'] : null;
        
        $id = $notificationSystem->saveNotification($_GET['message'], $type, $target, $duration, $title);
        if ($id) {
            echo json_encode(['status' => 'success', 'id' => $id, 'message' => 'Test popup notification created']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to create test notification. Check server logs.']);
        }
        exit;
    }
}
?>