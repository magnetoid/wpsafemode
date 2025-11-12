<?php
/**
 * Login View - Material Design 3
 * User authentication
 */
?>

<div class="md3-card" style="max-width: 400px; margin: 0 auto;">
    <div style="text-align: center; margin-bottom: 32px;">
        <img src="assets/img/safemode-logo.png" alt="WP Safe Mode" style="width: 64px; height: 64px; border-radius: 50%; margin-bottom: 16px;" onerror="this.style.display='none'">
        <h1 class="md3-card-title" style="margin: 0;">WP Safe Mode</h1>
        <p class="md3-card-subtitle" style="margin: 8px 0 0 0;">Sign in to start your session</p>
    </div>

    <form id="login-form" data-ajax data-endpoint="/api/submit?form=login">
        <input type="hidden" name="csrf_token" id="csrf-token" value="">
        
        <div class="md3-text-field">
            <input type="text" name="username" id="username" required>
            <label for="username">Username or Email</label>
            <span class="material-symbols-outlined" style="position: absolute; right: 16px; top: 50%; transform: translateY(-50%); pointer-events: none; color: var(--md-sys-color-on-surface-variant);">person</span>
        </div>
        
        <div class="md3-text-field">
            <input type="password" name="password" id="password" required>
            <label for="password">Password</label>
            <span class="material-symbols-outlined" style="position: absolute; right: 16px; top: 50%; transform: translateY(-50%); pointer-events: none; color: var(--md-sys-color-on-surface-variant);">lock</span>
        </div>
        
        <button type="submit" name="submit_login" class="md3-button md3-button-filled" style="width: 100%; margin-top: 24px;">
            <span class="material-symbols-outlined" style="margin-right: 8px; vertical-align: middle;">login</span>
            Sign In
        </button>
    </form>
</div>

<style>
.login-page .md3-card {
    box-shadow: var(--md-sys-elevation-level3);
}

.md3-text-field {
    position: relative;
}

.md3-text-field input {
    padding-right: 48px;
}

.md3-text-field .material-symbols-outlined {
    font-size: 20px;
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
