/**
 * Quick Actions Module
 * Handles quick action buttons
 */

window.QuickActionsModule = class extends BaseModule {
    constructor() {
        super();
        this.actions = [];
        this.siteData = null;
    }
    
    async load(view, action) {
        try {
            this.showLoading(true);
            
            // Load quick actions view via API
            const response = await WPSafeMode.API.get('/api/view', {view: 'quick_actions'});
            
            if (response.data && response.data.html) {
                this.updateContent(response.data.html);
            } else {
                this.render();
            }
            
            this.initHandlers();
        } catch (error) {
            console.error('Error loading quick actions:', error);
            this.showMessage('Error loading quick actions: ' + error.message, 'alert');
        } finally {
            this.showLoading(false);
        }
    }
    
    render() {
        const html = `
            <div class="row">
                <div class="columns large-12">
                    <h2>Quick Actions</h2>
                    <p>Loading quick actions...</p>
                </div>
            </div>
        `;
        
        this.updateContent(html);
    }
    
    initHandlers() {
        // Enhance action buttons
        const actionButtons = document.querySelectorAll('a.button, button.button');
        actionButtons.forEach(button => {
            const href = button.getAttribute('href');
            if (href && href.includes('action=')) {
                const match = href.match(/action=([^&]+)/);
                if (match) {
                    button.setAttribute('data-action', match[1]);
                    button.setAttribute('data-ajax', '');
                    button.addEventListener('click', (e) => {
                        e.preventDefault();
                        this.handleAction(match[1]);
                    });
                }
            }
        });
        
        // Enhance URL change form
        const urlForm = document.querySelector('form[action*="submit_site_url"]');
        if (urlForm && !urlForm.hasAttribute('data-ajax')) {
            urlForm.setAttribute('data-ajax', '');
            urlForm.setAttribute('data-endpoint', '/api/submit?form=site_url');
        }
    }
    
    async handleAction(action) {
        try {
            const response = await WPSafeMode.API.get('/api/action', {action: action});
            if (response.success) {
                this.showMessage(response.message || 'Action completed successfully', 'success');
                // Reload view to update state
                setTimeout(() => {
                    this.load('quick_actions');
                }, 1000);
            }
        } catch (error) {
            this.showMessage('Error executing action: ' + error.message, 'alert');
        }
    }
    
    cleanup() {
        // Cleanup if needed
    }
};


