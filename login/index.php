<?php
// Start session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Redirect already logged in users
if (isset($_SESSION['user_role'])) {
    $redirectTo = '/billing/';
    if ($_SESSION['user_role'] === 'admin') {
        $redirectTo = '/billing/admin/';
    } elseif ($_SESSION['user_role'] === 'staff') {
        $redirectTo = '/billing/staff/';
    }
    header('Location: ' . $redirectTo);
    exit;
}

$pageTitle = "Login - Billing System";
$bodyClass = "login-page";
$hideTopbar = true; // Don't show the topbar on login page

// Include header
require_once '../includes/header.php';
?>

<div class="login-page-container">
    <div class="login-form-wrapper">
        <div class="login-header">
            <h1>Welcome Back!</h1>
            <p>Log in to access your billing dashboard.</p>
        </div>
        
        <div class="login-error-message" id="errorMessage">
            <?php if(isset($_GET['error']) && $_GET['error'] == 'unauthorized'): ?>
                You must be logged in to access that page.
            <?php endif; ?>
        </div>
        
        <form id="loginForm" class="login-form glass">
            <div class="form-group">
                <label for="username">Username</label>
                <div class="icon-input">
                    <svg class="input-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                    <input type="text" id="username" name="username" placeholder="Enter your username" required autocomplete="username">
                </div>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <div class="icon-input">
                    <svg class="input-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                    <input type="password" id="password" name="password" placeholder="Enter your password" required autocomplete="current-password">
                </div>
            </div>
            
            <button type="submit" class="btn w-full">Login</button>
        </form>
        
        <a href="/billing/" class="login-back-link">
            <svg class="back-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            Back to Home
        </a>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const loginForm = document.getElementById('loginForm');
    const errorMessageDiv = document.getElementById('errorMessage');

    // Check for a redirect message passed from PHP via window.initialPageMessage
    if (window.initialPageMessage && errorMessageDiv) {
        if (window.initialPageMessage.type === 'error') { // Or any other type check you might implement
            errorMessageDiv.textContent = window.initialPageMessage.text;
            errorMessageDiv.style.display = 'block';
        }
        // It's a one-time message, so we can clear it from the window object
        // to prevent it from showing again if the user navigates within the SPA portion (if any)
        delete window.initialPageMessage;
    }


    if (loginForm) {
        loginForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            errorMessageDiv.style.display = 'none'; // Hide previous errors
            
            const formData = new FormData(e.target);
            formData.append('action', 'authenticateUser');
            
            try {
                const response = await fetch('/billing/server.php', {
                    method: 'POST',
                    body: formData
                });
                
                // Check content type before trying to parse JSON
                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    // If we got HTML instead of JSON, show a more helpful error
                    const errorText = await response.text();
                    console.error('Server returned non-JSON response:', errorText.substring(0, 200) + '...');
                    throw new Error('Server returned non-JSON response. The server might be experiencing issues.');
                }
                
                if (!response.ok) {
                    throw new Error(`Server error: ${response.status}`);
                }
                
                const result = await response.json();
                
                // Continue with login logic
                if (result.status === 'success') {
                    // Redirect based on role
                    let targetUrl = '/billing/'; // Default redirect
                    if (result.role === 'admin') {
                        targetUrl = '/billing/admin/';
                    } else if (result.role === 'staff') {
                        targetUrl = '/billing/staff/';
                    }
                    window.location.href = targetUrl;
                } else {
                    errorMessageDiv.textContent = result.message || "Invalid username or password. Please try again.";
                    errorMessageDiv.style.display = 'block';
                    if (window.popupNotification) { // Check if popup system is available
                        window.popupNotification.error(result.message || "Login failed.", "Authentication Error");
                    }
                }
            } catch (error) {
                console.error('Login error:', error);
                errorMessageDiv.textContent = "An error occurred during login. Please try again.";
                errorMessageDiv.style.display = 'block';
                if (window.popupNotification) {
                     window.popupNotification.error("A network or server error occurred. Please try again later.", "Login Error");
                }
            }
        });
    }
});
</script>

<?php
// Include footer
require_once '../includes/footer.php';
?>
