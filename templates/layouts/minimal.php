<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $e($pageTitle ?? $appConfig['name']); ?></title>
     <?php
        // Calculate filemtime relative to the template file location (templates/layouts)
        // The public directory is one level up and into 'public' (__DIR__ . '/../public')
        $cssPath = __DIR__ . '/../public/css/global.css';
        $jsPopupPath = __DIR__ . '/../public/js/popup-notification.js';
        $globalCssVersion = file_exists($cssPath) ? filemtime($cssPath) : '1';
        $popupJsVersion = file_exists($jsPopupPath) ? filemtime($jsPopupPath) : '1';

         // Use BASE_PATH for asset URLs if the app is in a subdirectory
        $baseUrl = BASE_PATH; // BASE_PATH is defined in index.php and available globally
    ?>
    <link rel="stylesheet" href="<?php echo $baseUrl; ?>/css/global.css?v=<?php echo $globalCssVersion; ?>">
    <!-- Add any other common head elements for minimal layout -->
</head>
<body class="layout-minimal <?php echo $e($bodyClass ?? ''); ?>">
    <div class="page-wrapper">
        <?php echo $content; // This is where the specific page content will be injected ?>
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
                    fetchFromServer: false // Disable auto-fetching on login/error pages etc.
                });
            }
            <?php
            // Initial page message handling
            $initialPageMessage = $_SESSION['initial_page_message'] ?? null;
            unset($_SESSION['initial_page_message']); // Consume the message after adding it to JS
            if ($initialPageMessage && is_array($initialPageMessage)):
            ?>
                <?php if ($initialPageMessage['type'] === 'error' && !empty($initialPageMessage['text'])): ?>
                    if (window.popupNotification) {
                        window.popupNotification.error("<?php echo $e(addslashes($initialPageMessage['text'])); ?>", "Error");
                    } else {
                        console.error("Error message (PopupNotification not ready):", "<?php echo $e(addslashes($initialPageMessage['text'])); ?>");
                    }
                <?php elseif ($initialPageMessage['type'] === 'success' && !empty($initialPageMessage['text'])): ?>
                     if (window.popupNotification) {
                        window.popupNotification.success("<?php echo $e(addslashes($initialPageMessage['text'])); ?>", "Success");
                    } else {
                         console.log("Success message (PopupNotification not ready):", "<?php echo $e(addslashes($initialPageMessage['text'])); ?>");
                    }
                <?php endif; ?>
            <?php endif; ?>
        });
    </script>
    <?php if(isset($pageScripts) && is_array($pageScripts)): ?>
        <?php foreach($pageScripts as $script): ?>
             <?php
                // Calculate filemtime for page-specific scripts
                 // Assumes scripts in $pageScripts start with /js/ or similar relative to public/
                $scriptPath = __DIR__ . '/../public' . $script; // e.g., ../public/js/mobile-scanner.js
                $scriptVersion = file_exists($scriptPath) ? filemtime($scriptPath) : '1';
            ?>
            <script src="<?php echo $baseUrl . $e($script); ?>?v=<?php echo $scriptVersion; ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>