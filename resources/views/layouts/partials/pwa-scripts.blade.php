<script src="{{ asset('js/facilita-pwa.js') }}" defer></script>
@auth
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (window.facilitaPwa && window.facilitaPwa.initInstallBanner) {
        window.facilitaPwa.initInstallBanner('staffPwaInstall', 'staffPwaInstallBtn', 'staffPwaInstallDismiss', 'facilita_pwa_dismissed');
    }
});
</script>
@endauth
