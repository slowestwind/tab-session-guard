/**
 * Laravel Tab Session Guard - Client Side Library
 * Provides client-side tab tracking and management
 */

class LaravelTabGuard {
    constructor(config = {}) {
        this.config = {
            storageKey: 'laravel_tab_guard',
            useLocalStorage: true,
            useSessionStorage: true,
            heartbeatInterval: 30000, // 30 seconds
            maxRetries: 3,
            retryDelay: 1000,
            debug: false,
            apiEndpoints: {
                heartbeat: '/tab-guard/heartbeat',
                closeTab: '/tab-guard/close-tab',
                status: '/tab-guard/status'
            },
            ...config
        };

        this.tabId = null;
        this.userId = null;
        this.sessionId = null;
        this.heartbeatTimer = null;
        this.isActive = true;
        this.retryCount = 0;

        this.init();
    }

    /**
     * Initialize the tab guard
     */
    init() {
        this.log('Initializing TabGuard');
        
        this.generateTabId();
        this.extractUserInfo();
        this.registerTab();
        this.setupEventListeners();
        this.startHeartbeat();
        this.cleanupOldTabs();
    }

    /**
     * Generate unique tab ID
     */
    generateTabId() {
        this.tabId = 'tab_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
        this.log('Generated tab ID:', this.tabId);
    }

    /**
     * Extract user information from meta tags or global variables
     */
    extractUserInfo() {
        // Try to get from meta tags
        const userIdMeta = document.querySelector('meta[name="user-id"]');
        const sessionIdMeta = document.querySelector('meta[name="session-id"]');
        
        this.userId = userIdMeta ? userIdMeta.getAttribute('content') : 
                     (window.Laravel && window.Laravel.userId ? window.Laravel.userId : null);
        
        this.sessionId = sessionIdMeta ? sessionIdMeta.getAttribute('content') : 
                        (window.Laravel && window.Laravel.sessionId ? window.Laravel.sessionId : null);

        this.log('User ID:', this.userId, 'Session ID:', this.sessionId);
    }

    /**
     * Register this tab in storage
     */
    registerTab() {
        const tabData = {
            id: this.tabId,
            created: Date.now(),
            lastActivity: Date.now(),
            url: window.location.href,
            title: document.title,
            userId: this.userId,
            sessionId: this.sessionId,
            userAgent: navigator.userAgent
        };

        if (this.config.useLocalStorage) {
            this.updateLocalStorageTabs(tabData);
        }

        if (this.config.useSessionStorage) {
            this.updateSessionStorageTabs(tabData);
        }

        this.log('Tab registered:', tabData);
    }

    /**
     * Update localStorage tabs
     */
    updateLocalStorageTabs(tabData) {
        try {
            const tabs = this.getStoredTabs('localStorage') || {};
            tabs[this.tabId] = tabData;
            localStorage.setItem(this.config.storageKey, JSON.stringify(tabs));
        } catch (error) {
            this.log('Error updating localStorage:', error);
        }
    }

    /**
     * Update sessionStorage tabs
     */
    updateSessionStorageTabs(tabData) {
        try {
            const tabs = this.getStoredTabs('sessionStorage') || {};
            tabs[this.tabId] = tabData;
            sessionStorage.setItem(this.config.storageKey, JSON.stringify(tabs));
        } catch (error) {
            this.log('Error updating sessionStorage:', error);
        }
    }

    /**
     * Get stored tabs from specified storage
     */
    getStoredTabs(storageType) {
        try {
            const stored = window[storageType].getItem(this.config.storageKey);
            return stored ? JSON.parse(stored) : {};
        } catch (error) {
            this.log('Error getting stored tabs:', error);
            return {};
        }
    }

    /**
     * Update tab activity timestamp
     */
    updateActivity() {
        if (!this.isActive) return;

        const now = Date.now();

        if (this.config.useLocalStorage) {
            const tabs = this.getStoredTabs('localStorage') || {};
            if (tabs[this.tabId]) {
                tabs[this.tabId].lastActivity = now;
                tabs[this.tabId].url = window.location.href;
                tabs[this.tabId].title = document.title;
                localStorage.setItem(this.config.storageKey, JSON.stringify(tabs));
            }
        }

        if (this.config.useSessionStorage) {
            const tabs = this.getStoredTabs('sessionStorage') || {};
            if (tabs[this.tabId]) {
                tabs[this.tabId].lastActivity = now;
                tabs[this.tabId].url = window.location.href;
                tabs[this.tabId].title = document.title;
                sessionStorage.setItem(this.config.storageKey, JSON.stringify(tabs));
            }
        }

        this.log('Activity updated');
    }

    /**
     * Setup event listeners
     */
    setupEventListeners() {
        // Activity tracking
        const activityEvents = ['click', 'keypress', 'scroll', 'mousemove', 'touchstart'];
        activityEvents.forEach(event => {
            document.addEventListener(event, () => this.updateActivity(), { passive: true });
        });

        // Page visibility
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                this.handleTabHidden();
            } else {
                this.handleTabVisible();
            }
        });

        // Window focus/blur
        window.addEventListener('focus', () => {
            this.isActive = true;
            this.updateActivity();
            this.log('Tab focused');
        });

        window.addEventListener('blur', () => {
            this.isActive = false;
            this.log('Tab blurred');
        });

        // Before unload
        window.addEventListener('beforeunload', () => {
            this.handleTabClose();
        });

        // Page hide (better than beforeunload for modern browsers)
        window.addEventListener('pagehide', () => {
            this.handleTabClose();
        });

        // Handle browser back/forward
        window.addEventListener('popstate', () => {
            this.updateActivity();
        });
    }

    /**
     * Handle tab being hidden
     */
    handleTabHidden() {
        this.isActive = false;
        this.stopHeartbeat();
        this.log('Tab hidden');
    }

    /**
     * Handle tab becoming visible
     */
    handleTabVisible() {
        this.isActive = true;
        this.updateActivity();
        this.startHeartbeat();
        this.log('Tab visible');
    }

    /**
     * Handle tab close
     */
    handleTabClose() {
        this.log('Tab closing');
        
        // Send close notification
        this.sendCloseNotification();
        
        // Remove from storage
        this.removeTabFromStorage();
        
        // Stop heartbeat
        this.stopHeartbeat();
    }

    /**
     * Send close notification to server
     */
    sendCloseNotification() {
        if (!this.userId || !this.tabId) return;

        const data = {
            tabId: this.tabId,
            userId: this.userId,
            _token: this.getCsrfToken()
        };

        // Use sendBeacon for reliable delivery
        if (navigator.sendBeacon) {
            const blob = new Blob([JSON.stringify(data)], { type: 'application/json' });
            navigator.sendBeacon(this.config.apiEndpoints.closeTab, blob);
        } else {
            // Fallback to synchronous XHR
            try {
                const xhr = new XMLHttpRequest();
                xhr.open('POST', this.config.apiEndpoints.closeTab, false);
                xhr.setRequestHeader('Content-Type', 'application/json');
                xhr.setRequestHeader('X-CSRF-TOKEN', this.getCsrfToken());
                xhr.send(JSON.stringify(data));
            } catch (error) {
                this.log('Error sending close notification:', error);
            }
        }
    }

    /**
     * Remove tab from storage
     */
    removeTabFromStorage() {
        if (this.config.useLocalStorage) {
            try {
                const tabs = this.getStoredTabs('localStorage') || {};
                delete tabs[this.tabId];
                localStorage.setItem(this.config.storageKey, JSON.stringify(tabs));
            } catch (error) {
                this.log('Error removing from localStorage:', error);
            }
        }

        if (this.config.useSessionStorage) {
            try {
                const tabs = this.getStoredTabs('sessionStorage') || {};
                delete tabs[this.tabId];
                sessionStorage.setItem(this.config.storageKey, JSON.stringify(tabs));
            } catch (error) {
                this.log('Error removing from sessionStorage:', error);
            }
        }
    }

    /**
     * Start heartbeat timer
     */
    startHeartbeat() {
        if (this.heartbeatTimer) {
            clearInterval(this.heartbeatTimer);
        }

        this.heartbeatTimer = setInterval(() => {
            if (this.isActive) {
                this.sendHeartbeat();
            }
        }, this.config.heartbeatInterval);

        this.log('Heartbeat started');
    }

    /**
     * Stop heartbeat timer
     */
    stopHeartbeat() {
        if (this.heartbeatTimer) {
            clearInterval(this.heartbeatTimer);
            this.heartbeatTimer = null;
        }
        this.log('Heartbeat stopped');
    }

    /**
     * Send heartbeat to server
     */
    async sendHeartbeat() {
        if (!this.userId || !this.tabId) return;

        try {
            const response = await fetch(this.config.apiEndpoints.heartbeat, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': this.getCsrfToken(),
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    tabId: this.tabId,
                    userId: this.userId,
                    url: window.location.href,
                    title: document.title
                })
            });

            if (response.ok) {
                this.retryCount = 0;
                this.log('Heartbeat sent successfully');
            } else {
                throw new Error(`HTTP ${response.status}`);
            }
        } catch (error) {
            this.log('Heartbeat failed:', error);
            this.handleHeartbeatError();
        }
    }

    /**
     * Handle heartbeat error with retry logic
     */
    handleHeartbeatError() {
        this.retryCount++;
        
        if (this.retryCount <= this.config.maxRetries) {
            setTimeout(() => {
                this.sendHeartbeat();
            }, this.config.retryDelay * this.retryCount);
        } else {
            this.log('Max retries reached, stopping heartbeat');
            this.stopHeartbeat();
        }
    }

    /**
     * Clean up old tabs from storage
     */
    cleanupOldTabs() {
        const timeout = 30 * 60 * 1000; // 30 minutes
        const cutoff = Date.now() - timeout;

        ['localStorage', 'sessionStorage'].forEach(storageType => {
            if (!this.config[`use${storageType.charAt(0).toUpperCase() + storageType.slice(1)}`]) return;

            try {
                const tabs = this.getStoredTabs(storageType) || {};
                let hasChanges = false;

                Object.keys(tabs).forEach(tabId => {
                    if (tabs[tabId].lastActivity < cutoff) {
                        delete tabs[tabId];
                        hasChanges = true;
                    }
                });

                if (hasChanges) {
                    window[storageType].setItem(this.config.storageKey, JSON.stringify(tabs));
                    this.log(`Cleaned up old tabs from ${storageType}`);
                }
            } catch (error) {
                this.log(`Error cleaning up ${storageType}:`, error);
            }
        });

        // Schedule next cleanup
        setTimeout(() => this.cleanupOldTabs(), 5 * 60 * 1000); // Every 5 minutes
    }

    /**
     * Get CSRF token
     */
    getCsrfToken() {
        const tokenMeta = document.querySelector('meta[name="csrf-token"]');
        return tokenMeta ? tokenMeta.getAttribute('content') : '';
    }

    /**
     * Get current tab count
     */
    getTabCount() {
        let count = 0;
        
        if (this.config.useLocalStorage) {
            const localTabs = this.getStoredTabs('localStorage') || {};
            count = Math.max(count, Object.keys(localTabs).length);
        }
        
        if (this.config.useSessionStorage) {
            const sessionTabs = this.getStoredTabs('sessionStorage') || {};
            count = Math.max(count, Object.keys(sessionTabs).length);
        }
        
        return count;
    }

    /**
     * Get tab information
     */
    getTabInfo() {
        return {
            tabId: this.tabId,
            userId: this.userId,
            sessionId: this.sessionId,
            isActive: this.isActive,
            tabCount: this.getTabCount(),
            localStorageTabs: this.getStoredTabs('localStorage'),
            sessionStorageTabs: this.getStoredTabs('sessionStorage')
        };
    }

    /**
     * Logging utility
     */
    log(...args) {
        if (this.config.debug) {
            console.log('[TabGuard]', ...args);
        }
    }

    /**
     * Destroy the instance
     */
    destroy() {
        this.stopHeartbeat();
        this.removeTabFromStorage();
        this.isActive = false;
        this.log('TabGuard destroyed');
    }
}

// Auto-initialize if not in a module environment
if (typeof module === 'undefined' && typeof window !== 'undefined') {
    window.LaravelTabGuard = LaravelTabGuard;
    
    // Auto-initialize with default config
    document.addEventListener('DOMContentLoaded', () => {
        if (!window.tabGuardInstance) {
            window.tabGuardInstance = new LaravelTabGuard();
        }
    });
}

// Export for module environments
if (typeof module !== 'undefined' && module.exports) {
    module.exports = LaravelTabGuard;
}
