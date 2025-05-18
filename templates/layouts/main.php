<html lang="en">
<head>
    <script>
    // Define BASE_PATH for JavaScript
    // Use the PHP-defined BASE_PATH, escaped for JS
    window.BASE_PATH = "<?php echo $e(BASE_PATH); ?>";
</script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $e($pageTitle ?? $appConfig['name']); ?></title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap">
    <?php
        // Calculate filemtime relative to the template file location (templates/layouts)
        // The public directory is one level up and into 'public' (__DIR__ . '/../public')
        $cssPath = __DIR__ . '/../public/css/global.css';
        $jsPopupPath = __DIR__ . '/../public/js/popup-notification.js';
        $globalCssVersion = file_exists($cssPath) ? filemtime($cssPath) : '1';
        $popupJsVersion = file_exists($jsPopupPath) ? filemtime($jsPopupPath) : '1';

        // Use BASE_PATH for asset URLs if the app is in a subdirectory
        // e.g., /my_app/css/global.css
        $baseUrl = BASE_PATH; // BASE_PATH is defined in index.php and available globally
    ?>
    <link rel="stylesheet" href="<?php echo $baseUrl; ?>/css/global.css?v=<?php echo $globalCssVersion; ?>">
    <?php if(isset($additionalStyles)) echo $additionalStyles; ?>
</head>
<body class="layout-main <?php echo $e($bodyClass ?? ''); ?>">
    <div class="page-wrapper">
        <?php if(!isset($hideTopbar) || !$hideTopbar): ?>
            <?php // Include partial relative to the template directory
            require __DIR__ . '/../partials/topbar.php';
            ?>
        <?php endif; ?>

        <main class="main-content-area">
            <?php echo $content; // This is where the specific page content will be injected ?>
        </main>

        <?php require __DIR__ . '/../partials/footer.php'; ?>
    </div>

    <script src="<?php echo $baseUrl; ?>/js/popup-notification.js?v=<?php echo $popupJsVersion; ?>"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // BASE_PATH is a global PHP constant, echo it into JS
            const BASE_PATH = '<?php echo BASE_PATH; ?>';

            if (typeof PopupNotification === 'function') {
                window.popupNotification = new PopupNotification({
                    // Use BASE_PATH for API endpoints
                    fetchUrl: BASE_PATH + '/api/notifications/fetch',
                    markSeenUrl: BASE_PATH + '/api/notifications/mark-seen',
                    fetchFromServer: true,
                    fetchInterval: 60000 // Increase interval to reduce server load
                });
            }
             // Global user session data for JS if needed by topbar or other scripts
             // Make sure $session is passed to the layout data from the controller if needed.
             // Controller::render adds $_SESSION to view data.
            window.userSessionData = {
                name: "<?php echo $e($session['username'] ?? 'User'); ?>",
                role: "<?php echo $e($session['user_role'] ?? ''); ?>",
                email: "<?php echo $e($session['user_email'] ?? ''); ?>",
                id: "<?php echo $e($session['user_id'] ?? ''); ?>"
            };
        });
    </script>
    <?php if(isset($pageScripts) && is_array($pageScripts)): ?>
        <?php foreach($pageScripts as $script): ?>
             <?php
                // Calculate filemtime for page-specific scripts
                // Assumes scripts in $pageScripts start with /js/ or similar relative to public/
                $scriptPath = __DIR__ . '/../public' . $script; // e.g., ../public/js/admin-dashboard.js
                $scriptVersion = file_exists($scriptPath) ? filemtime($scriptPath) : '1';
            ?>
            <script src="<?php echo $baseUrl . $e($script); ?>?v=<?php echo $scriptVersion; ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
    <script src="<?php echo $baseUrl; ?>/js/topbar.js" defer></script> <!-- topbar.js is common to main layout -->
</body>
</html>