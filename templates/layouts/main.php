<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $e($pageTitle ?? $appConfig['name']); ?></title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap">
    <link rel="stylesheet" href="/css/global.css?v=<?php echo filemtime(PROJECT_ROOT . '/public/css/global.css'); ?>">
    <?php if(isset($additionalStyles)) echo $additionalStyles; ?>
</head>
<body class="layout-main <?php echo $e($bodyClass ?? ''); ?>">
    <div class="page-wrapper">
        <?php if(!isset($hideTopbar) || !$hideTopbar): ?>
            <?php require PROJECT_ROOT . '/templates/partials/topbar.php'; ?>
        <?php endif; ?>

        <main class="main-content-area">
            <?php echo $content; // This is where the specific page content will be injected ?>
        </main>
        
        <?php require PROJECT_ROOT . '/templates/partials/footer.php'; ?>
    </div>

    <script src="/js/popup-notification.js?v=<?php echo filemtime(PROJECT_ROOT . '/public/js/popup-notification.js'); ?>"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof PopupNotification === 'function') {
                window.popupNotification = new PopupNotification({
                    fetchUrl: '/api/notifications/fetch',
                    markSeenUrl: '/api/notifications/mark-seen',
                    fetchFromServer: true,
                    fetchInterval: 60000 // Increase interval to reduce server load
                });
            }
             // Global user session data for JS if needed by topbar or other scripts
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
            <script src="<?php echo $e($script); ?>?v=<?php echo filemtime(PROJECT_ROOT . '/public' . $script); ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>
