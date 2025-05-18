<?php // templates/error/403.php
// $pageTitle, $message (from router) available
?>
<div class="container text-center error-page-container">
    <h1 class="error-title">403</h1>
    <h2 class="error-subtitle">Access Denied</h2>
    <p class="error-message"><?php echo $e($message ?? 'You do not have permission to view this page.'); ?></p>
    <a href="/" class="btn btn-primary mt-3">Go to Homepage</a>
    <?php if (isset($session['user_id'])): ?>
        <p class="mt-2"><a href="/logout" class="text-sm">Logout</a></p>
    <?php else: ?>
        <p class="mt-2"><a href="/login" class="text-sm">Login</a></p>
    <?php endif; ?>
</div>
<!-- Use same style as 404.php or define new -->
<style>
    .error-page-container { padding: 4rem 1rem; }
    .error-title { font-size: 6rem; font-weight: bold; color: var(--warning); margin-bottom: 0.5rem; }
    .error-subtitle { font-size: 1.75rem; color: var(--text-heading); margin-bottom: 1rem; }
    .error-message { font-size: 1.1rem; color: var(--text-secondary); margin-bottom: 2rem; }
</style>
