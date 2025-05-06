<?php
/**
 * Centralized Popup Notification System
 */
//billing/notification.php`** (Minor improvements for robustness)

// Include configuration file
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

    public function __construct() {
        try {
            $uri = defined('MONGODB_URI') ? MONGODB_URI : 'mongodb://localhost:27017';
            $this->client = new MongoDB\Client($uri, [], ['serverSelectionTimeoutMS' => 3000]);
            $this->db = $this->client->selectDatabase('billing'); // Use selectDatabase
        } catch (Exception $e) {
            // Log error and potentially rethrow or handle gracefully
            error_log("NotificationSystem: Failed to connect to MongoDB: " . $e->getMessage());
            $this->db = null; // Ensure db is null if connection fails
        }
    }

    private function checkDbConnection() {
        if ($this->db === null) {
            // Attempt to reconnect or throw an error
            try {
                $uri = defined('MONGODB_URI') ? MONGODB_URI : 'mongodb://localhost:27017';
                $this->client = new MongoDB\Client($uri, [], ['serverSelectionTimeoutMS' => 1000]);
                $this->db = $this->client->selectDatabase('billing');
                if ($this->db === null) throw new Exception("Reconnection failed.");
            } catch (Exception $e) {
                 error_log("NotificationSystem: DB Reconnection failed: " . $e->getMessage());
                return false;
            }
        }
        return true;
    }
    
    public function saveNotification($message, $type = 'info', $target = 'all', $duration = 5000, $title = null) {
        if (!$this->checkDbConnection()) return false; // Or throw exception

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
                if (isset($_POST['message']) && (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin')) { // Example: only admin can save
                    $type = isset($_POST['type']) ? $_POST['type'] : 'info';
                    $target = isset($_POST['target']) ? $_POST['target'] : 'all';
                    $duration = isset($_POST['duration']) ? intval($_POST['duration']) : 5000;
                    $title = isset($_POST['title']) ? $_POST['title'] : null;
                    
                    $id = $notificationSystem->saveNotification($_POST['message'], $type, $target, $duration, $title);
                    if ($id) {
                        $response = ['status' => 'success', 'id' => $id, 'message' => 'Notification saved.'];
                    } else {
                         $response['message'] = 'Failed to save notification.';
                    }
                } else {
                    $response['message'] = 'Missing message or insufficient permissions.';
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
