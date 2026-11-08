<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h2 class="h4 mb-0">
                <i class="bi bi-person-badge-fill me-2 text-primary"></i>Directores de Turma
            </h2>
            @can('create', App\Models\DirectorTurma::class)
                @if($podeEditar)
                    <a href="{{ route('directores-turma.create') }}" class="btn btn-primary">
                        <i class="bi bi-plus-lg me-1"></i> Novo Director
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
            <x-tip-card icon="bi-person-badge-fill" variant="info" title="Directores de Turma" id="tip-directores-index" compact>
                O director de turma acede a conselhos de classe e boletins da sua turma. Use <strong>Substituir</strong> para trocar o docente mantendo o histórico do cargo anterior.
            </x-tip-card>

            @if(!$podeEditar)
                <div class="alert alert-info alert-dismissible fade show" role="alert">
                    <i class="bi bi-info-circle me-2"></i>
                    <strong>Modo Consulta:</strong> Ano lectivo {{ $anoLectivo }}.
                    @if(!auth()->user()->hasRole('admin_full'))
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
                    <x-stat-card icon="bi-people" icon-class="stat-icon-primary" :value="$totalProfessores" label="Directores" />
                </div>
                <div class="col-6 col-md-3">
                    <x-stat-card icon="bi-check-circle" icon-class="stat-icon-success" :value="$totalActivos" label="Turmas activas" />
                </div>
                <div class="col-6 col-md-3">
                    <x-stat-card icon="bi-grid" icon-class="stat-icon-info" :value="$totalRegistos" label="Atribuições" />
                </div>
                <div class="col-6 col-md-3">
                    <x-stat-card icon="bi-pause-circle" icon-class="stat-icon-warning" :value="$totalInactivos" label="Inactivas" />
                </div>
            </div>

            <div class="card shadow-soft mb-3 cargo-filtros">
                <div class="card-body py-3">
                    <form action="{{ route('directores-turma.index') }}" method="GET" class="row g-2 align-items-end">
                        <div class="col-md-4">
                            <label for="ano_lectivo_id" class="form-label">Ano lectivo</label>
                            <select class="form-select form-select-sm" id="ano_lectivo_id" name="ano_lectivo_id" onchange="this.form.submit()">
                                @foreach($anosLectivos as $id => $nome)
                                    <option value="{{ $id }}" {{ $anoLectivoId == $id ? 'selected' : '' }}>{{ $nome }}</option>
                                @endforeach
                            </select>
                        </div>
                    </form>
                </div>
            </div>

            @if($directores->isEmpty())
                <div class="card shadow-soft">
                    <div class="card-body">
                        <x-empty-state
                            icon="bi-person-badge"
                            message="Nenhum director de turma encontrado."
                            :create-url="$podeEditar ? route('directores-turma.create') : null"
                            create-label="Criar primeiro director"
                            :can-create="$podeEditar && Gate::allows('create', App\Models\DirectorTurma::class)"
                        />
                    </div>
                </div>
            @else
                <div class="card shadow-soft fade-in">
                    <div class="card-header bg-white">
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                            <h5 class="mb-0"><i class="bi bi-grid-3x3-gap me-2"></i>Directores agrupados</h5>
                            <span class="badge bg-primary">{{ $directores->total() }} professores</span>
                        </div>
                        <div class="d-flex flex-column flex-md-row cargo-list-header justify-content-between align-items-stretch align-items-md-center">
                            <div class="cargo-legenda d-flex flex-wrap align-items-center gap-2">
                                <span class="cargo-chip cargo-chip--activo py-0 px-2"><i class="bi bi-check-circle"></i> Activo</span>
                                <span class="cargo-chip cargo-chip--inactivo py-0 px-2"><i class="bi bi-pause-circle"></i> Inactivo</span>
                            </div>
                            <div class="input-group input-group-sm cargo-search-wrap">
                                <span class="input-group-text"><i class="bi bi-search"></i></span>
                                <input type="text" class="form-control cargo-search" data-list="directores-list" placeholder="Pesquisar..." autocomplete="off">
                            </div>
                        </div>
                    </div>

                    <div class="card-body">
                        <div class="d-flex flex-column gap-3" id="directores-list">
                            @foreach($directores as $grupo)
                                @php
                                    $cardClass = 'cargo-card';
                                    if ($grupo->activas === 0) {
                                        $cardClass .= ' cargo-card--inactivo';
                                    } elseif ($grupo->inactivas > 0) {
                                        $cardClass .= ' cargo-card--parcial';
                                    }
                                    $podeEditarRegistro = auth()->user()->hasRole('admin_full') || $podeEditar;
                                @endphp
                                <article class="{{ $cardClass }}" data-searchable>
                                    <div class="cargo-card__top">
                                        <div class="cargo-professor">
                                            <div class="avatar-circle">
                                                {{ strtoupper(substr($grupo->director->name ?? '?', 0, 1)) }}
                                            </div>
                                            <div class="min-width-0">
                                                <div class="cargo-professor__nome" title="{{ $grupo->director->name }}">
                                                    {{ $grupo->director->name }}
                                                </div>
                                                <small class="text-muted">{{ $grupo->total_turmas }} {{ $grupo->total_turmas === 1 ? 'turma' : 'turmas' }}</small>
                                            </div>
                                        </div>
                                        <div class="cargo-meta">
                                            <span class="badge bg-light text-dark border">{{ $grupo->ano_lectivo }}</span>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle">{{ $grupo->activas }} activa{{ $grupo->activas === 1 ? '' : 's' }}</span>
                                            @if($grupo->inactivas > 0)
                                                <span class="badge bg-secondary-subtle text-secondary border">{{ $grupo->inactivas }} inactiva{{ $grupo->inactivas === 1 ? '' : 's' }}</span>
                                            @endif
                                        </div>
                                    </div>

                                    <div class="cargo-card__body">
                                        @foreach($grupo->registros as $item)
                                            @php $director = $item['registro']; @endphp
                                            <div class="cargo-item-row">
                                                <a href="{{ route('turmas.show', $item['turma']) }}" class="cargo-item-row__turma">
                                                    {{ $item['turma']->nome }}
                                                </a>
                                                <span class="cargo-item-row__curso">{{ $item['turma']->curso->nome ?? '—' }}</span>
                                                <span class="cargo-item-row__status cargo-chip {{ $item['activo'] ? 'cargo-chip--activo' : 'cargo-chip--inactivo' }}">
                                                    <i class="bi {{ $item['activo'] ? 'bi-check-circle' : 'bi-pause-circle' }}"></i>
                                                    {{ $item['activo'] ? 'Activo' : 'Inactivo' }}
                                                </span>
                                                <div class="cargo-item-row__acoes cargo-acoes-compact">
                                                    @can('view', $director)
                                                        <a href="{{ route('directores-turma.show', $director) }}" class="btn btn-sm btn-outline-primary">
                                                            <i class="bi bi-eye"></i>
                                                        </a>
                                                    @endcan
                                                    @if($podeEditarRegistro && Gate::allows('update', $director))
                                                        <button type="button"
                                                                class="btn btn-sm btn-outline-warning btn-substituir"
                                                                title="Substituir director"
                                                                data-bs-toggle="modal"
                                                                data-bs-target="#modalSubstituirDirector"
                                                                data-id="{{ $director->id }}"
                                                                data-turma="{{ $item['turma']->nome }}"
                                                                data-actual-id="{{ $director->user_id }}"
                                                                data-actual-nome="{{ $grupo->director->name }}">
                                                            <i class="bi bi-arrow-left-right"></i>
                                                        </button>
                                                        <a href="{{ route('directores-turma.edit', $director) }}" class="btn btn-sm btn-outline-secondary">
                                                            <i class="bi bi-pencil"></i>
                                                        </a>
                                                    @endif
                                                    @if($podeEditarRegistro && Gate::allows('delete', $director))
                                                        <form action="{{ route('directores-turma.destroy', $director) }}" method="POST" class="d-inline"
                                                              onsubmit="return confirm('Eliminar este director de turma?');">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar">
                                                                <i class="bi bi-trash"></i>
                                                            </button>
                                                        </form>
                                                    @endif
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </article>
                            @endforeach
                        </div>
                        <div class="cargo-search-empty text-center text-muted py-5" style="display:none;">
                            <i class="bi bi-search fs-3 d-block mb-2"></i>
                            Nenhum resultado para a pesquisa.
                        </div>
                    </div>

                    @if($directores->hasPages())
                        <div class="card-footer bg-white">{{ $directores->links() }}</div>
                    @endif
                </div>

                <div class="modal fade" id="modalSubstituirDirector" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog">
                        <form id="formSubstituirDirector" method="POST">
                            @csrf
                            @method('PATCH')
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title"><i class="bi bi-arrow-left-right me-2"></i>Substituir Director de Turma</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="mb-3">
                                        <label class="form-label text-muted small">Turma</label>
                                        <div class="fw-semibold" id="modalSubDirTurma">—</div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label text-muted small">Director Actual</label>
                                        <div class="fw-semibold" id="modalSubDirActual">—</div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="modalSubDirNovo" class="form-label">Novo Director <span class="text-danger">*</span></label>
                                        <select class="form-select" id="modalSubDirNovo" name="user_id" required>
                                            <option value="">— Seleccione —</option>
                                            @foreach($professoresDisponiveis ?? [] as $prof)
                                                <option value="{{ $prof->id }}">{{ $prof->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                    <button type="submit" class="btn btn-warning">
                                        <i class="bi bi-arrow-left-right me-1"></i>Substituir
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            @endif
        </div>
    </div>

    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.cargo-search').forEach(function (input) {
                const listId = input.getAttribute('data-list');
                const listEl = document.getElementById(listId);
                const cards = listEl ? Array.from(listEl.querySelectorAll('[data-searchable]')) : [];
                const emptyEl = listEl?.parentElement?.querySelector('.cargo-search-empty');

                input.addEventListener('input', function () {
                    const term = this.value.toLowerCase().trim();
                    let visible = 0;
                    cards.forEach(function (card) {
                        const match = !term || card.textContent.toLowerCase().includes(term);
                        card.style.display = match ? '' : 'none';
                        if (match) visible++;
                    });
                    if (listEl) listEl.style.display = visible ? '' : 'none';
                    if (emptyEl) emptyEl.style.display = visible ? 'none' : 'block';
                });
            });

            const modal = document.getElementById('modalSubstituirDirector');
            if (!modal) return;

            const form = document.getElementById('formSubstituirDirector');
            const turmaEl = document.getElementById('modalSubDirTurma');
            const actualEl = document.getElementById('modalSubDirActual');
            const selectNovo = document.getElementById('modalSubDirNovo');
            const baseUrl = "{{ url('directores-turma') }}";

            modal.addEventListener('show.bs.modal', function (event) {
                const btn = event.relatedTarget;
                form.action = `${baseUrl}/${btn.getAttribute('data-id')}/substituir`;
                turmaEl.textContent = btn.getAttribute('data-turma');
                actualEl.textContent = btn.getAttribute('data-actual-nome');
                const actualId = btn.getAttribute('data-actual-id');
                selectNovo.value = '';
                Array.from(selectNovo.options).forEach(opt => {
                    opt.hidden = (opt.value === actualId);
                    opt.disabled = (opt.value === actualId);
                });
            });
        });
    </script>
    @endpush
</x-app-layout>
