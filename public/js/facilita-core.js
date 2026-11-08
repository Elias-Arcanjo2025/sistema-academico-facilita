/**
 * Facilita Core — command palette, atalhos, skeleton, simulador, modo mobile.
 */
(function () {
    'use strict';

    var debounceTimer = null;

    function isEditableTarget(el) {
        if (!el) {
            return false;
        }
        var tag = (el.tagName || '').toLowerCase();
        if (tag === 'input' || tag === 'textarea' || tag === 'select') {
            return true;
        }
        return !!el.isContentEditable;
    }

    function modKey(e) {
        return e.ctrlKey || e.metaKey;
    }

    function initCommandPalette() {
        var palette = document.getElementById('fCommandPalette');
        var input = document.getElementById('fCommandInput');
        var results = document.getElementById('fCommandResults');
        var help = document.getElementById('fShortcutsHelp');
        var helpBtn = document.getElementById('fCommandHelpBtn');
        var helpClose = document.getElementById('fShortcutsHelpClose');
        var menuAtalhos = document.getElementById('menuAtalhosTeclado');
        var searchUrl = document.body.dataset.pesquisaUrl;
        var activeIndex = 0;

        if (!palette || !input || !results) {
            return;
        }

        var urls = {};
        try {
            urls = JSON.parse(palette.getAttribute('data-atalhos-urls') || '{}') || {};
        } catch (e) {
            urls = {};
        }

        function closePalette() {
            palette.classList.remove('is-open');
            palette.setAttribute('aria-hidden', 'true');
            input.value = '';
            results.innerHTML = '';
            activeIndex = 0;
        }

        function openPalette() {
            closeHelp();
            palette.classList.add('is-open');
            palette.setAttribute('aria-hidden', 'false');
            input.focus();
            search('');
        }

        function closeHelp() {
            if (!help) {
                return;
            }
            help.classList.remove('is-open');
            help.setAttribute('aria-hidden', 'true');
        }

        function openHelp() {
            if (!help) {
                return;
            }
            closePalette();
            help.classList.add('is-open');
            help.setAttribute('aria-hidden', 'false');
        }

        function toggleHelp() {
            if (help && help.classList.contains('is-open')) {
                closeHelp();
            } else {
                openHelp();
            }
        }

        function getItems() {
            return Array.prototype.slice.call(results.querySelectorAll('.f-command-palette__item'));
        }

        function setActive(index) {
            var items = getItems();
            if (!items.length) {
                return;
            }
            activeIndex = (index + items.length) % items.length;
            items.forEach(function (el, i) {
                el.classList.toggle('is-active', i === activeIndex);
            });
            items[activeIndex].scrollIntoView({ block: 'nearest' });
        }

        function render(items) {
            if (!items.length) {
                results.innerHTML = '<div class="p-3 text-muted small">Sem resultados. Experimente outro termo ou veja os atalhos (Ctrl+/).</div>';
                return;
            }
            results.innerHTML = items.map(function (item, i) {
                var shortcut = item.shortcut
                    ? '<span class="f-key f-command-palette__kbd">' + item.shortcut + '</span>'
                    : '';
                return '<a href="' + item.url + '" class="f-command-palette__item' + (i === 0 ? ' is-active' : '') + '" data-index="' + i + '">' +
                    '<span class="f-command-palette__item-main">' +
                    '<i class="bi ' + (item.icon || 'bi-arrow-right') + ' me-2"></i>' +
                    '<span>' + item.label + ' <small>— ' + item.group + '</small></span>' +
                    '</span>' + shortcut + '</a>';
            }).join('');
            activeIndex = 0;
        }

        function search(q) {
            if (!searchUrl) {
                results.innerHTML = '<div class="p-3 text-muted small">Pesquisa indisponível.</div>';
                return;
            }
            fetch(searchUrl + '?q=' + encodeURIComponent(q), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            })
                .then(function (r) { return r.json(); })
                .then(function (data) { render(data.items || []); })
                .catch(function () { results.innerHTML = ''; });
        }

        function go(url) {
            if (url) {
                window.location.href = url;
            }
        }

        function openAssistente() {
            window.dispatchEvent(new Event('assistente:open'));
        }

        function guardarFormulario() {
            var form = document.querySelector('form[data-atalho-guardar]');
            if (form) {
                if (typeof form.requestSubmit === 'function') {
                    form.requestSubmit();
                } else {
                    form.submit();
                }
                return true;
            }
            return false;
        }

        input.addEventListener('input', function () {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(function () { search(input.value.trim()); }, 200);
        });

        function activateItem(item) {
            if (!item) {
                return;
            }
            var href = item.getAttribute('href') || '';
            if (href === '#assistente' || href.endsWith('#assistente')) {
                closePalette();
                openAssistente();
                return;
            }
            window.location.href = href;
        }

        input.addEventListener('keydown', function (e) {
            var items = getItems();
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                setActive(activeIndex + 1);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                setActive(activeIndex - 1);
            } else if (e.key === 'Enter' && items[activeIndex]) {
                e.preventDefault();
                activateItem(items[activeIndex]);
            }
        });

        results.addEventListener('click', function (e) {
            var item = e.target.closest('.f-command-palette__item');
            if (!item) {
                return;
            }
            var href = item.getAttribute('href') || '';
            if (href === '#assistente' || href.endsWith('#assistente')) {
                e.preventDefault();
                activateItem(item);
            }
        });

        palette.addEventListener('click', function (e) {
            if (e.target === palette) {
                closePalette();
            }
        });

        if (help) {
            help.addEventListener('click', function (e) {
                if (e.target === help) {
                    closeHelp();
                }
            });
        }
        if (helpBtn) {
            helpBtn.addEventListener('click', function (e) {
                e.preventDefault();
                openHelp();
            });
        }
        if (helpClose) {
            helpClose.addEventListener('click', closeHelp);
        }
        if (menuAtalhos) {
            menuAtalhos.addEventListener('click', function (e) {
                e.preventDefault();
                openHelp();
            });
        }

        document.addEventListener('keydown', function (e) {
            var editing = isEditableTarget(e.target);
            var paletteOpen = palette.classList.contains('is-open');
            var helpOpen = help && help.classList.contains('is-open');

            if (e.key === 'Escape') {
                if (paletteOpen) {
                    e.preventDefault();
                    closePalette();
                    return;
                }
                if (helpOpen) {
                    e.preventDefault();
                    closeHelp();
                    return;
                }
                var assistente = document.getElementById('assistente-facilita');
                if (assistente && assistente.classList.contains('is-open')) {
                    e.preventDefault();
                    window.dispatchEvent(new CustomEvent('assistente:close'));
                    return;
                }
            }

            if (modKey(e) && (e.key === 'k' || e.key === 'K')) {
                e.preventDefault();
                if (paletteOpen) {
                    closePalette();
                } else {
                    openPalette();
                }
                return;
            }

            if (modKey(e) && (e.key === '/' || e.key === '?')) {
                e.preventDefault();
                toggleHelp();
                return;
            }

            if (modKey(e) && e.key === 's') {
                if (guardarFormulario()) {
                    e.preventDefault();
                }
                return;
            }

            if (editing && !paletteOpen) {
                return;
            }

            if (modKey(e) && e.shiftKey) {
                var key = (e.key || '').toLowerCase();
                if (key === 'a') {
                    e.preventDefault();
                    openAssistente();
                    return;
                }
                if (key === 'd' && urls.dashboard) {
                    e.preventDefault();
                    go(urls.dashboard);
                    return;
                }
                if (key === 'n' && urls.notas) {
                    e.preventDefault();
                    go(urls.notas);
                    return;
                }
                if (key === 'p' && urls.pautas) {
                    e.preventDefault();
                    go(urls.pautas);
                    return;
                }
                if (key === 'r' && urls.regras) {
                    e.preventDefault();
                    go(urls.regras);
                    return;
                }
            }
        });

        window.facilitaAtalhos = {
            openPalette: openPalette,
            openHelp: openHelp,
            closeHelp: closeHelp,
        };
    }

    function initAssistenteClose() {
        window.addEventListener('assistente:close', function () {
            var root = document.getElementById('assistente-facilita');
            if (!root || !window.Alpine) {
                return;
            }
            try {
                var data = Alpine.$data(root);
                if (data) {
                    data.aberto = false;
                    data.mostrarHistorico = false;
                }
            } catch (e) {
                root.classList.remove('is-open');
            }
        });
    }

    function initSkeleton() {
        document.querySelectorAll('[data-f-skeleton]').forEach(function (el) {
            var rows = parseInt(el.dataset.fSkeleton || '3', 10);
            var html = '';
            for (var i = 0; i < rows; i++) {
                html += '<div class="f-skeleton f-skeleton-row"></div>';
            }
            el.innerHTML = html;
            setTimeout(function () {
                el.classList.add('d-none');
                var target = document.querySelector(el.dataset.fSkeletonTarget);
                if (target) {
                    target.classList.remove('d-none');
                }
            }, parseInt(el.dataset.fSkeletonMs || '400', 10));
        });
    }

    function initMobileLancar() {
        if (!document.documentElement.classList.contains('facilita-pwa-mobile')) {
            return;
        }
        if (document.body.classList.contains('facilita-mobile-lancar')) {
            return;
        }
        var path = window.location.pathname;
        if (/(\/notas\/|\/recuperacoes\/|\/recursos\/).*lancar/.test(path) || /\/notas\/lancar/.test(path)) {
            document.body.classList.add('facilita-mobile-lancar');
        }
    }

    function initSimuladorTransicao() {
        var form = document.getElementById('form-recuperacao') || document.getElementById('form-recurso');
        if (!form || !form.dataset.simuladorUrl) {
            return;
        }

        var debounce = null;
        var paineis = {};

        function getPainel(alunoId) {
            if (!paineis[alunoId]) {
                var row = form.querySelector('[data-aluno-id="' + alunoId + '"]');
                if (!row) {
                    return null;
                }
                var el = document.createElement('div');
                el.className = 'f-simulador mt-1 d-none';
                el.setAttribute('data-simulador-aluno', alunoId);
                var cell = row.querySelector('.exame-disc-grid')?.parentElement || row.querySelector('td:nth-child(3)');
                if (cell) {
                    cell.appendChild(el);
                }
                paineis[alunoId] = el;
            }
            return paineis[alunoId];
        }

        function simular(alunoId, notas) {
            var painel = getPainel(alunoId);
            if (!painel) {
                return;
            }

            var body = new FormData();
            body.append('tipo_exame', form.dataset.tipoExame || 'recuperacao');
            body.append('_token', document.querySelector('meta[name="csrf-token"]')?.content || '');
            Object.keys(notas).forEach(function (discId) {
                body.append('notas[' + discId + ']', notas[discId]);
            });

            fetch(form.dataset.simuladorUrl.replace('__ALUNO__', alunoId), {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: body,
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.erro) {
                        painel.classList.add('d-none');
                        return;
                    }
                    var cls = 'f-simulador--' + (data.classificacao || 'igual');
                    painel.className = 'f-simulador mt-1 ' + cls;
                    painel.innerHTML = '<strong>Simulação:</strong> ' +
                        (data.antes?.resultado || '—') + ' → <strong>' + (data.depois?.resultado || '—') + '</strong>' +
                        (data.mudou ? ' <span class="badge bg-primary-subtle text-primary ms-1">alteração</span>' : '');
                    painel.classList.remove('d-none');
                })
                .catch(function () { painel.classList.add('d-none'); });
        }

        form.addEventListener('input', function (e) {
            var input = e.target;
            if (!input.matches('input[type="number"]')) {
                return;
            }
            var match = input.name.match(/\[(\d+)\]\[(\d+)\]/);
            if (!match) {
                return;
            }
            var alunoId = match[1];
            clearTimeout(debounce);
            debounce = setTimeout(function () {
                var notas = {};
                form.querySelectorAll('input[name^="' + (form.dataset.tipoExame === 'recurso' ? 'recursos' : 'recuperacoes') + '[' + alunoId + ']"]').forEach(function (inp) {
                    var m = inp.name.match(/\[(\d+)\]$/);
                    if (m && inp.value !== '') {
                        notas[m[1]] = inp.value;
                    }
                });
                if (Object.keys(notas).length) {
                    simular(alunoId, notas);
                }
            }, 500);
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        initCommandPalette();
        initAssistenteClose();
        initSkeleton();
        initMobileLancar();
        initSimuladorTransicao();
    });
})();
