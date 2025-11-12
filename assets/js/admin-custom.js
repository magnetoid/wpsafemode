/**
 * WP Safe Mode - Admin Custom JavaScript
 * Custom functionality for AdminLTE integration
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
        document.querySelectorAll('.nav-link').forEach(link => {
            link.classList.remove('active');
        });
        
        const activeLink = document.querySelector(`.nav-link[data-view="${view}"]`);
        if (activeLink) {
            activeLink.classList.add('active');
        }
    }

    // Enhance AdminLTE with our app
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
        
        // Enhance alerts - use Bootstrap dismiss
        $(document).on('click', '.alert .close', function() {
            $(this).closest('.alert').fadeOut(function() {
                $(this).remove();
            });
        });
        
        // Auto-dismiss alerts after 5 seconds
        setInterval(function() {
            $('.alert:not(.alert-permanent)').each(function() {
                const alert = $(this);
                if (!alert.hasClass('show')) {
                    alert.fadeOut(function() {
                        $(this).remove();
                    });
                }
            });
        }, 5000);
        
        // Initialize AdminLTE components
        if (window.AdminLTE) {
            // AdminLTE should auto-initialize, but ensure it's ready
            $(document).ready(function() {
                // Initialize any widgets
                $('[data-widget="pushmenu"]').PushMenu();
                
                // Mobile sidebar handling
                initMobileSidebar();
            });
        }
    });
    
    // Mobile sidebar functionality
    function initMobileSidebar() {
        // Create sidebar overlay for mobile
        if (!document.getElementById('sidebar-overlay')) {
            const overlay = document.createElement('div');
            overlay.id = 'sidebar-overlay';
            overlay.className = 'sidebar-overlay';
            overlay.addEventListener('click', function() {
                closeMobileSidebar();
            });
            document.body.appendChild(overlay);
        }
        
        // Handle sidebar toggle on mobile
        $(document).on('click', '[data-widget="pushmenu"]', function(e) {
            if (window.innerWidth <= 768) {
                e.preventDefault();
                toggleMobileSidebar();
            }
        });
        
        // Close sidebar when clicking outside on mobile
        $(document).on('click', '.main-sidebar .nav-link', function() {
            if (window.innerWidth <= 768) {
                setTimeout(closeMobileSidebar, 300);
            }
        });
        
        // Handle window resize
        let resizeTimer;
        $(window).on('resize', function() {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(function() {
                if (window.innerWidth > 768) {
                    closeMobileSidebar();
                }
            }, 250);
        });
    }
    
    function toggleMobileSidebar() {
        document.body.classList.toggle('sidebar-open');
        const sidebar = document.querySelector('.main-sidebar');
        if (sidebar) {
            sidebar.classList.toggle('sidebar-open');
        }
    }
    
    function closeMobileSidebar() {
        document.body.classList.remove('sidebar-open');
        const sidebar = document.querySelector('.main-sidebar');
        if (sidebar) {
            sidebar.classList.remove('sidebar-open');
        }
    }

    // Enhance forms with AdminLTE styling
    function enhanceForms() {
        $('form').each(function() {
            if (!$(this).hasClass('form-modern')) {
                $(this).addClass('form-modern');
            }
        });
    }

    // Enhance tables
    function enhanceTables() {
        $('table').each(function() {
            const $table = $(this);
            if (!$table.hasClass('table-modern')) {
                $table.addClass('table-modern table table-striped table-hover');
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
            }, 100);
        };
    }

})();


