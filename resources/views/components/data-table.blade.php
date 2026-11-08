@props([
    'title' => null,
    'icon' => 'bi-list-ul',
    'badge' => null,
    'pagination' => null,
    'searchable' => true,
])

@php
    $tableId = 'table-' . uniqid();
    $searchId = 'search-' . uniqid();
    $wrapId = 'fdt-' . uniqid();
@endphp

<div {{ $attributes->merge(['class' => 'f-data-table card shadow-soft fade-in']) }} id="{{ $wrapId }}">
    @if($title || $searchable)
        <div class="f-data-table__toolbar card-header border-0">
            <div class="f-data-table__toolbar-top">
                @if($title)
                    <div class="f-data-table__title">
                        <span class="f-data-table__icon" aria-hidden="true">
                            <i class="{{ $icon }}"></i>
                        </span>
                        <div>
                            <h5 class="mb-0">{{ $title }}</h5>
                            @if($searchable)
                                <small class="text-muted" id="{{ $searchId }}-count"></small>
                            @elseif($badge !== null)
                                <small class="text-muted">{{ $badge }} {{ (int) $badge === 1 ? 'registo' : 'registos' }}</small>
                            @endif
                        </div>
                    </div>
                @endif

                <div class="f-data-table__toolbar-end">
                    @if($badge !== null)
                        <span class="f-data-table__badge">{{ $badge }}</span>
                    @endif
                    @isset($actions)
                        {{ $actions }}
                    @endisset
                </div>
            </div>

            @if($searchable)
                <div class="f-data-table__search">
                    <i class="bi bi-search f-data-table__search-icon" aria-hidden="true"></i>
                    <input type="search"
                           class="form-control f-data-table__search-input"
                           id="{{ $searchId }}"
                           placeholder="Pesquisar na tabela..."
                           autocomplete="off"
                           aria-label="Pesquisar na tabela">
                    <button type="button"
                            class="btn f-data-table__search-clear"
                            id="{{ $searchId }}-clear"
                            style="display: none;"
                            aria-label="Limpar pesquisa">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
            @endif
        </div>
    @endif

    <div class="card-body p-0">
        <div class="f-data-table__scroll" data-fdt-scroll>
            <div class="f-data-table__scroll-hint d-md-none" data-fdt-hint>
                <i class="bi bi-arrow-left-right"></i> Deslize para ver mais colunas
            </div>
            <div class="table-responsive f-data-table__responsive" id="{{ $tableId }}-wrap">
                <div id="{{ $tableId }}">
                    {{ $slot }}
                </div>
            </div>
        </div>
        <div id="{{ $searchId }}-no-results" class="f-data-table__empty text-center text-muted py-5" style="display: none;">
            <i class="bi bi-search display-6 d-block mb-2 opacity-50"></i>
            Nenhum resultado encontrado para a pesquisa.
        </div>
    </div>

    @if($pagination && $pagination->hasPages())
        <div class="f-data-table__footer card-footer">
            {{ $pagination->links() }}
        </div>
    @endif
</div>

<script>
(function() {
    const root = document.getElementById('{{ $wrapId }}');
    if (!root) return;

    const tableContainer = document.getElementById('{{ $tableId }}');
    const table = tableContainer?.querySelector('table');
    if (table) {
        table.classList.add('table', 'table-hover', 'table-modern', 'f-data-table__table', 'mb-0');
        table.querySelectorAll('thead th').forEach((th) => {
            th.classList.add('f-data-table__th');
        });
        const lastTh = table.querySelector('thead th:last-child');
        if (lastTh && /aç(ã|a)o|aç(õ|o)es|acções|acoes/i.test(lastTh.textContent || '')) {
            root.classList.add('f-data-table--sticky-actions');
        }
    }

    const scrollEl = root.querySelector('[data-fdt-scroll] .f-data-table__responsive');
    const hint = root.querySelector('[data-fdt-hint]');
    if (scrollEl) {
        const updateShadows = () => {
            const max = scrollEl.scrollWidth - scrollEl.clientWidth;
            root.classList.toggle('is-scrollable', max > 8);
            root.classList.toggle('is-scrolled-start', scrollEl.scrollLeft > 4);
            root.classList.toggle('is-scrolled-end', scrollEl.scrollLeft < max - 4);
            if (hint && max <= 8) hint.style.display = 'none';
        };
        scrollEl.addEventListener('scroll', updateShadows, { passive: true });
        window.addEventListener('resize', updateShadows);
        updateShadows();
    }

    @if($searchable)
    const searchInput = document.getElementById('{{ $searchId }}');
    const clearBtn = document.getElementById('{{ $searchId }}-clear');
    const countDisplay = document.getElementById('{{ $searchId }}-count');
    const noResultsMsg = document.getElementById('{{ $searchId }}-no-results');
    const tbody = table?.querySelector('tbody');

    if (!table || !tbody || !searchInput) return;

    const allRows = Array.from(tbody.querySelectorAll('tr'));
    const totalRows = allRows.length;

    function updateCount(visible, total) {
        if (!countDisplay) return;
        if (visible === total) {
            countDisplay.textContent = `${total} ${total === 1 ? 'registo' : 'registos'}`;
        } else {
            countDisplay.textContent = `${visible} de ${total} ${total === 1 ? 'registo' : 'registos'}`;
        }
    }

    updateCount(totalRows, totalRows);

    searchInput.addEventListener('input', function (e) {
        const searchTerm = e.target.value.toLowerCase().trim();

        if (searchTerm === '') {
            clearBtn.style.display = 'none';
            allRows.forEach(row => row.style.display = '');
            tableContainer.style.display = '';
            noResultsMsg.style.display = 'none';
            updateCount(totalRows, totalRows);
            return;
        }

        clearBtn.style.display = 'inline-flex';

        let visibleCount = 0;
        allRows.forEach(row => {
            const text = row.textContent.toLowerCase();
            if (text.includes(searchTerm)) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });

        if (visibleCount === 0) {
            tableContainer.style.display = 'none';
            noResultsMsg.style.display = 'block';
        } else {
            tableContainer.style.display = '';
            noResultsMsg.style.display = 'none';
        }

        updateCount(visibleCount, totalRows);
    });

    clearBtn.addEventListener('click', function () {
        searchInput.value = '';
        searchInput.dispatchEvent(new Event('input'));
        searchInput.focus();
    });
    @endif
})();
</script>
