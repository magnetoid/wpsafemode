/**
 * Themes Module
 * Handles theme management functionality
 */

window.ThemesModule = class extends BaseModule {
    constructor() {
        super();
        this.data = null;
        this.currentTheme = null;
    }
    
    async load(view, action) {
        try {
            this.showLoading(true);
            
            // Load themes data
            const response = await WPSafeMode.API.get('/api/data', {type: 'themes'});
            this.data = response.data;
            
            // Determine current theme
            if (this.data.active_theme) {
                for (const theme of this.data.active_theme) {
                    if (theme.option_name === 'stylesheet') {
                        this.currentTheme = theme.option_value;
                        break;
                    }
                }
            }
            
            this.render();
            this.initHandlers();
        } catch (error) {
            console.error('Error loading themes:', error);
            this.showMessage('Error loading themes: ' + error.message, 'alert');
        } finally {
            this.showLoading(false);
        }
    }
    
    render() {
        const html = `
            <div class="row" data-equalizer>
                <div class="large-4 columns widget" data-equalizer-watch>
                    <div class="dashboard-panel widget-title">
                        <h6 class="heading bold">Set Current Theme</h6>
                        <form id="themes-form" data-ajax data-endpoint="/api/submit?form=themes">
                            <ul id="themes-list">
                                ${this.renderThemesList()}
                            </ul>
                            <input type="submit" name="submit_themes" class="btn btn-blue" value="Save Current Theme"/>
                        </form>
                    </div>
                </div>
            </div>
        `;
        
        this.updateContent(html);
    }
    
    renderThemesList() {
        if (!this.data || !this.data.all_themes) {
            return '<li>No themes found.</li>';
        }
        
        let html = '';
        for (const [key, value] of Object.entries(this.data.all_themes)) {
            const checked = (key === this.currentTheme) ? 'checked' : '';
            const current = (key === this.currentTheme) ? ' (current theme)' : '';
            html += `
                <li>
                    <input type="radio" name="active_theme" value="${this.escapeHtml(key)}" ${checked}/>
                    ${this.escapeHtml(value.theme_name || key)}${current}
                </li>
            `;
        }
        
        // Add download option
        html += `
            <li>
                <input type="radio" name="active_theme" value="downloadsafe"/>
                Download Twenty Fifteen (this will download and activate clean theme from wordpress.org)
            </li>
        `;
        
        return html;
    }
    
    initHandlers() {
        // Form submission is handled by UI manager via data-ajax attribute
    }
    
    cleanup() {
        // Cleanup if needed
    }
};


