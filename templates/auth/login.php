<?php // templates/auth/login.php
// $pageTitle, $error_message, $csrf_token_name, $csrf_token_value are available
?>
<div class="login-page-container">
    <div class="login-form-wrapper">
        <div class="login-header">
            <h1>Welcome Back!</h1>
            <p>Log in to access your billing dashboard.</p>
        </div>
        
        <?php if (!empty($error_message)): ?>
        <div class="login-error-message" style="display: block;">
            <?php echo $e($error_message); ?>
        </div>
        <?php endif; ?>
        
        <form action="/login" method="POST" class="login-form glass">
            <input type="hidden" name="<?php echo $e($csrf_token_name); ?>" value="<?php echo $e($csrf_token_value); ?>">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" placeholder="Enter username" required autocomplete="username">
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="Enter password" required autocomplete="current-password">
            </div>
            
            <button type="submit" class="btn w-full">Login</button>
        </form>
         <a href="/" class="login-back-link mt-3">Back to Home</a>
    </div>
</div>
<script>
// JS from original login page can be adapted here.
// Instead of fetch to /billing/server.php, the form now submits to /login (POST)
// Server-side validation and redirects will handle success/failure.
// Popup notifications can be triggered from the layout script based on session messages.
document.addEventListener('DOMContentLoaded', function() {
    // Example: if there was an error message passed from PHP that needs to be shown as a popup
    const errorMessageDiv = document.querySelector('.login-error-message');
    if (errorMessageDiv && errorMessageDiv.textContent.trim() !== '' && window.popupNotification) {
        // Don't show popup on login page, the error div is already visible
        // window.popupNotification.error(errorMessageDiv.textContent.trim(), "Login Error");
    }
});
</script>
