/**
 * Info Module
 * Displays WordPress system information
 */

window.InfoModule = class extends BaseModule {
    async load(view, action) {
        try {
            this.showLoading(true);
            
            // Load info data
            const response = await WPSafeMode.API.get('/api/data', {type: 'info'});
            
            this.data = response.data;
            this.render();
            this.initHandlers();
        } catch (error) {
            console.error('Error loading info:', error);
            this.showMessage('Error loading information: ' + error.message, 'alert');
        } finally {
            this.showLoading(false);
        }
    }
    
    render() {
        // Try to load admin view first, fallback to API
        this.loadDetailedInfo();
    }
    
    async loadDetailedInfo() {
        try {
            // Load view via API (will return admin view if available)
            const response = await WPSafeMode.API.get('/api/view', {view: 'info'});
            
            if (response.data && response.data.html) {
                this.updateContent(response.data.html);
            } else if (this.data) {
                // Fallback: render from data
                this.renderInfo(this.data);
            }
        } catch (error) {
            console.error('Error loading detailed info:', error);
        }
    }
    
    renderInfo(data) {
        // Render core info
        const coreInfo = document.getElementById('core-info');
        if (coreInfo && data.core_info) {
            coreInfo.innerHTML = this.formatInfo(data.core_info);
        }
        
        // Render PHP info
        const phpInfo = document.getElementById('php-info');
        if (phpInfo && data.php_info) {
            phpInfo.innerHTML = this.formatInfo(data.php_info);
        }
        
        // Render plugins info
        const pluginsInfo = document.getElementById('plugins-info');
        if (pluginsInfo && data.plugins_info) {
            pluginsInfo.innerHTML = this.formatPluginsInfo(data.plugins_info);
        }
    }
    
    formatInfo(info) {
        if (!info || typeof info !== 'object') return '<p>No information available</p>';
        
        let html = '<ul class="no-bullet">';
        for (const [key, value] of Object.entries(info)) {
            html += `<li><strong>${this.escapeHtml(key)}:</strong> ${this.escapeHtml(value)}</li>`;
        }
        html += '</ul>';
        return html;
    }
    
    formatPluginsInfo(plugins) {
        if (!plugins || typeof plugins !== 'object') return '<p>No plugins found</p>';
        
        let html = '<ul class="no-bullet">';
        for (const [path, plugin] of Object.entries(plugins)) {
            html += `<li>
                <strong>${this.escapeHtml(plugin.name || path)}</strong>
                ${plugin.version ? `<span class="label">v${this.escapeHtml(plugin.version)}</span>` : ''}
            </li>`;
        }
        html += '</ul>';
        return html;
    }
    
    initHandlers() {
        // Add any event handlers here
    }
};

