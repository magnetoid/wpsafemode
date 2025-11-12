/**
 * Backup Files Module
 * Handles file backup functionality
 */

window.BackupFilesModule = class extends BaseModule {
    constructor() {
        super();
        this.backups = [];
    }
    
    async load(view, action) {
        try {
            this.showLoading(true);
            
            // Load backups data
            const response = await WPSafeMode.API.get('/api/data', {type: 'backups', backup_type: 'files'});
            this.backups = response.data || [];
            
            this.render();
            this.initHandlers();
        } catch (error) {
            console.error('Error loading backup files:', error);
            this.showMessage('Error loading file backups: ' + error.message, 'alert');
        } finally {
            this.showLoading(false);
        }
    }
    
    render() {
        const html = `
            <div class="row">
                <div class="columns large-12">
                    <h2>File Backup</h2>
                </div>
            </div>
            
            <div class="row">
                <div class="columns large-12">
                    <form id="backup-files-form" data-ajax data-endpoint="/api/submit?form=backup_files">
                        <div class="panel">
                            <p>This will create a full backup of all WordPress files in a ZIP archive.</p>
                            <button type="submit" name="submit_backup_files" class="button success large">
                                Create Full File Backup
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="row">
                <div class="columns large-12">
                    <h3>Existing Backups</h3>
                    <div id="backups-list">
                        ${this.renderBackupsList()}
                    </div>
                </div>
            </div>
        `;
        
        this.updateContent(html);
    }
    
    renderBackupsList() {
        if (!this.backups || Object.keys(this.backups).length === 0) {
            return '<p>No backups found.</p>';
        }
        
        let html = '<ul class="no-bullet">';
        for (const [section, files] of Object.entries(this.backups)) {
            if (Array.isArray(files)) {
                files.forEach(file => {
                    const filename = file.split('/').pop();
                    html += `
                        <li>
                            <a href="?view=backup_files&action=download&download=sitefiles&filename=${encodeURIComponent(filename)}" 
                               class="button small">
                                Download: ${this.escapeHtml(filename)}
                            </a>
                        </li>
                    `;
                });
            }
        }
        html += '</ul>';
        
        return html;
    }
    
    initHandlers() {
        // Form submission is handled by UI manager via data-ajax attribute
    }
    
    cleanup() {
        // Cleanup if needed
    }
};


