<?php
/**
 * Login View - AdminLTE Design
 * User authentication
 */
?>

<div class="login-box">
    <div class="login-logo">
        <a href="?view=info" data-view="info">
            <b>WP</b> Safe Mode
        </a>
    </div>
    
    <div class="card">
        <div class="card-body login-card-body">
            <p class="login-box-msg">Sign in to start your session</p>

            <form id="login-form" data-ajax data-endpoint="/api/submit?form=login">
                <input type="hidden" name="csrf_token" id="csrf-token" value="">
                
                <div class="input-group mb-3">
                    <input type="text" name="username" class="form-control" placeholder="Username or Email" required>
                    <div class="input-group-append">
                        <div class="input-group-text">
                            <span class="fas fa-user"></span>
                        </div>
                    </div>
                </div>
                
                <div class="input-group mb-3">
                    <input type="password" name="password" class="form-control" placeholder="Password" required>
                    <div class="input-group-append">
                        <div class="input-group-text">
                            <span class="fas fa-lock"></span>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-12">
                        <button type="submit" name="submit_login" class="btn btn-primary btn-block">
                            <i class="fas fa-sign-in-alt mr-2"></i> Sign In
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.login-box {
    width: 360px;
    margin: 7% auto;
}

.login-logo {
    font-size: 2.1rem;
    font-weight: 300;
    margin-bottom: 0.9rem;
    text-align: center;
}

.login-logo a {
    color: #495057;
    text-decoration: none;
}

.login-logo a:hover {
    color: #007bff;
}

.login-card-body {
    padding: 2rem;
}

body.login-page {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    min-height: 100vh;
}
</style>

<script>
// Get CSRF token on load
document.addEventListener('DOMContentLoaded', async function() {
    try {
        const response = await WPSafeMode.API.get('/api/csrf', {form: 'login'});
        if (response.data && response.data.token) {
            document.getElementById('csrf-token').value = response.data.token;
        }
    } catch (error) {
        console.error('Error loading CSRF token:', error);
    }
});
</script>


