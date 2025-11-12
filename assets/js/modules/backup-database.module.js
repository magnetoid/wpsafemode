/**
 * Backup Database Module
 * Handles database backup functionality
 */

window.BackupDatabaseModule = class extends BaseModule {
    constructor() {
        super();
        this.tables = [];
        this.backups = [];
    }
    
    async load(view, action) {
        try {
            this.showLoading(true);
            
            // Load tables and backups data
            const [tablesResponse, backupsResponse] = await Promise.all([
                WPSafeMode.API.get('/api/data', {type: 'tables'}),
                WPSafeMode.API.get('/api/data', {type: 'backups', backup_type: 'database'})
            ]);
            
            this.tables = tablesResponse.data || [];
            this.backups = backupsResponse.data || [];
            
            this.render();
            this.initHandlers();
        } catch (error) {
            console.error('Error loading backup database:', error);
            this.showMessage('Error loading backup database: ' + error.message, 'alert');
        } finally {
            this.showLoading(false);
        }
    }
    
    render() {
        const html = `
            <div class="row">
                <div class="columns large-12">
                    <h2>Database Backup</h2>
                </div>
            </div>
            
            <div class="row">
                <div class="columns large-12">
                    <form id="backup-database-form" data-ajax data-endpoint="/api/submit?form=backup_database">
                        <fieldset>
                            <legend>Backup Type</legend>
                            <label>
                                <input type="radio" name="backup_database_type" value="full" checked>
                                Full Database Backup
                            </label>
                            <label>
                                <input type="radio" name="backup_database_type" value="partial">
                                Partial Backup (Select Tables)
                            </label>
                        </fieldset>
                        
                        <div id="tables-selection" style="display: none;">
                            <h3>Select Tables</h3>
                            <div class="row">
                                <div class="columns large-12">
                                    <button type="button" class="button small" id="select-all-tables">Select All</button>
                                    <button type="button" class="button small" id="deselect-all-tables">Deselect All</button>
                                </div>
                            </div>
                            <div id="tables-list" class="tables-list">
                                ${this.renderTablesList()}
                            </div>
                            
                            <fieldset>
                                <legend>Export Format</legend>
                                <label>
                                    <input type="checkbox" name="backup_tables_type[]" value="sql" checked>
                                    SQL Format
                                </label>
                                <label>
                                    <input type="checkbox" name="backup_tables_type[]" value="csv">
                                    CSV Format
                                </label>
                            </fieldset>
                        </div>
                        
                        <fieldset>
                            <legend>Options</legend>
                            <label>
                                <input type="checkbox" name="backup_archive" value="1">
                                Archive backup in ZIP format
                            </label>
                        </fieldset>
                        
                        <button type="submit" name="submit_backup_database" class="button success">Create Backup</button>
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
    
    renderTablesList() {
        if (!this.tables || this.tables.length === 0) {
            return '<p>No tables found.</p>';
        }
        
        let html = '<ul class="no-bullet">';
        this.tables.forEach(table => {
            html += `
                <li>
                    <label>
                        <input type="checkbox" name="backup_tables_list[]" value="${this.escapeHtml(table)}">
                        ${this.escapeHtml(table)}
                    </label>
                </li>
            `;
        });
        html += '</ul>';
        
        return html;
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
                            <a href="?view=backup_database&action=download&download=database&filename=${encodeURIComponent(filename)}" 
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
        // Show/hide tables selection based on backup type
        const backupTypeRadios = document.querySelectorAll('input[name="backup_database_type"]');
        const tablesSelection = document.getElementById('tables-selection');
        
        backupTypeRadios.forEach(radio => {
            radio.addEventListener('change', () => {
                if (radio.value === 'partial' && radio.checked) {
                    tablesSelection.style.display = 'block';
                } else {
                    tablesSelection.style.display = 'none';
                }
            });
        });
        
        // Select all / Deselect all tables
        document.getElementById('select-all-tables')?.addEventListener('click', () => {
            document.querySelectorAll('#tables-list input[type="checkbox"]').forEach(cb => cb.checked = true);
        });
        
        document.getElementById('deselect-all-tables')?.addEventListener('click', () => {
            document.querySelectorAll('#tables-list input[type="checkbox"]').forEach(cb => cb.checked = false);
        });
    }
    
    cleanup() {
        // Cleanup if needed
    }
};


