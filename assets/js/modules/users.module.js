/**
 * Users Module
 * Manage WordPress users
 */
if (typeof window.UsersModule === 'undefined') {
    window.UsersModule = class extends BaseModule {
    
    async load(view, action) {
        await this.render();
        this.initHandlers();
    }
    
    async render() {
        const response = await this.apiRequest('/api/view?view=users');
        if (response.success && response.data && response.data.html) {
            this.updateContent(response.data.html);
        }
    }
    
    initHandlers() {
        // Handlers are in the view's inline scripts
    }
    };
}


