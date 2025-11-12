/**
 * Base Module Class
 * All modules should extend this class
 */

window.BaseModule = class {
    constructor() {
        this.data = null;
        this.view = null;
        this.initialized = false;
    }
    
    /**
     * Load module - override in subclasses
     */
    async load(view, action = null) {
        this.view = view;
        this.initialized = true;
    }
    
    /**
     * Render module - override in subclasses
     */
    render() {
        // Override in subclasses
    }
    
    /**
     * Initialize event handlers - override in subclasses
     */
    initHandlers() {
        // Override in subclasses
    }
    
    /**
     * Cleanup when leaving module
     */
    cleanup() {
        // Remove event listeners, clear intervals, etc.
        this.initialized = false;
    }
    
    /**
     * Show loading state
     */
    showLoading(show = true) {
        WPSafeMode.Utils.showLoading(show);
    }
    
    /**
     * Show message
     */
    showMessage(message, type = 'success') {
        WPSafeMode.Utils.showMessage(message, type);
    }
    
    /**
     * Escape HTML
     */
    escapeHtml(text) {
        if (text === null || text === undefined) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    /**
     * Update content area
     */
    updateContent(html) {
        const content = document.getElementById('main-content');
        if (content) {
            // Ensure navigation drawer is visible (not closed) - do this first
            const drawer = document.getElementById('navigation-drawer');
            const contentArea = document.querySelector('.md3-content');
            if (drawer) {
                if (drawer.classList.contains('closed')) {
                    drawer.classList.remove('closed');
                }
                // Ensure content area doesn't have full-width class when drawer is visible
                if (contentArea && contentArea.classList.contains('full-width') && window.innerWidth > 960) {
                    contentArea.classList.remove('full-width');
                }
            }
            
            content.style.opacity = '0';
            setTimeout(() => {
                // Find or create a content wrapper to preserve breadcrumb and messages
                let contentWrapper = content.querySelector('.view-content-wrapper');
                const breadcrumb = content.querySelector('nav[style*="margin-bottom: 24px"]');
                const messages = content.querySelector('.md3-snackbar');
                
                if (!contentWrapper) {
                    // Create wrapper if it doesn't exist
                    contentWrapper = document.createElement('div');
                    contentWrapper.className = 'view-content-wrapper';
                    
                    // Store breadcrumb and messages HTML if they exist
                    const breadcrumbHTML = breadcrumb ? breadcrumb.outerHTML : '';
                    const messagesHTML = messages ? messages.outerHTML : '';
                    
                    // Clear content and rebuild with wrapper
                    const existingHTML = content.innerHTML;
                    content.innerHTML = breadcrumbHTML + messagesHTML + '<div class="view-content-wrapper"></div>';
                    contentWrapper = content.querySelector('.view-content-wrapper');
                }
                
                // Update only the content wrapper, preserving breadcrumb and messages
                if (contentWrapper) {
                    contentWrapper.innerHTML = html;
                } else {
                    // Fallback: just update the content directly
                    content.innerHTML = html;
                }
                
                // Reinitialize AdminLTE components if available
                if (window.AdminLTE && window.AdminLTE.init) {
                    // AdminLTE auto-initializes, but we can trigger updates
                    $(content).find('[data-widget]').each(function() {
                        const widget = $(this).data('widget');
                        if (widget && AdminLTE[widget]) {
                            AdminLTE[widget].call($(this));
                        }
                    });
                }
                // Reinitialize Bootstrap tooltips and popovers
                if (window.bootstrap) {
                    $(content).find('[data-toggle="tooltip"]').tooltip();
                    $(content).find('[data-toggle="popover"]').popover();
                }
                content.style.opacity = '1';
            }, 200);
        }
    }
    
    /**
     * Make API request
     */
    async apiRequest(endpoint, options = {}) {
        return await WPSafeMode.API.request(endpoint, options);
    }
};


