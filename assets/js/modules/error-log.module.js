/**
 * Error Log Module
 * Handles PHP error log viewing
 */

window.ErrorLogModule = class extends BaseModule {
    constructor() {
        super();
        this.logData = null;
        this.currentPage = 1;
        this.linesPerPage = 20;
        this.searchTerm = '';
    }
    
    async load(view, action) {
        try {
            this.showLoading(true);
            
            // Get URL parameters
            const urlParams = new URLSearchParams(window.location.search);
            this.currentPage = parseInt(urlParams.get('page')) || 1;
            this.linesPerPage = parseInt(urlParams.get('lines')) || 20;
            this.searchTerm = urlParams.get('search') || '';
            
            // Load error log view via API
            const response = await WPSafeMode.API.get('/api/view', {
                view: 'error_log',
                page: this.currentPage,
                lines: this.linesPerPage,
                search: this.searchTerm
            });
            
            if (response.data && response.data.html) {
                this.updateContent(response.data.html);
            } else {
                this.render();
            }
            
            this.initHandlers();
        } catch (error) {
            console.error('Error loading error log:', error);
            this.showMessage('Error loading error log: ' + error.message, 'alert');
        } finally {
            this.showLoading(false);
        }
    }
    
    render() {
        const html = `
            <div class="row">
                <div class="columns large-12">
                    <h2>PHP Error Log</h2>
                    <p>Loading error log...</p>
                </div>
            </div>
        `;
        
        this.updateContent(html);
    }
    
    initHandlers() {
        // Add search functionality
        const searchForm = document.getElementById('error-log-search');
        if (searchForm) {
            searchForm.addEventListener('submit', (e) => {
                e.preventDefault();
                const searchInput = searchForm.querySelector('input[name="search"]');
                if (searchInput) {
                    this.searchTerm = searchInput.value;
                    this.currentPage = 1;
                    this.load('error_log');
                }
            });
        }
        
        // Pagination links
        document.querySelectorAll('a[data-page]').forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                this.currentPage = parseInt(link.getAttribute('data-page'));
                WPSafeMode.Router.navigate('error_log', {
                    page: this.currentPage,
                    lines: this.linesPerPage,
                    search: this.searchTerm
                });
            });
        });
    }
    
    cleanup() {
        // Cleanup if needed
    }
};


