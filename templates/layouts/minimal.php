<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $e($pageTitle ?? $appConfig['name']); ?></title>
    <link rel="stylesheet" href="/css/global.css?v=<?php echo filemtime(PROJECT_ROOT . '/public/css/global.css'); ?>">
    <!-- Add any other common head elements for minimal layout -->
</head>
<body class="<?php echo $e($bodyClass ?? ''); ?>">
    <div class="page-wrapper">
        <?php echo $content; // This is where the specific page content will be injected ?>
    </div>
    <script src="/js/popup-notification.js?v=<?php echo filemtime(PROJECT_ROOT . '/public/js/popup-notification.js'); ?>"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof PopupNotification === 'function') {
                window.popupNotification = new PopupNotification({
                    fetchUrl: '/api/notifications/fetch', // Corrected API endpoint
                    markSeenUrl: '/api/notifications/mark-seen' // Corrected API endpoint
                    // dbCheckUrl could be an API endpoint if needed, e.g., /api/system/db-check
                });
            }
            <?php if (isset($initialPageMessage) && is_array($initialPageMessage)): ?>
                <?php if ($initialPageMessage['type'] === 'error' && !empty($initialPageMessage['text'])): ?>
                    window.popupNotification.error("<?php echo $e(addslashes($initialPageMessage['text'])); ?>", "Error");
                <?php elseif ($initialPageMessage['type'] === 'success' && !empty($initialPageMessage['text'])): ?>
                     window.popupNotification.success("<?php echo $e(addslashes($initialPageMessage['text'])); ?>", "Success");
                <?php endif; ?>
            <?php endif; ?>
        });
    </script>
    <?php if(isset($pageScripts) && is_array($pageScripts)): ?>
        <?php foreach($pageScripts as $script): ?>
            <script src="<?php echo $e($script); ?>?v=<?php echo filemtime(PROJECT_ROOT . '/public' . $script); ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>
