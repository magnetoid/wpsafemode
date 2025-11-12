/**
 * WP Config Module
 * Handles basic WordPress configuration
 */

window.WPConfigModule = class extends BaseModule {
    constructor() {
        super();
        this.config = null;
    }
    
    async load(view, action) {
        try {
            this.showLoading(true);
            
            // Load config data via view API
            const response = await WPSafeMode.API.get('/api/view', {view: 'wpconfig'});
            
            if (response.data && response.data.html) {
                this.updateContent(response.data.html);
            } else {
                this.render();
            }
            
            this.initHandlers();
        } catch (error) {
            console.error('Error loading WP Config:', error);
            this.showMessage('Error loading configuration: ' + error.message, 'alert');
        } finally {
            this.showLoading(false);
        }
    }
    
    render() {
        const html = `
            <div class="row">
                <div class="columns large-12">
                    <h2>WordPress Configuration</h2>
                    <p>Loading configuration...</p>
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
                form.setAttribute('data-endpoint', '/api/submit?form=wpconfig');
            }
        });
    }
    
    cleanup() {
        // Cleanup if needed
    }
};


