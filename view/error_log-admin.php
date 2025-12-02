<?php
/**
 * Error Log View - Premium Safe Mode Design
 */
?>

<div class="md3-card">
    <div class="md3-card-header"
        style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
        <div>
            <h2 class="md3-card-title">
                <span class="material-symbols-outlined" style="vertical-align: middle; margin-right: 8px;">error</span>
                Error Log
            </h2>
            <p class="md3-card-subtitle">Monitor and manage PHP errors</p>
        </div>
        <div style="display: flex; gap: 8px;">
            <a href="?view=error_log&action=archive_error_log" class="md3-button md3-button-outlined"
                onclick="return confirm('Are you sure you want to archive the current error log?');">
                <span class="material-symbols-outlined" style="margin-right: 8px;">archive</span>
                Archive
            </a>
            <a href="?view=error_log&action=download_error_log" class="md3-button md3-button-outlined">
                <span class="material-symbols-outlined" style="margin-right: 8px;">download</span>
                Download
            </a>
            <a href="?view=error_log&action=clear_error_log" class="md3-button md3-button-filled error"
                onclick="return confirm('Are you sure you want to clear the error log? This action cannot be undone.');"
                style="background-color: var(--md-sys-color-error); color: var(--md-sys-color-on-error);">
                <span class="material-symbols-outlined" style="margin-right: 8px;">delete</span>
                Clear Log
            </a>
        </div>
    </div>

    <!-- Statistics -->
    <?php if (isset($data['stats']) && $data['stats']['exists']): ?>
        <div
            style="display: flex; gap: 24px; margin: 24px 0; padding: 16px; background-color: var(--md-sys-color-surface-variant); border-radius: 12px; flex-wrap: wrap;">
            <div>
                <span style="display: block; font-size: 0.75rem; color: var(--md-sys-color-on-surface-variant);">File
                    Size</span>
                <span
                    style="font-size: 1.125rem; font-weight: 500;"><?php echo DashboardHelpers::format_size($data['stats']['size']); ?></span>
            </div>
            <div>
                <span style="display: block; font-size: 0.75rem; color: var(--md-sys-color-on-surface-variant);">Total
                    Lines</span>
                <span
                    style="font-size: 1.125rem; font-weight: 500;"><?php echo number_format($data['stats']['lines']); ?></span>
            </div>
            <div>
                <span style="display: block; font-size: 0.75rem; color: var(--md-sys-color-on-surface-variant);">Last
                    Modified</span>
                <span
                    style="font-size: 1.125rem; font-weight: 500;"><?php echo date('Y-m-d H:i:s', $data['stats']['last_modified']); ?></span>
            </div>
        </div>
    <?php endif; ?>

    <!-- Filters -->
    <div class="md3-card"
        style="margin-bottom: 24px; background-color: var(--md-sys-color-surface); border: 1px solid var(--md-sys-color-outline-variant);">
        <form action="" method="get" style="display: flex; gap: 16px; align-items: flex-end; flex-wrap: wrap;">
            <input type="hidden" name="view" value="error_log">

            <div class="md3-text-field" style="flex: 1; min-width: 200px;">
                <input type="text" name="search" id="search"
                    value="<?php echo htmlspecialchars($data['results']['search'] ?? ''); ?>"
                    placeholder="Search errors...">
                <label for="search">Search</label>
            </div>

            <div class="md3-text-field" style="width: 150px;">
                <input type="date" name="date_from" id="date_from"
                    value="<?php echo htmlspecialchars($data['results']['date_from'] ?? ''); ?>">
                <label for="date_from">From Date</label>
            </div>

            <div class="md3-text-field" style="width: 150px;">
                <input type="date" name="date_to" id="date_to"
                    value="<?php echo htmlspecialchars($data['results']['date_to'] ?? ''); ?>">
                <label for="date_to">To Date</label>
            </div>

            <div class="md3-text-field" style="width: 100px;">
                <input type="number" name="lines" id="lines"
                    value="<?php echo htmlspecialchars($data['results']['lines'] ?? 20); ?>" min="10" max="1000">
                <label for="lines">Lines</label>
            </div>

            <button type="submit" class="md3-button md3-button-filled">
                <span class="material-symbols-outlined" style="margin-right: 8px;">filter_list</span>
                Filter
            </button>

            <?php if (!empty($data['results']['search']) || !empty($data['results']['date_from']) || !empty($data['results']['date_to'])): ?>
                <a href="?view=error_log" class="md3-button md3-button-text">Reset</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Results -->
    <?php if (isset($data['results']['rows']) && !empty($data['results']['rows'])): ?>
        <div class="md3-table-container">
            <table class="md3-table">
                <thead>
                    <tr>
                        <th style="width: 180px;">Date</th>
                        <th style="width: 120px;">Type</th>
                        <th>Message</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data['results']['rows'] as $row): ?>
                        <tr>
                            <td style="white-space: nowrap; font-family: monospace; font-size: 0.85rem;">
                                <?php echo htmlspecialchars($row[0]); ?></td>
                            <td>
                                <?php
                                $type = trim($row[1]);
                                $class = 'info';
                                if (stripos($type, 'Fatal') !== false || stripos($type, 'Parse') !== false)
                                    $class = 'error';
                                elseif (stripos($type, 'Warning') !== false)
                                    $class = 'warning';
                                ?>
                                <span class="md3-chip <?php echo $class; ?>"
                                    style="height: 24px; font-size: 0.75rem;"><?php echo htmlspecialchars($type); ?></span>
                            </td>
                            <td style="font-family: monospace; font-size: 0.85rem; word-break: break-all;">
                                <?php echo htmlspecialchars($row[2]); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($data['results']['number_lines'] > $data['results']['lines']): ?>
            <div style="display: flex; justify-content: center; margin-top: 24px; gap: 8px;">
                <?php
                $current_page = $data['results']['page'];
                $total_pages = ceil($data['results']['number_lines'] / $data['results']['lines']);
                $range = 2;

                if ($current_page > 1): ?>
                    <a href="?view=error_log&page=<?php echo $current_page - 1; ?>&lines=<?php echo $data['results']['lines']; ?>&search=<?php echo urlencode($data['results']['search']); ?>&date_from=<?php echo urlencode($data['results']['date_from']); ?>&date_to=<?php echo urlencode($data['results']['date_to']); ?>"
                        class="md3-button md3-button-outlined">Previous</a>
                <?php endif; ?>

                <span style="display: flex; align-items: center; padding: 0 16px;">
                    Page <?php echo $current_page; ?> of <?php echo $total_pages; ?>
                </span>

                <?php if ($current_page < $total_pages): ?>
                    <a href="?view=error_log&page=<?php echo $current_page + 1; ?>&lines=<?php echo $data['results']['lines']; ?>&search=<?php echo urlencode($data['results']['search']); ?>&date_from=<?php echo urlencode($data['results']['date_from']); ?>&date_to=<?php echo urlencode($data['results']['date_to']); ?>"
                        class="md3-button md3-button-outlined">Next</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    <?php else: ?>
        <div style="text-align: center; padding: 48px; color: var(--md-sys-color-on-surface-variant);">
            <span class="material-symbols-outlined"
                style="font-size: 48px; opacity: 0.5; margin-bottom: 16px;">check_circle</span>
            <p style="font-size: 1.1rem;">No errors found matching your criteria.</p>
            <?php if (!empty($data['results']['search']) || !empty($data['results']['date_from'])): ?>
                <p>Try adjusting your filters or <a href="?view=error_log">clear all filters</a>.</p>
            <?php else: ?>
                <p>Your WordPress installation appears to be running smoothly!</p>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>