(function() {
    // ... (getNotificationIconForType, formatTimeAgo - same as original) ...
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    function actualFetchTopBarNotifications() {
        const notificationListEl = document.getElementById('notificationList');
        const notificationBadge = document.getElementById('notificationBadge');
        const isAuthenticated = window.USER_AUTHENTICATED || false; // Get from global var set in layout

        if (!notificationListEl || !isAuthenticated) {
            if (notificationListEl) notificationListEl.innerHTML = `<div class="notification-error">${isAuthenticated ? 'Element issue.' : 'Login to see notifications.'}</div>`;
            if (notificationBadge) notificationBadge.style.display = 'none';
            return;
        }
        // ... (circuit breaker logic same as original) ...
        notificationListEl.innerHTML = '<div class="loading-spinner">Loading notifications...</div>';

        fetch(`${window.APP_URL}/api/notifications/fetch`, { // Use APP_URL
            method: 'POST', // Or GET if your API changed
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
                ...(csrfToken && {'X-CSRF-TOKEN': csrfToken})
            },
            // body: JSON.stringify({ popup_action: 'get' }) // If needed by API
        })
        .then(response => { /* ... */ })
        .then(result => {
            if (result && result.status === 'success' && Array.isArray(result.data)) {
                // Assuming USER_ID is available globally if needed for 'seen_by' logic client-side
                // However, Laravel API should ideally return only relevant notifications or mark them.
                const unreadCount = result.data.filter(n => !n.seen_by || !n.seen_by.includes(window.USER_ID)).length; // USER_ID needs to be set
                
                if (notificationBadge) {
                    notificationBadge.textContent = unreadCount;
                    notificationBadge.style.display = unreadCount > 0 ? 'flex' : 'none';
                }
                if (result.data.length > 0) {
                    notificationListEl.innerHTML = result.data.map(n => `
                        <div class="notification-item ${n.type || 'info'} ${ (n.seen_by && n.seen_by.includes(window.USER_ID)) ? 'seen' : 'unseen'}">
                            {/* ... icon ... message ... time ... */}
                        </div>`).join('');
                } else { /* ... */ }
            } else { /* ... */ }
        })
        .catch(err => { /* ... */ });
    }
    // ... (rest of the file is mostly UI manipulation, largely the same) ...
    // Ensure USER_AUTHENTICATED and USER_ID are set in the main layout for this script
    // Ensure APP_URL is set in the main layout for this script

    document.addEventListener('DOMContentLoaded', function() {
        // --- Theme Toggle Logic ---
        const themeToggle = document.getElementById('themeToggle');
        const sunIcon = themeToggle?.querySelector('.icon-sun');
        const moonIcon = themeToggle?.querySelector('.icon-moon');

        function applyTheme(theme) {
            document.documentElement.setAttribute('data-theme', theme);
            if (theme === 'dark') {
                sunIcon?.style.display = 'inline-block';
                moonIcon?.style.display = 'none';
            } else {
                sunIcon?.style.display = 'none';
                moonIcon?.style.display = 'inline-block';
            }
        }
        
        if (themeToggle) {
            themeToggle.addEventListener('click', () => {
                const currentTheme = document.documentElement.getAttribute('data-theme');
                const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
                applyTheme(newTheme);
                localStorage.setItem('theme', newTheme);
            });
            const savedTheme = localStorage.getItem('theme');
            if (savedTheme) {
                applyTheme(savedTheme);
            } else if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                applyTheme('dark');
            } else {
                applyTheme('light'); // Default to light
            }
        }


        // ... (Notification Panel, User Profile Dropdown, Mobile Menu - same logic as original) ...
        // Ensure Role-based Navigation visibility uses window.USER_ROLE
        if (window.USER_ROLE) {
            const userRole = window.USER_ROLE;
            // ... (rest of role visibility logic same) ...
        }

        // --- Active Page Highlighting (Adapt to Blade URLs) ---
        function setActivePage() {
            const currentPath = window.location.pathname;
            const navLinks = document.querySelectorAll('.topbar-nav-item a, .mobile-menu .topbar-nav-item a');
            navLinks.forEach(link => {
                const linkPath = new URL(link.href).pathname; // Get pathname from full URL
                // Normalize paths (remove trailing slash unless it's root)
                const normalizedCurrentPath = currentPath === '/' ? '/' : currentPath.replace(/\/$/, "");
                const normalizedLinkPath = linkPath === '/' ? '/' : linkPath.replace(/\/$/, "");

                if (normalizedLinkPath === normalizedCurrentPath || (normalizedLinkPath !== '/' && normalizedCurrentPath.startsWith(normalizedLinkPath + '/'))) {
                    link.classList.add('active');
                } else {
                    link.classList.remove('active');
                }
            });
        }
        setActivePage();
        if (window.USER_AUTHENTICATED) { // Only fetch if authenticated
             setTimeout(window.fetchTopBarNotifications, 1000);
        }
    });
})();