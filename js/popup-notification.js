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
            fetchFromServer: options.fetchFromServer !== false,
            fetchInterval: options.fetchInterval || 30000 // 30 seconds
        };

        this.container = null;
        this.notifications = [];
        this.initialized = false;
        
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
        
        // Add styles
        this.addStyles();
        
        // Start fetching notifications from server if enabled
        if (this.options.fetchFromServer) {
            this.fetchFromServer();
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
    
    /**
     * Add notification styles to document
     */
    addStyles() {
        const style = document.createElement('style');
        style.textContent = `
            .${this.options.containerClass} {
                position: fixed;
                display: flex;
                flex-direction: column;
                gap: 10px;
                max-width: 350px;
                width: 100%;
                z-index: ${this.options.zIndex};
                pointer-events: none;
            }
            
            .${this.options.notificationClass} {
                background: var(--glass-bg, rgba(255, 255, 255, 0.75));
                backdrop-filter: blur(16px);
                -webkit-backdrop-filter: blur(16px);
                border-radius: 12px;
                border: 1px solid var(--glass-border, rgba(255, 255, 255, 0.4));
                box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.15);
                padding: 16px 20px;
                transition: all ${this.options.animationDuration}ms ease;
                pointer-events: auto;
                opacity: 0;
                transform: translateY(-20px);
                max-width: 100%;
                overflow: hidden;
                position: relative;
            }
            
            .${this.options.notificationClass}.show {
                opacity: 1;
                transform: translateY(0);
            }
            
            .${this.options.notificationClass}.exit {
                opacity: 0;
                transform: translateX(100%);
            }
            
            .${this.options.notificationClass} .notification-progress {
                position: absolute;
                bottom: 0;
                left: 0;
                height: 3px;
                background: linear-gradient(90deg, var(--primary, #4f46e5), var(--secondary, #06b6d4));
                width: 100%;
                transform-origin: left;
                animation-name: progress-animation;
                animation-timing-function: linear;
                animation-fill-mode: forwards;
            }
            
            .${this.options.notificationClass} .notification-content {
                display: flex;
                align-items: flex-start;
                gap: 12px;
            }
            
            .${this.options.notificationClass} .notification-icon {
                flex-shrink: 0;
                width: 24px;
                height: 24px;
            }
            
            .${this.options.notificationClass} .notification-body {
                flex-grow: 1;
            }
            
            .${this.options.notificationClass} .notification-title {
                font-weight: 600;
                font-size: 16px;
                margin-bottom: 5px;
                color: var(--text, #1e293b);
            }
            
            .${this.options.notificationClass} .notification-message {
                font-size: 14px;
                color: var(--text-light, #64748b);
                word-break: break-word;
            }
            
            .${this.options.notificationClass} .notification-close {
                position: absolute;
                top: 12px;
                right: 12px;
                width: 20px;
                height: 20px;
                display: flex;
                align-items: center;
                justify-content: center;
                cursor: pointer;
                background: rgba(0, 0, 0, 0.1);
                border-radius: 50%;
                transition: background 0.2s;
            }
            
            .${this.options.notificationClass} .notification-close:hover {
                background: rgba(0, 0, 0, 0.2);
            }
            
            .${this.options.notificationClass}.success {
                border-left: 4px solid var(--success, #10b981);
            }
            
            .${this.options.notificationClass}.error {
                border-left: 4px solid var(--error, #ef4444);
            }
            
            .${this.options.notificationClass}.warning {
                border-left: 4px solid var(--warning, #f59e0b);
            }
            
            .${this.options.notificationClass}.info {
                border-left: 4px solid var(--primary, #4f46e5);
            }
            
            @keyframes progress-animation {
                from { transform: scaleX(1); }
                to { transform: scaleX(0); }
            }
        `;
        
        document.head.appendChild(style);
    }
    
    /**
     * Create and display a notification
     * 
     * @param {Object} options Notification options
     * @returns {HTMLElement} The notification element
     */
    show(options) {
        // Enforce max notifications limit
        while (this.notifications.length >= this.options.maxNotifications) {
            this.hide(this.notifications[0].id);
        }
        
        const notificationId = 'notification-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9);
        const type = options.type || 'info';
        const title = options.title || this.getDefaultTitle(type);
        const message = options.message || '';
        const duration = options.duration !== undefined ? options.duration : this.options.defaultDuration;
        const serverId = options.id || null;
        
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
            ${duration ? `<div class="notification-progress"></div>` : ''}
        `;
        
        // Add to container
        this.container.appendChild(notification);
        this.notifications.push({ id: notificationId, element: notification, serverId });
        
        // Add event listeners
        notification.querySelector('.notification-close').addEventListener('click', () => {
            this.hide(notificationId);
        });
        
        // Apply animation
        setTimeout(() => notification.classList.add('show'), 10);
        
        // Set progress animation duration
        if (duration) {
            const progressBar = notification.querySelector('.notification-progress');
            progressBar.style.animationDuration = `${duration}ms`;
            
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
    
    /**
     * Show success notification
     * 
     * @param {string} message Notification message
     * @param {string} title Optional title
     * @param {number} duration Display duration in milliseconds
     * @returns {HTMLElement} The notification element
     */
    success(message, title = 'Success', duration = undefined) {
        return this.show({ type: 'success', title, message, duration });
    }
    
    /**
     * Show error notification
     * 
     * @param {string} message Notification message
     * @param {string} title Optional title
     * @param {number} duration Display duration in milliseconds
     * @returns {HTMLElement} The notification element
     */
    error(message, title = 'Error', duration = undefined) {
        return this.show({ type: 'error', title, message, duration });
    }
    
    /**
     * Show warning notification
     * 
     * @param {string} message Notification message
     * @param {string} title Optional title
     * @param {number} duration Display duration in milliseconds
     * @returns {HTMLElement} The notification element
     */
    warning(message, title = 'Warning', duration = undefined) {
        return this.show({ type: 'warning', title, message, duration });
    }
    
    /**
     * Show info notification
     * 
     * @param {string} message Notification message
     * @param {string} title Optional title
     * @param {number} duration Display duration in milliseconds
     * @returns {HTMLElement} The notification element
     */
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
        .then(response => response.json())
        .then(result => {
            if (result.status === 'success' && Array.isArray(result.data)) {
                result.data.forEach(notification => {
                    // Check if this notification was already displayed
                    const exists = this.notifications.some(n => n.serverId === (notification._id.$oid || notification._id));
                    if (!exists) {
                        this.show({
                            type: notification.type || 'info',
                            message: notification.message,
                            duration: notification.duration || this.options.defaultDuration,
                            id: notification._id.$oid || notification._id
                        });
                    }
                });
            }
        })
        .catch(err => console.error('Error fetching notifications:', err));
    }
    
    /**
     * Get default title based on notification type
     * 
     * @param {string} type Notification type
     * @returns {string} Default title
     */
    getDefaultTitle(type) {
        switch (type) {
            case 'success': return 'Success';
            case 'error': return 'Error';
            case 'warning': return 'Warning';
            case 'info': return 'Information';
            default: return 'Notification';
        }
    }
    
    /**
     * Get icon SVG based on notification type
     * 
     * @param {string} type Notification type
     * @returns {string} Icon SVG markup
     */
    getIconForType(type) {
        switch (type) {
            case 'success':
                return `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="#10b981">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>`;
                
            case 'error':
                return `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="#ef4444">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>`;
                
            case 'warning':
                return `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="#f59e0b">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>`;
                
            case 'info':
            default:
                return `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="#4f46e5">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>`;
        }
    }
}

// Initialize global instance
window.popupNotification = new PopupNotification();

// Replace native alert with popup notification
window.originalAlert = window.alert;
window.alert = function(message) {
    if (window.popupNotification) {
        window.popupNotification.info(message);
    } else {
        window.originalAlert(message);
    }
};

// Helper function to show confirmation dialog
window.confirmNotification = function(message, onConfirm, onCancel) {
    // Create a custom modal for confirmation
    const modalId = 'confirm-modal-' + Date.now();
    const modal = document.createElement('div');
    modal.className = 'popup-notification-modal';
    modal.id = modalId;
    modal.innerHTML = `
        <div class="popup-notification-modal-content">
            <div class="popup-notification-modal-header">
                <h3>Confirmation</h3>
                <button type="button" class="popup-notification-modal-close" aria-label="Close">&times;</button>
            </div>
            <div class="popup-notification-modal-body">
                ${message}
            </div>
            <div class="popup-notification-modal-footer">
                <button type="button" class="popup-notification-modal-btn popup-notification-modal-btn-cancel">Cancel</button>
                <button type="button" class="popup-notification-modal-btn popup-notification-modal-btn-confirm">Confirm</button>
            </div>
        </div>
    `;
    
    // Add styles for the modal
    const style = document.createElement('style');
    style.textContent = `
        .popup-notification-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(5px);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10000;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .popup-notification-modal.show {
            opacity: 1;
        }
        
        .popup-notification-modal-content {
            background: var(--glass-bg, rgba(255, 255, 255, 0.75));
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-radius: 12px;
            border: 1px solid var(--glass-border, rgba(255, 255, 255, 0.4));
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
            max-width: 400px;
            width: 100%;
            transform: translateY(-20px);
            transition: transform 0.3s ease;
        }
        
        .popup-notification-modal.show .popup-notification-modal-content {
            transform: translateY(0);
        }
        
        .popup-notification-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 20px;
            border-bottom: 1px solid var(--glass-border, rgba(255, 255, 255, 0.4));
        }
        
        .popup-notification-modal-header h3 {
            margin: 0;
            font-size: 18px;
            color: var(--text, #1e293b);
        }
        
        .popup-notification-modal-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: var(--text-light, #64748b);
        }
        
        .popup-notification-modal-body {
            padding: 20px;
            color: var(--text, #1e293b);
        }
        
        .popup-notification-modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            padding: 15px 20px;
            border-top: 1px solid var(--glass-border, rgba(255, 255, 255, 0.4));
        }
        
        .popup-notification-modal-btn {
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .popup-notification-modal-btn-cancel {
            background: var(--input-bg, rgba(255, 255, 255, 0.8));
            border: 1px solid var(--glass-border, rgba(255, 255, 255, 0.4));
            color: var(--text, #1e293b);
        }
        
        .popup-notification-modal-btn-confirm {
            background: linear-gradient(135deg, var(--primary, #4f46e5), var(--secondary, #06b6d4));
            border: none;
            color: white;
        }
    `;
    
    document.head.appendChild(style);
    document.body.appendChild(modal);
    
    // Show the modal
    setTimeout(() => modal.classList.add('show'), 10);
    
    // Handle events
    const handleClose = () => {
        modal.classList.remove('show');
        setTimeout(() => {
            document.body.removeChild(modal);
        }, 300);
    };
    
    const handleConfirm = () => {
        handleClose();
        if (typeof onConfirm === 'function') {
            onConfirm();
        }
    };
    
    const handleCancel = () => {
        handleClose();
        if (typeof onCancel === 'function') {
            onCancel();
        }
    };
    
    modal.querySelector('.popup-notification-modal-close').addEventListener('click', handleCancel);
    modal.querySelector('.popup-notification-modal-btn-cancel').addEventListener('click', handleCancel);
    modal.querySelector('.popup-notification-modal-btn-confirm').addEventListener('click', handleConfirm);
};
