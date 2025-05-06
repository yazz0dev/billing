//billing/js/popup-notification.js

// Use an IIFE to prevent global namespace pollution and avoid duplicate class declarations
(function() {
    // Check if already defined to prevent duplicate declaration
    if (window.PopupNotification && window.PopupNotification.initialized) {
        // If an instance exists and is initialized, ensure methods are available on the global scope if needed
        // This might be redundant if the original script already did this correctly.
        if (window.popupNotification && typeof window.popupNotification.show !== 'function') {
             // console.warn("PopupNotification class was defined, but instance methods were missing. Re-linking.");
            // This implies the class was loaded, but the global instance setup failed or was overwritten.
            // Re-creating or re-linking might be needed, or simply ensuring the constructor logic handles this.
        }
        return; // Already loaded and initialized
    }


    /**
     * Popup Notification System
     * Provides client-side functionality for displaying popup notifications
     */
    class PopupNotification {
        constructor(options = {}) {
            this.options = {
                position: options.position || 'top-right', // top-right, top-left, bottom-right, bottom-left, top-center, bottom-center
                maxNotifications: options.maxNotifications || 5,
                animationDuration: options.animationDuration || 300, // Matches CSS
                defaultDuration: options.defaultDuration || 5000,
                containerClass: options.containerClass || 'popup-notification-container',
                notificationClass: options.notificationClass || 'popup-notification',
                zIndex: options.zIndex || 9999, // Kept for reference, but CSS should handle it
                fetchFromServer: options.fetchFromServer !== undefined ? options.fetchFromServer : true,
                fetchInterval: options.fetchInterval || 30000, // 30 seconds
                fetchUrl: options.fetchUrl || '/billing/notification.php',
                markSeenUrl: options.markSeenUrl || '/billing/notification.php',
                dbCheckUrl: options.dbCheckUrl || '/billing/db-check.php', // Added for db-check.php
            };

            this.container = null;
            this.notifications = []; // Stores { id, element, serverId }
            this.initialized = false;
            
            this.fetchRetryCount = 0;
            this.fetchBackoffTime = 2000; // Initial backoff time
            this.fetchIntervalId = null; // Store interval ID for clearing

            // Initialize when DOM is ready
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', () => this.init());
            } else {
                this.init();
            }
        }

        /**
         * Initialize the notification system
         */
        init() {
            if (this.initialized) return;

            this.createContainer();

            // Styles are now in global.css, no need to add them here via JS

            if (this.options.fetchFromServer) {
                setTimeout(() => this.fetchFromServer(), 1500); // Initial fetch
                if (this.fetchIntervalId) clearInterval(this.fetchIntervalId); // Clear previous interval if any
                this.fetchIntervalId = setInterval(() => this.fetchFromServer(), this.options.fetchInterval);
            }
            
            this.initialized = true;
            PopupNotification.initialized = true; // Static flag
            // console.log("PopupNotification initialized.");
        }

        /**
         * Create notification container
         */
        createContainer() {
            if (document.querySelector('.' + this.options.containerClass)) {
                this.container = document.querySelector('.' + this.options.containerClass);
                // console.warn("PopupNotification container already exists. Re-using.");
                return;
            }
            this.container = document.createElement('div');
            this.container.className = this.options.containerClass;
            this.container.setAttribute('aria-live', 'polite');
            // Positioning classes will be handled by CSS based on this.options.position
            // e.g., this.container.classList.add(`position-${this.options.position}`);
            // For now, direct styling is kept for simplicity if CSS doesn't use position classes
            this.container.style.position = 'fixed'; // Ensure it's fixed
            this.container.style.zIndex = this.options.zIndex;


            // Set container position based on options (can be enhanced with classes)
            const [posY, posX] = this.options.position.split('-');
            if (posY === 'top') this.container.style.top = '20px';
            if (posY === 'bottom') this.container.style.bottom = '20px';
            
            if (posX === 'left') this.container.style.left = '20px';
            if (posX === 'right') this.container.style.right = '20px';
            
            if (posX === 'center') {
                this.container.style.left = '50%';
                this.container.style.transform = 'translateX(-50%)';
            }
             if (posY === 'center' && posX === 'center') { // True center
                this.container.style.top = '50%';
                this.container.style.left = '50%';
                this.container.style.transform = 'translate(-50%, -50%)';
            }


            document.body.appendChild(this.container);
        }

        /**
         * Create and display a notification
         * @param {Object} options Notification options (type, title, message, duration, id (server_id))
         * @returns {HTMLElement|null} The notification element or null if not created
         */
        show(options) {
            if (!this.container) {
                // console.warn('PopupNotification container not initialized. Queuing notification.');
                setTimeout(() => this.show(options), 100);
                return null;
            }

            while (this.notifications.length >= this.options.maxNotifications) {
                const oldestNotification = this.notifications.shift(); // Remove from beginning
                this.hide(oldestNotification.id, true); // Hide immediately
            }

            const notificationId = 'notif-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9);
            const type = options.type || 'info';
            const title = options.title || this.getDefaultTitle(type);
            const message = options.message || '';
            const duration = options.duration !== undefined ? options.duration : this.options.defaultDuration;
            const serverId = options.id || null; // Server-side ID for marking as seen

            const notification = document.createElement('div');
            notification.className = `${this.options.notificationClass} ${type}`;
            notification.id = notificationId;
            notification.setAttribute('role', 'alert');
            notification.setAttribute('aria-live', 'assertive'); // More assertive for important messages

            const iconHTML = this.getIconForType(type);

            notification.innerHTML = `
                <div class="notification-content">
                    <div class="notification-icon">${iconHTML}</div>
                    <div class="notification-body">
                        <strong class="notification-title">${title}</strong>
                        <p class="notification-message">${message}</p>
                    </div>
                </div>
                <button class="notification-close" aria-label="Close notification">×</button>
                ${duration && duration > 0 ? '<div class="notification-progress"></div>' : ''}
            `;

            this.container.appendChild(notification);
            this.notifications.push({ id: notificationId, element: notification, serverId: serverId });

            notification.querySelector('.notification-close').addEventListener('click', () => this.hide(notificationId));

            // Animate in
            requestAnimationFrame(() => { // Ensures element is in DOM for transition
                 setTimeout(() => notification.classList.add('show'), 10);
            });


            if (duration && duration > 0) {
                const progressBar = notification.querySelector('.notification-progress');
                if (progressBar) {
                    progressBar.style.animationDuration = `${duration}ms`;
                }
                setTimeout(() => this.hide(notificationId), duration);
            }

            if (serverId) {
                this.markAsSeen(serverId);
            }

            return notification;
        }

        /**
         * Hide and remove a notification
         * @param {string} id Notification ID
         * @param {boolean} immediate If true, remove without animation (e.g. for maxNotifications)
         */
        hide(id, immediate = false) {
            const index = this.notifications.findIndex(n => n.id === id);
            if (index === -1) return;

            const { element } = this.notifications[index];
            this.notifications.splice(index, 1); // Remove from tracking array first

            if (immediate) {
                if (element.parentNode) {
                    element.parentNode.removeChild(element);
                }
                return;
            }

            element.classList.remove('show');
            element.classList.add('exit'); // Add exit animation class

            setTimeout(() => {
                if (element.parentNode) {
                    element.parentNode.removeChild(element);
                }
            }, this.options.animationDuration);
        }

        success(message, title = 'Success', duration) { return this.show({ type: 'success', title, message, duration }); }
        error(message, title = 'Error', duration) { return this.show({ type: 'error', title, message, duration }); }
        warning(message, title = 'Warning', duration) { return this.show({ type: 'warning', title, message, duration }); }
        info(message, title = 'Information', duration) { return this.show({ type: 'info', title, message, duration }); }

        markAsSeen(serverId) {
            const formData = new FormData();
            formData.append('popup_action', 'mark_seen');
            formData.append('notification_id', serverId);

            fetch(this.options.markSeenUrl, {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(response => {
                if (!response.ok) console.error('Failed to mark notification as seen on server.');
            })
            .catch(err => console.error('Error marking notification as seen:', err));
        }

        fetchFromServer() {
            const formData = new FormData();
            formData.append('popup_action', 'get');

            fetch(this.options.fetchUrl, {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(response => {
                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    return response.text().then(text => {
                        let errorMsg = 'Expected JSON response from notification server';
                        if (text.toLowerCase().includes('mongodb\\client') && text.toLowerCase().includes('not found')) {
                            errorMsg = 'MongoDB driver missing or misconfigured on server.';
                        } else if (text.length > 0 && text.length < 200) {
                             errorMsg += `. Got: ${text}`;
                        }
                        throw new Error(errorMsg);
                    });
                }
                if (!response.ok) throw new Error(`HTTP error! Status: ${response.status}`);
                return response.json();
            })
            .then(result => {
                this.fetchRetryCount = 0; // Reset on success
                this.fetchBackoffTime = 2000;

                if (result.status === 'success' && Array.isArray(result.data)) {
                    result.data.forEach(notification => {
                        // MongoDB returns _id as an object like {$oid: "actual_id_string"}
                        const serverNotificationId = notification._id && notification._id.$oid ? notification._id.$oid : notification._id;
                        
                        const exists = this.notifications.some(n => n.serverId === serverNotificationId);
                        if (!exists) {
                            this.show({
                                type: notification.type || 'info',
                                title: notification.title || this.getDefaultTitle(notification.type || 'info'),
                                message: notification.message,
                                duration: notification.duration, // Server can specify duration
                                id: serverNotificationId // Pass server ID for markAsSeen
                            });
                        }
                    });
                } else if (result.message && result.status !== 'success') {
                    // console.warn('Could not fetch notifications:', result.message);
                }
            })
            .catch(err => {
                console.error('Error fetching notifications:', err.message);
                this.fetchRetryCount++;
                
                if (err.message.includes('MongoDB driver missing') || err.message.includes('Expected JSON response')) {
                    if (this.fetchRetryCount >= 3) { // After 3 quick fails
                        // console.warn('Persistent issue fetching notifications. Pausing polling.');
                        if (this.fetchIntervalId) clearInterval(this.fetchIntervalId);
                        this.fetchIntervalId = null; // Stop polling
                        
                        // Optionally, try to check DB connection after a longer delay
                        setTimeout(() => this.checkDatabaseConnectionAndResume(), this.fetchBackoffTime * 5);
                        this.show({
                           type: 'warning',
                           title: 'System Alert',
                           message: 'Notification service is having trouble. Will attempt to reconnect.',
                           duration: 10000
                        });
                    } else {
                        // Exponential backoff for retries
                        this.fetchBackoffTime = Math.min(this.fetchBackoffTime * 1.5, 60000); // Max 1 min
                        if (this.fetchIntervalId) clearInterval(this.fetchIntervalId);
                        this.fetchIntervalId = setInterval(() => this.fetchFromServer(), this.fetchBackoffTime);
                    }
                }
            });
        }
        
        checkDatabaseConnectionAndResume() {
            const formData = new FormData();
            formData.append('action', 'check_connection'); // Ensure db-check.php handles this action

            fetch(this.options.dbCheckUrl, { // Use configured URL
                method: 'POST', // Or GET, depending on how db-check.php is set up
                body: formData, // If POST
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(response => response.json())
            .then(result => {
                if (result.status === 'connected') {
                    // console.log('Database connection restored. Resuming notification polling.');
                    this.fetchRetryCount = 0;
                    this.fetchBackoffTime = 2000;
                    if (this.fetchIntervalId) clearInterval(this.fetchIntervalId);
                    this.fetchIntervalId = setInterval(() => this.fetchFromServer(), this.options.fetchInterval);
                    this.fetchFromServer(); // Fetch immediately
                } else {
                    // console.warn('Database connection check failed. Will retry later.');
                    setTimeout(() => this.checkDatabaseConnectionAndResume(), this.fetchBackoffTime * 2); // Longer delay
                }
            })
            .catch(() => {
                // console.error('Failed to execute database connection check. Will retry later.');
                setTimeout(() => this.checkDatabaseConnectionAndResume(), this.fetchBackoffTime * 2);
            });
        }


        getDefaultTitle(type) {
            const titles = { success: 'Success!', error: 'Error!', warning: 'Warning!', info: 'Information' };
            return titles[type] || 'Notification';
        }

        getIconForType(type) {
            // SVGs are styled by global.css, ensure they use `currentColor` or CSS vars
            const icons = {
                success: `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="var(--success, currentColor)"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>`,
                error: `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="var(--error, currentColor)"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" /></svg>`,
                warning: `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="var(--warning, currentColor)"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" /></svg>`,
                info: `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="var(--info, currentColor)"><path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" /></svg>`
            };
            return icons[type] || icons.info;
        }
    }

    PopupNotification.initialized = false; // Static flag on the class

    // Assign to window
    window.PopupNotification = PopupNotification;

    // Auto-initialize global instance if not already done (e.g. by footer.php)
    // This ensures it's available even if footer script order changes or is missed.
    if (!window.popupNotification && document.readyState !== 'loading') {
        window.popupNotification = new PopupNotification();
    } else if (!window.popupNotification) {
        document.addEventListener('DOMContentLoaded', () => {
            if (!window.popupNotification) { // Double check
                window.popupNotification = new PopupNotification();
            }
        });
    }


    // Override native alert
    if (!window._alertOverridden) {
        window._alertOverridden = true;
        window.originalAlert = window.alert;
        window.alert = function(message) {
            if (window.popupNotification && window.popupNotification.initialized) {
                window.popupNotification.info(String(message), 'System Alert');
            } else {
                console.info("Fallback Alert (PopupNotification not ready):", message);
                // window.originalAlert(message); // Optionally call original alert
            }
        };
    }

})(); // End IIFE

// Global confirmNotification function (styles are in global.css)
window.confirmNotification = function(message, onConfirm, onCancel, options = {}) {
    const modalId = 'confirm-modal-' + Date.now();
    const backdrop = document.createElement('div');
    backdrop.className = 'popup-notification-modal-backdrop';
    backdrop.id = modalId;

    const modal = document.createElement('div');
    modal.className = 'popup-notification-modal-content'; // Will pick up glass styling from global.css if .glass is added there

    modal.innerHTML = `
        <div class="popup-notification-modal-header">
            <h3>${options.title || 'Confirm Action'}</h3>
            <button type="button" class="popup-notification-modal-close" aria-label="Close">×</button>
        </div>
        <div class="popup-notification-modal-body">
            ${message} <!-- Allow HTML in message -->
        </div>
        <div class="popup-notification-modal-footer">
            <button type="button" class="popup-notification-modal-btn popup-notification-modal-btn-cancel">${options.cancelText || 'Cancel'}</button>
            <button type="button" class="popup-notification-modal-btn popup-notification-modal-btn-confirm">${options.confirmText || 'Confirm'}</button>
        </div>
    `;

    backdrop.appendChild(modal);
    document.body.appendChild(backdrop);

    // Show with animation (CSS driven)
    requestAnimationFrame(() => {
        setTimeout(() => { // Ensures styles are applied for transition
            backdrop.classList.add('show');
            modal.classList.add('show');
        }, 10);
    });


    const close = (callback) => {
        backdrop.classList.remove('show');
        modal.classList.remove('show');
        setTimeout(() => {
            if (document.body.contains(backdrop)) {
                document.body.removeChild(backdrop);
            }
            document.removeEventListener('keydown', handleEscKey);
            if (typeof callback === 'function') callback();
        }, 300); // Match CSS animation duration
    };

    const handleEscKey = (e) => {
        if (e.key === 'Escape') {
            close(onCancel);
        }
    };

    modal.querySelector('.popup-notification-modal-btn-confirm').addEventListener('click', () => close(onConfirm));
    modal.querySelector('.popup-notification-modal-btn-cancel').addEventListener('click', () => close(onCancel));
    modal.querySelector('.popup-notification-modal-close').addEventListener('click', () => close(onCancel));
    
    backdrop.addEventListener('click', (e) => {
        if (e.target === backdrop) {
            close(onCancel);
        }
    });
    document.addEventListener('keydown', handleEscKey);

    return modalId;
};