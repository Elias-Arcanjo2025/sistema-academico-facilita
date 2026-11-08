<script>
(function () {
    function applyTheme(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        document.documentElement.setAttribute('data-bs-theme', theme);
        localStorage.setItem('theme', theme);
    }

    function bindThemeToggle(btnId, iconId) {
        var toggle = document.getElementById(btnId);
        var icon = document.getElementById(iconId);
        if (!toggle || !icon) return;

        var current = document.documentElement.getAttribute('data-theme') || 'light';
        icon.className = current === 'dark' ? 'bi bi-sun-fill' : 'bi bi-moon-fill';

        toggle.addEventListener('click', function () {
            var now = document.documentElement.getAttribute('data-theme');
            var next = now === 'dark' ? 'light' : 'dark';
            applyTheme(next);
            icon.className = next === 'dark' ? 'bi bi-sun-fill' : 'bi bi-moon-fill';
        });
    }

    bindThemeToggle('themeTogglePortal', 'themeIconPortal');
    bindThemeToggle('themeToggle', 'themeIcon');
    bindThemeToggle('themeToggleWelcome', 'themeIconWelcome');
    bindThemeToggle('themeToggleGuest', 'themeIconGuest');
})();
</script>
