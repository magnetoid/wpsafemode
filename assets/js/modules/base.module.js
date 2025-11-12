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
            content.style.opacity = '0';
            setTimeout(() => {
                // Ensure navigation drawer is visible (not closed)
                const drawer = document.getElementById('navigation-drawer');
                if (drawer && drawer.classList.contains('closed')) {
                    drawer.classList.remove('closed');
                }
                
                // Find or create a content wrapper to preserve breadcrumb and messages
                let contentWrapper = content.querySelector('.view-content-wrapper');
                if (!contentWrapper) {
                    // Create wrapper if it doesn't exist
                    contentWrapper = document.createElement('div');
                    contentWrapper.className = 'view-content-wrapper';
                    // Move existing content (except breadcrumb and messages) into wrapper
                    const breadcrumb = content.querySelector('nav[style*="margin-bottom: 24px"]');
                    const messages = content.querySelector('.md3-snackbar');
                    const children = Array.from(content.children);
                    children.forEach(child => {
                        if (child !== breadcrumb && child !== messages && child !== contentWrapper) {
                            contentWrapper.appendChild(child);
                        }
                    });
                    if (breadcrumb) {
                        content.insertBefore(breadcrumb, contentWrapper);
                    }
                    if (messages) {
                        content.insertBefore(messages, contentWrapper);
                    }
                    content.appendChild(contentWrapper);
                }
                // Update only the content wrapper, preserving breadcrumb and messages
                contentWrapper.innerHTML = html;
                
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


