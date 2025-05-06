<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Default user session data
$userSessionData = [
    'name' => 'Guest',
    'role' => 'guest',
    'email' => 'guest@example.com'
];

if (isset($_SESSION['username']) && isset($_SESSION['user_role'])) {
    $userSessionData['name'] = $_SESSION['username'];
    $userSessionData['role'] = $_SESSION['user_role'];
    $userSessionData['email'] = isset($_SESSION['user_email']) ? $_SESSION['user_email'] : strtolower($_SESSION['username']) . '@example.com';
}

$currentPageTitle = isset($pageTitle) ? htmlspecialchars($pageTitle) . ' - BillSys' : 'BillSys - Supermarket Billing';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $currentPageTitle; ?></title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap">
    <link rel="stylesheet" href="/billing/global.css">
    <?php if (isset($pageStyles) && is_array($pageStyles)): ?>
        <?php foreach ($pageStyles as $style): ?>
            <link rel="stylesheet" href="<?php echo htmlspecialchars($style); ?>">
        <?php endforeach; ?>
    <?php endif; ?>
</head>
<body data-role="<?php echo htmlspecialchars($userSessionData['role']); ?>">
    <script>
        // Pass PHP session data to JavaScript for the topbar and other scripts
        window.userSessionData = <?php echo json_encode($userSessionData); ?>;

        <?php
        // Check for a one-time login message from the session
        if (isset($_SESSION['login_redirect_message'])) {
            // Make this data available to a global JS variable
            // The variable will only be defined if the message exists
            echo "window.initialPageMessage = { type: 'error', text: '" . addslashes($_SESSION['login_redirect_message']) . "' };\n";
            unset($_SESSION['login_redirect_message']); // Clear the message after setting it for JS
        }
        ?>
    </script>

    <div class="page-wrapper">
        <div id="topbarContainer">
            <?php
            ob_start();
            include __DIR__ . '/ui/topbar.html';
            echo ob_get_clean();
            ?>
        </div>

        <main class="main-content-area">
            <!-- Page-specific content will be included here by the router -->