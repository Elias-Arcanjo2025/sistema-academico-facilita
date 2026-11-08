<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between">
            <h2 class="h4 mb-0">Dashboard Pedagógico</h2>
            <a href="{{ route('pedagogico.dashboard.export-pdf', $filtros) }}" class="btn btn-outline-secondary btn-sm">Exportar PDF</a>
        </div>
    </x-slot>
    <div class="py-4">
        <div class="container">
            <x-tip-card icon="bi-graph-up" variant="info" title="Dashboard pedagógico" id="tip-dashboard-ped" compact>
                Indicadores de execução das planificações (aulas dadas, cobertura, alertas). Filtre por trimestre, turma ou disciplina para acompanhar o progresso.
            </x-tip-card>
            <form method="GET" class="card mb-4">
                <div class="card-body row g-2 align-items-end">
                    <div class="col-md-3">
                        <x-ano-lectivo-selector
                            :anoLectivoId="$anoLectivoId"
                            :anosLectivos="$anosLectivos"
                            size="sm"
                            label="Ano Lectivo"
                            class="mb-0"
                        />
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Trimestre</label>
                        <select name="trimestre" class="form-select form-select-sm">
                            @for($t=1;$t<=3;$t++)
                                <option value="{{ $t }}" @selected(($filtros['trimestre'] ?? $kpis['trimestre']) == $t)>{{ $t }}º</option>
                            @endfor
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Turma</label>
                        <select name="turma_id" class="form-select form-select-sm">
                            <option value="">Todas</option>
                            @foreach($turmas as $t)
                                <option value="{{ $t->id }}" @selected(($filtros['turma_id'] ?? '') == $t->id)>{{ $t->nome }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Disciplina</label>
                        <select name="disciplina_id" class="form-select form-select-sm">
                            <option value="">Todas</option>
                            @foreach($disciplinas as $d)
                                <option value="{{ $d->id }}" @selected(($filtros['disciplina_id'] ?? '') == $d->id)>{{ $d->nome }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button class="btn btn-primary btn-sm">Filtrar</button>
                    </div>
                </div>
            </form>

            <div class="row g-3 mb-4">
                <div class="col-md-3"><div class="card text-center p-3"><h4>{{ $kpis['total_planificacoes'] }}</h4><small>Planificações</small></div></div>
                <div class="col-md-3"><div class="card text-center p-3"><h4>{{ $kpis['planificacoes_activas'] }}</h4><small>Activas</small></div></div>
                <div class="col-md-3"><div class="card text-center p-3"><h4>{{ number_format($kpis['execucao_media_percentagem'],1) }}%</h4><small>Execução média</small></div></div>
                <div class="col-md-3"><div class="card text-center p-3"><h4>{{ $kpis['correcoes_pendentes'] }}</h4><small>Correcções pendentes</small></div></div>
            </div>

            <div class="card mb-4">
                <div class="card-header">Médias de notas ({{ $kpis['trimestre'] }}º trimestre)</div>
                <div class="card-body row text-center">
                    @foreach(['MAC','PP','PT','MT'] as $tipo)
                    <div class="col-md-3">
                        <strong>{{ $tipo }}</strong><br>
                        {{ $kpis['medias_notas'][$tipo] ?? '—' }}
                    </div>
                    @endforeach
                </div>
            </div>

            <div class="card">
                <div class="card-header">Aulas por estado</div>
                <ul class="list-group list-group-flush">
                    @forelse($kpis['aulas_por_estado'] as $estado => $total)
                        <li class="list-group-item d-flex justify-content-between"><span>{{ $estado }}</span><span>{{ $total }}</span></li>
                    @empty
                        <li class="list-group-item text-muted">Sem dados.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
</x-app-layout>
