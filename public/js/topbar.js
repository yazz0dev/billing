(function() {
    // Helper functions (kept within IIFE, accessible by actualFetchTopBarNotifications)
    function getNotificationIconForType(type) {
        // Placeholder: Implement or ensure this function is available globally if needed elsewhere
        // For now, providing a basic structure based on common patterns
        const icons = {
            success: '<svg class="icon-success"><!-- success icon --></svg>',
            error: '<svg class="icon-error"><!-- error icon --></svg>',
            warning: '<svg class="icon-warning"><!-- warning icon --></svg>',
            info: '<svg class="icon-info"><!-- info icon --></svg>'
        };
        return icons[type] || icons.info;
    }

    function formatTimeAgo(timestamp) {
        // Placeholder: Implement or ensure this function is available globally if needed elsewhere
        // For now, returning a simple date string
        if (!timestamp) return 'Just now';
        const date = new Date(timestamp);
        const seconds = Math.floor((new Date() - date) / 1000);
        let interval = seconds / 31536000;
        if (interval > 1) return Math.floor(interval) + " years ago";
        interval = seconds / 2592000;
        if (interval > 1) return Math.floor(interval) + " months ago";
        interval = seconds / 86400;
        if (interval > 1) return Math.floor(interval) + " days ago";
        interval = seconds / 3600;
        if (interval > 1) return Math.floor(interval) + " hours ago";
        interval = seconds / 60;
        if (interval > 1) return Math.floor(interval) + " minutes ago";
        return Math.floor(seconds) + " seconds ago";
    }

    // Main function for fetching notifications
    function actualFetchTopBarNotifications() {
        const notificationListEl = document.getElementById('notificationList');
        const notificationBadge = document.getElementById('notificationBadge');

        if (!notificationListEl || !window.userSessionData || !window.userSessionData.id) {
            // console.warn("Notification list element or user session data not found.");
            if (notificationListEl) notificationListEl.innerHTML = '<div class="notification-error">User session not found.</div>';
            return;
        }

        // Use circuit breaker pattern from popup-notification if available
        const circuitBreaker = window.popupNotification?.options?.circuitBreaker || {
            canRequest: () => true, recordFailure: () => {}
        };

        if (!circuitBreaker.canRequest()) {
            notificationListEl.innerHTML = '<div class="notification-error">Notifications temporarily unavailable.</div>';
            if (notificationBadge) notificationBadge.style.display = 'none';
            return;
        }
        notificationListEl.innerHTML = '<div class="loading-spinner">Loading notifications...</div>'; // Placeholder for loading state

        fetch(window.BASE_PATH + '/api/notifications/fetch', {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/json', 
                'X-Requested-With': 'XMLHttpRequest', 
                'Accept': 'application/json' 
            },
            body: JSON.stringify({ popup_action: 'get' }) // Assuming API expects this
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

                if (result.data.length > 0) {
                    notificationListEl.innerHTML = result.data.map(n => `
                        <div class="notification-item ${n.type || 'info'} ${ (n.seen_by && n.seen_by.includes(userId)) ? 'seen' : 'unseen'}">
                            <div class="notification-icon">${getNotificationIconForType(n.type || 'info')}</div>
                            <div class="notification-content">
                                <div class="notification-message">${n.message || 'No message content.'}</div>
                                <div class="notification-time">${formatTimeAgo(n.created_at?.$date || n.created_at)}</div>
                            </div>
                        </div>`).join('');
                } else {
                    notificationListEl.innerHTML = '<div class="empty-notifications">No new notifications</div>';
                }
            } else { throw new Error(result.message || 'Invalid data format from server'); }
        })
        .catch(err => {
            console.error('Error fetching topbar notifications:', err);
            circuitBreaker.recordFailure();
            if (notificationListEl) notificationListEl.innerHTML = `<div class="notification-error">Could not load notifications. <button class="retry-btn" onclick="window.fetchTopBarNotifications()">Retry</button></div>`;
            if (notificationBadge) notificationBadge.style.display = 'none';
        });
    }

    // Expose for retry button and direct calls
    window.fetchTopBarNotifications = actualFetchTopBarNotifications;

    document.addEventListener('DOMContentLoaded', function() {
        // --- Theme Toggle Logic ---
        const themeToggle = document.getElementById('themeToggle');
        if (themeToggle) {
            themeToggle.addEventListener('click', () => {
                const currentTheme = document.documentElement.getAttribute('data-theme');
                const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
                document.documentElement.setAttribute('data-theme', newTheme);
                localStorage.setItem('theme', newTheme); // Persist theme choice
            });
            // Apply saved theme on load
            const savedTheme = localStorage.getItem('theme');
            if (savedTheme) {
                document.documentElement.setAttribute('data-theme', savedTheme);
            } else if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                document.documentElement.setAttribute('data-theme', 'dark'); // Default to system dark mode
            }
        }

        // --- Notification Panel Logic ---
        const notificationButton = document.getElementById('notificationButton');
        const notificationPanel = document.getElementById('notificationPanel');
        if (notificationButton && notificationPanel) {
            notificationButton.addEventListener('click', function(e) {
                e.stopPropagation(); // Prevent click from closing panel immediately
                notificationPanel.classList.toggle('show');
                if (notificationPanel.classList.contains('show')) {
                    window.fetchTopBarNotifications(); // Fetch when opened
                }
            });
            // Close panel if clicked outside
            document.addEventListener('click', function(e) {
                if (notificationPanel.classList.contains('show') && !notificationPanel.contains(e.target) && e.target !== notificationButton) {
                    notificationPanel.classList.remove('show');
                }
            });
        }
        
        // --- User Profile Dropdown Logic ---
        const userProfileButton = document.getElementById('userProfileButton');
        const userDropdown = document.getElementById('userDropdown');
        if (userProfileButton && userDropdown) {
            userProfileButton.addEventListener('click', (e) => {
                e.stopPropagation();
                userDropdown.classList.toggle('show');
            });
            document.addEventListener('click', (e) => {
                if (userDropdown.classList.contains('show') && !userDropdown.contains(e.target) && e.target !== userProfileButton) {
                    userDropdown.classList.remove('show');
                }
            });
        }

        // --- Mobile Menu Toggle Logic ---
        const mobileMenuToggle = document.getElementById('mobileMenuToggle');
        const mobileMenu = document.getElementById('mobileMenu');
        const topbarNav = document.querySelector('.topbar-nav');
        if (mobileMenuToggle && mobileMenu && topbarNav) {
            mobileMenuToggle.addEventListener('click', () => {
                mobileMenu.classList.toggle('open');
                if (mobileMenu.classList.contains('open')) {
                    mobileMenu.innerHTML = topbarNav.innerHTML; // Clone nav items
                    setActivePage(); // Re-apply active state to cloned links
                } else {
                    mobileMenu.innerHTML = '';
                }
            });
        }
        
        // --- Role-based Navigation Visibility ---
        if (window.userSessionData) { // Ensure userSessionData is available
            const userRole = window.userSessionData.role;
            const adminOnlyNavItems = document.querySelectorAll('.topbar-nav .admin-only');
            const staffOnlyNavItems = document.querySelectorAll('.topbar-nav .staff-only');

            if (userRole === 'admin') {
                adminOnlyNavItems.forEach(item => item.style.display = 'flex');
                staffOnlyNavItems.forEach(item => item.style.display = 'flex');
            } else if (userRole === 'staff') {
                staffOnlyNavItems.forEach(item => item.style.display = 'flex');
                adminOnlyNavItems.forEach(item => item.style.display = 'none');
            } else { 
                adminOnlyNavItems.forEach(item => item.style.display = 'none');
                staffOnlyNavItems.forEach(item => item.style.display = 'none');
            }
        } else {
            // console.warn("userSessionData not found for role-based navigation.");
             document.querySelectorAll('.topbar-nav .admin-only, .topbar-nav .staff-only').forEach(item => item.style.display = 'none');
        }
        
        // --- Active Page Highlighting ---
        function setActivePage() {
            const currentPath = window.location.pathname;
            const navLinks = document.querySelectorAll('.topbar-nav-item a, .mobile-menu .topbar-nav-item a');
            navLinks.forEach(link => {
                const linkPath = link.getAttribute('href');
                if (!linkPath) return;

                // Normalize paths for comparison (remove trailing slashes unless it's just "/")
                const normalizedCurrentPath = currentPath === '/' ? '/' : currentPath.replace(/\/$/, "");
                const normalizedLinkPath = linkPath === '/' ? '/' : linkPath.replace(/\/$/, "");

                if (normalizedLinkPath === normalizedCurrentPath) {
                    link.classList.add('active');
                } else if (normalizedLinkPath !== '/' && normalizedCurrentPath.startsWith(normalizedLinkPath + '/')) {
                    // Handle cases like /admin being active for /admin/dashboard
                    link.classList.add('active');
                }
                 else {
                    link.classList.remove('active');
                }
            });
        }
        setActivePage();

        // Initial fetch for notification badge (optional, can be delayed)
        // setTimeout(window.fetchTopBarNotifications, 2500); 
    });
})();
