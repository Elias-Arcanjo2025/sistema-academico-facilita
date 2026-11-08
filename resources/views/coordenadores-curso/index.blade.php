<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h2 class="h4 mb-0">
                <i class="bi bi-mortarboard-fill me-2 text-primary"></i>Coordenadores de Curso
            </h2>
            @can('create', App\Models\CoordenadorCurso::class)
                @if($podeEditar)
                    <a href="{{ route('coordenadores-curso.create') }}" class="btn btn-primary">
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
            <x-tip-card icon="bi-mortarboard-fill" variant="info" title="Coordenadores de Curso" id="tip-coordenadores-index" compact>
                Acompanham o desempenho do curso e acedem a pautas de todas as turmas. Use <strong>Substituir</strong> para trocar o coordenador sem perder o histórico.
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
                    <x-stat-card icon="bi-mortarboard" icon-class="stat-icon-primary" :value="$totalCursos" label="Cursos" />
                </div>
                <div class="col-6 col-md-3">
                    <x-stat-card icon="bi-check-circle" icon-class="stat-icon-success" :value="$totalActivos" label="Activos" />
                </div>
                <div class="col-6 col-md-3">
                    <x-stat-card icon="bi-person-badge" icon-class="stat-icon-info" :value="$totalRegistos" label="Coordenadores" />
                </div>
                <div class="col-6 col-md-3">
                    <x-stat-card icon="bi-pause-circle" icon-class="stat-icon-warning" :value="$totalInactivos" label="Inactivos" />
                </div>
            </div>

            <div class="card shadow-soft mb-3 cargo-filtros">
                <div class="card-body py-3">
                    <form action="{{ route('coordenadores-curso.index') }}" method="GET" class="row g-2 align-items-end">
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

            @if($coordenadores->isEmpty())
                <div class="card shadow-soft">
                    <div class="card-body">
                        <x-empty-state
                            icon="bi-person-badge"
                            message="Nenhum coordenador de curso encontrado."
                            :create-url="$podeEditar ? route('coordenadores-curso.create') : null"
                            create-label="Criar primeiro coordenador"
                            :can-create="$podeEditar && Gate::allows('create', App\Models\CoordenadorCurso::class)"
                        />
                    </div>
                </div>
            @else
                <div class="card shadow-soft fade-in">
                    <div class="card-header bg-white">
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                            <h5 class="mb-0"><i class="bi bi-grid-3x3-gap me-2"></i>Coordenadores</h5>
                            <span class="badge bg-primary">{{ $coordenadores->total() }} registos</span>
                        </div>
                        <div class="d-flex flex-column flex-md-row cargo-list-header justify-content-between align-items-stretch align-items-md-center">
                            <div class="cargo-legenda d-flex flex-wrap align-items-center gap-2">
                                <span class="cargo-chip cargo-chip--activo py-0 px-2"><i class="bi bi-check-circle"></i> Activo</span>
                                <span class="cargo-chip cargo-chip--inactivo py-0 px-2"><i class="bi bi-pause-circle"></i> Inactivo</span>
                            </div>
                            <div class="input-group input-group-sm cargo-search-wrap">
                                <span class="input-group-text"><i class="bi bi-search"></i></span>
                                <input type="text" class="form-control cargo-search" data-list="coordenadores-list" placeholder="Pesquisar..." autocomplete="off">
                            </div>
                        </div>
                    </div>

                    <div class="card-body">
                        <div class="d-flex flex-column gap-3" id="coordenadores-list">
                            @foreach($coordenadores as $coordenador)
                                @php
                                    $podeEditarRegistro = auth()->user()->hasRole('admin_full') || $podeEditar;
                                    $cardClass = 'cargo-card cargo-card--single' . ($coordenador->activo ? '' : ' cargo-card--inactivo');
                                @endphp
                                <article class="{{ $cardClass }}" data-searchable>
                                    <div class="cargo-single__head">
                                        <div class="cargo-professor flex-grow-1">
                                            <div class="avatar-circle">
                                                {{ strtoupper(substr($coordenador->coordenador->name ?? '?', 0, 1)) }}
                                            </div>
                                            <div class="min-width-0">
                                                <div class="cargo-professor__nome">{{ $coordenador->coordenador->name }}</div>
                                                <div class="cargo-professor__sub">Coordenador de curso</div>
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
                                        <i class="bi bi-mortarboard me-1 text-primary"></i>
                                        <a href="{{ route('cursos.show', $coordenador->curso) }}">{{ $coordenador->curso->nome }}</a>
                                    </div>

                                    <div class="cargo-single__acoes cargo-acoes-compact">
                                        @can('view', $coordenador)
                                            <a href="{{ route('coordenadores-curso.show', $coordenador) }}" class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-eye"></i><span class="d-none d-sm-inline ms-1">Ver</span>
                                            </a>
                                        @endcan
                                        @if($podeEditarRegistro && Gate::allows('update', $coordenador))
                                            <button type="button"
                                                    class="btn btn-sm btn-outline-warning"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#modalSubstituirCoordenador"
                                                    data-id="{{ $coordenador->id }}"
                                                    data-curso="{{ $coordenador->curso->nome }}"
                                                    data-actual-id="{{ $coordenador->user_id }}"
                                                    data-actual-nome="{{ $coordenador->coordenador->name }}">
                                                <i class="bi bi-arrow-left-right"></i><span class="d-none d-sm-inline ms-1">Substituir</span>
                                            </button>
                                            <a href="{{ route('coordenadores-curso.edit', $coordenador) }}" class="btn btn-sm btn-outline-secondary">
                                                <i class="bi bi-pencil"></i><span class="d-none d-sm-inline ms-1">Editar</span>
                                            </a>
                                        @endif
                                        @if($podeEditarRegistro && Gate::allows('delete', $coordenador))
                                            <form action="{{ route('coordenadores-curso.destroy', $coordenador) }}" method="POST" class="d-inline flex-grow-1 flex-sm-grow-0"
                                                  onsubmit="return confirm('Eliminar este coordenador?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger w-100">
                                                    <i class="bi bi-trash"></i><span class="d-none d-sm-inline ms-1">Eliminar</span>
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

                <div class="modal fade" id="modalSubstituirCoordenador" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog">
                        <form id="formSubstituirCoordenador" method="POST">
                            @csrf
                            @method('PATCH')
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title"><i class="bi bi-arrow-left-right me-2"></i>Substituir Coordenador</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="mb-3">
                                        <label class="form-label text-muted small">Curso</label>
                                        <div class="fw-semibold" id="modalSubCoordCurso">—</div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label text-muted small">Coordenador Actual</label>
                                        <div class="fw-semibold" id="modalSubCoordActual">—</div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="modalSubCoordNovo" class="form-label">Novo Coordenador <span class="text-danger">*</span></label>
                                        <select class="form-select" id="modalSubCoordNovo" name="user_id" required>
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

            const modal = document.getElementById('modalSubstituirCoordenador');
            if (!modal) return;

            const form = document.getElementById('formSubstituirCoordenador');
            const cursoEl = document.getElementById('modalSubCoordCurso');
            const actualEl = document.getElementById('modalSubCoordActual');
            const selectNovo = document.getElementById('modalSubCoordNovo');
            const baseUrl = "{{ url('coordenadores-curso') }}";

            modal.addEventListener('show.bs.modal', function (event) {
                const btn = event.relatedTarget;
                form.action = `${baseUrl}/${btn.getAttribute('data-id')}/substituir`;
                cursoEl.textContent = btn.getAttribute('data-curso');
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
