<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h2 class="h4 mb-0">
                <i class="bi bi-link-45deg me-2 text-primary"></i>Associações Professor-Disciplina-Turma
            </h2>
            @can('create', App\Models\ProfessorDisciplinaTurma::class)
                <a href="{{ route('associacoes.create') }}" class="btn btn-primary">
                    <i class="bi bi-plus-lg me-1"></i> Nova Associação
                </a>
            @endcan
        </div>
    </x-slot>

    @push('styles')
    <style>
        .associacoes-filtros .form-label { font-size: 0.8rem; margin-bottom: 0.25rem; color: var(--text-muted); }
        .associacoes-filtros .form-select { font-size: 0.875rem; }

        .associacoes-legenda { font-size: 0.8rem; color: var(--text-muted); }
        .associacoes-legenda .badge { font-size: 0.72rem; }

        .associacao-card {
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 1rem 1.1rem;
            background: var(--bg-card);
            transition: box-shadow 0.15s ease, border-color 0.15s ease;
        }
        .associacao-card:hover {
            box-shadow: 0 4px 14px rgba(79, 70, 229, 0.1);
            border-color: rgba(79, 70, 229, 0.25);
        }
        .associacao-card--parcial { border-left: 4px solid #f59e0b; }
        .associacao-card--inactiva { border-left: 4px solid #94a3b8; opacity: 0.92; }

        .associacao-card__top {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-start;
            justify-content: space-between;
            gap: 0.75rem 1rem;
        }
        .associacao-disciplina {
            flex: 1 1 180px;
            padding: 0.55rem 0.7rem;
            border-radius: 10px;
            background: rgba(6, 182, 212, 0.08);
            border: 1px solid rgba(6, 182, 212, 0.15);
        }
        .cargo-search-wrap { width: 100%; max-width: 320px; }
        @media (min-width: 768px) { .cargo-search-wrap { width: 280px; } }
        .cargo-list-header { gap: 0.75rem; }

        .associacao-card__head {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: flex-start;
            gap: 0.75rem;
            width: 100%;
        }
        .associacao-acoes {
            display: flex;
            flex-wrap: wrap;
            gap: 0.4rem;
            width: 100%;
            padding-top: 0.75rem;
            border-top: 1px solid var(--border-color);
        }

        .associacao-professor {
            display: flex;
            align-items: center;
            gap: 0.65rem;
            min-width: 0;
        }
        .associacao-professor__nome {
            font-weight: 600;
            font-size: 0.95rem;
            line-height: 1.35;
            word-break: break-word;
        }

        .associacao-disciplina__nome { font-size: 0.95rem; font-weight: 600; }
        .associacao-disciplina__curso { font-size: 0.78rem; color: var(--text-muted); margin-top: 0.15rem; }

        .associacao-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 0.35rem;
            justify-content: flex-end;
        }
        @media (max-width: 991.98px) {
            .associacao-meta { justify-content: flex-start; }
        }

        .associacao-acoes {
            display: flex;
            flex-wrap: wrap;
            gap: 0.4rem;
            justify-content: flex-end;
        }
        @media (max-width: 991.98px) {
            .associacao-acoes { justify-content: flex-start; }
        }

        .associacao-card__turmas {
            margin-top: 0.85rem;
            padding-top: 0.85rem;
            border-top: 1px dashed var(--border-color);
        }
        .associacao-card__turmas-label {
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: var(--text-muted);
            font-weight: 600;
            margin-bottom: 0.45rem;
        }
        .associacao-turma-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.3rem 0.55rem;
            border-radius: 999px;
            font-size: 0.78rem;
            font-weight: 600;
            text-decoration: none;
            border: 1px solid transparent;
        }
        .associacao-turma-chip--activa {
            background: rgba(139, 92, 246, 0.12);
            color: #6d28d9;
            border-color: rgba(139, 92, 246, 0.2);
        }
        .associacao-turma-chip--activa:hover { background: rgba(139, 92, 246, 0.2); color: #5b21b6; }
        .associacao-turma-chip--inactiva {
            background: #f1f5f9;
            color: #64748b;
            border-color: #e2e8f0;
        }
        .associacao-turma-chip--inactiva:hover { background: #e2e8f0; color: #475569; }

        .filtros-activos .badge { font-size: 0.78rem; font-weight: 500; }
    </style>
    @endpush

    <div class="py-4">
        <div class="container">
            <x-tip-card icon="bi-link-45deg" variant="info" title="Associações Professor-Turma-Disciplina" id="tip-associacoes-index" compact>
                Definem quem pode lançar notas. Para <strong>substituir ou remover</strong> um docente de uma disciplina: desactive a associação (ou a turma no grupo) — o histórico de notas mantém-se.
            </x-tip-card>

            <div class="row g-3 mb-4">
                <div class="col-6 col-md-3">
                    <x-stat-card icon="bi-link-45deg" icon-class="stat-icon-primary" :value="$totalGrupos" label="Grupos" />
                </div>
                <div class="col-6 col-md-3">
                    <x-stat-card icon="bi-check-circle" icon-class="stat-icon-success" :value="$totalActivas" label="Turmas activas" />
                </div>
                <div class="col-6 col-md-3">
                    <x-stat-card icon="bi-person-badge" icon-class="stat-icon-info" :value="$totalProfessores" label="Professores" />
                </div>
                <div class="col-6 col-md-3">
                    <x-stat-card icon="bi-pause-circle" icon-class="stat-icon-warning" :value="$totalInactivas" label="Turmas inactivas" />
                </div>
            </div>

            <div class="card shadow-soft mb-3 associacoes-filtros">
                <div class="card-body py-3">
                    <form action="{{ route('associacoes.index') }}" method="GET" class="row g-2 g-md-3 align-items-end">
                        <div class="col-md-3">
                            <label for="ano_lectivo_id" class="form-label">Ano lectivo</label>
                            <select class="form-select form-select-sm" id="ano_lectivo_id" name="ano_lectivo_id" onchange="this.form.submit()">
                                <option value="">Todos</option>
                                @foreach($anosLectivos as $id => $nome)
                                    <option value="{{ $id }}" {{ $anoLectivoId == $id ? 'selected' : '' }}>{{ $nome }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="professor_id" class="form-label">Professor</label>
                            <select class="form-select form-select-sm" id="professor_id" name="professor_id" onchange="this.form.submit()">
                                <option value="">Todos</option>
                                @foreach($professores as $professor)
                                    <option value="{{ $professor->id }}" {{ $professorId == $professor->id ? 'selected' : '' }}>{{ $professor->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="turma_id" class="form-label">Turma</label>
                            <select class="form-select form-select-sm" id="turma_id" name="turma_id" onchange="this.form.submit()">
                                <option value="">Todas</option>
                                @foreach($turmas as $turma)
                                    <option value="{{ $turma->id }}" {{ $turmaId == $turma->id ? 'selected' : '' }}>
                                        {{ $turma->nome }} — {{ $turma->curso->nome }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <a href="{{ route('associacoes.index') }}" class="btn btn-outline-secondary btn-sm w-100">
                                <i class="bi bi-x-lg"></i> Limpar
                            </a>
                        </div>
                    </form>

                    @if($professorId || $turmaId || $anoLectivoId)
                        <div class="filtros-activos mt-3 d-flex flex-wrap align-items-center gap-2">
                            <span class="text-muted small">Filtros:</span>
                            @if($anoLectivoId)
                                <span class="badge bg-light text-dark border">{{ $anoLectivo }}</span>
                            @endif
                            @if($professorId)
                                <span class="badge bg-light text-dark border">{{ $professores->firstWhere('id', $professorId)?->name }}</span>
                            @endif
                            @if($turmaId)
                                <span class="badge bg-light text-dark border">{{ $turmas->firstWhere('id', $turmaId)?->nome }}</span>
                            @endif
                        </div>
                    @endif
                </div>
            </div>

            <div class="card shadow-soft fade-in">
                <div class="card-header bg-white">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                        <h5 class="mb-0"><i class="bi bi-grid-3x3-gap me-2"></i>Associações agrupadas</h5>
                        <span class="badge bg-primary">{{ $associacoes->total() }} grupos</span>
                    </div>

                    <div class="d-flex flex-column flex-md-row cargo-list-header justify-content-between align-items-stretch align-items-md-center">
                        <div class="associacoes-legenda d-flex flex-wrap align-items-center gap-2">
                            <span class="associacao-turma-chip associacao-turma-chip--activa py-0 px-2"><i class="bi bi-check-circle"></i> Activa</span>
                            <span class="associacao-turma-chip associacao-turma-chip--inactiva py-0 px-2"><i class="bi bi-pause-circle"></i> Inactiva</span>
                        </div>
                        <div class="input-group input-group-sm cargo-search-wrap">
                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                            <input type="text" class="form-control" id="associacoes-search" placeholder="Pesquisar professor, disciplina, turma..." autocomplete="off">
                            <button class="btn btn-outline-secondary" type="button" id="associacoes-search-clear" style="display:none;"><i class="bi bi-x-lg"></i></button>
                        </div>
                    </div>
                    <small class="text-muted d-block mt-2" id="associacoes-search-count"></small>
                </div>

                <div class="card-body">
                    <div class="d-flex flex-column gap-3" id="associacoes-list">
                        @forelse($associacoes as $grupo)
                            @php
                                $cardClass = 'associacao-card';
                                if ($grupo->activas === 0) {
                                    $cardClass .= ' associacao-card--inactiva';
                                } elseif ($grupo->inactivas > 0) {
                                    $cardClass .= ' associacao-card--parcial';
                                }
                                $assoc = $grupo->primeira_associacao;
                            @endphp
                            <article class="{{ $cardClass }}" data-searchable>
                                <div class="associacao-card__top">
                                    <div class="associacao-card__head">
                                        <div class="associacao-professor flex-grow-1">
                                            <div class="avatar-circle">
                                                {{ strtoupper(substr($grupo->professor->name ?? '?', 0, 1)) }}
                                            </div>
                                            <div class="min-width-0">
                                                <div class="associacao-professor__nome">{{ $grupo->professor->name }}</div>
                                                <small class="text-muted">{{ $grupo->total_turmas }} {{ $grupo->total_turmas === 1 ? 'turma' : 'turmas' }}</small>
                                            </div>
                                        </div>
                                        <div class="associacao-meta">
                                            <span class="badge bg-light text-dark border">{{ $grupo->ano_lectivo }}</span>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle">
                                                {{ $grupo->activas }} activa{{ $grupo->activas === 1 ? '' : 's' }}
                                            </span>
                                            @if($grupo->inactivas > 0)
                                                <span class="badge bg-secondary-subtle text-secondary border">
                                                    {{ $grupo->inactivas }} inactiva{{ $grupo->inactivas === 1 ? '' : 's' }}
                                                </span>
                                            @endif
                                        </div>
                                    </div>

                                    <div class="associacao-disciplina w-100">
                                        <div class="associacao-disciplina__nome">
                                            <span class="badge bg-info text-dark">{{ $grupo->disciplina->nome }}</span>
                                        </div>
                                        @if($grupo->disciplina->cursos_nomes ?? false)
                                            <div class="associacao-disciplina__curso">{{ $grupo->disciplina->cursos_nomes }}</div>
                                        @endif
                                    </div>
                                </div>

                                <div class="associacao-acoes cargo-acoes-compact">
                                    @can('view', $assoc)
                                        <a href="{{ route('associacoes.show-grupo', ['professor_id' => $grupo->professor->id, 'disciplina_id' => $grupo->disciplina->id, 'ano_lectivo' => $grupo->ano_lectivo]) }}"
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-eye"></i><span class="d-none d-sm-inline ms-1">Ver</span>
                                        </a>
                                    @endcan
                                    @can('update', $assoc)
                                        <a href="{{ route('associacoes.edit-grupo', ['professor_id' => $grupo->professor->id, 'disciplina_id' => $grupo->disciplina->id, 'ano_lectivo' => $grupo->ano_lectivo]) }}"
                                           class="btn btn-sm btn-outline-secondary">
                                            <i class="bi bi-pencil"></i><span class="d-none d-sm-inline ms-1">Editar</span>
                                        </a>
                                    @endcan
                                </div>

                                <div class="associacao-card__turmas">
                                    <div class="associacao-card__turmas-label">Turmas associadas</div>
                                    <div class="d-flex flex-wrap gap-2">
                                        @foreach($grupo->turmas as $turmaItem)
                                            <a href="{{ route('turmas.show', $turmaItem['turma']) }}"
                                               class="associacao-turma-chip {{ $turmaItem['activo'] ? 'associacao-turma-chip--activa' : 'associacao-turma-chip--inactiva' }}"
                                               title="{{ $turmaItem['turma']->curso->nome ?? '' }}">
                                                {{ $turmaItem['turma']->nome }}
                                                <i class="bi {{ $turmaItem['activo'] ? 'bi-check-circle' : 'bi-pause-circle' }}"></i>
                                            </a>
                                        @endforeach
                                    </div>
                                </div>
                            </article>
                        @empty
                            <x-empty-state
                                icon="bi-link-45deg"
                                message="Nenhuma associação encontrada."
                                :create-url="route('associacoes.create')"
                                create-label="Criar primeira associação"
                                :can-create="Gate::allows('create', App\Models\ProfessorDisciplinaTurma::class)"
                            />
                        @endforelse
                    </div>

                    <div id="associacoes-search-empty" class="text-center text-muted py-5" style="display:none;">
                        <i class="bi bi-search fs-3 d-block mb-2"></i>
                        Nenhum resultado para a pesquisa.
                    </div>
                </div>

                @if($associacoes->hasPages())
                    <div class="card-footer bg-white">
                        {{ $associacoes->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const searchInput = document.getElementById('associacoes-search');
            const clearBtn = document.getElementById('associacoes-search-clear');
            const countEl = document.getElementById('associacoes-search-count');
            const listEl = document.getElementById('associacoes-list');
            const emptyEl = document.getElementById('associacoes-search-empty');
            const cards = Array.from(document.querySelectorAll('[data-searchable]'));
            const total = cards.length;

            function updateCount(visible) {
                if (!countEl) return;
                countEl.textContent = visible === total
                    ? `${total} ${total === 1 ? 'grupo' : 'grupos'} nesta página`
                    : `${visible} de ${total} grupos visíveis`;
            }

            updateCount(total);

            if (!searchInput) return;

            searchInput.addEventListener('input', function () {
                const term = this.value.toLowerCase().trim();
                clearBtn.style.display = term ? 'inline-block' : 'none';

                let visible = 0;
                cards.forEach(function (card) {
                    const match = !term || card.textContent.toLowerCase().includes(term);
                    card.style.display = match ? '' : 'none';
                    if (match) visible++;
                });

                if (listEl) listEl.style.display = visible ? '' : 'none';
                if (emptyEl) emptyEl.style.display = visible ? 'none' : 'block';
                updateCount(visible);
            });

            clearBtn.addEventListener('click', function () {
                searchInput.value = '';
                searchInput.dispatchEvent(new Event('input'));
                searchInput.focus();
            });
        });
    </script>
    @endpush
</x-app-layout>
