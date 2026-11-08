<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="h4 mb-0"><i class="bi bi-calendar-week me-2"></i>Planificações</h2>
            @can('pedagogico.planificacao.criar')
                <a href="{{ route('pedagogico.planificacoes.create') }}" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-lg"></i> Nova
                </a>
            @endcan
        </div>
    </x-slot>

    <div class="py-4">
        <div class="container">
            <x-tip-card icon="bi-calendar-week" variant="info" title="Planificações" id="tip-planificacoes-index" compact>
                Organize o trimestre por turma e disciplina (quinzenal ou mensal). Filtre pelo ano lectivo para ver planos anteriores. Depois de concluído o wizard, submeta para o coordenador da disciplina aprovar antes de activar o calendário.
            </x-tip-card>
            @foreach (['success' => 'success', 'error' => 'danger'] as $key => $variant)
                @if(session($key))
                    <div class="alert alert-{{ $variant }}">{{ session($key) }}</div>
                @endif
            @endforeach

            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3 align-items-end">
                        <div class="col-sm-6 col-lg-3">
                            <x-ano-lectivo-selector
                                :anoLectivoId="$anoLectivoId"
                                :anosLectivos="$anosLectivos"
                                size="sm"
                                label="Ano Lectivo"
                                class="mb-0"
                            />
                        </div>
                        <div class="col-sm-6 col-lg-2">
                            <label class="form-label">Trimestre</label>
                            <select name="trimestre" class="form-select form-select-sm">
                                <option value="">Todos</option>
                                @for($t = 1; $t <= 3; $t++)
                                    <option value="{{ $t }}" @selected(($filtros['trimestre'] ?? '') == $t)>{{ $t }}º</option>
                                @endfor
                            </select>
                        </div>
                        <div class="col-sm-6 col-lg-3">
                            <label class="form-label">Turma</label>
                            <select name="turma_id" class="form-select form-select-sm">
                                <option value="">Todas</option>
                                @foreach($turmas as $turma)
                                    <option value="{{ $turma->id }}" @selected(($filtros['turma_id'] ?? '') == $turma->id)>{{ $turma->nome }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-sm-6 col-lg-2">
                            <label class="form-label">Estado</label>
                            <select name="estado" class="form-select form-select-sm">
                                <option value="">Todos</option>
                                @foreach(['rascunho','activo','concluido'] as $estado)
                                    <option value="{{ $estado }}" @selected(($filtros['estado'] ?? '') == $estado)>{{ ucfirst($estado) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-lg-2 d-flex gap-2">
                            <button class="btn btn-primary btn-sm">Filtrar</button>
                            <a href="{{ route('pedagogico.planificacoes.index') }}" class="btn btn-outline-secondary btn-sm">Limpar</a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Turma</th>
                                <th>Disciplina</th>
                                <th>Trim.</th>
                                <th>Professor</th>
                                <th>Estado</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($planificacoes as $p)
                                <tr>
                                    <td>{{ $p->turma->nome ?? '-' }}</td>
                                    <td>{{ $p->disciplina->nome ?? '-' }}</td>
                                    <td>{{ $p->trimestre }}º</td>
                                    <td>{{ $p->professor->name ?? '-' }}</td>
                                    <td><span class="badge bg-secondary">{{ $p->estado }}</span></td>
                                    <td class="text-end">
                                        <a href="{{ route('pedagogico.planificacoes.show', $p) }}" class="btn btn-sm btn-outline-primary">Ver</a>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center text-muted py-4">Sem planificações.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($planificacoes->hasPages())
                    <div class="card-footer">{{ $planificacoes->withQueryString()->links() }}</div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
