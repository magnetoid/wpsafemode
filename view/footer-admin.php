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
    });
</script>

</body>

</html>