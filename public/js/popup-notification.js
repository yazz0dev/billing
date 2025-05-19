// billing/public/js/popup-notification.js
(function() {
    if (window.PopupNotification && window.PopupNotification.initialized) return;

    class PopupNotification {
        constructor(options = {}) {
            this.options = {
                position: options.position || 'top-right',
                maxNotifications: options.maxNotifications || 5,
                animationDuration: options.animationDuration || 300,
                defaultDuration: options.defaultDuration || 5000,
                containerClass: options.containerClass || 'popup-notification-container',
                notificationClass: options.notificationClass || 'popup-notification',
                zIndex: options.zIndex || 9999,
                fetchFromServer: options.fetchFromServer !== undefined ? options.fetchFromServer : true,
                fetchInterval: options.fetchInterval || 30000,
                fetchUrl: (options.fetchUrl || (window.APP_URL ? `${window.APP_URL}/api/notifications/fetch` : '/api/notifications/fetch')),
                markSeenUrl: (options.markSeenUrl || (window.APP_URL ? `${window.APP_URL}/api/notifications/mark-seen` : '/api/notifications/mark-seen')),
            };
            this.options.circuitBreaker = { /* ... (same as original) ... */ };
            this.container = null;
            this.notifications = [];
            this.initialized = false;
            this.fetchRetryCount = 0;
            this.fetchBackoffTime = 2000;
            this.fetchIntervalId = null;
            const csrfTokenMeta = document.querySelector('meta[name="csrf-token"]');
            this.csrfToken = csrfTokenMeta ? csrfTokenMeta.getAttribute('content') : null;


            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', () => this.init());
            } else {
                this.init();
            }
        }

        init() {
            if (this.initialized) return;
            this.createContainer();
            if (this.options.fetchFromServer && window.USER_AUTHENTICATED) { // Only fetch if user is authenticated
                setTimeout(() => this.fetchFromServer(), 1500);
                if (this.fetchIntervalId) clearInterval(this.fetchIntervalId);
                this.fetchIntervalId = setInterval(() => this.fetchFromServer(), this.options.fetchInterval);
            }
            this.initialized = true;
            PopupNotification.initialized = true;
        }

        createContainer() { /* ... (same as original) ... */ }

        show(options) { /* ... (same as original) ... */ }
        hide(id, immediate = false) { /* ... (same as original) ... */ }
        success(message, title = 'Success', duration) { return this.show({ type: 'success', title, message, duration }); }
        error(message, title = 'Error', duration) { return this.show({ type: 'error', title, message, duration }); }
        warning(message, title = 'Warning', duration) { return this.show({ type: 'warning', title, message, duration }); }
        info(message, title = 'Information', duration) { return this.show({ type: 'info', title, message, duration }); }

        markAsSeen(serverId) {
            fetch(this.options.markSeenUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    ...(this.csrfToken && {'X-CSRF-TOKEN': this.csrfToken})
                },
                body: JSON.stringify({ notification_id: serverId })
            })
            .then(response => { /* ... */ })
            .catch(err => console.error('Error marking notification as seen:', err));
        }

        fetchFromServer() {
            if (!this.options.circuitBreaker.canRequest()) { /* ... */ return; }
            if (!window.USER_AUTHENTICATED) { // Do not fetch if user is not logged in
                if (this.fetchIntervalId) clearInterval(this.fetchIntervalId);
                this.fetchIntervalId = null;
                return;
            }

            fetch(this.options.fetchUrl, {
                method: 'POST', // Or GET, depending on your API
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    ...(this.csrfToken && {'X-CSRF-TOKEN': this.csrfToken})
                },
                // body: JSON.stringify({ popup_action: 'get' }) // If POST and body is needed
            })
            .then(response => { /* ... (handle JSON, text, errors as before) ... */ })
            .then(result => {
                this.options.circuitBreaker.reset();
                this.fetchRetryCount = 0;
                this.fetchBackoffTime = 2000;
                if (result.status === 'success' && Array.isArray(result.data)) {
                    result.data.forEach(notification => {
                        const serverNotificationId = notification.id || notification._id; // Laravel might use 'id'
                        const exists = this.notifications.some(n => n.serverId === serverNotificationId);
                        if (!exists) {
                            this.show({
                                type: notification.type || 'info',
                                title: notification.title || this.getDefaultTitle(notification.type || 'info'),
                                message: notification.message,
                                duration: notification.duration,
                                id: serverNotificationId
                            });
                        }
                    });
                } else if (result.message && result.status !== 'success') { /* console.warn ... */ }
            })
            .catch(err => { /* ... (error handling, circuit breaker logic as before) ... */ });
        }

        getDefaultTitle(type) { /* ... (same as original) ... */ }
        getIconForType(type) { /* ... (same as original) ... */ }
    }

    // ... (rest of the IIFE and confirmNotification, same as original) ...
    PopupNotification.initialized = false; 
    window.PopupNotification = PopupNotification;

    if (!window.popupNotification && document.readyState !== 'loading') {
        window.popupNotification = new PopupNotification();
    } else if (!window.popupNotification) {
        document.addEventListener('DOMContentLoaded', () => {
            if (!window.popupNotification) { 
                window.popupNotification = new PopupNotification();
            }
        });
    }

    if (!window._alertOverridden) { /* ... (same alert override) ... */ }

})();
window.confirmNotification = function(message, onConfirm, onCancel, options = {}) { /* ... (same as original) ... */ };