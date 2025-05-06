//billing/js/popup-notification.js`**
 
/**
 * Popup Notification System
 * Provides client-side functionality for displaying popup notifications
 */

class PopupNotification {
    constructor(options = {}) {
        this.options = {
            position: options.position || 'top-right', // top-right, top-left, bottom-right, bottom-left, top-center, bottom-center
            maxNotifications: options.maxNotifications || 5,
            animationDuration: options.animationDuration || 300,
            defaultDuration: options.defaultDuration || 5000,
            containerClass: options.containerClass || 'popup-notification-container',
            notificationClass: options.notificationClass || 'popup-notification',
            zIndex: options.zIndex || 9999,
            fetchFromServer: options.fetchFromServer !== false, // Default to true
            fetchInterval: options.fetchInterval || 30000 // 30 seconds
        };

        this.container = null;
        this.notifications = [];
        this.initialized = false;
        this.styleElement = null; // To hold reference to confirm modal style

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

        // Create notification container
        this.createContainer();

        // Styles are now in global.css, no need to add them here

        // Start fetching notifications from server if enabled
        if (this.options.fetchFromServer) {
            // Initial fetch with a small delay to ensure user context (if any) is set up
            setTimeout(() => this.fetchFromServer(), 1500);
            setInterval(() => this.fetchFromServer(), this.options.fetchInterval);
        }

        this.initialized = true;
    }

    /**
     * Create notification container
     */
    createContainer() {
        this.container = document.createElement('div');
        this.container.className = this.options.containerClass;
        this.container.setAttribute('aria-live', 'polite');

        // Set container position based on options
        switch (this.options.position) {
            case 'top-right':
                this.container.style.top = '20px';
                this.container.style.right = '20px';
                break;
            case 'top-left':
                this.container.style.top = '20px';
                this.container.style.left = '20px';
                break;
            case 'bottom-right':
                this.container.style.bottom = '20px';
                this.container.style.right = '20px';
                break;
            case 'bottom-left':
                this.container.style.bottom = '20px';
                this.container.style.left = '20px';
                break;
            case 'top-center':
                this.container.style.top = '20px';
                this.container.style.left = '50%';
                this.container.style.transform = 'translateX(-50%)';
                break;
            case 'bottom-center':
                this.container.style.bottom = '20px';
                this.container.style.left = '50%';
                this.container.style.transform = 'translateX(-50%)';
                break;
        }

        document.body.appendChild(this.container);
    }

    // addStyles() method removed as styles are in global.css

    /**
     * Create and display a notification
     *
     * @param {Object} options Notification options
     * @returns {HTMLElement} The notification element
     */
    show(options) {
        if (!this.container) {
            console.warn('PopupNotification container not initialized. Queuing notification.');
            setTimeout(() => this.show(options), 100);
            return null;
        }
        // Enforce max notifications limit
        while (this.notifications.length >= this.options.maxNotifications) {
            this.hide(this.notifications[0].id);
        }

        const notificationId = 'notification-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9);
        const type = options.type || 'info';
        const title = options.title || this.getDefaultTitle(type);
        const message = options.message || '';
        const duration = options.duration !== undefined ? options.duration : this.options.defaultDuration;
        const serverId = options.id || null; // Server-side ID

        // Create notification element
        const notification = document.createElement('div');
        notification.className = `${this.options.notificationClass} ${type}`;
        notification.id = notificationId;
        notification.setAttribute('role', 'alert');
        notification.setAttribute('aria-live', 'assertive');

        // Icon based on type
        const icon = this.getIconForType(type);

        // Create notification content
        notification.innerHTML = `
            <div class="notification-content">
                <div class="notification-icon">${icon}</div>
                <div class="notification-body">
                    <div class="notification-title">${title}</div>
                    <div class="notification-message">${message}</div>
                </div>
            </div>
            <button class="notification-close" aria-label="Close notification">✕</button>
            ${duration && duration > 0 ? `<div class="notification-progress"></div>` : ''}
        `;

        // Add to container
        this.container.appendChild(notification);
        this.notifications.push({ id: notificationId, element: notification, serverId });

        // Add event listeners
        const closeButton = notification.querySelector('.notification-close');
        if (closeButton) {
            closeButton.addEventListener('click', () => {
                this.hide(notificationId);
            });
        }


        // Apply animation
        setTimeout(() => notification.classList.add('show'), 10);

        // Set progress animation duration
        if (duration && duration > 0) {
            const progressBar = notification.querySelector('.notification-progress');
            if (progressBar) {
                 progressBar.style.animationDuration = `${duration}ms`;
            }

            // Auto-hide after duration
            setTimeout(() => {
                this.hide(notificationId);
            }, duration);
        }

        // Mark as seen in database if it came from server
        if (serverId) {
            this.markAsSeen(serverId);
        }

        return notification;
    }

    /**
     * Hide and remove a notification
     *
     * @param {string} id Notification ID
     */
    hide(id) {
        const index = this.notifications.findIndex(n => n.id === id);
        if (index === -1) return;

        const { element } = this.notifications[index];

        // Play exit animation
        element.classList.add('exit');
        element.classList.remove('show');

        // Remove after animation
        setTimeout(() => {
            if (element.parentNode) {
                element.parentNode.removeChild(element);
            }
            this.notifications.splice(index, 1);
        }, this.options.animationDuration);
    }

    success(message, title = 'Success', duration = undefined) {
        return this.show({ type: 'success', title, message, duration });
    }
    error(message, title = 'Error', duration = undefined) {
        return this.show({ type: 'error', title, message, duration });
    }
    warning(message, title = 'Warning', duration = undefined) {
        return this.show({ type: 'warning', title, message, duration });
    }
    info(message, title = 'Information', duration = undefined) {
        return this.show({ type: 'info', title, message, duration });
    }

    /**
     * Mark notification as seen on the server
     *
     * @param {string} serverId Server notification ID
     */
    markAsSeen(serverId) {
        const formData = new FormData();
        formData.append('popup_action', 'mark_seen');
        formData.append('notification_id', serverId);

        fetch('/billing/notification.php', {
            method: 'POST',
            body: formData
        }).catch(err => console.error('Error marking notification as seen:', err));
    }

    /**
     * Fetch notifications from server
     */
    fetchFromServer() {
        const formData = new FormData();
        formData.append('popup_action', 'get');

        fetch('/billing/notification.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(result => {
            if (result.status === 'success' && Array.isArray(result.data)) {
                result.data.forEach(notification => {
                    const serverNotificationId = notification._id.$oid || notification._id;
                    const exists = this.notifications.some(n => n.serverId === serverNotificationId);
                    if (!exists) {
                        this.show({
                            type: notification.type || 'info',
                            title: notification.title || this.getDefaultTitle(notification.type || 'info'),
                            message: notification.message,
                            duration: notification.duration !== null && notification.duration !== undefined ? notification.duration : this.options.defaultDuration,
                            id: serverNotificationId // Pass server ID
                        });
                    }
                });
            } else if (result.message) {
                // console.warn('Could not fetch notifications:', result.message);
            }
        })
        .catch(err => console.error('Error fetching notifications:', err));
    }

    getDefaultTitle(type) {
        switch (type) {
            case 'success': return 'Success';
            case 'error': return 'Error';
            case 'warning': return 'Warning';
            case 'info': return 'Information';
            default: return 'Notification';
        }
    }

    getIconForType(type) {
        // SVGs are now styled by global.css if needed, ensure they have proper stroke/fill
        switch (type) {
            case 'success':
                return `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="var(--success, #10b981)">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>`;
            case 'error':
                return `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="var(--error, #ef4444)">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                </svg>`;
            case 'warning':
                return `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="var(--warning, #f59e0b)">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                </svg>`;
            case 'info':
            default:
                return `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="var(--info, #3b82f6)">
                     <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
                </svg>`;
        }
    }
}

// Initialize global instance
window.popupNotification = new PopupNotification();

// Replace native alert with popup notification
window.originalAlert = window.alert;
window.alert = function(message) {
    if (window.popupNotification && window.popupNotification.initialized) {
        window.popupNotification.info(String(message), 'Alert');
    } else {
        // Fallback if popup system not ready
        console.info("Popup Alert:", message);
        // window.originalAlert(message); // Optionally call original alert
    }
};

// Helper function to show confirmation dialog
window.confirmNotification = function(message, onConfirm, onCancel, options = {}) {
    const modalId = 'confirm-modal-' + Date.now();
    const modalBackdrop = document.createElement('div');
    modalBackdrop.className = 'popup-notification-modal-backdrop'; // Use new class for backdrop
    modalBackdrop.id = modalId;

    const modalContent = document.createElement('div');
    modalContent.className = 'popup-notification-modal-content'; // Use new class for content

    modalContent.innerHTML = `
        <div class="popup-notification-modal-header">
            <h3>${options.title || 'Confirmation'}</h3>
            <button type="button" class="popup-notification-modal-close" aria-label="Close">&times;</button>
        </div>
        <div class="popup-notification-modal-body">
            <p>${message}</p> <!-- Wrap message in a p tag for better styling -->
        </div>
        <div class="popup-notification-modal-footer">
            <button type="button" class="popup-notification-modal-btn popup-notification-modal-btn-cancel">${options.cancelText || 'Cancel'}</button>
            <button type="button" class="popup-notification-modal-btn popup-notification-modal-btn-confirm">${options.confirmText || 'Confirm'}</button>
        </div>
    `;

    modalBackdrop.appendChild(modalContent);
    document.body.appendChild(modalBackdrop);

    // Styles for the modal are now in global.css

    // Show the modal
    setTimeout(() => {
        modalBackdrop.classList.add('show');
        modalContent.classList.add('show'); // Animate content too
    }, 10); // Small delay for transition

    // Handle events
    const handleClose = (callback) => {
        modalBackdrop.classList.remove('show');
        modalContent.classList.remove('show');
        setTimeout(() => {
            if (document.body.contains(modalBackdrop)) {
                document.body.removeChild(modalBackdrop);
            }
            if (typeof callback === 'function') {
                callback();
            }
        }, 300); // Match transition duration
    };

    const confirmButton = modalContent.querySelector('.popup-notification-modal-btn-confirm');
    const cancelButton = modalContent.querySelector('.popup-notification-modal-btn-cancel');
    const closeButton = modalContent.querySelector('.popup-notification-modal-close');

    confirmButton.addEventListener('click', () => handleClose(onConfirm));
    cancelButton.addEventListener('click', () => handleClose(onCancel));
    closeButton.addEventListener('click', () => handleClose(onCancel)); // Close button usually means cancel

    // Close on escape key
    const escapeListener = (event) => {
        if (event.key === 'Escape') {
            handleClose(onCancel);
            document.removeEventListener('keydown', escapeListener);
        }
    };
    document.addEventListener('keydown', escapeListener);

    // Close on backdrop click (optional)
    modalBackdrop.addEventListener('click', (event) => {
        if (event.target === modalBackdrop) {
            handleClose(onCancel);
            document.removeEventListener('keydown', escapeListener);
        }
    });
};
