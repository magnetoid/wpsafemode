<?php
/**
 * Info View - Premium Safe Mode Design
 * System information dashboard
 */
?>

<!-- Stats Grid -->
<div class="stats-grid">
    <?php if (isset($data['info']['php_info']['PHP Version'])): ?>
        <div class="stat-card">
            <div class="stat-icon">
                <span class="material-symbols-outlined">code</span>
            </div>
            <div class="stat-content">
                <div class="stat-value"><?php echo htmlspecialchars($data['info']['php_info']['PHP Version']['value']); ?>
                </div>
                <div class="stat-label">PHP Version</div>
            </div>
        </div>
    <?php endif; ?>

    <?php if (isset($data['info']['core_info'][0])): ?>
        <div class="stat-card">
            <div class="stat-icon" style="background-color: rgba(124, 77, 255, 0.1); color: var(--color-secondary);">
                <span class="material-symbols-outlined">wordpress</span>
            </div>
            <div class="stat-content">
                <div class="stat-value"><?php echo htmlspecialchars($data['info']['core_info'][0]['version']); ?></div>
                <div class="stat-label">WordPress Version</div>
            </div>
        </div>
    <?php endif; ?>

    <?php if (isset($data['info']['plugins_info']) && is_array($data['info']['plugins_info'])): ?>
        <div class="stat-card">
            <div class="stat-icon" style="background-color: rgba(0, 230, 118, 0.1); color: var(--color-success);">
                <span class="material-symbols-outlined">extension</span>
            </div>
            <div class="stat-content">
                <div class="stat-value"><?php echo count($data['info']['plugins_info']); ?></div>
                <div class="stat-label">Active Plugins</div>
            </div>
        </div>
    <?php else: ?>
        <div class="stat-card">
            <div class="stat-icon" style="background-color: rgba(0, 230, 118, 0.1); color: var(--color-success);">
                <span class="material-symbols-outlined">extension</span>
            </div>
            <div class="stat-content">
                <div class="stat-value">0</div>
                <div class="stat-label">Active Plugins</div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- System Information Grid -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: var(--space-lg);">

    <!-- WordPress Core Info -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <span class="material-symbols-outlined"
                    style="vertical-align: middle; margin-right: 8px;">wordpress</span>
                WordPress Core
            </h3>
        </div>
        <?php if (isset($data['info']['core_info'])): ?>
            <div style="display: flex; flex-direction: column; gap: var(--space-md);">
                <?php foreach ($data['info']['core_info'] as $core_info): ?>
                    <div>
                        <div style="font-weight: 600; margin-bottom: 4px;"><?php echo htmlspecialchars($core_info['name']); ?>
                        </div>
                        <div class="text-muted" style="font-size: var(--font-size-sm);">
                            <?php echo htmlspecialchars($core_info['description']); ?>
                        </div>
                        <div style="margin-top: 4px;">
                            <span class="badge badge-success">v<?php echo htmlspecialchars($core_info['version']); ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- PHP & Server Info -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <span class="material-symbols-outlined" style="vertical-align: middle; margin-right: 8px;">dns</span>
                PHP & Server
            </h3>
        </div>
        <?php if (isset($data['info']['php_info'])): ?>
            <div style="display: flex; flex-direction: column; gap: var(--space-sm);">
                <?php foreach ($data['info']['php_info'] as $php_slug => $php_info): ?>
                    <div
                        style="display: flex; justify-content: space-between; padding: var(--space-sm) 0; border-bottom: 1px solid var(--color-border);">
                        <span style="font-weight: 500;"><?php echo htmlspecialchars($php_slug); ?></span>
                        <?php if ($php_info['value'] != ''): ?>
                            <span class="text-muted"><?php echo htmlspecialchars($php_info['value']); ?></span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Themes -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <span class="material-symbols-outlined"
                    style="vertical-align: middle; margin-right: 8px;">palette</span>
                Themes
            </h3>
        </div>
        <?php if (isset($data['info']['themes_versions'])): ?>
            <div style="display: flex; flex-direction: column; gap: var(--space-sm);">
                <?php foreach ($data['info']['themes_versions'] as $theme_slug => $theme_info): ?>
                    <div
                        style="display: flex; justify-content: space-between; align-items: center; padding: var(--space-sm) 0;">
                        <span style="font-weight: 500;"><?php echo htmlspecialchars($theme_info['theme_name']); ?></span>
                        <?php if (isset($theme_info['theme_version'])): ?>
                            <span class="badge badge-warning">v<?php echo htmlspecialchars($theme_info['theme_version']); ?></span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Plugins Summary -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <span class="material-symbols-outlined"
                    style="vertical-align: middle; margin-right: 8px;">extension</span>
                Plugins
            </h3>
        </div>
        <?php if (isset($data['info']['plugins_info']) && is_array($data['info']['plugins_info'])): ?>
            <div style="text-align: center; padding: var(--space-xl) 0;">
                <div style="font-size: 3rem; font-weight: 700; color: var(--color-primary);">
                    <?php echo count($data['info']['plugins_info']); ?>
                </div>
                <div class="text-muted">Total Plugins Installed</div>
                <a href="?view=plugins" class="btn btn-primary" style="margin-top: var(--space-md);">
                    <span class="material-symbols-outlined">settings</span>
                    Manage Plugins
                </a>
            </div>
        <?php else: ?>
            <div style="text-align: center; padding: var(--space-xl) 0;">
                <div style="font-size: 3rem; font-weight: 700; color: var(--color-primary);">
                    0
                </div>
                <div class="text-muted">Total Plugins Installed</div>
                <a href="?view=plugins" class="btn btn-primary" style="margin-top: var(--space-md);">
                    <span class="material-symbols-outlined">settings</span>
                    Manage Plugins
                </a>
            </div>
        <?php endif; ?>
    </div>

</div>