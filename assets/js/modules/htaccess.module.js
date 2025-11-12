/**
 * Htaccess Module
 * Handles .htaccess file management
 */

window.HtaccessModule = class extends BaseModule {
    constructor() {
        super();
        this.settings = null;
        this.htaccessContent = null;
    }
    
    async load(view, action) {
        try {
            this.showLoading(true);
            
            // Load htaccess view via API
            const response = await WPSafeMode.API.get('/api/view', {view: 'htaccess'});
            
            if (response.data && response.data.html) {
                this.updateContent(response.data.html);
            } else {
                this.render();
            }
            
            this.initHandlers();
        } catch (error) {
            console.error('Error loading htaccess:', error);
            this.showMessage('Error loading .htaccess settings: ' + error.message, 'alert');
        } finally {
            this.showLoading(false);
        }
    }
    
    render() {
        const html = `
            <div class="row">
                <div class="columns large-12">
                    <h2>.htaccess Management</h2>
                    <p>Loading .htaccess settings...</p>
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
                form.setAttribute('data-endpoint', '/api/submit?form=htaccess');
            }
        });
        
        // Enhance action buttons
        const actionButtons = document.querySelectorAll('button[data-action], a[data-action]');
        actionButtons.forEach(button => {
            if (!button.hasAttribute('data-ajax')) {
                button.setAttribute('data-ajax', '');
            }
        });
    }
    
    cleanup() {
        // Cleanup if needed
    }
};


