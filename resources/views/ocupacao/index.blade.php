<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h2 class="h4 mb-0">
                <i class="bi bi-building me-2 text-primary"></i>Ocupação de Salas
            </h2>
            <a href="{{ route('ocupacao.por-turno', ['ano_lectivo_id' => $anoLectivoId]) }}" class="btn btn-outline-primary">
                <i class="bi bi-clock me-1"></i> Ver por Turno
            </a>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="container">
            <x-breadcrumb :items="[['label' => 'Infraestrutura', 'url' => route('salas.index')], ['label' => 'Ocupação de Salas']]" />

            <x-tip-card icon="bi-building" variant="info" title="Ocupação de salas por turma">
                Mostra quais salas estão atribuídas a turmas activas no ano lectivo seleccionado.
                A taxa reflecte salas com pelo menos uma turma. Conflitos aparecem quando a mesma sala
                e turno têm várias turmas (verifique horários).
            </x-tip-card>

            <!-- Filtros -->
            <div class="card shadow-soft mb-4">
                <div class="card-body">
                    <form action="{{ route('ocupacao.index') }}" method="GET" class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label for="ano_lectivo_id" class="form-label">Ano Lectivo</label>
                            <select class="form-select" id="ano_lectivo_id" name="ano_lectivo_id" onchange="this.form.submit()">
                                @forelse($anosLectivos as $ano)
                                    <option value="{{ $ano->id }}" @selected($anoLectivoId == $ano->id)>
                                        {{ $ano->nome }}
                                    </option>
                                @empty
                                    <option value="">— Sem anos lectivos —</option>
                                @endforelse
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="turno_id" class="form-label">Turno</label>
                            <select class="form-select" id="turno_id" name="turno_id" onchange="this.form.submit()">
                                <option value="">Todos os turnos</option>
                                @foreach($turnos as $turno)
                                    <option value="{{ $turno->id }}" @selected($turnoId == $turno->id)>
                                        {{ $turno->nome }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <a href="{{ route('ocupacao.index') }}" class="btn btn-outline-secondary">
                                <i class="bi bi-x-lg me-1"></i> Limpar filtros
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Estatísticas -->
            <div class="row g-3 mb-4">
                <div class="col-md-3 col-sm-6">
                    <div class="card shadow-soft border-0 h-100">
                        <div class="card-body">
                            <div class="text-muted small">Total de salas</div>
                            <div class="display-6 fw-bold text-primary mb-0">{{ $stats['total_salas'] }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="card shadow-soft border-0 h-100 border-start border-success border-3">
                        <div class="card-body">
                            <div class="text-muted small">Salas ocupadas</div>
                            <div class="display-6 fw-bold text-success mb-0">{{ $stats['salas_ocupadas'] }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="card shadow-soft border-0 h-100">
                        <div class="card-body">
                            <div class="text-muted small">Salas livres</div>
                            <div class="display-6 fw-bold text-secondary mb-0">{{ $stats['salas_livres'] }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="card shadow-soft border-0 h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="text-muted small">Taxa de ocupação</span>
                                <span class="fw-bold">{{ $stats['taxa_ocupacao'] }}%</span>
                            </div>
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar bg-primary" role="progressbar"
                                     style="width: {{ min(100, $stats['taxa_ocupacao']) }}%"></div>
                            </div>
                            <div class="small text-muted mt-2">{{ $stats['turmas_alocadas'] }} turma(s) alocada(s)</div>
                        </div>
                    </div>
                </div>
            </div>

            @if(! empty($conflitos))
                <div class="alert alert-warning shadow-soft mb-4">
                    <h6 class="alert-heading"><i class="bi bi-exclamation-triangle me-1"></i> Conflitos detectados</h6>
                    <p class="small mb-2">A mesma sala está atribuída a mais de uma turma no mesmo turno:</p>
                    <ul class="small mb-0">
                        @foreach($conflitos as $conflito)
                            <li>
                                <strong>{{ $conflito['sala'] }}</strong> ({{ $conflito['turno'] }}):
                                {{ implode(', ', $conflito['turmas']) }}
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if($salas->isEmpty())
                <x-empty-state icon="bi-door-closed" message="Nenhuma sala activa encontrada." />
            @else
                <div class="card shadow-soft">
                    <div class="card-header bg-transparent d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <h3 class="h6 mb-0">
                            <i class="bi bi-door-open me-1"></i>
                            Ocupação por sala — {{ $anoLectivoLabel }}
                            @if($turnoId)
                                <span class="text-muted fw-normal">· {{ $turnos->firstWhere('id', $turnoId)?->nome }}</span>
                            @endif
                        </h3>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Sala</th>
                                    <th>Capacidade</th>
                                    <th>Turmas</th>
                                    <th>Utilização</th>
                                    <th>Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($salas as $sala)
                                    @php $cap = $sala->capacidade_info; @endphp
                                    <tr>
                                        <td>
                                            <a href="{{ route('salas.show', $sala) }}" class="fw-semibold text-decoration-none">
                                                {{ $sala->nome }}
                                            </a>
                                            <div class="small text-muted">{{ $sala->codigo }}</div>
                                        </td>
                                        <td>{{ $sala->capacidade ? $sala->capacidade.' lugares' : '—' }}</td>
                                        <td>
                                            @if($sala->turmas->isEmpty())
                                                <span class="text-muted">Sem turmas</span>
                                            @else
                                                @foreach($sala->turmas as $turma)
                                                    <div class="mb-2 pb-2 {{ ! $loop->last ? 'border-bottom border-light' : '' }}">
                                                        <a href="{{ route('turmas.show', $turma) }}" class="fw-semibold">{{ $turma->nome }}</a>
                                                        <div class="small text-muted">
                                                            {{ $turma->curso?->nome }} · {{ $turma->turno?->nome }}
                                                            · {{ $turma->classe?->nome ?? '—' }}
                                                        </div>
                                                        <span class="badge bg-light text-dark">{{ $turma->alunos_count }} aluno(s)</span>
                                                    </div>
                                                @endforeach
                                            @endif
                                        </td>
                                        <td style="min-width: 120px;">
                                            @if($cap)
                                                <div class="small mb-1">{{ $cap['alunos'] }}/{{ $cap['capacidade'] }} lugares</div>
                                                <div class="progress" style="height: 6px;">
                                                    <div class="progress-bar {{ $cap['excede'] ? 'bg-danger' : 'bg-info' }}"
                                                         style="width: {{ min(100, $cap['percent']) }}%"></div>
                                                </div>
                                                @if($cap['excede'])
                                                    <span class="badge bg-danger mt-1">Excede capacidade</span>
                                                @endif
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($sala->turmas->isEmpty())
                                                <span class="badge bg-secondary">Livre</span>
                                            @else
                                                <span class="badge bg-success">Ocupada</span>
                                                <div class="small text-muted">{{ $sala->turmas->count() }} turma(s)</div>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if($salas->hasPages())
                        <div class="card-footer bg-transparent">
                            {{ $salas->links() }}
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
