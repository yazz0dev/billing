<?php // templates/home.php
// $pageTitle is available
// $appConfig is available
// $session (user session data) is available
?>
<div class="container homepage-hero-container">
    <h1 class="homepage-hero-title"><?php echo $e($appConfig['name'] ?? 'Supermarket Billing System'); ?></h1>
    <p class="homepage-hero-subtitle">
        Efficiently manage your inventory, process sales, and gain insights with our modern, user-friendly billing solution.
    </p>
    
    <div class="homepage-cards-container">
        <div class="homepage-login-card glass">
            <img src="https://cdn.jsdelivr.net/npm/heroicons@2.1.3/24/outline/user-circle.svg" alt="Admin Icon" style="width: 70px; height: 70px; margin-bottom: 1.5rem;">
            <h3>Administrator</h3>
            <p>Oversee operations, manage products and staff, view sales analytics, and configure system settings.</p>
            <a href="/login?role=admin" class="btn homepage-login-btn">Admin Login</a> <!-- Consider if ?role=admin is still how you want to pre-fill or hint role -->
        </div>
        
        <div class="homepage-login-card glass">
            <img src="https://cdn.jsdelivr.net/npm/heroicons@2.1.3/24/outline/shopping-cart.svg" alt="Staff Icon" style="width: 70px; height: 70px; margin-bottom: 1.5rem;">
            <h3>Staff Member</h3>
            <p>Quickly process customer transactions, generate accurate bills, and manage daily sales tasks with ease.</p>
            <a href="/login?role=staff" class="btn homepage-login-btn">Staff Login</a>
        </div>
    </div>
</div>
