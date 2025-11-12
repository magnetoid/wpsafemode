<?php
/**
 * Database Query View - Material Design 3
 * Execute custom SQL queries
 */
?>

<div class="md3-card">
    <h2 class="md3-card-title">Database Query Builder</h2>
    <p class="md3-card-subtitle">Execute custom SQL queries safely</p>
    
    <div style="margin-top: 24px;">
        <form id="query-form" onsubmit="executeQuery(event)">
            <div class="md3-text-field">
                <textarea id="sql-query" rows="10" style="width: 100%; font-family: 'Roboto Mono', monospace;" placeholder="SELECT * FROM wp_posts LIMIT 10;"></textarea>
                <label for="sql-query">SQL Query</label>
            </div>
            
            <div style="margin-top: 16px;">
                <button type="submit" class="md3-button md3-button-filled">
                    <span class="material-symbols-outlined" style="margin-right: 8px; vertical-align: middle;">play_arrow</span>
                    Execute Query
                </button>
                <button type="button" class="md3-button md3-button-outlined" onclick="clearQuery()" style="margin-left: 8px;">
                    <span class="material-symbols-outlined" style="margin-right: 8px; vertical-align: middle;">clear</span>
                    Clear
                </button>
            </div>
        </form>
    </div>
    
    <!-- Quick Queries -->
    <div class="md3-card" style="margin-top: 24px;">
        <h3 class="md3-card-title" style="font-size: 1.25rem;">Quick Queries</h3>
        <div style="display: flex; flex-wrap: wrap; gap: 8px;">
            <button class="md3-chip" onclick="setQuery('SELECT * FROM <?php echo htmlspecialchars($data['tables'][0] ?? 'wp_posts'); ?> LIMIT 10;')">List Posts</button>
            <button class="md3-chip" onclick="setQuery('SELECT COUNT(*) as total FROM <?php echo htmlspecialchars($data['tables'][0] ?? 'wp_posts'); ?>;')">Count Posts</button>
            <button class="md3-chip" onclick="setQuery('SHOW TABLES;')">Show Tables</button>
            <button class="md3-chip" onclick="setQuery('SELECT * FROM <?php echo htmlspecialchars($data['tables'][0] ?? 'wp_options'); ?> WHERE option_name LIKE \\'active_plugins\\';')">Active Plugins</button>
        </div>
    </div>
    
    <!-- Available Tables -->
    <?php if(isset($data['tables']) && !empty($data['tables'])): ?>
    <div class="md3-card" style="margin-top: 24px;">
        <h3 class="md3-card-title" style="font-size: 1.25rem;">Available Tables</h3>
        <div style="display: flex; flex-wrap: wrap; gap: 8px;">
            <?php foreach($data['tables'] as $table): ?>
            <button class="md3-chip" onclick="setQuery('SELECT * FROM <?php echo htmlspecialchars($table); ?> LIMIT 10;')"><?php echo htmlspecialchars($table); ?></button>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Results -->
    <div id="query-results" style="margin-top: 24px; display: none;">
        <div class="md3-card">
            <h3 class="md3-card-title" style="font-size: 1.25rem;">Query Results</h3>
            <div id="results-content"></div>
        </div>
    </div>
</div>

<script>
function executeQuery(event) {
    event.preventDefault();
    const query = document.getElementById('sql-query').value.trim();
    
    if (!query) {
        WPSafeMode.Utils.showMessage('Please enter a SQL query', 'error');
        return;
    }
    
    // Basic validation - only SELECT queries allowed for safety
    if (!query.toUpperCase().startsWith('SELECT') && !query.toUpperCase().startsWith('SHOW') && !query.toUpperCase().startsWith('DESCRIBE')) {
        if (!confirm('This query may modify data. Are you sure you want to execute it?')) {
            return;
        }
    }
    
    WPSafeMode.Utils.showLoading(true);
    
    // TODO: Implement via API
    WPSafeMode.Utils.showMessage('Query execution coming soon', 'info');
    WPSafeMode.Utils.showLoading(false);
}

function setQuery(query) {
    document.getElementById('sql-query').value = query;
}

function clearQuery() {
    document.getElementById('sql-query').value = '';
    document.getElementById('query-results').style.display = 'none';
}
</script>


