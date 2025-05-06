<?php
/**
 * Centralized Popup Notification System
 * Provides server-side functionality for managing and triggering notifications
 */

require_once 'vendor/autoload.php';

class NotificationSystem {
    private $db;
    
    /**
     * Initialize notification system with database connection
     */
    public function __construct() {
        $client = new MongoDB\Client("mongodb://localhost:27017");
        $this->db = $client->billing;
    }
    
    /**
     * Save notification to database for persistent notifications
     * 
     * @param string $message The notification message
     * @param string $type The notification type (info, success, warning, error)
     * @param string $target User ID or role the notification is for ('all' for everyone)
     * @param int $duration Display duration in milliseconds (0 for manual dismiss only)
     * @return string The created notification ID
     */
    public function saveNotification($message, $type = 'info', $target = 'all', $duration = 5000) {
        $notification = [
            'message' => $message,
            'type' => $type,
            'target' => $target,
            'duration' => $duration,
            'created_at' => new MongoDB\BSON\UTCDateTime(),
            'is_active' => true,
            'seen_by' => []
        ];
        
        $result = $this->db->popup_notifications->insertOne($notification);
        return (string)$result->getInsertedId();
    }
    
    /**
     * Get active popup notifications for a user
     * 
     * @param string $userId User ID
     * @param string $userRole User role
     * @return array Array of notifications
     */
    public function getActiveNotifications($userId, $userRole) {
        $query = [
            'is_active' => true,
            'seen_by' => ['$ne' => $userId],
            '$or' => [
                ['target' => 'all'],
                ['target' => $userId],
                ['target' => $userRole]
            ]
        ];
        
        $options = [
            'sort' => ['created_at' => -1],
            'limit' => 5 // Limit to 5 most recent notifications
        ];
        
        $notifications = $this->db->popup_notifications->find($query, $options)->toArray();
        return $notifications;
    }
    
    /**
     * Mark notification as seen by a user
     * 
     * @param string $notificationId Notification ID
     * @param string $userId User ID
     * @return bool Success status
     */
    public function markAsSeen($notificationId, $userId) {
        $result = $this->db->popup_notifications->updateOne(
            ['_id' => new MongoDB\BSON\ObjectId($notificationId)],
            ['$addToSet' => ['seen_by' => $userId]]
        );
        
        return $result->getModifiedCount() > 0;
    }
    
    /**
     * Deactivate a notification
     * 
     * @param string $notificationId Notification ID
     * @return bool Success status
     */
    public function deactivate($notificationId) {
        $result = $this->db->popup_notifications->updateOne(
            ['_id' => new MongoDB\BSON\ObjectId($notificationId)],
            ['$set' => ['is_active' => false]]
        );
        
        return $result->getModifiedCount() > 0;
    }
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['popup_action'])) {
    $notificationSystem = new NotificationSystem();
    $action = $_POST['popup_action'];
    $response = ['status' => 'error', 'message' => 'Invalid action'];
    
    // Make sure session is started for authentication
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Get user information from session if available
    $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'guest';
    $userRole = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : 'guest';
    
    switch ($action) {
        case 'get':
            $notifications = $notificationSystem->getActiveNotifications($userId, $userRole);
            $response = ['status' => 'success', 'data' => $notifications];
            break;
            
        case 'mark_seen':
            if (isset($_POST['notification_id'])) {
                $success = $notificationSystem->markAsSeen($_POST['notification_id'], $userId);
                $response = ['status' => ($success ? 'success' : 'error')];
            }
            break;
            
        case 'save':
            if (isset($_POST['message'])) {
                $type = isset($_POST['type']) ? $_POST['type'] : 'info';
                $target = isset($_POST['target']) ? $_POST['target'] : 'all';
                $duration = isset($_POST['duration']) ? intval($_POST['duration']) : 5000;
                
                $id = $notificationSystem->saveNotification($_POST['message'], $type, $target, $duration);
                $response = ['status' => 'success', 'id' => $id];
            }
            break;
    }
    
    echo json_encode($response);
    exit;
}

// Test endpoint to create a popup notification (for demonstration)
if (isset($_GET['create_popup']) && !empty($_GET['message'])) {
    $notificationSystem = new NotificationSystem();
    
    $type = isset($_GET['type']) ? $_GET['type'] : 'info';
    $target = isset($_GET['target']) ? $_GET['target'] : 'all';
    $duration = isset($_GET['duration']) ? intval($_GET['duration']) : 5000;
    
    $id = $notificationSystem->saveNotification($_GET['message'], $type, $target, $duration);
    echo json_encode(['status' => 'success', 'id' => $id, 'message' => 'Popup notification created']);
    exit;
}
?>
