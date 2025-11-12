<?php
/**
 * Quick Actions View - AdminLTE Design
 * Quick action buttons
 */
?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-bolt mr-2"></i>Quick Actions</h3>
            </div>
            <div class="card-body">
                <?php if(isset($data['quick_actions']['links'])): ?>
                <div class="quick-actions-grid">
                    <?php foreach($data['quick_actions']['links'] as $key => $quick_action_link): ?>
                    <div class="quick-action-card" data-action-link="<?php echo htmlspecialchars($quick_action_link['link']); ?>">
                        <i class="fas fa-cog"></i>
                        <h5><?php echo htmlspecialchars($quick_action_link['text']); ?></h5>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <?php if(isset($data['quick_actions']['data']['siteurl']) && isset($data['quick_actions']['data']['homeurl'])): ?>
                <div class="row mt-4">
                    <div class="col-md-6">
                        <div class="card card-primary card-outline">
                            <div class="card-header">
                                <h3 class="card-title">Quick Change Site URLs</h3>
                            </div>
                            <div class="card-body">
                                <form data-ajax data-endpoint="/api/submit?form=site_url">
                                    <div class="form-group">
                                        <label for="site_url">Site URL</label>
                                        <input type="text" name="site_url" id="site_url" class="form-control" 
                                               value="<?php echo htmlspecialchars($data['quick_actions']['data']['siteurl']['option_value'] ?? ''); ?>">
                                    </div>
                                    <div class="form-group">
                                        <label for="home_url">Home URL</label>
                                        <input type="text" name="home_url" id="home_url" class="form-control" 
                                               value="<?php echo htmlspecialchars($data['quick_actions']['data']['homeurl']['option_value'] ?? ''); ?>">
                                    </div>
                                    <button type="submit" name="submit_site_url" class="btn btn-primary">
                                        <i class="fas fa-save"></i> Save URLs
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle quick action card clicks
    document.querySelectorAll('.quick-action-card').forEach(card => {
        card.addEventListener('click', function() {
            const link = this.getAttribute('data-action-link');
            if (link) {
                // Extract action from link
                const match = link.match(/action=([^&]+)/);
                if (match) {
                    const action = match[1];
                    // Execute via AJAX
                    WPSafeMode.API.get('/api/action', {action: action})
                        .then(response => {
                            if (response.success) {
                                WPSafeMode.Utils.showMessage(response.message || 'Action completed', 'success');
                            }
                        })
                        .catch(error => {
                            WPSafeMode.Utils.showMessage('Error: ' + error.message, 'alert');
                        });
                }
            }
        });
    });
});
</script>


