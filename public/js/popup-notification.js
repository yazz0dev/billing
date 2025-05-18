//billing/public/js/popup-notification.js

// Use an IIFE to prevent global namespace pollution and avoid duplicate class declarations
(function() {
    // Check if already defined to prevent duplicate declaration
    if (window.PopupNotification && window.PopupNotification.initialized) {
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
                zIndex: options.zIndex || 9999, 
                fetchFromServer: options.fetchFromServer !== undefined ? options.fetchFromServer : true,
                fetchInterval: options.fetchInterval || 30000, // 30 seconds
                fetchUrl: options.fetchUrl || '/api/notifications/fetch', // Updated
                markSeenUrl: options.markSeenUrl || '/api/notifications/mark-seen', // Updated
                // dbCheckUrl: options.dbCheckUrl || '/billing/db-check.php', // Commented out
            };

            // Add circuit breaker to prevent constant retries
            this.options.circuitBreaker = {
                failureThreshold: 3,
                resetTimeout: 30000,
                failureCount: 0,
                lastFailureTime: 0,
                isOpen: false,
                canRequest: function() {
                    if (!this.isOpen) return true;
                    
                    const now = Date.now();
                    if (now - this.lastFailureTime > this.resetTimeout) {
                        // Circuit half-open, allow a test request
                        this.isOpen = false;
                        return true;
                    }
                    return false;
                },
                recordFailure: function() {
                    this.failureCount++;
                    this.lastFailureTime = Date.now();
                    
                    if (this.failureCount >= this.failureThreshold) {
                        this.isOpen = true;
                    }
                },
                reset: function() {
                    this.failureCount = 0;
                    this.isOpen = false;
                }
            };

            this.container = null;
            this.notifications = []; // Stores { id, element, serverId }
            this.initialized = false;
            
            this.fetchRetryCount = 0;
            this.fetchBackoffTime = 2000; // Initial backoff time
            this.fetchIntervalId = null; // Store interval ID for clearing

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', () => this.init());
            } else {
                this.init();
            }
        }

        init() {
            if (this.initialized) return;
            this.createContainer();

            if (this.options.fetchFromServer) {
                setTimeout(() => this.fetchFromServer(), 1500); 
                if (this.fetchIntervalId) clearInterval(this.fetchIntervalId);
                this.fetchIntervalId = setInterval(() => this.fetchFromServer(), this.options.fetchInterval);
            }
            
            this.initialized = true;
            PopupNotification.initialized = true; // Static flag
        }

        createContainer() {
            if (document.querySelector('.' + this.options.containerClass)) {
                this.container = document.querySelector('.' + this.options.containerClass);
                return;
            }
            this.container = document.createElement('div');
            this.container.className = this.options.containerClass;
            this.container.setAttribute('aria-live', 'polite');
            this.container.style.position = 'fixed'; 
            this.container.style.zIndex = this.options.zIndex;

            const [posY, posX] = this.options.position.split('-');
            if (posY === 'top') this.container.style.top = '20px';
            if (posY === 'bottom') this.container.style.bottom = '20px';
            if (posX === 'left') this.container.style.left = '20px';
            if (posX === 'right') this.container.style.right = '20px';
            if (posX === 'center') {
                this.container.style.left = '50%';
                this.container.style.transform = 'translateX(-50%)';
            }
            if (posY === 'center' && posX === 'center') { 
                this.container.style.top = '50%';
                this.container.style.left = '50%';
                this.container.style.transform = 'translate(-50%, -50%)';
            }
            document.body.appendChild(this.container);
        }

        show(options) {
            if (!this.container) {
                setTimeout(() => this.show(options), 100);
                return null;
            }

            while (this.notifications.length >= this.options.maxNotifications) {
                const oldestNotification = this.notifications.shift();
                this.hide(oldestNotification.id, true); 
            }

            const notificationId = 'notif-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9);
            const type = options.type || 'info';
            const title = options.title || this.getDefaultTitle(type);
            const message = options.message || '';
            const duration = options.duration !== undefined ? options.duration : this.options.defaultDuration;
            const serverId = options.id || null; 

            const notification = document.createElement('div');
            notification.className = `${this.options.notificationClass} ${type}`;
            notification.id = notificationId;
            notification.setAttribute('role', 'alert');
            notification.setAttribute('aria-live', 'assertive'); 

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

            requestAnimationFrame(() => { 
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

        hide(id, immediate = false) {
            const index = this.notifications.findIndex(n => n.id === id);
            if (index === -1) return;

            const { element } = this.notifications[index];
            this.notifications.splice(index, 1); 

            if (immediate) {
                if (element.parentNode) {
                    element.parentNode.removeChild(element);
                }
                return;
            }

            element.classList.remove('show');
            element.classList.add('exit'); 

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
            fetch(this.options.markSeenUrl, {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    notification_id: serverId
                })
            })
            .then(response => {
                if (!response.ok) console.error('Failed to mark notification as seen on server.');
            })
            .catch(err => console.error('Error marking notification as seen:', err));
        }

        fetchFromServer() {
            // Use circuit breaker to prevent constant retries
            if (!this.options.circuitBreaker.canRequest()) {
                console.log("Circuit breaker open - skipping notification fetch");
                return;
            }

            fetch(this.options.fetchUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    popup_action: 'get'
                })
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
                // Reset circuit breaker on successful response
                this.options.circuitBreaker.reset();
                this.fetchRetryCount = 0; 
                this.fetchBackoffTime = 2000;

                if (result.status === 'success' && Array.isArray(result.data)) {
                    result.data.forEach(notification => {
                        const serverNotificationId = notification._id && notification._id.$oid ? notification._id.$oid : notification._id;
                        
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
                } else if (result.message && result.status !== 'success') {
                    // console.warn('Could not fetch notifications:', result.message);
                }
            })
            .catch(err => {
                console.error('Error fetching notifications:', err.message);
                // Record failure in circuit breaker
                this.options.circuitBreaker.recordFailure();
                this.fetchRetryCount++;
                
                if (err.message.includes('MongoDB driver missing') || err.message.includes('Expected JSON response')) {
                    if (this.fetchRetryCount >= 3) { 
                        if (this.fetchIntervalId) clearInterval(this.fetchIntervalId);
                        this.fetchIntervalId = null; 
                        
                        // Only show error once instead of constantly retrying
                        this.show({
                           type: 'warning',
                           title: 'System Alert',
                           message: 'Notification service is temporarily unavailable.',
                           duration: 10000
                        });
                    } else {
                        this.fetchBackoffTime = Math.min(this.fetchBackoffTime * 1.5, 60000); 
                        if (this.fetchIntervalId) clearInterval(this.fetchIntervalId);
                        // Use exponential backoff for retries
                        this.fetchIntervalId = setInterval(() => this.fetchFromServer(), this.fetchBackoffTime);
                    }
                }
            });
        }

        getDefaultTitle(type) {
            const titles = { success: 'Success!', error: 'Error!', warning: 'Warning!', info: 'Information' };
            return titles[type] || 'Notification';
        }

        getIconForType(type) {
            const icons = {
                success: `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="var(--success, currentColor)"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>`,
                error: `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="var(--error, currentColor)"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" /></svg>`,
                // Fix the warning icon viewBox
                warning: `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="var(--warning, currentColor)"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" /></svg>`,
                info: `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="var(--info, currentColor)"><path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" /></svg>`
            };
            return icons[type] || icons.info;
        }
    }

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

    if (!window._alertOverridden) {
        window._alertOverridden = true;
        window.originalAlert = window.alert;
        window.alert = function(message) {
            if (window.popupNotification && window.popupNotification.initialized) {
                window.popupNotification.info(String(message), 'System Alert');
            } else {
                console.info("Fallback Alert (PopupNotification not ready):", message);
            }
        };
    }

})(); 

window.confirmNotification = function(message, onConfirm, onCancel, options = {}) {
    const modalId = 'confirm-modal-' + Date.now();
    const backdrop = document.createElement('div');
    backdrop.className = 'popup-notification-modal-backdrop';
    backdrop.id = modalId;

    const modal = document.createElement('div');
    modal.className = 'popup-notification-modal-content'; 

    modal.innerHTML = `
        <div class="popup-notification-modal-header">
            <h3>${options.title || 'Confirm Action'}</h3>
            <button type="button" class="popup-notification-modal-close" aria-label="Close">×</button>
        </div>
        <div class="popup-notification-modal-body">
            ${message} 
        </div>
        <div class="popup-notification-modal-footer">
            <button type="button" class="popup-notification-modal-btn popup-notification-modal-btn-cancel">${options.cancelText || 'Cancel'}</button>
            <button type="button" class="popup-notification-modal-btn popup-notification-modal-btn-confirm">${options.confirmText || 'Confirm'}</button>
        </div>
    `;

    backdrop.appendChild(modal);
    document.body.appendChild(backdrop);

    requestAnimationFrame(() => {
        setTimeout(() => { 
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
        }, 300); 
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