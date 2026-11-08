@php
    $user = auth()->user();
    $atalhosUrls = array_filter([
        'dashboard' => Route::has('dashboard') ? route('dashboard') : null,
        'notas' => ($user?->can('notas.lancar') && Route::has('notas.lancar')) ? route('notas.lancar') : null,
        'pautas' => ($user?->can('pautas.ver') && Route::has('pautas.index')) ? route('pautas.index') : null,
        'regras' => Route::has('regras-avaliacao') ? route('regras-avaliacao') : null,
    ]);
    $atalhosLista = array_values(array_filter([
        ['keys' => 'Ctrl+K', 'acao' => 'Abrir / fechar pesquisa rápida'],
        ['keys' => 'Ctrl+/', 'acao' => 'Ver lista de atalhos'],
        ['keys' => 'Ctrl+Shift+A', 'acao' => 'Abrir Assistente IA', 'se' => $user?->can('assistente.usar')],
        ['keys' => 'Ctrl+Shift+D', 'acao' => 'Ir ao Dashboard'],
        ['keys' => 'Ctrl+Shift+N', 'acao' => 'Lançar notas', 'se' => ! empty($atalhosUrls['notas'])],
        ['keys' => 'Ctrl+Shift+P', 'acao' => 'Abrir pautas', 'se' => ! empty($atalhosUrls['pautas'])],
        ['keys' => 'Ctrl+Shift+R', 'acao' => 'Regras de avaliação', 'se' => ! empty($atalhosUrls['regras'])],
        ['keys' => 'Ctrl+S', 'acao' => 'Guardar (em ecrãs de lançamento)'],
        ['keys' => 'Esc', 'acao' => 'Fechar pesquisa, ajuda ou assistente'],
    ], fn ($a) => ($a['se'] ?? true) !== false));
@endphp

<div
    id="fCommandPalette"
    class="f-command-palette"
    role="dialog"
    aria-label="Pesquisa rápida"
    aria-hidden="true"
    data-atalhos-urls='@json($atalhosUrls)'
>
    <div class="f-command-palette__box">
        <input
            type="search"
            class="f-command-palette__input"
            id="fCommandInput"
            placeholder="Pesquisar… (Ctrl+K)"
            autocomplete="off"
            aria-label="Pesquisa"
        >
        <div class="f-command-palette__results" id="fCommandResults"></div>
        <div class="f-command-palette__footer">
            <span><span class="f-key">↑</span><span class="f-key">↓</span> navegar · <span class="f-key">Enter</span> abrir · <span class="f-key">Esc</span> fechar</span>
            <button type="button" class="f-command-palette__help-btn" id="fCommandHelpBtn" title="Ver atalhos">
                <i class="bi bi-keyboard"></i> Atalhos <span class="f-key">Ctrl+/</span>
            </button>
        </div>
    </div>
</div>

<div id="fShortcutsHelp" class="f-shortcuts-help" role="dialog" aria-label="Atalhos de teclado" aria-hidden="true">
    <div class="f-shortcuts-help__box">
        <div class="f-shortcuts-help__header">
            <div>
                <strong><i class="bi bi-keyboard me-1"></i> Atalhos de teclado</strong>
                <small>Acelere a navegação no Facilita</small>
            </div>
            <button type="button" class="f-shortcuts-help__close" id="fShortcutsHelpClose" aria-label="Fechar">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <div class="f-shortcuts-help__body">
            <ul class="f-shortcuts-help__list">
                @foreach($atalhosLista as $atalho)
                    <li>
                        <span class="f-shortcuts-help__keys" aria-label="{{ $atalho['keys'] }}">
                            @foreach(preg_split('/\s*\+\s*/', $atalho['keys']) as $i => $parte)
                                @if($i > 0)<span class="f-shortcuts-help__plus">+</span>@endif
                                <span class="f-key">{{ $parte }}</span>
                            @endforeach
                        </span>
                        <span class="f-shortcuts-help__acao">{{ $atalho['acao'] }}</span>
                    </li>
                @endforeach
            </ul>
            <p class="f-shortcuts-help__nota">
                No Mac use <span class="f-key">⌘</span> no lugar de <span class="f-key">Ctrl</span>.
                Os atalhos não funcionam enquanto escreve num campo de texto (excepto Ctrl+S para guardar).
            </p>
        </div>
    </div>
</div>
