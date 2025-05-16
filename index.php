<?php
// Start session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$pageTitle = "Supermarket Billing System";
$bodyClass = "homepage";
$hideTopbar = true; // Homepage doesn't need the topbar

// Include header
require_once 'includes/header.php';
?>

<div class="container homepage-hero-container">
    <h1 class="homepage-hero-title">Supermarket Billing System</h1>
    <p class="homepage-hero-subtitle">
        Efficiently manage your inventory, process sales, and gain insights with our modern, user-friendly billing solution.
    </p>
    
    <div class="homepage-cards-container">
        <div class="homepage-login-card glass">
            <img src="https://cdn.jsdelivr.net/npm/heroicons@2.1.3/24/outline/user-circle.svg" alt="Admin Icon">
            <h3>Administrator</h3>
            <p>Oversee operations, manage products and staff, view sales analytics, and configure system settings.</p>
            <a href="/billing/login/?role=admin" class="btn homepage-login-btn">Admin Login</a>
        </div>
        
        <div class="homepage-login-card glass">
            <img src="https://cdn.jsdelivr.net/npm/heroicons@2.1.3/24/outline/shopping-cart.svg" alt="Staff Icon">
            <h3>Staff Member</h3>
            <p>Quickly process customer transactions, generate accurate bills, and manage daily sales tasks with ease.</p>
            <a href="/billing/login/?role=staff" class="btn homepage-login-btn">Staff Login</a>
        </div>
    </div>
</div>

<?php
// Include footer
require_once 'includes/footer.php';
?>
