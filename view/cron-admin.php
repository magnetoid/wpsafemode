<?php
/**
 * Cron Jobs View - Material Design 3
 * Manage WordPress cron jobs
 */
?>

<div class="md3-card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
        <div>
            <h2 class="md3-card-title">Cron Jobs</h2>
            <p class="md3-card-subtitle">Manage WordPress scheduled tasks</p>
        </div>
        <button class="md3-button md3-button-filled" onclick="refreshCron()">
            <span class="material-symbols-outlined" style="margin-right: 8px; vertical-align: middle;">refresh</span>
            Refresh
        </button>
    </div>

    <?php if (isset($data['cron_jobs']) && !empty($data['cron_jobs'])): ?>
        <div class="md3-table-container">
            <table class="md3-table">
                <thead>
                    <tr>
                        <th>Hook</th>
                        <th>Schedule</th>
                        <th>Next Run</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data['cron_jobs'] as $job): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($job['hook']); ?></strong></td>
                            <td>
                                <?php if ($job['schedule']): ?>
                                    <span class="md3-chip"><?php echo htmlspecialchars($job['schedule']); ?></span>
                                <?php else: ?>
                                    <span class="md3-chip">One-time</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo $job['next_run_formatted']; ?>
                                <?php if ($job['is_past_due']): ?>
                                    <span class="md3-chip error" style="margin-left: 8px;">Past Due</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($job['is_past_due']): ?>
                                    <span class="md3-chip error">Overdue</span>
                                <?php else: ?>
                                    <span class="md3-chip success">Scheduled</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="display: flex; gap: 4px;">
                                    <button class="md3-button md3-button-text"
                                        onclick="runCronJob('<?php echo htmlspecialchars($job['hook']); ?>')" title="Run Now">
                                        <span class="material-symbols-outlined"
                                            style="margin-right: 4px; font-size: 18px;">play_arrow</span>
                                        Run
                                    </button>
                                    <button class="md3-icon-button"
                                        onclick="deleteCronJob('<?php echo htmlspecialchars($job['hook']); ?>', <?php echo $job['timestamp']; ?>)"
                                        title="Delete" style="color: var(--md-sys-color-error);">
                                        <span class="material-symbols-outlined">delete</span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="md3-snackbar info">
            <span class="material-symbols-outlined" style="margin-right: 12px;">info</span>
            <span>No cron jobs found</span>
        </div>
    <?php endif; ?>
</div>

<script>
    function refreshCron() {
        WPSafeMode.Router.navigate('cron');
    }

    function runCronJob(hook) {
        if (!confirm('Run this cron job now?')) {
            return;
        }

        WPSafeMode.Utils.showLoading(true);

        WPSafeMode.API.post('/api/cron?action=run', { hook: hook })
            .then(response => {
                if (response.success) {
                    WPSafeMode.Utils.showMessage(response.message || 'Cron job executed successfully', 'success');
                    refreshCron();
                } else {
                    WPSafeMode.Utils.showMessage(response.message || 'Failed to run cron job', 'error');
                }
            })
            .catch(error => {
                WPSafeMode.Utils.showMessage('Error: ' + error.message, 'error');
            })
            .finally(() => {
                WPSafeMode.Utils.showLoading(false);
            });
    }

    function deleteCronJob(hook, timestamp) {
        if (!confirm('Delete this cron job?')) {
            return;
        }

        WPSafeMode.Utils.showLoading(true);

        WPSafeMode.API.post('/api/cron?action=delete', { hook: hook, timestamp: timestamp })
            .then(response => {
                if (response.success) {
                    WPSafeMode.Utils.showMessage(response.message || 'Cron job deleted successfully', 'success');
                    refreshCron();
                } else {
                    WPSafeMode.Utils.showMessage(response.message || 'Failed to delete cron job', 'error');
                }
            })
            .catch(error => {
                WPSafeMode.Utils.showMessage('Error: ' + error.message, 'error');
            })
            .finally(() => {
                WPSafeMode.Utils.showLoading(false);
            });
    }
</script>