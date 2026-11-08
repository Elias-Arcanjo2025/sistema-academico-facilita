/**
 * PWA Facilita — registo apenas em telemóvel/tablet.
 * Em computador: versão web normal, sem service worker nem manifest.
 */
(function () {
    'use strict';

    function isMobileDevice() {
        if (window.matchMedia('(max-width: 768px)').matches) {
            return true;
        }
        var ua = navigator.userAgent || '';
        return /Android|iPhone|iPad|iPod|Mobile|webOS|BlackBerry|IEMobile|Opera Mini/i.test(ua);
    }

    if (!isMobileDevice()) {
        document.querySelectorAll('link[rel="manifest"]').forEach(function (el) {
            el.parentNode.removeChild(el);
        });
        document.querySelectorAll('meta[name="mobile-web-app-capable"], meta[name="apple-mobile-web-app-capable"]').forEach(function (el) {
            el.parentNode.removeChild(el);
        });
        return;
    }

    document.documentElement.classList.add('facilita-pwa-mobile');

    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function () {
            navigator.serviceWorker.register('/sw-app.js', { scope: '/' }).catch(function () {});
        });
    }

    window.facilitaPwa = {
        isMobile: true,
        deferredPrompt: null,
        initInstallBanner: function (bannerId, btnId, dismissId, storageKey) {
            var banner = document.getElementById(bannerId);
            var btn = document.getElementById(btnId);
            var dismiss = dismissId ? document.getElementById(dismissId) : null;
            if (!banner || !btn) {
                return;
            }

            var dismissed = localStorage.getItem(storageKey || 'facilita_pwa_dismissed') === '1';

            window.addEventListener('beforeinstallprompt', function (e) {
                e.preventDefault();
                window.facilitaPwa.deferredPrompt = e;
                if (!dismissed && window.matchMedia('(display-mode: browser)').matches) {
                    banner.classList.remove('d-none');
                }
            });

            if (window.matchMedia('(display-mode: standalone)').matches) {
                banner.classList.add('d-none');
            }

            btn.addEventListener('click', async function () {
                if (window.facilitaPwa.deferredPrompt) {
                    window.facilitaPwa.deferredPrompt.prompt();
                    await window.facilitaPwa.deferredPrompt.userChoice;
                    window.facilitaPwa.deferredPrompt = null;
                    banner.classList.add('d-none');
                    return;
                }
                alert('Menu do browser → «Instalar aplicação» ou «Adicionar ao ecrã principal».');
            });

            if (dismiss) {
                dismiss.addEventListener('click', function () {
                    localStorage.setItem(storageKey || 'facilita_pwa_dismissed', '1');
                    banner.classList.add('d-none');
                });
            }
        },
    };
})();
