<?php
/**
 * Error Log View - Premium Safe Mode Design
 */
?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            <span class="material-symbols-outlined" style="vertical-align: middle; margin-right: 8px;">error</span>
            Error Log
        </h3>
        <div style="display: flex; gap: var(--space-sm);">
            <a href="?view=error_log&action=clear_error_log" class="btn btn-outline">
                <span class="material-symbols-outlined">delete</span>
                Clear Log
            </a>
            <a href="?view=error_log&action=download_error_log" class="btn btn-outline">
                <span class="material-symbols-outlined">download</span>
                Download
            </a>
        </div>
    </div>

    <?php if (isset($data['error_log']) && !empty($data['error_log'])): ?>
        <div
            style="background-color: #000; padding: var(--space-lg); border-radius: var(--radius-md); font-family: 'Courier New', monospace; font-size: 0.875rem; overflow-x: auto; max-height: 600px; overflow-y: auto;">
            <pre
                style="margin: 0; color: #00ff00; line-height: 1.6;"><?php echo htmlspecialchars($data['error_log']); ?></pre>
        </div>
    <?php else: ?>
        <div style="text-align: center; padding: var(--space-xl); color: var(--color-text-muted);">
            <span class="material-symbols-outlined" style="font-size: 4rem; opacity: 0.3;">check_circle</span>
            <p style="margin-top: var(--space-md);">No errors found. Your WordPress installation is running smoothly!</p>
        </div>
    <?php endif; ?>
</div>