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
                content.innerHTML = html;
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


