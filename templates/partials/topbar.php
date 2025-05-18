<?php // templates/partials/topbar.php
// $session variable is available here from the View class
// $appConfig is also available
?>
<div class="topbar">
    <div class="topbar-container">
        <div class="topbar-logo">
            <a href="/"> <!-- Link to app root -->
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="logo-icon">
                    <path d="M3 3h18v18H3z"></path><path d="M3 9h18"></path><path d="M9 21V9"></path>
                </svg>
                <span><?php echo $e(explode(' ', $appConfig['name'])[0] ?? 'BillSys'); ?></span>
            </a>
        </div>

        <nav class="topbar-nav">
            <div class="topbar-nav-item">
                <a href="/" data-page="home"> <!-- App root -->
                    <svg class="nav-icon"><!-- ... --></svg><span>Home</span>
                </a>
            </div>

            <?php if (isset($session['user_role']) && $session['user_role'] === 'admin'): ?>
            <div class="topbar-nav-item admin-only">
                <a href="/admin/dashboard" data-page="admin-dashboard">
                    <svg class="nav-icon"><!-- ... --></svg><span>Dashboard</span>
                </a>
            </div>
            <div class="topbar-nav-item admin-only">
                <a href="/admin/products" data-page="products">
                    <svg class="nav-icon"><!-- ... --></svg><span>Products</span>
                </a>
            </div>
            <?php endif; ?>

            <?php if (isset($session['user_role']) && in_array($session['user_role'], ['staff', 'admin'])): ?>
            <div class="topbar-nav-item staff-only"> <!-- CSS will hide if not staff, JS adjusts based on exact role -->
                <a href="/staff/pos" data-page="billing">
                    <svg class="nav-icon"><!-- ... --></svg><span>Billing</span>
                </a>
            </div>
            <div class="topbar-nav-item staff-only">
                <a href="/staff/bills" data-page="bill-history">
                    <svg class="nav-icon"><!-- ... --></svg><span>Bill History</span>
                </a>
            </div>
            <?php endif; ?>
        </nav>

        <div class="topbar-actions">
            <button class="theme-toggle" id="themeToggle" title="Toggle dark/light mode">
                <svg class="icon-sun"><!-- ... --></svg><svg class="icon-moon"><!-- ... --></svg>
            </button>
            
            <div class="topbar-notifications">
                <button class="notification-btn" id="notificationButton" title="Notifications">
                    <svg class="icon-bell"><!-- ... --></svg>
                    <span class="notification-badge" id="notificationBadge" style="display: none;">0</span>
                </button>
                <div class="notification-panel" id="notificationPanel">
                    <!-- ... Notification panel structure ... -->
                </div>
            </div>

            <div class="topbar-user-profile">
                <button class="user-profile-btn" id="userProfileButton">
                    <div class="user-avatar"><svg><!-- ... --></svg></div>
                    <span class="user-name" id="userName"><?php echo $e($session['username'] ?? 'User'); ?></span>
                    <svg class="icon-chevron"><!-- ... --></svg>
                </button>
                <div class="user-dropdown" id="userDropdown">
                    <div class="user-dropdown-header">
                        <span class="user-role" id="userRole"><?php echo $e(ucfirst($session['user_role'] ?? 'Role')); ?></span>
                        <span class="user-email" id="userEmail"><?php echo $e($session['user_email'] ?? ''); ?></span>
                    </div>
                    <!-- ... Dropdown items ... -->
                    <a href="/logout" class="dropdown-item text-danger">
                        <svg class="dropdown-icon"><!-- ... --></svg> Logout
                    </a>
                </div>
            </div>
            <button class="mobile-menu-toggle" id="mobileMenuToggle" aria-label="Toggle menu"><svg><!-- ... --></svg></button>
        </div>
    </div>
    <div class="mobile-menu" id="mobileMenu"></div>
</div>

<script>
// NOTE: The JS for topbar functionality from your original topbar.php should be adapted.
// Ensure fetchTopBarNotifications uses '/api/notifications/fetch'
// Ensure user role visibility logic uses window.userSessionData.role
// Ensure active page highlighting works with the new clean URLs.
document.addEventListener('DOMContentLoaded', function() {
    // ... (existing theme toggle, dropdown, mobile menu JS)

    // --- Notification Panel Logic ---
    const notificationButton = document.getElementById('notificationButton');
    const notificationPanel = document.getElementById('notificationPanel');
    const notificationListEl = document.getElementById('notificationList');
    const notificationBadge = document.getElementById('notificationBadge');
    // ... (close button, etc.)

    function getNotificationIconForType(type) { /* ... same as your existing one ... */ }
    function formatTimeAgo(timestamp) { /* ... same as your existing one ... */ }

    function fetchTopBarNotifications() {
        if (!notificationListEl || !window.userSessionData || !window.userSessionData.id) return; // Need user context
        
        // Use the global popupNotification instance's circuit breaker if available
        const circuitBreaker = window.popupNotification?.options?.circuitBreaker || {
            canRequest: () => true, recordFailure: () => {} // Dummy if popupNotification not fully init
        };

        if (!circuitBreaker.canRequest()) {
            notificationListEl.innerHTML = '<div class="notification-error">Notifications temporarily unavailable.</div>';
            if (notificationBadge) notificationBadge.style.display = 'none';
            return;
        }
        notificationListEl.innerHTML = '<div class="loading-spinner">Loading...</div>';

        fetch('/api/notifications/fetch', { // New API endpoint
            method: 'POST', // Assuming your API is POST for this
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            body: JSON.stringify({ popup_action: 'get' }) // If your API expects this body
        })
        .then(response => {
            if (!response.ok) throw new Error(`Server error: ${response.status}`);
            return response.json();
        })
        .then(result => {
            if (result && result.status === 'success' && Array.isArray(result.data)) {
                const userId = window.userSessionData.id;
                const unreadCount = result.data.filter(n => !(n.seen_by && n.seen_by.includes(userId))).length;
                
                if (notificationBadge) {
                    notificationBadge.textContent = unreadCount;
                    notificationBadge.style.display = unreadCount > 0 ? 'flex' : 'none';
                }
                if (notificationListEl) {
                    if (result.data.length > 0) {
                        notificationListEl.innerHTML = result.data.map(n => `
                            <div class="notification-item ${n.type || 'info'} ${ (n.seen_by && n.seen_by.includes(userId)) ? 'seen' : 'unseen'}">
                                <div class="notification-icon">${getNotificationIconForType(n.type || 'info')}</div>
                                <div class="notification-content">
                                    <div class="notification-message">${n.message}</div>
                                    <div class="notification-time">${formatTimeAgo(n.created_at?.$date || n.created_at)}</div>
                                </div>
                            </div>`).join('');
                    } else {
                        notificationListEl.innerHTML = '<div class="empty-notifications">No new notifications</div>';
                    }
                }
            } else { throw new Error(result.message || 'Invalid data format'); }
        })
        .catch(err => {
            console.error('Error fetching topbar notifications:', err);
            circuitBreaker.recordFailure();
            if (notificationListEl) notificationListEl.innerHTML = `<div class="notification-error">Could not load. <button class="retry-btn" onclick="fetchTopBarNotifications()">Retry</button></div>`;
            if (notificationBadge) notificationBadge.style.display = 'none';
        });
    }

    if (notificationButton && notificationPanel) {
        notificationButton.addEventListener('click', function(e) {
            e.stopPropagation();
            notificationPanel.classList.toggle('show');
            if (notificationPanel.classList.contains('show')) {
                fetchTopBarNotifications();
            }
        });
        // ... (close logic)
    }

    // --- Role-based Navigation Visibility ---
    const userRole = window.userSessionData?.role;
    const adminOnlyNavItems = document.querySelectorAll('.topbar-nav .admin-only');
    const staffOnlyNavItems = document.querySelectorAll('.topbar-nav .staff-only');

    if (userRole === 'admin') {
        adminOnlyNavItems.forEach(item => item.style.display = 'flex');
        staffOnlyNavItems.forEach(item => item.style.display = 'flex'); // Admin can also see staff links
    } else if (userRole === 'staff') {
        staffOnlyNavItems.forEach(item => item.style.display = 'flex');
        adminOnlyNavItems.forEach(item => item.style.display = 'none');
    } else { // Not logged in or other role
        adminOnlyNavItems.forEach(item => item.style.display = 'none');
        staffOnlyNavItems.forEach(item => item.style.display = 'none');
    }
    
    // --- Active Page Highlighting (simplified for example) ---
    function setActivePage() {
        const currentPath = window.location.pathname; // e.g., /login, /admin/dashboard
        const navLinks = document.querySelectorAll('.topbar-nav-item a, .mobile-menu .topbar-nav-item a');
        navLinks.forEach(link => {
            const linkPath = link.getAttribute('href');
            if (linkPath === currentPath || (currentPath.startsWith(linkPath) && linkPath !== '/' && linkPath.length > 1)) {
                link.classList.add('active');
            } else {
                link.classList.remove('active');
            }
        });
    }
    setActivePage();

    // Initial badge count fetch (optional, if popupNotification script handles it mostly)
    // setTimeout(fetchTopBarNotifications, 2500); // Or rely on popupNotification instance
});
</script>
