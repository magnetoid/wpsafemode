/**
 * WP Safe Mode - Admin Custom JavaScript
 * Material Design 3 Integration
 */

(function() {
    'use strict';

    // Update page title and breadcrumb when navigating
    const pageTitles = {
        'info': 'Dashboard',
        'plugins': 'Plugins',
        'themes': 'Themes',
        'wpconfig': 'WP Config',
        'wpconfig_advanced': 'WP Config Advanced',
        'backup_database': 'Database Backup',
        'backup_files': 'File Backup',
        'htaccess': '.htaccess',
        'robots': 'robots.txt',
        'error_log': 'Error Log',
        'autobackup': 'Auto Backup',
        'quick_actions': 'Quick Actions',
        'global_settings': 'Global Settings',
        'ai-assistant': 'AI Assistant',
        'login': 'Login'
    };

    // Update UI when route changes
    function updatePageInfo(view) {
        const title = pageTitles[view] || 'Dashboard';
        const titleEl = document.getElementById('page-title');
        const breadcrumbEl = document.getElementById('breadcrumb-current');
        
        if (titleEl) {
            titleEl.textContent = title;
        }
        if (breadcrumbEl) {
            breadcrumbEl.textContent = title;
        }
        
        // Update active menu item
        document.querySelectorAll('.md3-list-item').forEach(item => {
            item.classList.remove('active');
        });
        
        const activeLink = document.querySelector(`.md3-list-item[data-view="${view}"]`);
        if (activeLink) {
            activeLink.classList.add('active');
        }
    }

    // Initialize Material Design 3 components
    document.addEventListener('DOMContentLoaded', function() {
        // Wait for WPSafeMode to be initialized
        if (typeof WPSafeMode === 'undefined') {
            setTimeout(arguments.callee, 100);
            return;
        }
        
        // Update page info on initial load
        const urlParams = new URLSearchParams(window.location.search);
        const view = urlParams.get('view') || 'info';
        updatePageInfo(view);
        
        // Listen for route changes
        if (WPSafeMode && WPSafeMode.Router) {
            const originalNavigate = WPSafeMode.Router.navigate;
            WPSafeMode.Router.navigate = function(view, params) {
                updatePageInfo(view);
                if (originalNavigate) {
                    originalNavigate.call(this, view, params);
                }
            };
        }
        
        // Initialize Material Design components
        initMaterialComponents();
        
        // Auto-dismiss snackbars after 5 seconds
        setInterval(function() {
            document.querySelectorAll('.md3-snackbar:not(.permanent)').forEach(function(snackbar) {
                setTimeout(function() {
                    snackbar.style.animation = 'slideOut 0.3s ease-in-out';
                    setTimeout(function() {
                        snackbar.remove();
                    }, 300);
                }, 5000);
            });
        }, 1000);
    });
    
    // Initialize Material Design 3 components
    function initMaterialComponents() {
        // Initialize text fields
        const textFields = document.querySelectorAll('.md3-text-field input, .md3-text-field textarea');
        textFields.forEach(field => {
            field.addEventListener('focus', function() {
                this.parentElement.classList.add('focused');
            });
            field.addEventListener('blur', function() {
                if (!this.value) {
                    this.parentElement.classList.remove('focused');
                }
            });
        });
        
        // Initialize buttons with ripple effect
        const buttons = document.querySelectorAll('.md3-button');
        buttons.forEach(button => {
            button.addEventListener('click', function(e) {
                createRipple(e, this);
            });
        });
        
        // Initialize icon buttons
        const iconButtons = document.querySelectorAll('.md3-icon-button');
        iconButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                createRipple(e, this);
            });
        });
    }
    
    // Create ripple effect for buttons
    function createRipple(event, element) {
        const ripple = document.createElement('span');
        const rect = element.getBoundingClientRect();
        const size = Math.max(rect.width, rect.height);
        const x = event.clientX - rect.left - size / 2;
        const y = event.clientY - rect.top - size / 2;
        
        ripple.style.width = ripple.style.height = size + 'px';
        ripple.style.left = x + 'px';
        ripple.style.top = y + 'px';
        ripple.classList.add('ripple');
        
        element.style.position = 'relative';
        element.style.overflow = 'hidden';
        element.appendChild(ripple);
        
        setTimeout(() => {
            ripple.remove();
        }, 600);
    }
    
    // Enhance forms with Material Design 3 styling
    function enhanceForms() {
        const forms = document.querySelectorAll('form');
        forms.forEach(form => {
            if (!form.classList.contains('md3-form')) {
                form.classList.add('md3-form');
                
                // Convert inputs to Material Design 3 text fields
                const inputs = form.querySelectorAll('input[type="text"], input[type="email"], input[type="password"], textarea, select');
                inputs.forEach(input => {
                    if (!input.closest('.md3-text-field')) {
                        const wrapper = document.createElement('div');
                        wrapper.className = 'md3-text-field';
                        input.parentNode.insertBefore(wrapper, input);
                        wrapper.appendChild(input);
                        
                        if (input.placeholder) {
                            const label = document.createElement('label');
                            label.textContent = input.placeholder;
                            input.placeholder = '';
                            wrapper.appendChild(label);
                        }
                    }
                });
            }
        });
    }

    // Enhance tables with Material Design 3 styling
    function enhanceTables() {
        const tables = document.querySelectorAll('table');
        tables.forEach(table => {
            if (!table.classList.contains('md3-table')) {
                table.classList.add('md3-table');
            }
        });
    }
    
    // Enhance buttons with Material Design 3 styling
    function enhanceButtons() {
        const buttons = document.querySelectorAll('button, .btn, input[type="submit"]');
        buttons.forEach(button => {
            if (!button.classList.contains('md3-button') && !button.classList.contains('md3-icon-button')) {
                if (button.classList.contains('btn-primary') || button.type === 'submit') {
                    button.classList.add('md3-button', 'md3-button-filled');
                } else if (button.classList.contains('btn-outline')) {
                    button.classList.add('md3-button', 'md3-button-outlined');
                } else {
                    button.classList.add('md3-button', 'md3-button-text');
                }
            }
        });
    }

    // Call enhancements after content loads
    if (WPSafeMode && WPSafeMode.Router) {
        const originalRenderView = WPSafeMode.Router.renderView;
        WPSafeMode.Router.renderView = function(html) {
            if (originalRenderView) {
                originalRenderView.call(this, html);
            }
            setTimeout(() => {
                enhanceForms();
                enhanceTables();
                enhanceButtons();
                initMaterialComponents();
            }, 100);
        };
    }
    
    // Add ripple effect styles
    const style = document.createElement('style');
    style.textContent = `
        .ripple {
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.6);
            transform: scale(0);
            animation: ripple-animation 0.6s ease-out;
            pointer-events: none;
        }
        
        @keyframes ripple-animation {
            to {
                transform: scale(4);
                opacity: 0;
            }
        }
        
        @keyframes slideOut {
            from {
                transform: translateY(0);
                opacity: 1;
            }
            to {
                transform: translateY(-100%);
                opacity: 0;
            }
        }
    `;
    document.head.appendChild(style);

})();
