/**
 * System Health Module
 * Real-time system health monitoring
 */
class SystemHealthModule extends BaseModule {
    
    async load(view, action) {
        await this.render();
        this.initHandlers();
    }
    
    async render() {
        const response = await this.apiRequest('/api/view?view=system-health');
        if (response.success && response.data && response.data.html) {
            this.updateContent(response.data.html);
        }
    }
    
    initHandlers() {
        // Auto-refresh every 30 seconds
        if (this.refreshInterval) {
            clearInterval(this.refreshInterval);
        }
        this.refreshInterval = setInterval(() => {
            this.render();
        }, 30000);
    }
    
    cleanup() {
        if (this.refreshInterval) {
            clearInterval(this.refreshInterval);
        }
    }
}


