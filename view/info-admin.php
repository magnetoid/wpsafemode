<?php
/**
 * Info View - AdminLTE Design
 * System information dashboard
 */
?>

<!-- Info Row -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-info-circle mr-2"></i>System Information</h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <!-- WordPress Core Info -->
                    <div class="col-md-6">
                        <div class="card card-primary card-outline">
                            <div class="card-header">
                                <h3 class="card-title">WordPress Core</h3>
                            </div>
                            <div class="card-body">
                                <?php if(isset($data['info']['core_info'])): ?>
                                <ul class="list-unstyled">
                                    <?php foreach($data['info']['core_info'] as $core_info): ?>
                                    <li class="mb-3">
                                        <strong><?php echo htmlspecialchars($core_info['name']); ?></strong>
                                        <div class="text-muted small"><?php echo htmlspecialchars($core_info['description']); ?></div>
                                        <div class="mt-1">
                                            <span class="badge badge-info">v<?php echo htmlspecialchars($core_info['version']); ?></span>
                                        </div>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- PHP & Server Info -->
                    <div class="col-md-6">
                        <div class="card card-success card-outline">
                            <div class="card-header">
                                <h3 class="card-title">PHP & Server</h3>
                            </div>
                            <div class="card-body">
                                <?php if(isset($data['info']['php_info'])): ?>
                                <ul class="list-unstyled">
                                    <?php foreach($data['info']['php_info'] as $php_slug => $php_info): ?>
                                    <li class="mb-2">
                                        <strong><?php echo htmlspecialchars($php_slug); ?></strong>
                                        <?php if($php_info['value'] != ''): ?>
                                        <span class="text-muted">: <?php echo htmlspecialchars($php_info['value']); ?></span>
                                        <?php endif; ?>
                                        <div class="text-muted small"><?php echo htmlspecialchars($php_info['description']); ?></div>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Plugins & Themes -->
                <div class="row mt-3">
                    <div class="col-md-6">
                        <div class="card card-warning card-outline">
                            <div class="card-header">
                                <h3 class="card-title">WordPress Themes</h3>
                            </div>
                            <div class="card-body">
                                <?php if(isset($data['info']['themes_versions'])): ?>
                                <ul class="list-unstyled">
                                    <?php foreach($data['info']['themes_versions'] as $theme_slug => $theme_info): ?>
                                    <li class="mb-2">
                                        <strong><?php echo htmlspecialchars($theme_info['theme_name']); ?></strong>
                                        <?php if(isset($theme_info['version'])): ?>
                                        <span class="badge badge-warning">v<?php echo htmlspecialchars($theme_info['version']); ?></span>
                                        <?php endif; ?>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="card card-info card-outline">
                            <div class="card-header">
                                <h3 class="card-title">Plugins</h3>
                            </div>
                            <div class="card-body">
                                <?php if(isset($data['info']['plugins_info'])): ?>
                                <div class="info-box mb-3">
                                    <span class="info-box-icon bg-info elevation-1">
                                        <i class="fas fa-plug"></i>
                                    </span>
                                    <div class="info-box-content">
                                        <span class="info-box-text">Total Plugins</span>
                                        <span class="info-box-number"><?php echo count($data['info']['plugins_info']); ?></span>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>


