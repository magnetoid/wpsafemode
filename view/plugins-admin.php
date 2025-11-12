<?php
/**
 * Plugins View - AdminLTE Design
 * Plugin management interface
 */
?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-plug mr-2"></i>Plugins Management</h3>
                <div class="card-tools">
                    <button type="button" class="btn btn-tool" data-card-widget="collapse">
                        <i class="fas fa-minus"></i>
                    </button>
                </div>
            </div>
            <div class="card-body">
                <form id="plugins-form" data-ajax data-endpoint="/api/submit?form=plugins">
                    <div class="row mb-3">
                        <div class="col-12">
                            <div class="btn-group" role="group">
                                <button type="button" class="btn btn-sm btn-outline-primary" id="select-all-plugins">
                                    <i class="fas fa-check-square"></i> Select All
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-primary" id="deselect-all-plugins">
                                    <i class="far fa-square"></i> Deselect All
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive plugin-list">
                        <table class="table table-hover table-modern">
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
                            <tbody id="plugins-list-body">
                                <?php if(isset($data['plugins']['all_plugins'])): ?>
                                <?php 
                                $active_plugins = isset($data['plugins']['active_plugins']) ? 
                                    (is_array($data['plugins']['active_plugins']) ? $data['plugins']['active_plugins'] : 
                                    (isset($data['plugins']['active_plugins']['option_value']) ? 
                                        unserialize($data['plugins']['active_plugins']['option_value']) : [])) : [];
                                ?>
                                <?php foreach($data['plugins']['all_plugins'] as $plugin_path => $plugin_info): ?>
                                <?php $is_active = in_array($plugin_path, $active_plugins); ?>
                                <tr class="plugin-item">
                                    <td>
                                        <input type="checkbox" name="plugins[]" value="<?php echo htmlspecialchars($plugin_path); ?>" 
                                               <?php echo $is_active ? 'checked' : ''; ?>>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($plugin_info['name'] ?? $plugin_path); ?></strong>
                                    </td>
                                    <td>
                                        <?php if(isset($plugin_info['version'])): ?>
                                        <span class="badge badge-secondary"><?php echo htmlspecialchars($plugin_info['version']); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if($is_active): ?>
                                        <span class="badge badge-success">Active</span>
                                        <?php else: ?>
                                        <span class="badge badge-secondary">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="row mt-3">
                        <div class="col-12">
                            <button type="submit" name="submit_plugins_action" value="activate" class="btn btn-success">
                                <i class="fas fa-check"></i> Activate Selected
                            </button>
                            <button type="button" class="btn btn-danger" id="disable-all-plugins" data-action="disable_all_plugins" data-ajax>
                                <i class="fas fa-times"></i> Disable All
                            </button>
                            <button type="button" class="btn btn-warning" id="revert-plugins" data-action="revert_plugins" data-ajax>
                                <i class="fas fa-undo"></i> Revert to Original
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Select all checkbox
    const selectAllCheckbox = document.getElementById('select-all-checkbox');
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            document.querySelectorAll('#plugins-list-body input[type="checkbox"]').forEach(cb => {
                cb.checked = this.checked;
            });
        });
    }
});
</script>


