@once
@push('styles')
<style>
    .facilita-lock-overlay {
        display: none;
        position: fixed;
        inset: 0;
        z-index: 1040;
        background: rgba(15, 23, 42, 0.88);
        backdrop-filter: blur(10px);
    }
    .facilita-lock-overlay.is-active { display: block; }
    body.facilita-locked { overflow: hidden !important; }
    body.facilita-locked .facilita-lock-overlay { pointer-events: auto; }
</style>
@endpush
@endonce
<script>
(function () {
    var config = window.facilitaBloqueioInactividade;
    if (!config || !config.activo) return;

    var LOCK_KEY = 'facilita_sessao_bloqueada';
    var ACTIVITY_KEY = 'facilita_ultima_actividade';
    var timeoutMs = Math.max(1, Number(config.minutos) || 30) * 60 * 1000;
    var overlay = document.getElementById('facilita-lock-overlay');
    var prompting = false;
    var lastTouchWrite = 0;

    function csrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.content : '';
    }

    function storageGet(key) {
        try { return localStorage.getItem(key); } catch (e) { return null; }
    }

    function storageSet(key, value) {
        try { localStorage.setItem(key, value); } catch (e) {}
    }

    function storageRemove(key) {
        try { localStorage.removeItem(key); } catch (e) {}
    }

    function isLocked() {
        return storageGet(LOCK_KEY) === '1';
    }

    function lastActivityMs() {
        var local = parseInt(storageGet(ACTIVITY_KEY) || '0', 10) || 0;
        var server = parseInt(config.ultimaActividadeServidor || '0', 10) || 0;
        return Math.max(local, server);
    }

    function touchActivity(force) {
        if (isLocked()) return;
        var now = Date.now();
        // Evita escrita excessiva em mousemove; força em navegação/página
        if (!force && (now - lastTouchWrite) < 1000) return;
        lastTouchWrite = now;
        storageSet(ACTIVITY_KEY, String(now));
    }

    function activateLock() {
        storageSet(LOCK_KEY, '1');
        if (overlay) {
            overlay.classList.add('is-active');
            overlay.setAttribute('aria-hidden', 'false');
        }
        document.body.classList.add('facilita-locked');
        fetch(config.urlBloquear, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: '{}',
        }).then(function (res) {
            return res.json().catch(function () { return {}; });
        }).then(function (data) {
            // Servidor recusou (ainda há actividade noutro separador/pedido)
            if (data && data.locked === false) {
                deactivateLock();
            }
        }).catch(function () {});
        showUnlockPrompt();
    }

    function deactivateLock() {
        storageRemove(LOCK_KEY);
        // limpar chave antiga por separador, se existir
        try { sessionStorage.removeItem(LOCK_KEY); } catch (e) {}
        touchActivity(true);
        if (overlay) {
            overlay.classList.remove('is-active');
            overlay.setAttribute('aria-hidden', 'true');
        }
        document.body.classList.remove('facilita-locked');
        prompting = false;
    }

    function checkIdle() {
        if (isLocked()) {
            activateLock();
            return;
        }
        var last = lastActivityMs() || Date.now();
        if (Date.now() - last >= timeoutMs) {
            activateLock();
        }
    }

    async function showUnlockPrompt() {
        if (prompting || typeof Swal === 'undefined') return;
        prompting = true;

        while (isLocked()) {
            var result = await Swal.fire({
                title: 'Sessão bloqueada',
                html: '<p class="small mb-0">Por inactividade, o sistema foi bloqueado.<br>Introduza a sua palavra-passe para continuar.</p>',
                input: 'password',
                inputAttributes: {
                    autocomplete: 'current-password',
                    autocapitalize: 'off',
                    spellcheck: 'false',
                },
                allowOutsideClick: false,
                allowEscapeKey: false,
                confirmButtonText: 'Desbloquear',
                showCancelButton: false,
                backdrop: true,
                customClass: { popup: 'facilita-unlock-popup' },
                didOpen: function () {
                    var input = Swal.getInput();
                    if (input) input.focus();
                },
                preConfirm: async function (password) {
                    if (!password) {
                        Swal.showValidationMessage('Introduza a palavra-passe.');
                        return false;
                    }
                    try {
                        var res = await fetch(config.url, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': csrfToken(),
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            body: JSON.stringify({ password: password }),
                        });
                        var data = await res.json().catch(function () { return {}; });
                        if (!res.ok) {
                            var msg = data.message || (data.errors && data.errors.password && data.errors.password[0]) || 'Palavra-passe incorrecta.';
                            Swal.showValidationMessage(msg);
                            return false;
                        }
                        return true;
                    } catch (e) {
                        Swal.showValidationMessage('Não foi possível verificar. Tente novamente.');
                        return false;
                    }
                },
            });

            if (result.isConfirmed) {
                deactivateLock();
                break;
            }
        }

        prompting = false;
    }

    ['click', 'keydown', 'mousemove', 'scroll', 'touchstart'].forEach(function (evt) {
        document.addEventListener(evt, function () { touchActivity(false); }, { passive: true });
    });

    // Sincronizar actividade / desbloqueio entre separadores
    window.addEventListener('storage', function (event) {
        if (event.key === ACTIVITY_KEY && event.newValue) {
            lastTouchWrite = parseInt(event.newValue, 10) || Date.now();
        }
        if (event.key === LOCK_KEY && event.newValue === null && isLocked() === false) {
            deactivateLock();
        }
        if (event.key === LOCK_KEY && event.newValue === '1' && !document.body.classList.contains('facilita-locked')) {
            activateLock();
        }
    });

    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'visible') checkIdle();
    });

    // Migrar actividade antiga do sessionStorage (por separador) se existir
    try {
        var legado = sessionStorage.getItem(ACTIVITY_KEY);
        if (legado && !storageGet(ACTIVITY_KEY)) {
            storageSet(ACTIVITY_KEY, legado);
        }
        sessionStorage.removeItem(ACTIVITY_KEY);
        if (sessionStorage.getItem(LOCK_KEY) === '1') {
            storageSet(LOCK_KEY, '1');
            sessionStorage.removeItem(LOCK_KEY);
        }
    } catch (e) {}

    if (isLocked() || config.lockedOnServer) {
        if (config.lockedOnServer) {
            storageSet(LOCK_KEY, '1');
        }
        activateLock();
    } else {
        // Navegar / carregar uma página conta como actividade
        touchActivity(true);
        checkIdle();
    }

    setInterval(checkIdle, 15000);
})();
</script>
