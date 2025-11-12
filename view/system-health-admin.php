<?php
/**
 * System Health View - Material Design 3
 * Real-time system health monitoring
 */
?>

<div class="md3-card">
    <h2 class="md3-card-title">System Health Dashboard</h2>
    <p class="md3-card-subtitle">Real-time monitoring of your WordPress installation</p>
    
    <?php if(isset($data['health'])): ?>
    
    <!-- Server Metrics -->
    <div class="md3-card" style="margin-top: 24px;">
        <h3 class="md3-card-title" style="font-size: 1.25rem;">Server Information</h3>
        <div class="md3-table-container">
            <table class="md3-table">
                <tbody>
                    <tr>
                        <td><strong>Operating System</strong></td>
                        <td><?php echo htmlspecialchars($data['health']['server']['os'] ?? 'Unknown'); ?></td>
                    </tr>
                    <tr>
                        <td><strong>PHP Version</strong></td>
                        <td><?php echo htmlspecialchars($data['health']['server']['php_version'] ?? 'Unknown'); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Server Software</strong></td>
                        <td><?php echo htmlspecialchars($data['health']['server']['server_software'] ?? 'Unknown'); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Timezone</strong></td>
                        <td><?php echo htmlspecialchars($data['health']['server']['timezone'] ?? 'Unknown'); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Database Metrics -->
    <div class="md3-card" style="margin-top: 24px;">
        <h3 class="md3-card-title" style="font-size: 1.25rem;">Database</h3>
        <?php if(isset($data['health']['database']) && $data['health']['database']['status'] === 'healthy'): ?>
        <div class="md3-stats-card">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px;">
                <div>
                    <div class="md3-stats-card-value"><?php echo $data['health']['database']['table_count'] ?? 0; ?></div>
                    <div class="md3-stats-card-label">Tables</div>
                </div>
                <div>
                    <div class="md3-stats-card-value"><?php echo $data['health']['database']['total_size_formatted'] ?? '0 B'; ?></div>
                    <div class="md3-stats-card-label">Total Size</div>
                </div>
            </div>
        </div>
        <?php else: ?>
        <div class="md3-snackbar error">
            <span class="material-symbols-outlined" style="margin-right: 12px;">error</span>
            <span>Database metrics unavailable</span>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- WordPress Metrics -->
    <div class="md3-card" style="margin-top: 24px;">
        <h3 class="md3-card-title" style="font-size: 1.25rem;">WordPress</h3>
        <?php if(isset($data['health']['wordpress'])): ?>
        <div class="md3-stats-card">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px;">
                <div>
                    <div class="md3-stats-card-value"><?php echo $data['health']['wordpress']['wp_version'] ?? 'Unknown'; ?></div>
                    <div class="md3-stats-card-label">WordPress Version</div>
                </div>
                <div>
                    <div class="md3-stats-card-value"><?php echo $data['health']['wordpress']['active_plugins'] ?? 0; ?></div>
                    <div class="md3-stats-card-label">Active Plugins</div>
                </div>
                <div>
                    <div class="md3-stats-card-value"><?php echo $data['health']['wordpress']['total_themes'] ?? 0; ?></div>
                    <div class="md3-stats-card-label">Total Themes</div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Disk Usage -->
    <div class="md3-card" style="margin-top: 24px;">
        <h3 class="md3-card-title" style="font-size: 1.25rem;">Disk Usage</h3>
        <?php if(isset($data['health']['disk']) && $data['health']['disk']['status'] !== 'unknown'): ?>
        <div>
            <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                <span>Used: <?php echo $data['health']['disk']['used_formatted'] ?? '0 B'; ?></span>
                <span>Free: <?php echo $data['health']['disk']['free_formatted'] ?? '0 B'; ?></span>
                <span>Total: <?php echo $data['health']['disk']['total_formatted'] ?? '0 B'; ?></span>
            </div>
            <div class="md3-linear-progress">
                <div class="md3-linear-progress-bar" style="width: <?php echo $data['health']['disk']['percent_used'] ?? 0; ?>%;"></div>
            </div>
            <div style="margin-top: 8px; text-align: right;">
                <span class="md3-chip <?php echo ($data['health']['disk']['percent_used'] ?? 0) > 90 ? 'error' : (($data['health']['disk']['percent_used'] ?? 0) > 75 ? 'warning' : 'success'); ?>">
                    <?php echo $data['health']['disk']['percent_used'] ?? 0; ?>% Used
                </span>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Memory Usage -->
    <div class="md3-card" style="margin-top: 24px;">
        <h3 class="md3-card-title" style="font-size: 1.25rem;">Memory Usage</h3>
        <?php if(isset($data['health']['memory'])): ?>
        <div>
            <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                <span>Usage: <?php echo $data['health']['memory']['usage_formatted'] ?? '0 B'; ?></span>
                <span>Peak: <?php echo $data['health']['memory']['peak_formatted'] ?? '0 B'; ?></span>
                <span>Limit: <?php echo $data['health']['memory']['limit'] ?? 'Unknown'; ?></span>
            </div>
            <div class="md3-linear-progress">
                <div class="md3-linear-progress-bar" style="width: <?php echo $data['health']['memory']['percent_used'] ?? 0; ?>%;"></div>
            </div>
            <div style="margin-top: 8px; text-align: right;">
                <span class="md3-chip <?php echo ($data['health']['memory']['percent_used'] ?? 0) > 90 ? 'error' : (($data['health']['memory']['percent_used'] ?? 0) > 75 ? 'warning' : 'success'); ?>">
                    <?php echo $data['health']['memory']['percent_used'] ?? 0; ?>% Used
                </span>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Security Status -->
    <div class="md3-card" style="margin-top: 24px;">
        <h3 class="md3-card-title" style="font-size: 1.25rem;">Security Status</h3>
        <?php if(isset($data['health']['security'])): ?>
        <div>
            <div style="display: flex; align-items: center; gap: 16px; margin-bottom: 16px;">
                <div class="md3-stats-card-value" style="font-size: 2rem;"><?php echo $data['health']['security']['score'] ?? 0; ?></div>
                <div>
                    <div class="md3-stats-card-label">Security Score</div>
                    <span class="md3-chip <?php echo ($data['health']['security']['status'] ?? '') === 'good' ? 'success' : 'warning'; ?>">
                        <?php echo ucfirst($data['health']['security']['status'] ?? 'unknown'); ?>
                    </span>
                </div>
            </div>
            <?php if(isset($data['health']['security']['checks'])): ?>
            <ul style="list-style: none; padding: 0;">
                <?php foreach($data['health']['security']['checks'] as $check => $status): ?>
                <li style="padding: 8px 0; display: flex; align-items: center; gap: 8px;">
                    <span class="material-symbols-outlined" style="color: <?php echo $status ? 'var(--md-sys-color-primary)' : 'var(--md-sys-color-error)'; ?>;">
                        <?php echo $status ? 'check_circle' : 'cancel'; ?>
                    </span>
                    <span><?php echo ucwords(str_replace('_', ' ', $check)); ?></span>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    
    <?php else: ?>
    <div class="md3-snackbar error">
        <span class="material-symbols-outlined" style="margin-right: 12px;">error</span>
        <span>Unable to load system health data</span>
    </div>
    <?php endif; ?>
    
    <div style="margin-top: 24px;">
        <button class="md3-button md3-button-filled" onclick="location.reload()">
            <span class="material-symbols-outlined" style="margin-right: 8px; vertical-align: middle;">refresh</span>
            Refresh Data
        </button>
    </div>
</div>


