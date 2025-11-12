/**
 * Login Module
 * Handles login functionality
 */

window.LoginModule = class extends BaseModule {
    constructor() {
        super();
        this.csrfToken = null;
    }
    
    async load(view, action) {
        try {
            // Get CSRF token
            const tokenResponse = await WPSafeMode.API.get('/api/csrf', {form: 'login'});
            this.csrfToken = tokenResponse.data.token;
            
            // Render view
            await this.render();
            
            // Initialize event handlers
            this.initHandlers();
        } catch (error) {
            console.error('Error loading login:', error);
            await this.render();
        }
    }
    
    async render() {
        // Try to load admin view first
        try {
            const viewResponse = await WPSafeMode.API.get('/api/view', {view: 'login'});
            if (viewResponse.data && viewResponse.data.html) {
                this.updateContent(viewResponse.data.html);
                // Update CSRF token
                setTimeout(() => {
                    const tokenInput = document.getElementById('csrf-token');
                    if (tokenInput) {
                        tokenInput.value = this.csrfToken;
                    }
                }, 100);
                return;
            }
        } catch (error) {
            console.warn('Could not load admin login view, using fallback');
        }
        
        // Fallback: render AdminLTE login form
        const html = `
            <div class="login-box">
                <div class="login-logo">
                    <a href="?view=info" data-view="info"><b>WP</b> Safe Mode</a>
                </div>
                <div class="card">
                    <div class="card-body login-card-body">
                        <p class="login-box-msg">Sign in to start your session</p>
                        <form id="login-form" data-ajax data-endpoint="/api/submit?form=login">
                            <input type="hidden" name="csrf_token" id="csrf-token" value="${this.csrfToken || ''}">
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
        `;
        this.updateContent(html);
    }
    
    initHandlers() {
        const form = document.getElementById('login-form');
        if (form) {
            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                await this.handleLogin();
            });
        }
    }
    
    async handleLogin() {
        const form = document.getElementById('login-form');
        const formData = new FormData(form);
        const submitButton = form.querySelector('button[type="submit"]');
        const originalText = submitButton.textContent;
        
        submitButton.disabled = true;
        submitButton.textContent = 'Logging in...';
        
        try {
            const response = await WPSafeMode.API.post('/api/submit?form=login', formData);
            
            if (response.success) {
                WPSafeMode.Utils.showMessage(response.message || 'Login successful', 'success');
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            } else {
                WPSafeMode.Utils.showMessage(response.message || 'Login failed', 'alert');
            }
        } catch (error) {
            WPSafeMode.Utils.showMessage('Error: ' + error.message, 'alert');
        } finally {
            submitButton.disabled = false;
            submitButton.textContent = originalText;
        }
    }
    
    cleanup() {
        // Cleanup if needed
    }
};

