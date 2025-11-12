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
            // Map types to Bootstrap alert classes
            const alertTypeMap = {
                'success': 'success',
                'alert': 'danger',
                'error': 'danger',
                'warning': 'warning',
                'info': 'info'
            };
            const alertClass = alertTypeMap[type] || 'info';
            
            // Find message container
            let messageContainer = document.getElementById('main-content');
            if (!messageContainer) {
                messageContainer = document.querySelector('.content');
                if (!messageContainer) {
                    messageContainer = document.body;
                }
            }
            
            // Create Bootstrap alert
            const alertBox = document.createElement('div');
            alertBox.className = `alert alert-${alertClass} alert-dismissible fade show`;
            alertBox.setAttribute('role', 'alert');
            alertBox.innerHTML = `
                ${message}
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            `;
            
            // Insert at the top
            if (messageContainer === document.body) {
                messageContainer.insertBefore(alertBox, messageContainer.firstChild);
            } else {
                messageContainer.insertBefore(alertBox, messageContainer.firstChild);
            }
            
            // Initialize Bootstrap dismiss
            if (window.jQuery) {
                $(alertBox).find('[data-dismiss="alert"]').on('click', function() {
                    $(alertBox).fadeOut(function() {
                        $(this).remove();
                    });
                });
            }
            
            // Auto-hide after 5 seconds
            setTimeout(() => {
                if (alertBox.parentNode) {
                    if (window.jQuery) {
                        $(alertBox).fadeOut(function() {
                            $(this).remove();
                        });
                    } else {
                        alertBox.remove();
                    }
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
                }
            } else {
                if (loader) {
                    loader.remove();
                }
            }
        };
        
        this.updateURL = function(view, params = {}) {
            const query = new URLSearchParams(params).toString();
            const url = WPSafeMode.config.baseUrl + '?view=' + view + (query ? '&' + query : '');
            window.history.pushState({view: view, params: params}, '', url);
        };
    }

    /**
     * API Client
     */
    function API() {
        // Get base URL without query string
        const basePath = window.location.pathname.replace(/\/[^\/]*$/, '') || '';
        this.baseUrl = basePath;
        
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
                const data = await response.json();
                
                if (!response.ok) {
                    throw new Error(data.message || 'Request failed');
                }
                
                return data;
            } catch (error) {
                console.error('API Error:', error);
                WPSafeMode.Utils.showMessage('Error: ' + error.message, 'alert');
                throw error;
            } finally {
                WPSafeMode.Utils.showLoading(false);
            }
        };
        
        this.get = function(endpoint, params = {}) {
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
            'global_settings': {module: 'GlobalSettingsModule', view: 'global_settings'}
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
                // Load module dynamically - moduleName is already the full class name
                const ModuleClass = window[moduleName];
                if (ModuleClass) {
                    this.currentModule = new ModuleClass();
                    await this.currentModule.load(view, action);
                } else {
                    // Fallback: load via API
                    console.warn('Module not found:', moduleName, '- loading via API');
                    await this.loadViewViaAPI(view, action);
                }
            } catch (error) {
                console.error('Error loading module:', error);
                WPSafeMode.Utils.showMessage('Error loading page: ' + error.message, 'alert');
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
                    // Reinitialize AdminLTE components if available
                    if (window.AdminLTE && window.AdminLTE.init) {
                        $(contentArea).find('[data-widget]').each(function() {
                            const widget = $(this).data('widget');
                            if (widget && AdminLTE[widget]) {
                                AdminLTE[widget].call($(this));
                            }
                        });
                    }
                    // Reinitialize Bootstrap tooltips and popovers
                    if (window.bootstrap || window.jQuery) {
                        $(contentArea).find('[data-toggle="tooltip"]').tooltip();
                        $(contentArea).find('[data-toggle="popover"]').popover();
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
            // Initialize AdminLTE/Bootstrap components
            if (window.AdminLTE) {
                // AdminLTE auto-initializes
            }
            // Bootstrap tooltips and popovers will be initialized as needed
            
            // Handle form submissions
            document.addEventListener('submit', (e) => {
                const form = e.target;
                if (form.hasAttribute('data-ajax')) {
                    e.preventDefault();
                    this.handleAjaxForm(form);
                }
            });
            
            // Handle Bootstrap alert close buttons
            document.addEventListener('click', (e) => {
                if (e.target.classList.contains('close') || e.target.closest('.close')) {
                    const closeBtn = e.target.classList.contains('close') ? e.target : e.target.closest('.close');
                    const alert = closeBtn.closest('.alert');
                    if (alert) {
                        e.preventDefault();
                        if (window.jQuery) {
                            $(alert).fadeOut(function() {
                                $(this).remove();
                            });
                        } else {
                            alert.remove();
                        }
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

