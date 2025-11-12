/**
 * Plugins Module
 * Handles plugin management functionality
 */

window.PluginsModule = class extends BaseModule {
    constructor() {
        super();
        this.data = null;
    }
    
    async load(view, action) {
        try {
            // Load plugins data
            const response = await WPSafeMode.API.get('/api/data', {type: 'plugins'});
            this.data = response.data;
            
            // Render view
            await this.render();
            
            // Initialize event handlers
            this.initHandlers();
        } catch (error) {
            console.error('Error loading plugins:', error);
            WPSafeMode.Utils.showMessage('Error loading plugins: ' + error.message, 'alert');
        }
    }
    
    async render() {
        try {
            // Try to load admin view first
            const response = await WPSafeMode.API.get('/api/view', {view: 'plugins'});
            
            if (response.data && response.data.html) {
                this.updateContent(response.data.html);
                this.initHandlers();
            } else {
                // Fallback: render basic view
                this.renderBasic();
            }
        } catch (error) {
            console.error('Error rendering plugins:', error);
            this.renderBasic();
        }
    }
    
    renderBasic() {
        const html = `
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-plug mr-2"></i>Plugins Management</h3>
                        </div>
                        <div class="card-body">
                            <p>Loading plugins...</p>
                        </div>
                    </div>
                </div>
            </div>
        `;
        this.updateContent(html);
    }
    
    renderPluginsList() {
        if (!this.data || !this.data.all_plugins) {
            return '<p>No plugins found.</p>';
        }
        
        // Handle serialized PHP data
        let activePlugins = [];
        if (this.data.active_plugins && this.data.active_plugins.option_value) {
            try {
                // Try to parse as JSON first
                activePlugins = JSON.parse(this.data.active_plugins.option_value);
            } catch (e) {
                // If not JSON, might be PHP serialized - will need server-side handling
                // For now, try to extract from string
                activePlugins = [];
            }
        }
        
        let html = '<ul class="no-bullet">';
        for (const [pluginPath, pluginInfo] of Object.entries(this.data.all_plugins)) {
            const isActive = activePlugins.includes(pluginPath);
            html += `
                <li>
                    <label>
                        <input type="checkbox" name="plugins[]" value="${this.escapeHtml(pluginPath)}" ${isActive ? 'checked' : ''}>
                        <strong>${this.escapeHtml(pluginInfo.name || pluginPath)}</strong>
                        ${pluginInfo.version ? `<span class="label">v${this.escapeHtml(pluginInfo.version)}</span>` : ''}
                    </label>
                </li>
            `;
        }
        html += '</ul>';
        
        return html;
    }
    
    initHandlers() {
        // Select all / Deselect all
        document.getElementById('select-all-plugins')?.addEventListener('click', () => {
            document.querySelectorAll('#plugins-form input[type="checkbox"]').forEach(cb => cb.checked = true);
        });
        
        document.getElementById('deselect-all-plugins')?.addEventListener('click', () => {
            document.querySelectorAll('#plugins-form input[type="checkbox"]').forEach(cb => cb.checked = false);
        });
    }
    
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    cleanup() {
        // Cleanup if needed
    }
};

