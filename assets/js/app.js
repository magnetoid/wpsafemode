/**
 * WP Safe Mode - Modern JavaScript Application
 * Main application entry point and core framework
 */

(function() {
    'use strict';

    // Application namespace
    window.WPSafeMode = {
        config: {
            apiUrl: '',
            csrfToken: null,
            currentView: 'info',
            baseUrl: window.location.protocol + '//' + window.location.host + window.location.pathname
        },
        
        // Core modules
        Router: null,
        API: null,
        UI: null,
        Utils: null,
        
        // Initialize application
        init: function() {
            this.Utils = new Utils();
            this.API = new API();
            this.Router = new Router();
            this.UI = new UI();
            
            // Get CSRF token if available
            this.config.csrfToken = this.Utils.getCSRFToken();
            
            // Initialize router
            this.Router.init();
            
            // Initialize UI
            this.UI.init();
            
            // Handle initial route
            this.Router.handleRoute();
            
            console.log('WP Safe Mode Application Initialized');
        }
    };

    /**
     * Utility Functions
     */
    function Utils() {
        this.getCSRFToken = function() {
            const tokenField = document.querySelector('input[name="csrf_token"]');
            return tokenField ? tokenField.value : null;
        };
        
        this.generateCSRFToken = function() {
            return 'csrf_' + Math.random().toString(36).substr(2, 9) + Date.now().toString(36);
        };
        
        this.serializeForm = function(form) {
            const formData = new FormData(form);
            const data = {};
            for (let [key, value] of formData.entries()) {
                data[key] = value;
            }
            return data;
        };
        
        this.showMessage = function(message, type = 'success') {
            // Map types to Material Design 3 snackbar classes
            const snackbarTypeMap = {
                'success': 'success',
                'error': 'error',
                'alert': 'error',
                'warning': 'warning',
                'info': 'info'
            };
            const snackbarClass = snackbarTypeMap[type] || 'info';
            
            // Find message container
            let messageContainer = document.getElementById('main-content');
            if (!messageContainer) {
                messageContainer = document.querySelector('.md3-content');
                if (!messageContainer) {
                    messageContainer = document.body;
                }
            }
            
            // Create Material Design 3 snackbar
            const snackbar = document.createElement('div');
            snackbar.className = `md3-snackbar ${snackbarClass}`;
            snackbar.setAttribute('role', 'alert');
            
            // Get icon based on type
            const iconMap = {
                'success': 'check_circle',
                'error': 'error',
                'warning': 'warning',
                'info': 'info'
            };
            const icon = iconMap[snackbarClass] || 'info';
            
            snackbar.innerHTML = `
                <span class="material-symbols-outlined" style="margin-right: 12px; font-size: 20px;">${icon}</span>
                <span style="flex: 1;">${this.escapeHtml(message)}</span>
                <button onclick="this.parentElement.remove()" style="background: none; border: none; color: inherit; cursor: pointer; padding: 4px; margin-left: 8px;">
                    <span class="material-symbols-outlined" style="font-size: 20px;">close</span>
                </button>
            `;
            
            // Insert at the top
            if (messageContainer === document.body) {
                messageContainer.insertBefore(snackbar, messageContainer.firstChild);
            } else {
                messageContainer.insertBefore(snackbar, messageContainer.firstChild);
            }
            
            // Auto-hide after 5 seconds
            setTimeout(() => {
                if (snackbar.parentNode) {
                    snackbar.style.animation = 'slideOut 0.3s ease-in-out';
                    setTimeout(() => {
                        if (snackbar.parentNode) {
                            snackbar.remove();
                        }
                    }, 300);
                }
            }, 5000);
        };
        
        this.showLoading = function(show = true) {
            const loader = document.getElementById('app-loader');
            if (show) {
                if (!loader) {
                    const loaderEl = document.createElement('div');
                    loaderEl.id = 'app-loader';
                    loaderEl.className = 'app-loader';
                    loaderEl.innerHTML = '<div class="spinner"></div><p>Loading...</p>';
                    document.body.appendChild(loaderEl);
                    // Prevent body scroll on mobile
                    document.body.classList.add('loading-active');
                } else {
                    // Show if hidden
                    loader.style.display = 'flex';
                }
            } else {
                if (loader) {
                    loader.style.display = 'none';
                    // Allow body scroll again
                    document.body.classList.remove('loading-active');
                    // Remove after animation
                    setTimeout(() => {
                        if (loader && loader.parentNode) {
                            loader.remove();
                        }
                    }, 300);
                }
            }
        };
        
        this.updateURL = function(view, params = {}) {
            const query = new URLSearchParams(params).toString();
            const url = WPSafeMode.config.baseUrl + '?view=' + view + (query ? '&' + query : '');
            window.history.pushState({view: view, params: params}, '', url);
        };
        
        this.escapeHtml = function(text) {
            if (text === null || text === undefined) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        };
    }

    /**
     * API Client
     */
    function API() {
        // Get base URL - extract the wpsafemode directory path
        // e.g., /wpsm/ or / from window.location.pathname
        let basePath = window.location.pathname;
        // Remove trailing filename if present (but keep index.php if it's there)
        if (basePath.endsWith('.php')) {
            // If path ends with .php, remove just the filename
            basePath = basePath.replace(/\/[^\/]*\.php$/, '');
        } else {
            // Remove trailing filename
            basePath = basePath.replace(/\/[^\/]*$/, '');
        }
        // Ensure it ends with /
        if (!basePath.endsWith('/')) {
            basePath += '/';
        }
        // Use index.php for API calls to ensure proper routing
        this.baseUrl = basePath + 'index.php';
        
        this.request = async function(endpoint, options = {}) {
            const defaultOptions = {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            };
            
            // Add CSRF token if available
            if (WPSafeMode.config.csrfToken) {
                defaultOptions.headers['X-CSRF-Token'] = WPSafeMode.config.csrfToken;
            }
            
            const config = Object.assign({}, defaultOptions, options);
            
            // Handle FormData
            if (options.body instanceof FormData) {
                // Don't stringify FormData, let fetch handle it
                config.body = options.body;
                // Remove Content-Type header for FormData (browser will set it with boundary)
                delete config.headers['Content-Type'];
            } else if (options.body && typeof options.body === 'object') {
                config.body = JSON.stringify(options.body);
            }
            
            try {
                WPSafeMode.Utils.showLoading(true);
                const response = await fetch(this.baseUrl + endpoint, config);
                
                // Check if response is JSON
                const contentType = response.headers.get('content-type') || '';
                
                if (!contentType.includes('application/json')) {
                    const text = await response.text();
                    console.error('Non-JSON response:', text.substring(0, 500));
                    throw new Error('Server returned invalid response. Expected JSON but got: ' + (contentType || 'unknown'));
                }
                
                let data;
                try {
                    data = await response.json();
                } catch (jsonError) {
                    console.error('JSON parse error:', jsonError);
                    throw new Error('Invalid JSON response from server. Check console for details.');
                }
                
                if (!response.ok) {
                    throw new Error(data.message || data.error || 'Request failed');
                }
                
                return data;
            } catch (error) {
                console.error('API Error:', error);
                // Don't show message here, let the caller handle it
                throw error;
            } finally {
                WPSafeMode.Utils.showLoading(false);
            }
        };
        
        this.get = function(endpoint, params = {}) {
            // If endpoint starts with /api/, convert to query parameter format
            if (endpoint.startsWith('/api/')) {
                const endpointName = endpoint.replace('/api/', '');
                params.endpoint = endpointName;
                const query = new URLSearchParams(params).toString();
                return this.request('?' + query, {method: 'GET'});
            }
            const query = new URLSearchParams(params).toString();
            return this.request(endpoint + (query ? '?' + query : ''), {method: 'GET'});
        };
        
        this.post = function(endpoint, data = {}) {
            return this.request(endpoint, {
                method: 'POST',
                body: data
            });
        };
        
        this.put = function(endpoint, data = {}) {
            return this.request(endpoint, {
                method: 'PUT',
                body: data
            });
        };
        
        this.delete = function(endpoint) {
            return this.request(endpoint, {
                method: 'DELETE'
            });
        };
    }

    /**
     * Client-Side Router
     */
    function Router() {
        this.routes = {
            'info': {module: 'InfoModule', view: 'info'},
            'plugins': {module: 'PluginsModule', view: 'plugins'},
            'login': {module: 'LoginModule', view: 'login'},
            'themes': {module: 'ThemesModule', view: 'themes'},
            'wpconfig': {module: 'WPConfigModule', view: 'wpconfig'},
            'wpconfig_advanced': {module: 'WPConfigAdvancedModule', view: 'wpconfig_advanced'},
            'backup_database': {module: 'BackupDatabaseModule', view: 'backup_database'},
            'backup_files': {module: 'BackupFilesModule', view: 'backup_files'},
            'htaccess': {module: 'HtaccessModule', view: 'htaccess'},
            'robots': {module: 'RobotsModule', view: 'robots'},
            'error_log': {module: 'ErrorLogModule', view: 'error_log'},
            'autobackup': {module: 'AutobackupModule', view: 'autobackup'},
            'quick_actions': {module: 'QuickActionsModule', view: 'quick_actions'},
            'global_settings': {module: 'GlobalSettingsModule', view: 'global_settings'},
            'ai-assistant': {module: 'AIAssistantModule', view: 'ai-assistant'},
            'system-health': {module: 'SystemHealthModule', view: 'system-health'},
            'file-manager': {module: 'FileManagerModule', view: 'file-manager'},
            'users': {module: 'UsersModule', view: 'users'},
            'cron': {module: 'CronModule', view: 'cron'},
            'database-query': {module: 'DatabaseQueryModule', view: 'database-query'}
        };
        
        this.currentModule = null;
        
        this.init = function() {
            // Handle browser back/forward
            window.addEventListener('popstate', (e) => {
                this.handleRoute();
            });
            
            // Handle link clicks
            document.addEventListener('click', (e) => {
                const link = e.target.closest('a[data-view]');
                if (link) {
                    e.preventDefault();
                    const view = link.getAttribute('data-view');
                    this.navigate(view);
                }
            });
        };
        
        this.navigate = function(view, params = {}) {
            WPSafeMode.config.currentView = view;
            WPSafeMode.Utils.updateURL(view, params);
            this.handleRoute();
        };
        
        this.handleRoute = function() {
            const urlParams = new URLSearchParams(window.location.search);
            const view = urlParams.get('view') || 'info';
            const action = urlParams.get('action');
            
            WPSafeMode.config.currentView = view;
            
            // Clean up previous module
            if (this.currentModule && this.currentModule.cleanup) {
                this.currentModule.cleanup();
            }
            
            // Load new module
            const route = this.routes[view];
            if (route) {
                this.loadModule(route.module, view, action);
            } else {
                this.loadModule('InfoModule', 'info');
            }
        };
        
        this.loadModule = async function(moduleName, view, action = null) {
            try {
                // Try to load module first, fallback to API if not available
                const ModuleClass = window[moduleName];
                if (ModuleClass) {
                    this.currentModule = new ModuleClass();
                    await this.currentModule.load(view, action);
                } else {
                    // Fallback: load via API
                    await this.loadViewViaAPI(view, action);
                }
            } catch (error) {
                console.error('Error loading module:', error);
                // Fallback to API on error
                try {
                    await this.loadViewViaAPI(view, action);
                } catch (apiError) {
                    WPSafeMode.Utils.showMessage('Error loading page: ' + error.message, 'alert');
                }
            }
        };
        
        this.loadViewViaAPI = async function(view, action = null) {
            try {
                const response = await WPSafeMode.API.get('/api/view', {view: view, action: action});
                if (response.data && response.data.html) {
                    this.renderView(response.data.html);
                } else {
                    WPSafeMode.Utils.showMessage('View not found: ' + view, 'alert');
                }
            } catch (error) {
                console.error('Error loading view:', error);
            }
        };
        
        this.renderView = function(html) {
            const contentArea = document.getElementById('main-content');
            if (contentArea) {
                // Fade out
                contentArea.style.opacity = '0';
                setTimeout(() => {
                    contentArea.innerHTML = html;
                    // Reinitialize Material Design 3 components
                    if (typeof initMaterialComponents === 'function') {
                        initMaterialComponents();
                    }
                    // Fade in
                    contentArea.style.opacity = '1';
                }, 200);
            }
        };
    }

    /**
     * UI Manager
     */
    function UI() {
        this.init = function() {
            // Initialize Material Design 3 components
            if (typeof initMaterialComponents === 'function') {
                initMaterialComponents();
            }
            
            // Handle form submissions
            document.addEventListener('submit', (e) => {
                const form = e.target;
                if (form.hasAttribute('data-ajax')) {
                    e.preventDefault();
                    this.handleAjaxForm(form);
                }
            });
            
            // Handle Material Design 3 snackbar close buttons
            document.addEventListener('click', (e) => {
                const closeBtn = e.target.closest('.md3-snackbar button');
                if (closeBtn) {
                    const snackbar = closeBtn.closest('.md3-snackbar');
                    if (snackbar) {
                        e.preventDefault();
                        snackbar.style.animation = 'slideOut 0.3s ease-in-out';
                        setTimeout(() => {
                            if (snackbar.parentNode) {
                                snackbar.remove();
                            }
                        }, 300);
                    }
                }
            });
            
            // Add loading states to buttons
            this.initButtonLoading();
        };
        
        this.handleAjaxForm = async function(form) {
            const formData = new FormData(form);
            const submitButton = form.querySelector('button[type="submit"], input[type="submit"]');
            const originalText = submitButton ? submitButton.textContent : '';
            
            // Disable submit button
            if (submitButton) {
                submitButton.disabled = true;
                submitButton.textContent = 'Processing...';
            }
            
            try {
                const endpoint = form.getAttribute('data-endpoint') || '/api/submit';
                const response = await WPSafeMode.API.post(endpoint, formData);
                
                if (response.success) {
                    WPSafeMode.Utils.showMessage(response.message || 'Operation successful', 'success');
                    
                    // Handle redirect if specified
                    if (response.redirect) {
                        setTimeout(() => {
                            WPSafeMode.Router.navigate(response.redirect.view, response.redirect.params || {});
                        }, 1000);
                    } else {
                        // Reload current view
                        WPSafeMode.Router.handleRoute();
                    }
                } else {
                    WPSafeMode.Utils.showMessage(response.message || 'Operation failed', 'alert');
                }
            } catch (error) {
                WPSafeMode.Utils.showMessage('Error: ' + error.message, 'alert');
            } finally {
                if (submitButton) {
                    submitButton.disabled = false;
                    submitButton.textContent = originalText;
                }
            }
        };
        
        this.initButtonLoading = function() {
            document.addEventListener('click', async (e) => {
                const button = e.target.closest('button[data-action], a[data-action]');
                if (button && button.hasAttribute('data-ajax')) {
                    e.preventDefault();
                    const action = button.getAttribute('data-action');
                    const originalText = button.textContent;
                    
                    button.disabled = true;
                    button.textContent = 'Loading...';
                    
                    try {
                        const response = await WPSafeMode.API.get('/api/action', {action: action});
                        if (response.success) {
                            WPSafeMode.Utils.showMessage(response.message || 'Action completed', 'success');
                            WPSafeMode.Router.handleRoute();
                        }
                    } catch (error) {
                        WPSafeMode.Utils.showMessage('Error: ' + error.message, 'alert');
                    } finally {
                        button.disabled = false;
                        button.textContent = originalText;
                    }
                }
            });
        };
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            WPSafeMode.init();
        });
    } else {
        WPSafeMode.init();
    }

})();

