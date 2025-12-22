<?php
/**
 * Database Optimizer View
 */
?>
<div class="md3-card">
    <div class="md3-card-header">
        <h3 class="md3-card-title">Database Optimization</h3>
    </div>
    <div class="md3-card-content">
        <div class="md3-list">
            <div class="md3-list-item">
                <span class="md3-list-item-text">
                    <span class="md3-list-item-primary-text">Table Optimization</span>
                    <span class="md3-list-item-secondary-text">Run OPTIMIZE TABLE on all tables</span>
                </span>
                <a href="<?php echo $data['script_url']; ?>?action=optimize_tables&view=database_optimizer"
                    class="md3-button md3-button-text">Run</a>
            </div>
            <div class="md3-list-item">
                <span class="md3-list-item-text">
                    <span class="md3-list-item-primary-text">Clean Revisions</span>
                    <span class="md3-list-item-secondary-text">Delete all post revisions</span>
                </span>
                <a href="<?php echo $data['script_url']; ?>?action=delete_revisions&view=database_optimizer"
                    class="md3-button md3-button-text">Clean</a>
            </div>
            <div class="md3-list-item">
                <span class="md3-list-item-text">
                    <span class="md3-list-item-primary-text">Clean Spam Comments</span>
                    <span class="md3-list-item-secondary-text">Delete spam comments</span>
                </span>
                <a href="<?php echo $data['script_url']; ?>?action=delete_spam_comments&view=database_optimizer"
                    class="md3-button md3-button-text">Clean</a>
            </div>
        </div>
    </div>
</div>

<div class="md3-card" style="margin-top: 20px;">
    <div class="md3-card-header">
        <h3 class="md3-card-title">Object Cache & Transients</h3>
    </div>
    <div class="md3-card-content">
        <div class="info-grid">
            <div class="info-item">
                <strong>Object Cache:</strong>
                <span class="badge <?php echo $data['cache_status']['has_dropin'] ? 'success' : 'warning'; ?>">
                    <?php echo $data['cache_status']['type']; ?>
                    <?php if ($data['cache_status']['has_dropin']): ?>
                        (<?php echo $data['cache_status']['dropin_size']; ?>)
                    <?php endif; ?>
                </span>
            </div>
            <div class="info-item">
                <strong>Transients:</strong>
                <span>Total: <?php echo $data['transient_stats']['total']; ?></span>
                |
                <span>Expired: <?php echo $data['transient_stats']['expired']; ?></span>
            </div>
        </div>
    </div>
    <div class="md3-card-actions">
        <a href="<?php echo $data['script_url']; ?>?action=flush_object_cache"
            class="md3-button md3-button-filled">Flush Object Cache</a>
        <a href="<?php echo $data['script_url']; ?>?action=clean_transients" class="md3-button md3-button-text">Clean
            Expired Transients</a>
    </div>
</div>

<style>
    .info-grid {
        display: flex;
        gap: 20px;
        margin-bottom: 16px;
    }

    .badge {
        padding: 2px 8px;
        border-radius: 4px;
        font-size: 0.85em;
        font-weight: 500;
    }

    .badge.success {
        background-color: rgba(76, 175, 80, 0.2);
        color: #2e7d32;
    }

    .badge.warning {
        background-color: rgba(255, 193, 7, 0.2);
        color: #f57f17;
    }
</style>