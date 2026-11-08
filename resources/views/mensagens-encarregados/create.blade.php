<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h2 class="h4 mb-0">Nova conversa</h2>
            <a href="{{ route('mensagens-encarregados.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left"></i> Voltar
            </a>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="container" style="max-width: 36rem;">
            <ul class="nav nav-pills gap-2 mb-3" role="tablist">
                <li class="nav-item">
                    <a class="nav-link {{ $tipo === 'familia' ? 'active' : '' }}"
                       href="{{ route('mensagens-encarregados.create', ['tipo' => 'familia']) }}">
                        <i class="bi bi-people me-1"></i>Encarregado
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ $tipo === 'interna' ? 'active' : '' }}"
                       href="{{ route('mensagens-encarregados.create', ['tipo' => 'interna']) }}">
                        <i class="bi bi-person-badge me-1"></i>Colaborador
                    </a>
                </li>
            </ul>

            @if($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if($tipo === 'interna')
                <x-tip-card icon="bi-person-badge" variant="info" title="Mensagem interna" id="tip-msg-interna" compact>
                    Contacte professores, directores de turma, coordenadores, secretaria e outros colaboradores. Cada destinatário abre uma conversa separada.
                </x-tip-card>

                @if($colaboradores->isEmpty())
                    <div class="card">
                        <div class="card-body text-muted text-center py-4">
                            Nenhum colaborador contactável de momento.
                        </div>
                    </div>
                @else
                    <form method="POST" action="{{ route('mensagens-internas.iniciar') }}" id="formNovaConversaInterna">
                        @csrf
                        <div id="idsContainer"></div>

                        <div class="card mb-3">
                            <div class="card-body">
                                <label class="form-label" for="q">Pesquisar colaborador</label>
                                <div class="input-group mb-2">
                                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                                    <input type="search" id="q" value="{{ $q }}" class="form-control"
                                           placeholder="Nome ou email…" autocomplete="off" autofocus>
                                </div>
                                <div class="d-flex justify-content-between align-items-center small text-muted mb-2 flex-wrap gap-1">
                                    <span id="listaContagem">{{ $colaboradores->count() }} colaborador(es)</span>
                                    <span id="seleccionadoLabel" class="text-primary fw-semibold"></span>
                                </div>
                                <div class="d-flex gap-2 mb-2">
                                    <button type="button" class="btn btn-outline-secondary btn-sm" id="btnSeleccionarVisiveis">Seleccionar visíveis</button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm" id="btnLimparSeleccao">Limpar selecção</button>
                                </div>

                                <div id="listaItems" class="list-group msg-aluno-lista" style="max-height: 18rem; overflow-y: auto;">
                                    @foreach($colaboradores as $colab)
                                        @php
                                            $cargo = $mensagensInternas->cargoLabel($colab);
                                            $textoBusca = mb_strtolower(implode(' ', [$colab->name, $colab->email, $cargo]));
                                            $seleccionado = in_array((int) $colab->id, $userIds ?? [], true);
                                        @endphp
                                        <button type="button"
                                                class="list-group-item list-group-item-action msg-aluno-item {{ $seleccionado ? 'active' : '' }}"
                                                data-id="{{ $colab->id }}"
                                                data-nome="{{ $colab->name }}"
                                                data-search="{{ $textoBusca }}"
                                                aria-pressed="{{ $seleccionado ? 'true' : 'false' }}">
                                            <div class="d-flex align-items-start gap-2">
                                                <i class="bi {{ $seleccionado ? 'bi-check-square-fill' : 'bi-square' }} mt-1 msg-aluno-check"></i>
                                                <div class="min-w-0">
                                                    <div class="fw-semibold">{{ $colab->name }}</div>
                                                    <div class="small text-muted msg-aluno-meta">{{ $cargo }} · {{ $colab->email }}</div>
                                                </div>
                                            </div>
                                        </button>
                                    @endforeach
                                </div>
                                <div id="listaVazia" class="text-muted text-center small py-3 d-none">
                                    Nenhum colaborador corresponde à pesquisa.
                                </div>
                            </div>
                        </div>

                        @include('mensagens-encarregados.partials.composer', [
                            'placeholder' => 'Escreva a mensagem ao colaborador…',
                            'hiddenName' => 'user_ids[]',
                            'emptyMsg' => 'Seleccione pelo menos um colaborador.',
                            'contagemLabel' => 'colaborador(es)',
                        ])
                    </form>
                @endif
            @else
                <x-tip-card icon="bi-people" variant="info" title="Mensagem à família" id="tip-msg-nova" compact>
                    Seleccione um ou vários alunos. A mesma mensagem é enviada a cada encarregado (conversas separadas). Clique de novo para desmarcar.
                </x-tip-card>

                @if($alunos->isEmpty() && $q === '')
                    <div class="card">
                        <div class="card-body text-muted text-center py-4">
                            Nenhum aluno contactável no seu âmbito (sem encarregado com conta no portal).
                        </div>
                    </div>
                @else
                    <form method="POST" action="{{ route('mensagens-encarregados.iniciar') }}" id="formNovaConversaFamilia">
                        @csrf
                        <div id="idsContainer"></div>

                        <div class="card mb-3">
                            <div class="card-body">
                                <label class="form-label" for="q">Pesquisar aluno</label>
                                <div class="input-group mb-2">
                                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                                    <input type="search" id="q" value="{{ $q }}" class="form-control"
                                           placeholder="Nome, turma ou nº de processo…" autocomplete="off" autofocus>
                                </div>
                                <div class="d-flex justify-content-between align-items-center small text-muted mb-2 flex-wrap gap-1">
                                    <span id="listaContagem">{{ $alunos->count() }} aluno(s)</span>
                                    <span id="seleccionadoLabel" class="text-primary fw-semibold"></span>
                                </div>
                                <div class="d-flex gap-2 mb-2">
                                    <button type="button" class="btn btn-outline-secondary btn-sm" id="btnSeleccionarVisiveis">Seleccionar visíveis</button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm" id="btnLimparSeleccao">Limpar selecção</button>
                                </div>

                                <div id="listaItems" class="list-group msg-aluno-lista" style="max-height: 18rem; overflow-y: auto;">
                                    @forelse($alunos as $aluno)
                                        @php
                                            $enc = $aluno->encarregadoPrincipal();
                                            $encLabel = $enc?->user?->name ?? $enc?->nome ?? '—';
                                            $turmaLabel = $aluno->turma?->codigo ?? $aluno->turma?->nome ?? '—';
                                            $textoBusca = mb_strtolower(implode(' ', [
                                                $aluno->nome,
                                                $aluno->numero_processo,
                                                $turmaLabel,
                                                $encLabel,
                                            ]));
                                            $seleccionado = in_array((int) $aluno->id, $alunoIds ?? [], true);
                                        @endphp
                                        <button type="button"
                                                class="list-group-item list-group-item-action msg-aluno-item {{ $seleccionado ? 'active' : '' }}"
                                                data-id="{{ $aluno->id }}"
                                                data-nome="{{ $aluno->nome }}"
                                                data-search="{{ $textoBusca }}"
                                                aria-pressed="{{ $seleccionado ? 'true' : 'false' }}">
                                            <div class="d-flex align-items-start gap-2">
                                                <i class="bi {{ $seleccionado ? 'bi-check-square-fill' : 'bi-square' }} mt-1 msg-aluno-check"></i>
                                                <div class="min-w-0">
                                                    <div class="fw-semibold">{{ $aluno->nome }}</div>
                                                    <div class="small text-muted msg-aluno-meta">
                                                        {{ $turmaLabel }} · Proc. {{ $aluno->numero_processo ?? '—' }} · Enc.: {{ $encLabel }}
                                                    </div>
                                                </div>
                                            </div>
                                        </button>
                                    @empty
                                        <div class="list-group-item text-muted text-center">
                                            Nenhum aluno para «{{ $q }}».
                                        </div>
                                    @endforelse
                                </div>
                                <div id="listaVazia" class="text-muted text-center small py-3 d-none">
                                    Nenhum aluno corresponde à pesquisa.
                                </div>
                            </div>
                        </div>

                        @include('mensagens-encarregados.partials.composer', [
                            'placeholder' => 'Escreva a mensagem aos encarregados…',
                            'hiddenName' => 'aluno_ids[]',
                            'emptyMsg' => 'Seleccione pelo menos um aluno.',
                            'contagemLabel' => 'aluno(s)',
                        ])
                    </form>
                @endif
            @endif
        </div>
    </div>

    @push('styles')
    <style>
        .msg-aluno-lista .msg-aluno-item { cursor: pointer; border-left: 3px solid transparent; }
        .msg-aluno-lista .msg-aluno-item:hover { border-left-color: var(--bs-primary); }
        .msg-aluno-lista .msg-aluno-item.active {
            background: rgba(13, 110, 253, .12);
            border-left-color: var(--bs-primary);
            color: inherit;
        }
        .msg-aluno-check { color: var(--bs-secondary); }
        .msg-aluno-lista .msg-aluno-item.active .msg-aluno-check { color: var(--bs-primary); }
    </style>
    @endpush

    @push('scripts')
    <script>
    (function () {
        var input = document.getElementById('q');
        var container = document.getElementById('idsContainer');
        var lista = document.getElementById('listaItems');
        var vazia = document.getElementById('listaVazia');
        var contagem = document.getElementById('listaContagem');
        var labelSel = document.getElementById('seleccionadoLabel');
        var form = document.getElementById('formNovaConversaInterna') || document.getElementById('formNovaConversaFamilia');
        var btnVisiveis = document.getElementById('btnSeleccionarVisiveis');
        var btnLimpar = document.getElementById('btnLimparSeleccao');
        var hiddenName = @json($tipo === 'interna' ? 'user_ids[]' : 'aluno_ids[]');
        var emptyMsg = @json($tipo === 'interna' ? 'Seleccione pelo menos um colaborador.' : 'Seleccione pelo menos um aluno.');
        var contagemLabel = @json($tipo === 'interna' ? 'colaborador(es)' : 'aluno(s)');
        if (!input || !lista || !container) return;

        var items = Array.prototype.slice.call(lista.querySelectorAll('.msg-aluno-item'));
        var seleccionados = new Set(
            items.filter(function (el) { return el.classList.contains('active'); })
                .map(function (el) { return el.getAttribute('data-id'); })
        );

        function syncHidden() {
            container.innerHTML = '';
            seleccionados.forEach(function (id) {
                var inp = document.createElement('input');
                inp.type = 'hidden';
                inp.name = hiddenName;
                inp.value = id;
                container.appendChild(inp);
            });
        }

        function setActivo(el, on) {
            var id = el.getAttribute('data-id');
            var icon = el.querySelector('.msg-aluno-check');
            el.classList.toggle('active', on);
            el.setAttribute('aria-pressed', on ? 'true' : 'false');
            if (icon) {
                icon.classList.toggle('bi-check-square-fill', on);
                icon.classList.toggle('bi-square', !on);
            }
            if (on) seleccionados.add(id);
            else seleccionados.delete(id);
        }

        function actualizarLabel() {
            var n = seleccionados.size;
            if (!labelSel) return;
            if (n === 0) { labelSel.textContent = ''; return; }
            if (n === 1) {
                var el = items.find(function (i) { return seleccionados.has(i.getAttribute('data-id')); });
                labelSel.textContent = el ? ('1 seleccionado: ' + el.getAttribute('data-nome')) : '1 seleccionado';
                return;
            }
            labelSel.textContent = n + ' seleccionados';
        }

        function filtrar() {
            var termo = (input.value || '').trim().toLowerCase();
            var visiveis = 0;
            items.forEach(function (el) {
                var hay = el.getAttribute('data-search') || '';
                var ok = !termo || hay.indexOf(termo) !== -1;
                el.classList.toggle('d-none', !ok);
                if (ok) visiveis++;
            });
            if (contagem) contagem.textContent = visiveis + ' ' + contagemLabel;
            if (vazia) vazia.classList.toggle('d-none', visiveis > 0 || items.length === 0);
            lista.classList.toggle('d-none', visiveis === 0 && items.length > 0);
        }

        items.forEach(function (el) {
            el.addEventListener('click', function () {
                setActivo(el, !el.classList.contains('active'));
                syncHidden();
                actualizarLabel();
            });
        });

        if (btnVisiveis) {
            btnVisiveis.addEventListener('click', function () {
                items.forEach(function (el) {
                    if (!el.classList.contains('d-none')) setActivo(el, true);
                });
                syncHidden();
                actualizarLabel();
            });
        }
        if (btnLimpar) {
            btnLimpar.addEventListener('click', function () {
                items.forEach(function (el) { setActivo(el, false); });
                syncHidden();
                actualizarLabel();
            });
        }

        input.addEventListener('input', filtrar);
        input.addEventListener('keydown', function (e) { if (e.key === 'Enter') e.preventDefault(); });

        if (form) {
            form.addEventListener('submit', function (e) {
                syncHidden();
                if (seleccionados.size === 0) {
                    e.preventDefault();
                    input.focus();
                    alert(emptyMsg);
                }
            });
        }

        syncHidden();
        actualizarLabel();
        filtrar();
    })();
    </script>
    @endpush
</x-app-layout>
