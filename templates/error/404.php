<?php // templates/error/404.php
// $pageTitle, $message (from router), $appConfig, $e available
?>
<div class="container text-center error-page-container">
    <h1 class="error-title">404</h1>
    <h2 class="error-subtitle">Page Not Found</h2>
    <p class="error-message">Sorry, the page you are looking for could not be found or does not exist.</p>
    <?php if (isset($appConfig['debug']) && $appConfig['debug'] && !empty($message)): ?>
        <div class="debug-info">
            <strong>Debug Information:</strong><br>
            <?php echo $e($message); // Assuming $message contains debug details if debug is on ?>
        </div>
    <?php endif; ?>
    <a href="/" class="btn btn-primary mt-3">Go to Homepage</a>
</div>
<style>
    .error-page-container { padding: 4rem 1rem; }
    .error-title { font-size: 6rem; font-weight: bold; color: var(--primary); margin-bottom: 0.5rem; }
    .error-subtitle { font-size: 1.75rem; color: var(--text-heading); margin-bottom: 1rem; }
    .error-message { font-size: 1.1rem; color: var(--text-secondary); margin-bottom: 2rem; }
    .debug-info {
        text-align: left;
        background: #f9f9f9;
        border: 1px solid #ddd;
        padding: 15px;
        margin-top: 20px;
        overflow-x: auto;
        font-family: monospace;
        font-size: 0.9em;
        color: #333;
        max-height: 300px;
    }
</style>
