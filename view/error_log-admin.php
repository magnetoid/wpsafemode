<?php
/**
 * Error Log View - Premium Safe Mode Design with AI
 */
?>

<!-- Stats Grid -->
<div class="stats-grid">
    <?php if (isset($data['stats']) && $data['stats']['exists']): ?>
        <div class="stat-card">
            <div class="stat-icon">
                <span class="material-symbols-outlined">storage</span>
            </div>
            <div class="stat-content">
                <div class="stat-value"><?php echo DashboardHelpers::format_size($data['stats']['total_size']); ?></div>
                <div class="stat-label">Total Size</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon" style="background-color: rgba(124, 77, 255, 0.1); color: var(--color-secondary);">
                <span class="material-symbols-outlined">error</span>
            </div>
            <div class="stat-content">
                <div class="stat-value"><?php echo number_format($data['stats']['total_lines']); ?></div>
                <div class="stat-label">Total Entries</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon" style="background-color: rgba(0, 230, 118, 0.1); color: var(--color-success);">
                <span class="material-symbols-outlined">code</span>
            </div>
            <div class="stat-content">
                <div class="stat-value"><?php echo number_format($data['stats']['php_log']['lines']); ?></div>
                <div class="stat-label">WordPress Errors</div>
            </div>
        </div>

        <?php if ($data['stats']['last_modified']): ?>
            <div class="stat-card">
                <div class="stat-icon" style="background-color: rgba(255, 23, 68, 0.1); color: var(--color-danger);">
                    <span class="material-symbols-outlined">schedule</span>
                </div>
                <div class="stat-content">
                    <div class="stat-value" style="font-size: 1rem;">
                        <?php echo date('H:i:s', $data['stats']['last_modified']); ?></div>
                    <div class="stat-label">Last Error</div>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- AI Analysis Section -->
<?php if (isset($data['ai_analysis'])): ?>
    <div class="card"
        style="background: linear-gradient(135deg, rgba(124, 77, 255, 0.1) 0%, rgba(0, 229, 255, 0.05) 100%); border-color: var(--color-secondary);">
        <div class="card-header" style="border-bottom-color: var(--color-secondary);">
            <h3 class="card-title"
                style="color: var(--color-secondary); display: flex; align-items: center; gap: var(--space-sm);">
                <span class="material-symbols-outlined">psychology</span>
                AI Analysis
            </h3>
            <div style="display: flex; gap: var(--space-md); align-items: center;">
                <span class="text-muted" style="font-size: var(--font-size-sm);">
                    <?php echo date('Y-m-d H:i:s', $data['ai_analysis_timestamp']); ?>
                </span>
                <a href="?view=error_log" class="btn btn-outline" style="padding: var(--space-xs) var(--space-sm);">
                    <span class="material-symbols-outlined">close</span>
                </a>
            </div>
        </div>
        <div style="line-height: 1.8;">
            <?php
            // Simple markdown-like formatting for AI response
            $analysis = htmlspecialchars($data['ai_analysis']);
            // Convert numbered lists
            $analysis = preg_replace('/^(\d+)\.\s+(.+)$/m', '<div style="margin: var(--space-sm) 0;"><strong>$1.</strong> $2</div>', $analysis);
            // Convert headers (lines ending with :)
            $analysis = preg_replace('/^([A-Z][^:]+):$/m', '<h4 style="color: var(--color-secondary); margin: var(--space-md) 0 var(--space-sm) 0;">$1</h4>', $analysis);
            // Convert bold (**text**)
            $analysis = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $analysis);
            // Convert code blocks (`code`)
            $analysis = preg_replace('/`([^`]+)`/', '<code style="background-color: var(--color-bg-surface-hover); padding: 2px 6px; border-radius: var(--radius-sm); font-family: monospace; color: var(--color-primary);">$1</code>', $analysis);
            // Convert line breaks
            $analysis = nl2br($analysis);
            echo $analysis;
            ?>
        </div>
    </div>
<?php endif; ?>

<!-- Actions Card -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            <span class="material-symbols-outlined" style="vertical-align: middle; margin-right: 8px;">error</span>
            Error Log Management
        </h3>
        <div style="display: flex; gap: var(--space-sm); align-items: center; flex-wrap: wrap;">
            <!-- AI Analyze Button -->
            <?php if (isset($data['ai_configured']) && $data['ai_configured']): ?>
                <a href="?view=error_log&action=ai_analyze_error_log" class="btn"
                    style="background: linear-gradient(135deg, #7c4dff 0%, #00e5ff 100%); color: white; border: none;"
                    onclick="return confirm('Analyze recent errors with AI? This may take a moment.');">
                    <span class="material-symbols-outlined">psychology</span>
                    AI Analyze
                </a>
            <?php endif; ?>

            <!-- Enable/Disable Toggle -->
            <?php if ($data['stats']['enabled']): ?>
                <span class="badge badge-success"
                    style="padding: var(--space-sm) var(--space-md); font-size: var(--font-size-sm);">
                    <span class="material-symbols-outlined"
                        style="font-size: 14px; vertical-align: middle; margin-right: 4px;">check_circle</span>
                    Logging Active
                </span>
                <a href="?view=error_log&action=disable_error_log" class="btn btn-outline"
                    onclick="return confirm('Are you sure you want to disable error logging?');">
                    <span class="material-symbols-outlined">pause_circle</span>
                    Disable
                </a>
            <?php else: ?>
                <span class="badge badge-warning"
                    style="padding: var(--space-sm) var(--space-md); font-size: var(--font-size-sm);">
                    <span class="material-symbols-outlined"
                        style="font-size: 14px; vertical-align: middle; margin-right: 4px;">pause_circle</span>
                    Logging Inactive
                </span>
                <a href="?view=error_log&action=enable_error_log" class="btn btn-primary">
                    <span class="material-symbols-outlined">play_circle</span>
                    Enable
                </a>
            <?php endif; ?>

            <a href="?view=error_log&action=archive_error_log" class="btn btn-outline"
                onclick="return confirm('Are you sure you want to archive the current error logs?');">
                <span class="material-symbols-outlined">archive</span>
                Archive
            </a>
            <a href="?view=error_log&action=download_error_log" class="btn btn-outline">
                <span class="material-symbols-outlined">download</span>
                Download
            </a>
            <a href="?view=error_log&action=clear_error_log" class="btn"
                style="background-color: var(--color-danger); color: white; border: none;"
                onclick="return confirm('Are you sure you want to clear all error logs? This action cannot be undone.');">
                <span class="material-symbols-outlined">delete</span>
                Clear Logs
            </a>
        </div>
    </div>

    <!-- Filters -->
    <div
        style="background-color: var(--color-bg-surface-hover); padding: var(--space-md); border-radius: var(--radius-md); margin-bottom: var(--space-md);">
        <form action="" method="get"
            style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: var(--space-md); align-items: end;">
            <input type="hidden" name="view" value="error_log">

            <div>
                <label for="search"
                    style="display: block; margin-bottom: var(--space-xs); font-size: var(--font-size-sm); color: var(--color-text-muted);">Search</label>
                <input type="text" name="search" id="search"
                    value="<?php echo htmlspecialchars($data['results']['search'] ?? ''); ?>"
                    placeholder="Search errors..."
                    style="width: 100%; padding: var(--space-sm); background-color: var(--color-bg-surface); border: 1px solid var(--color-border); border-radius: var(--radius-md); color: var(--color-text-main);">
            </div>

            <div>
                <label for="severity"
                    style="display: block; margin-bottom: var(--space-xs); font-size: var(--font-size-sm); color: var(--color-text-muted);">Severity</label>
                <select name="severity" id="severity"
                    style="width: 100%; padding: var(--space-sm); background-color: var(--color-bg-surface); border: 1px solid var(--color-border); border-radius: var(--radius-md); color: var(--color-text-main);">
                    <option value="">All Severities</option>
                    <option value="CRITICAL" <?php echo ($data['results']['severity'] ?? '') === 'CRITICAL' ? 'selected' : ''; ?>>Critical</option>
                    <option value="ERROR" <?php echo ($data['results']['severity'] ?? '') === 'ERROR' ? 'selected' : ''; ?>>Error</option>
                    <option value="WARNING" <?php echo ($data['results']['severity'] ?? '') === 'WARNING' ? 'selected' : ''; ?>>Warning</option>
                    <option value="INFO" <?php echo ($data['results']['severity'] ?? '') === 'INFO' ? 'selected' : ''; ?>>
                        Info</option>
                </select>
            </div>

            <div>
                <label for="source"
                    style="display: block; margin-bottom: var(--space-xs); font-size: var(--font-size-sm); color: var(--color-text-muted);">Source</label>
                <select name="source" id="source"
                    style="width: 100%; padding: var(--space-sm); background-color: var(--color-bg-surface); border: 1px solid var(--color-border); border-radius: var(--radius-md); color: var(--color-text-main);">
                    <option value="php" <?php echo ($data['results']['source'] ?? 'php') === 'php' ? 'selected' : ''; ?>>
                        WordPress Errors</option>
                    <option value="all" <?php echo ($data['results']['source'] ?? '') === 'all' ? 'selected' : ''; ?>>All
                        Sources</option>
                    <option value="app" <?php echo ($data['results']['source'] ?? '') === 'app' ? 'selected' : ''; ?>>App
                        Only</option>
                </select>
            </div>

            <div>
                <label for="date_from"
                    style="display: block; margin-bottom: var(--space-xs); font-size: var(--font-size-sm); color: var(--color-text-muted);">From
                    Date</label>
                <input type="date" name="date_from" id="date_from"
                    value="<?php echo htmlspecialchars($data['results']['date_from'] ?? ''); ?>"
                    style="width: 100%; padding: var(--space-sm); background-color: var(--color-bg-surface); border: 1px solid var(--color-border); border-radius: var(--radius-md); color: var(--color-text-main);">
            </div>

            <div>
                <label for="date_to"
                    style="display: block; margin-bottom: var(--space-xs); font-size: var(--font-size-sm); color: var(--color-text-muted);">To
                    Date</label>
                <input type="date" name="date_to" id="date_to"
                    value="<?php echo htmlspecialchars($data['results']['date_to'] ?? ''); ?>"
                    style="width: 100%; padding: var(--space-sm); background-color: var(--color-bg-surface); border: 1px solid var(--color-border); border-radius: var(--radius-md); color: var(--color-text-main);">
            </div>

            <div>
                <label for="lines"
                    style="display: block; margin-bottom: var(--space-xs); font-size: var(--font-size-sm); color: var(--color-text-muted);">Lines</label>
                <input type="number" name="lines" id="lines"
                    value="<?php echo htmlspecialchars($data['results']['lines'] ?? 20); ?>" min="10" max="1000"
                    style="width: 100%; padding: var(--space-sm); background-color: var(--color-bg-surface); border: 1px solid var(--color-border); border-radius: var(--radius-md); color: var(--color-text-main);">
            </div>

            <button type="submit" class="btn btn-primary">
                <span class="material-symbols-outlined">filter_list</span>
                Filter
            </button>

            <?php if (!empty($data['results']['search']) || !empty($data['results']['date_from']) || !empty($data['results']['date_to']) || !empty($data['results']['severity']) || ($data['results']['source'] ?? 'php') === 'all' || ($data['results']['source'] ?? 'php') === 'app'): ?>
                <a href="?view=error_log" class="btn btn-outline">Reset</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Results -->
    <?php if (isset($data['results']['rows']) && !empty($data['results']['rows'])): ?>
        <div style="overflow-x: auto;">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width: 180px;">Date</th>
                        <th style="width: 80px;">Source</th>
                        <th style="width: 100px;">Severity</th>
                        <th style="width: 150px;">Type</th>
                        <th>Message</th>
                        <th style="width: 60px; text-align: center;">Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data['results']['rows'] as $index => $row): ?>
                        <tr>
                            <td style="white-space: nowrap; font-family: monospace; font-size: var(--font-size-sm);">
                                <?php echo htmlspecialchars($row[0]); ?>
                            </td>
                            <td>
                                <?php
                                $source = trim($row[1]);
                                $sourceClass = $source === 'PHP' ? 'badge-success' : 'badge-warning';
                                ?>
                                <span class="badge <?php echo $sourceClass; ?>"><?php echo htmlspecialchars($source); ?></span>
                            </td>
                            <td>
                                <?php
                                $severity = trim($row[3]);
                                $severityStyle = '';
                                if ($severity === 'CRITICAL' || $severity === 'ERROR') {
                                    $severityStyle = 'background-color: rgba(255, 23, 68, 0.15); color: var(--color-danger);';
                                } elseif ($severity === 'WARNING') {
                                    $severityStyle = 'background-color: rgba(255, 234, 0, 0.15); color: var(--color-warning);';
                                } else {
                                    $severityStyle = 'background-color: rgba(41, 121, 255, 0.15); color: var(--color-info);';
                                }
                                ?>
                                <span class="badge"
                                    style="<?php echo $severityStyle; ?>"><?php echo htmlspecialchars($severity); ?></span>
                            </td>
                            <td style="font-size: var(--font-size-sm);">
                                <?php echo htmlspecialchars($row[2]); ?>
                            </td>
                            <td
                                style="font-family: monospace; font-size: var(--font-size-sm); word-break: break-word; max-width: 400px;">
                                <?php echo htmlspecialchars($row[4]); ?>
                            </td>
                            <td style="text-align: center;">
                                <?php if (!empty($row[5]) || !empty($row[6]) || !empty($row[7])): ?>
                                    <button class="btn btn-outline" style="padding: var(--space-xs); min-width: auto;"
                                        onclick="toggleErrorDetails(<?php echo $index; ?>)">
                                        <span class="material-symbols-outlined" style="font-size: 18px;">expand_more</span>
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php if (!empty($row[5]) || !empty($row[6]) || !empty($row[7])): ?>
                            <tr id="error-details-<?php echo $index; ?>" style="display: none;">
                                <td colspan="6" style="background-color: var(--color-bg-surface-hover); padding: var(--space-md);">
                                    <div style="font-family: monospace; font-size: var(--font-size-sm);">
                                        <?php if (!empty($row[5]) || !empty($row[6])): ?>
                                            <div style="margin-bottom: var(--space-sm);">
                                                <strong style="color: var(--color-primary);">Location:</strong>
                                                <?php echo htmlspecialchars($row[5]); ?>
                                                <?php if (!empty($row[6])): ?>
                                                    <span class="text-muted">:<?php echo htmlspecialchars($row[6]); ?></span>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($row[7])): ?>
                                            <div>
                                                <strong style="color: var(--color-primary);">Stack Trace:</strong>
                                                <pre
                                                    style="margin: var(--space-sm) 0 0 0; padding: var(--space-md); background-color: var(--color-bg-surface); border-radius: var(--radius-md); overflow-x: auto; white-space: pre-wrap; word-wrap: break-word; border: 1px solid var(--color-border);"><?php echo htmlspecialchars($row[7]); ?></pre>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($data['results']['number_lines'] > $data['results']['lines']): ?>
            <div style="display: flex; justify-content: center; margin-top: var(--space-lg); gap: var(--space-sm);">
                <?php
                $current_page = $data['results']['page'];
                $total_pages = ceil($data['results']['number_lines'] / $data['results']['lines']);

                $params = [
                    'view' => 'error_log',
                    'lines' => $data['results']['lines'],
                    'search' => $data['results']['search'],
                    'date_from' => $data['results']['date_from'],
                    'date_to' => $data['results']['date_to'],
                    'severity' => $data['results']['severity'] ?? '',
                    'source' => $data['results']['source'] ?? 'php'
                ];

                if ($current_page > 1):
                    $params['page'] = $current_page - 1;
                    $prevUrl = '?' . http_build_query($params);
                    ?>
                    <a href="<?php echo $prevUrl; ?>" class="btn btn-outline">Previous</a>
                <?php endif; ?>

                <span style="display: flex; align-items: center; padding: 0 var(--space-md); color: var(--color-text-muted);">
                    Page <?php echo $current_page; ?> of <?php echo $total_pages; ?>
                </span>

                <?php if ($current_page < $total_pages):
                    $params['page'] = $current_page + 1;
                    $nextUrl = '?' . http_build_query($params);
                    ?>
                    <a href="<?php echo $nextUrl; ?>" class="btn btn-outline">Next</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    <?php else: ?>
        <div style="text-align: center; padding: var(--space-xl); color: var(--color-text-muted);">
            <span class="material-symbols-outlined"
                style="font-size: 48px; opacity: 0.5; margin-bottom: var(--space-md);">check_circle</span>
            <p style="font-size: var(--font-size-lg); margin-bottom: var(--space-sm);">No errors found matching your
                criteria.</p>
            <?php if (!empty($data['results']['search']) || !empty($data['results']['date_from']) || !empty($data['results']['severity']) || ($data['results']['source'] ?? 'php') === 'all' || ($data['results']['source'] ?? 'php') === 'app'): ?>
                <p>Try adjusting your filters or <a href="?view=error_log">clear all filters</a>.</p>
            <?php else: ?>
                <p>Your application appears to be running smoothly!</p>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<script>
function toggleErrorDetails(index) {
    const detailsRow = document.getElementById('error-details-' + index);
    const button = event.currentTarget;
    const icon = button.querySelector('.material-symbols-outlined');
    
    if (detailsRow.style.display === 'none') {
        detailsRow.style.display = 'table-row';
        icon.textContent = 'expand_less';
    } else {
        detailsRow.style.display = 'none';
        icon.textContent = 'expand_more';
    }
}
</script>