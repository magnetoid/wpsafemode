<?php
/**
 * Global Settings View - AdminLTE Design
 */
?>

<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">Global Settings</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="#" data-view="info">Home</a></li>
                    <li class="breadcrumb-item active">Global Settings</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<section class="content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-8">
                <div class="card card-primary">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-cog mr-2"></i>Application Settings
                        </h3>
                    </div>
                    <form id="global-settings-form" data-ajax data-endpoint="/api/submit?form=global_settings" method="post">
                        <div class="card-body">
                            <!-- Login Settings -->
                            <div class="form-group">
                                <label for="username">Username</label>
                                <input type="text" 
                                       class="form-control" 
                                       id="username" 
                                       name="username" 
                                       placeholder="Set your login username" 
                                       value="<?php echo htmlspecialchars($data['global_settings']['login']['username'] ?? ''); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="email">Email</label>
                                <input type="email" 
                                       class="form-control" 
                                       id="email" 
                                       name="email" 
                                       placeholder="Set your email" 
                                       value="<?php echo htmlspecialchars($data['global_settings']['login']['email'] ?? ''); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="password">Password</label>
                                <input type="password" 
                                       class="form-control" 
                                       id="password" 
                                       name="password" 
                                       placeholder="Set your password (leave blank to keep current)">
                            </div>
                            
                            <div class="form-group">
                                <label for="repeat_password">Repeat Password</label>
                                <input type="password" 
                                       class="form-control" 
                                       id="repeat_password" 
                                       name="repeat_password" 
                                       placeholder="Repeat your password">
                            </div>
                            
                            <hr>
                            
                            <!-- API Keys -->
                            <h5><i class="fas fa-key mr-2"></i>API Keys</h5>
                            
                            <div class="form-group">
                                <label for="api_key">WP Safe Mode API Key</label>
                                <input type="text" 
                                       class="form-control" 
                                       id="api_key" 
                                       name="api_key" 
                                       placeholder="Set your WP Safe Mode API key" 
                                       value="<?php echo htmlspecialchars($data['global_settings']['api_key_value']['api_key'] ?? ''); ?>">
                                <small class="form-text text-muted">Optional: For WP Safe Mode services</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="openai_api_key">OpenAI API Key</label>
                                <input type="password" 
                                       class="form-control" 
                                       id="openai_api_key" 
                                       name="openai_api_key" 
                                       placeholder="sk-..." 
                                       value="<?php echo htmlspecialchars($data['global_settings']['api_key_value']['openai_api_key'] ?? ''); ?>">
                                <small class="form-text text-muted">
                                    Required for AI Assistant features. 
                                    <a href="https://platform.openai.com/api-keys" target="_blank">Get your API key</a>
                                </small>
                            </div>
                        </div>
                        <div class="card-footer">
                            <button type="submit" name="submit_global_settings" class="btn btn-primary">
                                <i class="fas fa-save mr-2"></i>Save Settings
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card card-info">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-info-circle mr-2"></i>About Settings
                        </h3>
                    </div>
                    <div class="card-body">
                        <h6>Login Credentials</h6>
                        <p>Set your username and password to secure access to WP Safe Mode.</p>
                        
                        <h6>API Keys</h6>
                        <p><strong>WP Safe Mode API Key:</strong> Optional key for WP Safe Mode services.</p>
                        <p><strong>OpenAI API Key:</strong> Required for AI Assistant features. Get your key from <a href="https://platform.openai.com/api-keys" target="_blank">OpenAI Platform</a>.</p>
                        
                        <div class="alert alert-warning mt-3">
                            <i class="fas fa-exclamation-triangle mr-2"></i>
                            <strong>Security Note:</strong> Keep your API keys secure and never share them publicly.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

