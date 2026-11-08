<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h2 class="h4 mb-0">
                <i class="bi bi-journal-bookmark me-2 text-primary"></i>Coordenadores de Disciplina
            </h2>
            @can('create', App\Models\CoordenadorDisciplina::class)
                @if($podeEditar)
                    <a href="{{ route('coordenadores-disciplina.create') }}" class="btn btn-primary">
                        <i class="bi bi-plus-lg me-1"></i> Novo Coordenador
                    </a>
                @endif
            @endcan
        </div>
    </x-slot>

    @push('styles')
    @include('partials.cargo-cards-css')
    @endpush

    <div class="py-4">
        <div class="container">
            <x-tip-card icon="bi-journal-bookmark" variant="info" title="Coordenadores de Disciplina" id="tip-coord-disciplina-index" compact>
                Supervisionam planificações e provas da disciplina nas classes atribuídas, aprovam artefactos pedagógicos e gerem o banco de questões.
            </x-tip-card>

            @if(! $podeEditar)
                <div class="alert alert-info alert-dismissible fade show" role="alert">
                    <i class="bi bi-info-circle me-2"></i>
                    <strong>Modo Consulta:</strong> Ano lectivo {{ $anoLectivo }}.
                    @if(! auth()->user()->hasRole('admin_full'))
                        Apenas o admin_full pode alterar anos anteriores.
                    @endif
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            <div class="row g-3 mb-4">
                <div class="col-6 col-md-3">
                    <x-stat-card icon="bi-people" icon-class="stat-icon-primary" :value="$totalRegistos" label="Coordenadores" />
                </div>
                <div class="col-6 col-md-3">
                    <x-stat-card icon="bi-journal-text" icon-class="stat-icon-info" :value="$totalDisciplinas" label="Disciplinas" />
                </div>
                <div class="col-6 col-md-3">
                    <x-stat-card icon="bi-check-circle" icon-class="stat-icon-success" :value="$totalActivos" label="Activos" />
                </div>
                <div class="col-6 col-md-3">
                    <x-stat-card icon="bi-pause-circle" icon-class="stat-icon-warning" :value="$totalInactivos" label="Inactivos" />
                </div>
            </div>

            <div class="card shadow-soft mb-3 cargo-filtros">
                <div class="card-body py-3">
                    <form action="{{ route('coordenadores-disciplina.index') }}" method="GET" class="row g-2 align-items-end">
                        <div class="col-md-4">
                            <label for="ano_lectivo_id" class="form-label">Ano lectivo</label>
                            <select class="form-select form-select-sm" id="ano_lectivo_id" name="ano_lectivo_id" onchange="this.form.submit()">
                                @foreach($anosLectivos as $id => $nome)
                                    <option value="{{ $id }}" {{ (string) $anoLectivoId === (string) $id ? 'selected' : '' }}>{{ $nome }}</option>
                                @endforeach
                            </select>
                        </div>
                    </form>
                </div>
            </div>

            @if($coordenadores->isEmpty())
                <div class="card shadow-soft">
                    <div class="card-body">
                        <x-empty-state
                            icon="bi-journal-bookmark"
                            message="Nenhum coordenador de disciplina encontrado."
                            :create-url="$podeEditar ? route('coordenadores-disciplina.create') : null"
                            create-label="Criar primeiro coordenador"
                            :can-create="$podeEditar && Gate::allows('create', App\Models\CoordenadorDisciplina::class)"
                        />
                    </div>
                </div>
            @else
                <div class="card shadow-soft fade-in">
                    <div class="card-header bg-white">
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                            <h5 class="mb-0"><i class="bi bi-grid-3x3-gap me-2"></i>Atribuições</h5>
                            <span class="badge bg-primary">{{ $coordenadores->total() }} registos</span>
                        </div>
                        <div class="d-flex flex-column flex-md-row cargo-list-header justify-content-between align-items-stretch align-items-md-center">
                            <div class="cargo-legenda d-flex flex-wrap align-items-center gap-2">
                                <span class="cargo-chip cargo-chip--activo py-0 px-2"><i class="bi bi-check-circle"></i> Activo</span>
                                <span class="cargo-chip cargo-chip--inactivo py-0 px-2"><i class="bi bi-pause-circle"></i> Inactivo</span>
                            </div>
                            <div class="input-group input-group-sm cargo-search-wrap">
                                <span class="input-group-text"><i class="bi bi-search"></i></span>
                                <input type="text" class="form-control cargo-search" data-list="coord-disciplina-list" placeholder="Pesquisar..." autocomplete="off">
                            </div>
                        </div>
                    </div>

                    <div class="card-body">
                        <div class="d-flex flex-column gap-3" id="coord-disciplina-list">
                            @foreach($coordenadores as $coordenador)
                                @php
                                    $podeEditarRegistro = auth()->user()->hasRole('admin_full') || $podeEditar;
                                    $cardClass = 'cargo-card cargo-card--single'.($coordenador->activo ? '' : ' cargo-card--inactivo');
                                @endphp
                                <article class="{{ $cardClass }}" data-searchable>
                                    <div class="cargo-single__head">
                                        <div class="cargo-professor flex-grow-1">
                                            <div class="avatar-circle">
                                                {{ strtoupper(substr($coordenador->coordenador->name ?? '?', 0, 1)) }}
                                            </div>
                                            <div class="min-width-0">
                                                <div class="cargo-professor__nome">{{ $coordenador->coordenador->name }}</div>
                                                <div class="cargo-professor__sub">Coordenador de disciplina</div>
                                            </div>
                                        </div>
                                        <div class="cargo-meta">
                                            <span class="badge bg-light text-dark border">{{ $coordenador->ano_lectivo }}</span>
                                            <span class="cargo-chip {{ $coordenador->activo ? 'cargo-chip--activo' : 'cargo-chip--inactivo' }}">
                                                <i class="bi {{ $coordenador->activo ? 'bi-check-circle' : 'bi-pause-circle' }}"></i>
                                                {{ $coordenador->activo ? 'Activo' : 'Inactivo' }}
                                            </span>
                                        </div>
                                    </div>

                                    <div class="cargo-single__curso">
                                        <i class="bi bi-journal-text me-1 text-primary"></i>
                                        {{ $coordenador->disciplina?->nome ?? '—' }}
                                    </div>
                                    <div class="d-flex flex-wrap gap-1 mt-2">
                                        @forelse($coordenador->classes->sortBy('ordem') as $classe)
                                            <span class="badge bg-light text-dark border">{{ $classe->nome }}</span>
                                        @empty
                                            <span class="text-muted small">Sem classes</span>
                                        @endforelse
                                    </div>

                                    <div class="cargo-single__acoes cargo-acoes-compact">
                                        @if($podeEditarRegistro && Gate::allows('delete', $coordenador))
                                            <form action="{{ route('coordenadores-disciplina.destroy', $coordenador) }}" method="POST" class="d-inline flex-grow-1 flex-sm-grow-0"
                                                  onsubmit="return confirm('Remover esta atribuição?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger w-100">
                                                    <i class="bi bi-trash"></i><span class="d-none d-sm-inline ms-1">Remover</span>
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </article>
                            @endforeach
                        </div>
                        <div class="cargo-search-empty text-center text-muted py-5" style="display:none;">
                            <i class="bi bi-search fs-3 d-block mb-2"></i>
                            Nenhum resultado para a pesquisa.
                        </div>
                    </div>

                    @if($coordenadores->hasPages())
                        <div class="card-footer bg-white">{{ $coordenadores->links() }}</div>
                    @endif
                </div>
            @endif
        </div>
    </div>

    @push('scripts')
    <script>
        document.querySelectorAll('.cargo-search').forEach(function (input) {
            input.addEventListener('input', function () {
                var listId = input.getAttribute('data-list');
                var list = document.getElementById(listId);
                if (!list) return;
                var q = (input.value || '').toLowerCase().trim();
                var visible = 0;
                list.querySelectorAll('[data-searchable]').forEach(function (el) {
                    var show = !q || el.textContent.toLowerCase().includes(q);
                    el.style.display = show ? '' : 'none';
                    if (show) visible++;
                });
                var empty = list.parentElement.querySelector('.cargo-search-empty');
                if (empty) empty.style.display = visible ? 'none' : '';
            });
        });
    </script>
    @endpush
</x-app-layout>
