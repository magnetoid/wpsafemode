    </main>
</div>

<!-- Material Components for Web JS -->
<script src="https://unpkg.com/material-components-web@latest/dist/material-components-web.min.js"></script>

<!-- Modern JavaScript Application -->
<script src="assets/js/app.js"></script>

<!-- Base Module -->
<script src="assets/js/modules/base.module.js"></script>

<!-- Module Loader -->
<script src="assets/js/modules/login.module.js"></script>
<script src="assets/js/modules/info.module.js"></script>
<script src="assets/js/modules/plugins.module.js"></script>
<script src="assets/js/modules/themes.module.js"></script>
<script src="assets/js/modules/wpconfig.module.js"></script>
<script src="assets/js/modules/wpconfig-advanced.module.js"></script>
<script src="assets/js/modules/backup-database.module.js"></script>
<script src="assets/js/modules/backup-files.module.js"></script>
<script src="assets/js/modules/htaccess.module.js"></script>
<script src="assets/js/modules/robots.module.js"></script>
<script src="assets/js/modules/error-log.module.js"></script>
<script src="assets/js/modules/autobackup.module.js"></script>
<script src="assets/js/modules/quick-actions.module.js"></script>
<script src="assets/js/modules/global-settings.module.js"></script>
<script src="assets/js/modules/ai-assistant.module.js"></script>
<script src="assets/js/modules/system-health.module.js"></script>
<script src="assets/js/modules/file-manager.module.js"></script>
<script src="assets/js/modules/users.module.js"></script>
<script src="assets/js/modules/cron.module.js"></script>
<script src="assets/js/modules/database-query.module.js"></script>

<!-- Custom Admin JS -->
<script src="assets/js/admin-custom.js"></script>

<script>
// Material Design 3 Navigation Drawer Toggle
(function() {
    const menuToggle = document.getElementById('menu-toggle');
    const drawer = document.getElementById('navigation-drawer');
    const overlay = document.getElementById('drawer-overlay');
    const content = document.querySelector('.md3-content');
    
    if (menuToggle && drawer) {
        menuToggle.addEventListener('click', function() {
            if (window.innerWidth <= 960) {
                drawer.classList.toggle('open');
                overlay.classList.toggle('show');
            } else {
                drawer.classList.toggle('closed');
                content.classList.toggle('full-width');
            }
        });
        
        if (overlay) {
            overlay.addEventListener('click', function() {
                drawer.classList.remove('open');
                overlay.classList.remove('show');
            });
        }
        
        // Close drawer when clicking a menu item on mobile
        const menuItems = drawer.querySelectorAll('.md3-list-item');
        menuItems.forEach(item => {
            item.addEventListener('click', function() {
                if (window.innerWidth <= 960) {
                    drawer.classList.remove('open');
                    overlay.classList.remove('show');
                }
            });
        });
        
        // Handle window resize
        let resizeTimer;
        window.addEventListener('resize', function() {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(function() {
                if (window.innerWidth > 960) {
                    drawer.classList.remove('open');
                    overlay.classList.remove('show');
                } else {
                    drawer.classList.add('closed');
                    content.classList.add('full-width');
                }
            }, 250);
        });
    }
})();
</script>

</body>
</html>
