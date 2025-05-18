<?php // templates/error/500.php
// $pageTitle, $message (from router, potentially with debug info) available
?>
<div class="container text-center error-page-container">
    <h1 class="error-title">500</h1>
    <h2 class="error-subtitle">Server Error</h2>
    <p class="error-message">We are sorry, but something went wrong on our end. Please try again later.</p>
    <?php if ($appConfig['debug'] && !empty($message)): ?>
        <div class="debug-info" style="text-align: left; background: #f9f9f9; border: 1px solid #ddd; padding: 15px; margin-top: 20px; overflow-x: auto; font-family: monospace; font-size: 0.9em; color: #333;">
            <strong>Debug Information:</strong><br>
            <?php echo $message; // This comes pre-formatted with nl2br and htmlspecialchars from api/index.php ?>
        </div>
    <?php endif; ?>
    <a href="/" class="btn btn-primary mt-3">Go to Homepage</a>
</div>
<!-- Use same style as 404.php or define new -->
<style>
    .error-page-container { padding: 4rem 1rem; }
    .error-title { font-size: 6rem; font-weight: bold; color: var(--error); margin-bottom: 0.5rem; }
    .error-subtitle { font-size: 1.75rem; color: var(--text-heading); margin-bottom: 1rem; }
    .error-message { font-size: 1.1rem; color: var(--text-secondary); margin-bottom: 2rem; }
    .debug-info { max-height: 300px; }
</style>
