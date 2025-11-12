/**
 * Cron Module
 * Manage WordPress cron jobs
 */
class CronModule extends BaseModule {
    
    async load(view, action) {
        await this.render();
        this.initHandlers();
    }
    
    async render() {
        const response = await this.apiRequest('/api/view?view=cron');
        if (response.success && response.data && response.data.html) {
            this.updateContent(response.data.html);
        }
    }
    
    initHandlers() {
        // Handlers are in the view's inline scripts
    }
}


