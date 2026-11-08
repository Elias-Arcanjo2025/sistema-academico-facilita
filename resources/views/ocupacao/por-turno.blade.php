<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h2 class="h4 mb-0">
                <i class="bi bi-clock me-2 text-primary"></i>Ocupação por Turno
            </h2>
            <a href="{{ route('ocupacao.index', ['ano_lectivo_id' => $anoLectivoId]) }}" class="btn btn-outline-primary">
                <i class="bi bi-building me-1"></i> Ver por Sala
            </a>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="container">
            <x-breadcrumb :items="[['label' => 'Infraestrutura', 'url' => route('salas.index')], ['label' => 'Ocupação por Turno']]" />

            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="card shadow-soft border-0 h-100">
                        <div class="card-body">
                            <div class="text-muted small">Turnos activos</div>
                            <div class="h3 fw-bold mb-0">{{ $stats['turnos_activos'] }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card shadow-soft border-0 h-100">
                        <div class="card-body">
                            <div class="text-muted small">Turmas no ano</div>
                            <div class="h3 fw-bold text-primary mb-0">{{ $stats['turmas_total'] }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card shadow-soft border-0 h-100">
                        <div class="card-body">
                            <div class="text-muted small">Alunos matriculados</div>
                            <div class="h3 fw-bold text-success mb-0">{{ $stats['alunos_total'] }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow-soft mb-4">
                <div class="card-body">
                    <form action="{{ route('ocupacao.por-turno') }}" method="GET" class="row g-3 align-items-end">
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
                    </form>
                </div>
            </div>

            @if($turnos->isEmpty())
                <x-empty-state icon="bi-clock" message="Nenhum turno activo encontrado." />
            @else
                <div class="row g-4">
                    @foreach($turnos as $turno)
                        <div class="col-md-6 col-xl-4">
                            <div class="card shadow-soft h-100">
                                <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
                                    <h3 class="h6 mb-0">
                                        <a href="{{ route('turnos.show', $turno) }}" class="text-decoration-none">
                                            {{ $turno->nome }}
                                        </a>
                                    </h3>
                                    <span class="badge bg-primary">{{ $turno->turmas_count }} turma(s)</span>
                                </div>
                                <div class="card-body">
                                    @if($turno->hora_inicio && $turno->hora_fim)
                                        <p class="text-muted small mb-3">
                                            <i class="bi bi-clock me-1"></i>
                                            {{ \Carbon\Carbon::parse($turno->hora_inicio)->format('H:i') }}
                                            – {{ \Carbon\Carbon::parse($turno->hora_fim)->format('H:i') }}
                                        </p>
                                    @endif

                                    @if($turno->turmas->isEmpty())
                                        <p class="text-muted mb-0">Nenhuma turma neste turno para {{ $anoLectivoLabel }}.</p>
                                    @else
                                        <div class="list-group list-group-flush">
                                            @foreach($turno->turmas as $turma)
                                                <div class="list-group-item px-0 border-0 border-bottom">
                                                    <div class="d-flex justify-content-between gap-2">
                                                        <div>
                                                            <a href="{{ route('turmas.show', $turma) }}" class="fw-semibold">
                                                                {{ $turma->nome }}
                                                            </a>
                                                            <div class="small text-muted">{{ $turma->curso?->nome }}</div>
                                                        </div>
                                                        <div class="text-end">
                                                            <span class="badge bg-info">{{ $turma->alunos_count }} alunos</span>
                                                            @if($turma->sala)
                                                                <div class="small mt-1">
                                                                    <a href="{{ route('salas.show', $turma->sala) }}" class="text-muted">
                                                                        <i class="bi bi-door-open"></i> {{ $turma->sala->nome }}
                                                                    </a>
                                                                </div>
                                                            @endif
                                                        </div>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                                @if($turno->turmas->isNotEmpty())
                                    <div class="card-footer bg-transparent small text-muted">
                                        Total de alunos: {{ $turno->turmas->sum('alunos_count') }}
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>

                @if($turnos->hasPages())
                    <div class="d-flex justify-content-center mt-4">
                        {{ $turnos->links() }}
                    </div>
                @endif
            @endif
        </div>
    </div>
</x-app-layout>
