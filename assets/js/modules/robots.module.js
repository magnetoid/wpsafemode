/**
 * Robots Module
 * Handles robots.txt file management
 */

window.RobotsModule = class extends BaseModule {
    constructor() {
        super();
        this.settings = null;
        this.robotsContent = null;
    }
    
    async load(view, action) {
        try {
            this.showLoading(true);
            
            // Load robots view via API
            const response = await WPSafeMode.API.get('/api/view', {view: 'robots'});
            
            if (response.data && response.data.html) {
                this.updateContent(response.data.html);
            } else {
                this.render();
            }
            
            this.initHandlers();
        } catch (error) {
            console.error('Error loading robots:', error);
            this.showMessage('Error loading robots.txt settings: ' + error.message, 'alert');
        } finally {
            this.showLoading(false);
        }
    }
    
    render() {
        const html = `
            <div class="row">
                <div class="columns large-12">
                    <h2>robots.txt Management</h2>
                    <p>Loading robots.txt settings...</p>
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
                form.setAttribute('data-endpoint', '/api/submit?form=robots');
            }
        });
    }
    
    cleanup() {
        // Cleanup if needed
    }
};


