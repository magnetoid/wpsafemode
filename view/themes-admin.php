<?php
/**
 * Themes View - Premium Safe Mode Design
 */
?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            <span class="material-symbols-outlined" style="vertical-align: middle; margin-right: 8px;">palette</span>
            Theme Management
        </h3>
    </div>

    <form id="themes-form" method="post">
        <!-- Action Buttons -->
        <div style="display: flex; gap: var(--space-sm); margin-bottom: var(--space-lg); flex-wrap: wrap;">
            <button type="submit" name="submit_themes_action" value="activate" class="btn btn-primary">
                <span class="material-symbols-outlined">check_circle</span>
                Activate Selected
            </button>
        </div>

        <!-- Themes Grid -->
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: var(--space-lg);">
            <?php if (isset($data['themes']['all_themes'])): ?>
                <?php
                $active_theme = isset($data['themes']['active_theme']) ? $data['themes']['active_theme'] : '';
                ?>
                <?php foreach ($data['themes']['all_themes'] as $theme_slug => $theme_info): ?>
                    <?php $is_active = ($theme_slug === $active_theme); ?>

                    <div class="card"
                        style="padding: 0; overflow: hidden; <?php echo $is_active ? 'border-color: var(--color-primary);' : ''; ?>">
                        <!-- Theme Screenshot -->
                        <div
                            style="height: 180px; background: linear-gradient(135deg, var(--color-bg-surface-hover), var(--color-bg-surface)); display: flex; align-items: center; justify-content: center;">
                            <?php if (isset($theme_info['screenshot'])): ?>
                                <img src="<?php echo htmlspecialchars($theme_info['screenshot']); ?>"
                                    alt="<?php echo htmlspecialchars($theme_info['name'] ?? $theme_slug); ?>"
                                    style="width: 100%; height: 100%; object-fit: cover;">
                            <?php else: ?>
                                <span class="material-symbols-outlined"
                                    style="font-size: 4rem; color: var(--color-text-dim);">palette</span>
                            <?php endif; ?>
                        </div>

                        <!-- Theme Info -->
                        <div style="padding: var(--space-lg);">
                            <div
                                style="display: flex; justify-content: space-between; align-items: start; margin-bottom: var(--space-sm);">
                                <div>
                                    <h4 style="margin: 0 0 var(--space-xs) 0; font-weight: 600;">
                                        <?php echo htmlspecialchars($theme_info['name'] ?? $theme_slug); ?>
                                    </h4>
                                    <?php if (isset($theme_info['version'])): ?>
                                        <span class="badge"
                                            style="background-color: var(--color-bg-surface-hover); color: var(--color-text-muted);">
                                            v<?php echo htmlspecialchars($theme_info['version']); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <?php if ($is_active): ?>
                                    <span class="badge badge-success">Active</span>
                                <?php endif; ?>
                            </div>

                            <?php if (isset($theme_info['description'])): ?>
                                <p class="text-muted" style="font-size: var(--font-size-sm); margin: var(--space-sm) 0;">
                                    <?php echo htmlspecialchars(substr($theme_info['description'], 0, 100)); ?>...
                                </p>
                            <?php endif; ?>

                            <?php if (!$is_active): ?>
                                <label
                                    style="display: flex; align-items: center; gap: var(--space-sm); margin-top: var(--space-md); cursor: pointer;">
                                    <input type="radio" name="theme" value="<?php echo htmlspecialchars($theme_slug); ?>">
                                    <span class="text-muted" style="font-size: var(--font-size-sm);">Select to activate</span>
                                </label>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="card" style="grid-column: 1 / -1;">
                    <p class="text-muted" style="text-align: center; padding: var(--space-xl);">No themes found</p>
                </div>
            <?php endif; ?>
        </div>
    </form>
</div>