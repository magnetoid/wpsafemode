/**
 * File Manager Module
 * Browse and manage WordPress files
 */
if (typeof window.FileManagerModule === 'undefined') {
    window.FileManagerModule = class extends BaseModule {
    
    async load(view, action) {
        const params = new URLSearchParams(window.location.search);
        const path = params.get('path') || '';
        await this.render(path);
        this.initHandlers();
    }
    
    async render(path = '') {
        const url = path ? `/api/view?view=file-manager&path=${encodeURIComponent(path)}` : '/api/view?view=file-manager';
        const response = await this.apiRequest(url);
        if (response.success && response.data && response.data.html) {
            this.updateContent(response.data.html);
        }
    }
    
    initHandlers() {
        // Handlers are in the view's inline scripts
    }
    };
}


