/**
 * Global Settings Module
 * Handles global application settings
 */

window.GlobalSettingsModule = class extends BaseModule {
    constructor() {
        super();
        this.settings = null;
    }
    
    async load(view, action) {
        try {
            this.showLoading(true);
            
            // Load global settings view via API
            const response = await WPSafeMode.API.get('/api/view', {view: 'global_settings'});
            
            if (response.data && response.data.html) {
                this.updateContent(response.data.html);
            } else {
                this.render();
            }
            
            this.initHandlers();
        } catch (error) {
            console.error('Error loading global settings:', error);
            this.showMessage('Error loading global settings: ' + error.message, 'alert');
        } finally {
            this.showLoading(false);
        }
    }
    
    render() {
        const html = `
            <div class="row">
                <div class="columns large-12">
                    <h2>Global Settings</h2>
                    <p>Loading global settings...</p>
                </div>
            </div>
        `;
        
        this.updateContent(html);
    }
    
    initHandlers() {
        // Find and enhance forms
        const forms = document.querySelectorAll('form[method="post"]');
        forms.forEach(form => {
            if (!form.hasAttribute('data-ajax')) {
                form.setAttribute('data-ajax', '');
                form.setAttribute('data-endpoint', '/api/submit?form=global_settings');
            }
        });
    }
    
    cleanup() {
        // Cleanup if needed
    }
};


