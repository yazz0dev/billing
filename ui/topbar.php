<?php
/**
 * Topbar component for the billing system
 * Handles navigation, user profile, theme switching, and notifications
 */
// Ensure no whitespace before PHP tag to prevent output
?>
<div class="topbar">
    <div class="topbar-container">
        <!-- Logo Section -->
        <div class="topbar-logo">
            <a href="/billing/">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="logo-icon">
                    <path d="M3 3h18v18H3z"></path>
                    <path d="M3 9h18"></path>
                    <path d="M9 21V9"></path>
                </svg>
                <span>BillSys</span>
            </a>
        </div>

        <!-- Navigation Links - Shown based on user role -->
        <nav class="topbar-nav">
            <!-- Common Links -->
            <div class="topbar-nav-item">
                <a href="/billing/" data-page="home">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="nav-icon">
                        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                        <polyline points="9 22 9 12 15 12 15 22"></polyline>
                    </svg>
                    <span>Home</span>
                </a>
            </div>

            <!-- Admin Links -->
            <div class="topbar-nav-item admin-only">
                <a href="/billing/admin/" data-page="admin-dashboard">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="nav-icon">
                        <rect x="3" y="3" width="7" height="7"></rect>
                        <rect x="14" y="3" width="7" height="7"></rect>
                        <rect x="14" y="14" width="7" height="7"></rect>
                        <rect x="3" y="14" width="7" height="7"></rect>
                    </svg>
                    <span>Dashboard</span>
                </a>
            </div>
            
            <div class="topbar-nav-item admin-only">
                <a href="/billing/product/" data-page="products">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="nav-icon">
                        <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
                        <line x1="3" y1="6" x2="21" y2="6"></line>
                        <path d="M16 10a4 4 0 0 1-8 0"></path>
                    </svg>
                    <span>Products</span>
                </a>
            </div>

            <!-- Staff Links -->
            <div class="topbar-nav-item staff-only">
                <a href="/billing/staff/" data-page="billing">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="nav-icon">
                        <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path>
                    </svg>
                    <span>Billing</span>
                </a>
            </div>
            
            <div class="topbar-nav-item staff-only">
                <a href="/billing/staff/billview.php" data-page="bill-history">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="nav-icon">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                        <polyline points="14 2 14 8 20 8"></polyline>
                        <line x1="16" y1="13" x2="8" y2="13"></line>
                        <line x1="16" y1="17" x2="8" y2="17"></line>
                        <polyline points="10 9 9 9 8 9"></polyline>
                    </svg>
                    <span>Bill History</span>
                </a>
            </div>
        </nav>

        <!-- Right side user actions -->
        <div class="topbar-actions">
            <!-- Theme Toggle -->
            <button class="theme-toggle" id="themeToggle" title="Toggle dark/light mode">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-sun">
                    <circle cx="12" cy="12" r="5"></circle>
                    <line x1="12" y1="1" x2="12" y2="3"></line>
                    <line x1="12" y1="21" x2="12" y2="23"></line>
                    <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line>
                    <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line>
                    <line x1="1" y1="12" x2="3" y2="12"></line>
                    <line x1="21" y1="12" x2="23" y2="12"></line>
                    <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line>
                    <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line>
                </svg>
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-moon">
                    <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
                </svg>
            </button>
            
            <!-- Notifications -->
            <div class="topbar-notifications">
                <button class="notification-btn" id="notificationButton" title="Notifications">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-bell">
                        <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                        <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                    </svg>
                    <span class="notification-badge" id="notificationBadge" style="display: none;">0</span>
                </button>
                <div class="notification-panel" id="notificationPanel">
                    <div class="notification-header">
                        <h3>Notifications</h3>
                        <button class="close-btn" id="closeNotifications">&times;</button>
                    </div>
                    <div class="notification-list" id="notificationList">
                        <div class="empty-notifications">No new notifications</div>
                    </div>
                </div>
            </div>

            <!-- User profile with dropdown -->
            <div class="topbar-user-profile">
                <button class="user-profile-btn" id="userProfileButton">
                    <div class="user-avatar">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                            <circle cx="12" cy="7" r="4"></circle>
                        </svg>
                    </div>
                    <span class="user-name" id="userName"><?php echo isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'User'; ?></span>
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-chevron">
                        <polyline points="6 9 12 15 18 9"></polyline>
                    </svg>
                </button>

                <div class="user-dropdown" id="userDropdown">
                    <div class="user-dropdown-header">
                        <div class="user-info">
                            <span class="user-role" id="userRole"><?php echo isset($_SESSION['user_role']) ? ucfirst(htmlspecialchars($_SESSION['user_role'])) : 'Role'; ?></span>
                            <span class="user-email" id="userEmail"><?php echo isset($_SESSION['user_email']) ? htmlspecialchars($_SESSION['user_email']) : 'user@example.com'; ?></span>
                        </div>
                    </div>
                    <div class="user-dropdown-body">
                        <a href="#" class="dropdown-item disabled">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="dropdown-icon">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                <circle cx="12" cy="7" r="4"></circle>
                            </svg>
                            Profile (soon)
                        </a>
                        <a href="#" class="dropdown-item disabled">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="dropdown-icon">
                                <circle cx="12" cy="12" r="3"></circle>
                                <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
                            </svg>
                            Settings (soon)
                        </a>
                        <div class="dropdown-divider"></div>
                        <a href="/billing/logout.php" class="dropdown-item text-danger">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="dropdown-icon">
                                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                                <polyline points="16 17 21 12 16 7"></polyline>
                                <line x1="21" y1="12" x2="9" y2="12"></line>
                            </svg>
                            Logout
                        </a>
                    </div>
                </div>
            </div>

            <!-- Mobile menu toggle -->
            <button class="mobile-menu-toggle" id="mobileMenuToggle" aria-label="Toggle menu">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="3" y1="12" x2="21" y2="12"></line>
                    <line x1="3" y1="6" x2="21" y2="6"></line>
                    <line x1="3" y1="18" x2="21" y2="18"></line>
                </svg>
            </button>
        </div> <!-- End of topbar-actions -->
    </div>

    <!-- Mobile menu (hidden by default) -->
    <div class="mobile-menu" id="mobileMenu">
        <!-- Mobile navigation links will be populated by JavaScript -->
    </div>
</div>

<!-- Script for topbar functionality -->
<script>
// Define fetchTopBarNotifications in global scope
function fetchTopBarNotifications() {
    const notificationListEl = document.getElementById('notificationList');
    const notificationBadge = document.getElementById('notificationBadge');
    const notificationCircuitBreaker = window.notificationCircuitBreaker || {
        failures: 0, maxFailures: 3, resetTimeout: 60000, lastFailure: 0, isOpen: false,
        recordFailure() { this.failures++; this.lastFailure = Date.now(); if (this.failures >= this.maxFailures) { this.isOpen = true; setTimeout(() => { this.isOpen = false; this.failures = 0; }, this.resetTimeout); } return this.isOpen; },
        canRequest() { return !this.isOpen; }
    };

    // Define fallback icon generator if window.popupNotification doesn't exist
    const getNotificationIcon = (type) => {
        if (window.popupNotification && typeof window.popupNotification.getIconForType === 'function') {
            return window.popupNotification.getIconForType(type);
        }
        
        // Fallback icons if popupNotification is not available
        const icons = {
            'success': '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>',
            'info': '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>',
            'warning': '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>',
            'error': '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>'
        };
        return icons[type] || icons['info'];
    };

    if (!notificationCircuitBreaker.canRequest() || !notificationListEl) {
        if (notificationListEl) notificationListEl.innerHTML = '<div class="notification-error">Notifications temporarily unavailable.</div>';
        if (notificationBadge) notificationBadge.style.display = 'none';
        return;
    }
    if (notificationListEl) notificationListEl.innerHTML = '<div class="loading-spinner">Loading...</div>';

    const formData = new FormData();
    formData.append('popup_action', 'get'); // This is from popup-notification.js
    
    fetch('/billing/notification.php', { 
        method: 'POST', 
        body: formData, 
        signal: AbortSignal.timeout(8000)
    })
    .then(response => {
        if (!response.ok) throw new Error(`Server error: ${response.status}`);
        // Check if the response is actually JSON
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            throw new Error('Server returned non-JSON response. Check server configuration.');
        }
        return response.json();
    })
    .then(result => {
        if (result && result.status === 'success' && Array.isArray(result.data)) {
            // PHP integration: get user ID from PHP session for unread logic
            const userId = "<?php echo isset($_SESSION['user_id']) ? $_SESSION['user_id'] : (isset($_SESSION['username']) ? $_SESSION['username'] : ''); ?>";
            
            const unreadCount = result.data.filter(n => !(n.seen_by && n.seen_by.includes(userId || window.userSessionData?.id || window.userSessionData?.name))).length;
            if (notificationBadge) {
                notificationBadge.textContent = unreadCount;
                notificationBadge.style.display = unreadCount > 0 ? 'flex' : 'none';
            }
            if (notificationListEl) {
                if (result.data.length > 0) {
                    notificationListEl.innerHTML = result.data.map(n => `
                        <div class="notification-item ${n.type || 'info'} ${ (n.seen_by && n.seen_by.includes(userId || window.userSessionData?.id || window.userSessionData?.name)) ? 'seen' : 'unseen'}">
                            <div class="notification-icon">${getNotificationIcon(n.type || 'info')}</div>
                            <div class="notification-content">
                                <div class="notification-message">${n.message}</div>
                                <div class="notification-time">${formatTimeAgo(n.created_at.$date || n.created_at)}</div>
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
        notificationCircuitBreaker.recordFailure();
        if (notificationListEl) notificationListEl.innerHTML = `<div class="notification-error">Could not load. <button class="retry-btn" onclick="fetchTopBarNotifications()">Retry</button></div>`;
        if (notificationBadge) notificationBadge.style.display = 'none';
    });
}

// Function to format time for notifications 
function formatTimeAgo(timestamp) {
    if (!timestamp) return 'Some time ago';
    const date = new Date(timestamp);
    const now = new Date();
    const seconds = Math.round((now - date) / 1000);
    const minutes = Math.round(seconds / 60);
    const hours = Math.round(minutes / 60);
    const days = Math.round(hours / 24);

    if (seconds < 5) return 'Just now';
    if (seconds < 60) return `${seconds} sec ago`;
    if (minutes < 60) return `${minutes} min ago`;
    if (hours < 24) return `${hours} hr ago`;
    if (days < 7) return `${days} day${days > 1 ? 's' : ''} ago`;
    return date.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
}

// Store circuit breaker in window for reuse
window.notificationCircuitBreaker = {
    failures: 0, maxFailures: 3, resetTimeout: 60000, lastFailure: 0, isOpen: false,
    recordFailure() { this.failures++; this.lastFailure = Date.now(); if (this.failures >= this.maxFailures) { this.isOpen = true; setTimeout(() => { this.isOpen = false; this.failures = 0; }, this.resetTimeout); } return this.isOpen; },
    canRequest() { return !this.isOpen; }
};

document.addEventListener('DOMContentLoaded', function() {
    // Set PHP session data for JavaScript
    const userHasBillingAccess = <?php echo (isset($_SESSION['user_role']) && in_array($_SESSION['user_role'], ['admin', 'staff'])) ? 'true' : 'false'; ?>;
    const userIsAdmin = <?php echo (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') ? 'true' : 'false'; ?>;


    window.userSessionData = {
        name: "<?php echo isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'User'; ?>",
        role: "<?php echo isset($_SESSION['user_role']) ? htmlspecialchars($_SESSION['user_role']) : ''; ?>",
        email: "<?php echo isset($_SESSION['user_email']) ? htmlspecialchars($_SESSION['user_email']) : 'user@example.com'; ?>",
        id: "<?php echo isset($_SESSION['user_id']) ? htmlspecialchars($_SESSION['user_id']) : ''; ?>"
    };

    const themeToggle = document.getElementById('themeToggle');
    const userProfileButton = document.getElementById('userProfileButton');
    const userDropdown = document.getElementById('userDropdown');
    const notificationButton = document.getElementById('notificationButton');
    const notificationPanel = document.getElementById('notificationPanel');
    const closeNotifications = document.getElementById('closeNotifications');
    const mobileMenuToggle = document.getElementById('mobileMenuToggle');
    const mobileMenu = document.getElementById('mobileMenu');

    // Role-based navigation visibility
    const userRole = window.userSessionData.role;
    const adminOnlyNavItems = document.querySelectorAll('.topbar-nav .admin-only');
    const staffOnlyNavItems = document.querySelectorAll('.topbar-nav .staff-only');

    if (userRole === 'admin') {
        adminOnlyNavItems.forEach(item => item.style.display = 'flex');
        // staffOnlyNavItems remain hidden by default CSS
    } else if (userRole === 'staff') {
        staffOnlyNavItems.forEach(item => item.style.display = 'flex');
        // adminOnlyNavItems remain hidden by default CSS
    }
    // For other roles or if role is not set, both admin and staff items remain hidden by CSS default
    
    // Theme toggle
    if (themeToggle) {
        const prefersDarkMode = window.matchMedia('(prefers-color-scheme: dark)').matches;
        const savedTheme = localStorage.getItem('theme');
        if (savedTheme === 'dark' || (!savedTheme && prefersDarkMode)) {
            document.body.classList.add('dark-mode');
        }
        themeToggle.addEventListener('click', function() {
            document.body.classList.toggle('dark-mode');
            localStorage.setItem('theme', document.body.classList.contains('dark-mode') ? 'dark' : 'light');
        });
    }

    // User dropdown
    if (userProfileButton && userDropdown) {
        userProfileButton.addEventListener('click', function() {
            userDropdown.classList.toggle('show');
            const chevron = userProfileButton.querySelector('.icon-chevron');
            if (chevron) {
                chevron.style.transform = userDropdown.classList.contains('show') ? 'rotate(180deg)' : 'rotate(0)';
            }
        });
        document.addEventListener('click', function(event) {
            if (userProfileButton && !userProfileButton.contains(event.target) && userDropdown && !userDropdown.contains(event.target)) {
                userDropdown.classList.remove('show');
                 const chevron = userProfileButton.querySelector('.icon-chevron');
                if (chevron) chevron.style.transform = 'rotate(0)';
            }
        });
    }

    // Notifications panel
    if (notificationButton && notificationPanel) {
        notificationButton.addEventListener('click', function(e) {
            e.preventDefault(); // Prevent default action
            e.stopPropagation(); // Prevent event bubbling
            
            // Toggle panel visibility
            notificationPanel.classList.toggle('show');
            
            // If panel is now visible, fetch notifications
            if (notificationPanel.classList.contains('show')) {
                fetchTopBarNotifications();
            }
        });
        
        if (closeNotifications) {
            closeNotifications.addEventListener('click', function(e) {
                e.preventDefault();
                notificationPanel.classList.remove('show');
            });
        }
        
        // Close panel when clicking outside
        document.addEventListener('click', function(event) {
            if (notificationButton && !notificationButton.contains(event.target) && 
                notificationPanel && !notificationPanel.contains(event.target)) {
                notificationPanel.classList.remove('show');
            }
        });
    }

    // Mobile menu
    if (mobileMenuToggle && mobileMenu) {
        mobileMenuToggle.addEventListener('click', () => mobileMenu.classList.toggle('show'));
        
        // Clear mobile menu first
        mobileMenu.innerHTML = '';
        
        // Create mobile nav items from VISIBLE desktop nav items
        const navItemsToClone = document.querySelectorAll('.topbar-nav .topbar-nav-item');
        navItemsToClone.forEach(item => {
            // Check if the item is currently displayed (not 'none' due to role logic)
            // getComputedStyle is more robust if classes were used, but inline style check is fine here.
            if (window.getComputedStyle(item).display !== 'none') {
                const clone = item.cloneNode(true);
                // Remove inline display style from clone so mobile CSS can control it
                clone.style.removeProperty('display'); 
                
                clone.querySelectorAll('a').forEach(a => {
                    a.addEventListener('click', () => mobileMenu.classList.remove('show'));
                });
                mobileMenu.appendChild(clone);
            }
        });
    }

    // User info update from global JS variable
    if (window.userSessionData) {
        const userNameEl = document.getElementById('userName');
        const userRoleEl = document.getElementById('userRole');
        const userEmailEl = document.getElementById('userEmail');
        if (userNameEl) userNameEl.textContent = window.userSessionData.name || 'User';
        if (userRoleEl) userRoleEl.textContent = window.userSessionData.role ? window.userSessionData.role.charAt(0).toUpperCase() + window.userSessionData.role.slice(1) : 'Role';
        if (userEmailEl) userEmailEl.textContent = window.userSessionData.email || 'email@example.com';
 
    }

    // Active page highlighting
    function setActivePage() {
        const currentPath = window.location.pathname;
        const navLinks = document.querySelectorAll('.topbar-nav-item a, .mobile-menu .topbar-nav-item a');
        navLinks.forEach(link => {
            const linkPath = link.getAttribute('href');
            // More robust matching for active link
            if (linkPath === currentPath ||
                (currentPath.startsWith(linkPath) && linkPath !== '/billing/' && linkPath.length > ('/billing/').length) || // for paths like /billing/admin/*
                (currentPath === '/billing/' && linkPath === '/billing/') // Home specifically
            ) {
                link.classList.add('active');
            } else {
                link.classList.remove('active');
            }
        });
    }
    setActivePage();

    function formatTimeAgo(timestamp) {
        if (!timestamp) return 'Some time ago';
        const date = new Date(timestamp);
        const now = new Date();
        const seconds = Math.round((now - date) / 1000);
        const minutes = Math.round(seconds / 60);
        const hours = Math.round(minutes / 60);
        const days = Math.round(hours / 24);

        if (seconds < 5) return 'Just now';
        if (seconds < 60) return `${seconds} sec ago`;
        if (minutes < 60) return `${minutes} min ago`;
        if (hours < 24) return `${hours} hr ago`;
        if (days < 7) return `${days} day${days > 1 ? 's' : ''} ago`;
        return date.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
    }
    
    // Initial check for notifications count for the badge after a slight delay
    setTimeout(() => {
        if (notificationCircuitBreaker.canRequest() && window.popupNotification) {
             const formData = new FormData();
             formData.append('popup_action', 'get');
             fetch('/billing/notification.php', { method: 'POST', body: formData, signal: AbortSignal.timeout(5000) })
                .then(r => r.ok ? r.json() : Promise.reject(r))
                .then(res => {
                    if (res.status === 'success' && Array.isArray(res.data)) {
                         const unreadCount = res.data.filter(n => !(n.seen_by && n.seen_by.includes(window.userSessionData.id || window.userSessionData.name))).length;
                         if (notificationBadge) {
                            notificationBadge.textContent = unreadCount;
                            notificationBadge.style.display = unreadCount > 0 ? 'flex' : 'none';
                        }
                    }
                }).catch(e => console.warn("Initial badge count fetch failed", e));
        }
    }, 2500);

});
</script>