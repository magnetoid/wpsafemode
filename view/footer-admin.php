<!-- Content ends here -->

</main>

</div>

<!-- Premium Safe Mode JavaScript -->
<script>
    // Simple navigation highlight
    document.addEventListener('DOMContentLoaded', function () {
        const navItems = document.querySelectorAll('.nav-item');
        navItems.forEach(item => {
            item.addEventListener('click', function () {
                navItems.forEach(i => i.classList.remove('active'));
                this.classList.add('active');
            });
        });

        // Theme Toggle Logic
        const themeToggle = document.getElementById('theme-toggle');
        const themeIcon = document.getElementById('theme-icon');
        const body = document.body;

        // Load saved theme
        const savedTheme = localStorage.getItem('wpsafemode_theme');
        if (savedTheme) {
            body.setAttribute('data-theme', savedTheme);
            updateIcon(savedTheme);
        }

        if (themeToggle) {
            themeToggle.addEventListener('click', () => {
                const currentTheme = body.getAttribute('data-theme');
                const newTheme = currentTheme === 'light' ? 'dark' : 'light';

                body.setAttribute('data-theme', newTheme);
                localStorage.setItem('wpsafemode_theme', newTheme);
                updateIcon(newTheme);
            });
        }

        function updateIcon(theme) {
            if (themeIcon) {
                themeIcon.textContent = theme === 'light' ? 'dark_mode' : 'light_mode';
            }
        }
    });
</script>

</body>

</html>