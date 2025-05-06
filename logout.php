// billing/logout.php
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include notification system for logout message
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
    if (file_exists(__DIR__ . '/notification.php')) {
        require_once __DIR__ . '/notification.php';
        $notificationSystem = new NotificationSystem();
        if (isset($_SESSION['username'])) {
            $notificationSystem->saveNotification(
                "User '{$_SESSION['username']}' logged out.",
                'info',
                isset($_SESSION['user_id']) ? (string)$_SESSION['user_id'] : 'all', // Target self or all if ID not set
                3000,
                "Logout Successful"
            );
        }
    }
}


// Unset all session variables
$_SESSION = array();

// If it's desired to kill the session, also delete the session cookie.
// Note: This will destroy the session, and not just the session data!
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Finally, destroy the session.
session_destroy();

// Redirect to homepage or login page
// The $base_path should ideally be defined in a central config
$base_path = '/billing'; 
header('Location: ' . $base_path . '/index');
exit;
?>
