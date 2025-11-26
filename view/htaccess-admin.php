<?php
/**
 * .htaccess Editor - Premium Safe Mode Design
 */
?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            <span class="material-symbols-outlined" style="vertical-align: middle; margin-right: 8px;">code</span>
            .htaccess Editor
        </h3>
    </div>

    <form id="htaccess-form" method="post">
        <div style="margin-bottom: var(--space-lg);">
            <label class="form-label" style="display: block; margin-bottom: var(--space-sm); font-weight: 500;">
                .htaccess Content
            </label>
            <textarea name="htaccess_content" rows="20"
                style="width: 100%; padding: var(--space-md); background-color: #000; color: #00ff00; border: 1px solid var(--color-border); border-radius: var(--radius-md); font-family: 'Courier New', monospace; font-size: 0.875rem; resize: vertical;"><?php echo isset($data['htaccess_content']) ? htmlspecialchars($data['htaccess_content']) : ''; ?></textarea>
        </div>

        <div style="display: flex; gap: var(--space-sm); flex-wrap: wrap;">
            <button type="submit" name="submit_htaccess" class="btn btn-primary">
                <span class="material-symbols-outlined">save</span>
                Save Changes
            </button>
            <a href="?view=htaccess&action=download_htaccess" class="btn btn-outline">
                <span class="material-symbols-outlined">download</span>
                Download Backup
            </a>
        </div>
    </form>
</div>

<!-- Quick Actions -->
<?php if (isset($data['htaccess_items'])): ?>
    <div class="card" style="margin-top: var(--space-lg);">
        <div class="card-header">
            <h3 class="card-title">
                <span class="material-symbols-outlined" style="vertical-align: middle; margin-right: 8px;">bolt</span>
                Quick Actions
            </h3>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: var(--space-md);">
            <?php foreach ($data['htaccess_items'] as $item): ?>
                <a href="?view=htaccess&action=<?php echo htmlspecialchars($item['action']); ?>" class="card"
                    style="padding: var(--space-md); text-decoration: none; transition: all var(--transition-fast); border-color: var(--color-border);">
                    <div style="display: flex; align-items: center; gap: var(--space-md);">
                        <span class="material-symbols-outlined"
                            style="font-size: 2rem; color: var(--color-primary);">settings</span>
                        <div>
                            <div style="font-weight: 600; margin-bottom: 4px;"><?php echo htmlspecialchars($item['name']); ?>
                            </div>
                            <div class="text-muted" style="font-size: var(--font-size-sm);">
                                <?php echo htmlspecialchars($item['description'] ?? ''); ?></div>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>