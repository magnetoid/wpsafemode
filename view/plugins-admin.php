<?php
/**
 * Plugins View - Premium Safe Mode Design
 * Plugin management interface
 */
?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            <span class="material-symbols-outlined" style="vertical-align: middle; margin-right: 8px;">extension</span>
            Plugin Management
        </h3>
    </div>
    
    <form id="plugins-form" method="post">
        <!-- Action Buttons -->
        <div style="display: flex; gap: var(--space-sm); margin-bottom: var(--space-lg); flex-wrap: wrap;">
            <button type="button" class="btn btn-outline" id="select-all-plugins">
                <span class="material-symbols-outlined">check_box</span>
                Select All
            </button>
            <button type="button" class="btn btn-outline" id="deselect-all-plugins">
                <span class="material-symbols-outlined">check_box_outline_blank</span>
                Deselect All
            </button>
            <button type="submit" name="submit_plugins_action" value="activate" class="btn btn-primary">
                <span class="material-symbols-outlined">check_circle</span>
                Activate Selected
            </button>
            <button type="submit" name="submit_plugins_action" value="disable_all_plugins" class="btn" style="background-color: var(--color-danger); color: white;">
                <span class="material-symbols-outlined">cancel</span>
                Disable All
            </button>
            <button type="submit" name="submit_plugins_action" value="revert_plugins" class="btn" style="background-color: var(--color-warning); color: #000;">
                <span class="material-symbols-outlined">undo</span>
                Revert to Original
            </button>
        </div>
        
        <!-- Plugins Table -->
        <div style="overflow-x: auto;">
            <table class="table">
                <thead>
                    <tr>
                        <th width="40">
                            <input type="checkbox" id="select-all-checkbox">
                        </th>
                        <th>Plugin Name</th>
                        <th>Version</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(isset($data['plugins']['all_plugins'])): ?>
                    <?php 
                    $active_plugins = isset($data['plugins']['active_plugins']) ? 
                        (is_array($data['plugins']['active_plugins']) ? $data['plugins']['active_plugins'] : 
                        (isset($data['plugins']['active_plugins']['option_value']) ? 
                            unserialize($data['plugins']['active_plugins']['option_value']) : [])) : [];
                    ?>
                    <?php foreach($data['plugins']['all_plugins'] as $plugin_path => $plugin_info): ?>
                    <?php $is_active = in_array($plugin_path, $active_plugins); ?>
                    <tr>
                        <td>
                            <input type="checkbox" name="plugins[]" value="<?php echo htmlspecialchars($plugin_path); ?>" 
                                   <?php echo $is_active ? 'checked' : ''; ?>>
                        </td>
                        <td>
                            <strong><?php echo htmlspecialchars($plugin_info['name'] ?? $plugin_path); ?></strong>
                        </td>
                        <td>
                            <?php if(isset($plugin_info['version'])): ?>
                            <span class="badge" style="background-color: var(--color-bg-surface-hover); color: var(--color-text-muted);">
                                v<?php echo htmlspecialchars($plugin_info['version']); ?>
                            </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if($is_active): ?>
                            <span class="badge badge-success">Active</span>
                            <?php else: ?>
                            <span class="badge" style="background-color: var(--color-bg-surface-hover); color: var(--color-text-muted);">Inactive</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php else: ?>
                    <tr>
                        <td colspan="4" style="text-align: center; padding: var(--space-xl); color: var(--color-text-muted);">
                            No plugins found
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Select all checkbox functionality
    const selectAllCheckbox = document.getElementById('select-all-checkbox');
    const pluginCheckboxes = document.querySelectorAll('input[name="plugins[]"]');
    
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            pluginCheckboxes.forEach(cb => {
                cb.checked = this.checked;
            });
        });
    }
    
    // Select/Deselect all buttons
    const selectAllBtn = document.getElementById('select-all-plugins');
    const deselectAllBtn = document.getElementById('deselect-all-plugins');
    
    if (selectAllBtn) {
        selectAllBtn.addEventListener('click', function() {
            pluginCheckboxes.forEach(cb => cb.checked = true);
            if (selectAllCheckbox) selectAllCheckbox.checked = true;
        });
    }
    
    if (deselectAllBtn) {
        deselectAllBtn.addEventListener('click', function() {
            pluginCheckboxes.forEach(cb => cb.checked = false);
            if (selectAllCheckbox) selectAllCheckbox.checked = false;
        });
    }
});
</script>
