<x-app-layout>
    <x-slot name="header">Dashboard do Professor</x-slot>

    <!-- Welcome Banner -->
    <div class="welcome-banner">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h2 class="welcome-title">
                    <i class="bi bi-journal-bookmark me-2"></i>Olá, {{ auth()->user()->name }}!
                </h2>
                <p class="welcome-subtitle mb-0">
                    Ano Lectivo {{ $anoLectivo }} &mdash; As suas turmas e disciplinas
                </p>
            </div>
            <div class="col-md-4 text-md-end mt-3 mt-md-0 d-flex flex-wrap gap-2 justify-content-md-end">
                <a href="{{ route('regras-avaliacao') }}" class="btn btn-outline-light btn-modern">
                    <i class="bi bi-journal-bookmark me-1"></i>Regras de Avaliação
                </a>
                <a href="{{ route('notas.lancar') }}" class="btn btn-modern btn-primary-modern">
                    <i class="bi bi-pencil-square me-1"></i>Lançar Notas
                </a>
            </div>
        </div>
    </div>

    <x-regras-avaliacao-destaque />

    @push('styles')
        <link href="{{ asset('css/dashboard-admin.css') }}" rel="stylesheet">
    @endpush

    @isset($saude)
        @include('dashboard.partials.saude-turmas', ['saude' => $saude])
    @endisset

    <x-tip-card icon="bi-lightbulb" variant="info" title="Dica para professores" id="tip-dash-professor">
        Para lançar notas, aceda a "Avaliação → Lançar Notas" no menu lateral (modos Por tipo ou Folha completa). As médias trimestrais (MT) são calculadas automaticamente após inserir MAC, PP e PT. Pode também consultar as suas turmas e disciplinas na secção "Académico".
    </x-tip-card>

    <!-- Gráficos de Análise -->
    <h5 class="mb-3 fw-semibold">
        <i class="bi bi-bar-chart-line me-2" style="color: var(--primary-color);"></i>Análise de Desempenho
    </h5>
    <div class="row g-4 mb-4">
        <div class="col-md-6">
            <div class="content-card h-100">
                <div class="content-card-header"><h6 class="content-card-title mb-0"><i class="bi bi-clipboard-data me-2"></i>Distribuição de Notas</h6></div>
                <div class="content-card-body">
                    @if(count($chartDistribuicaoNotas) > 0)
                        <div class="chart-container"><canvas id="chartDistribuicaoNotas" height="250"></canvas></div>
                    @else
                        <div class="text-center py-4">
                            <i class="bi bi-inbox text-muted fs-2"></i>
                            <p class="text-muted mt-2 mb-0">Sem dados suficientes para exibir o gráfico.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="content-card h-100">
                <div class="content-card-header"><h6 class="content-card-title mb-0"><i class="bi bi-pie-chart me-2"></i>Progresso de Lançamento</h6></div>
                <div class="content-card-body d-flex align-items-center justify-content-center">
                    @if($chartProgresso['data'][0] > 0 || $chartProgresso['data'][1] > 0)
                        <div class="chart-container"><canvas id="chartProgresso" height="250"></canvas></div>
                    @else
                        <div class="text-center py-4">
                            <i class="bi bi-inbox text-muted fs-2"></i>
                            <p class="text-muted mt-2 mb-0">Nenhuma nota lançada ainda.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-12">
            <div class="content-card">
                <div class="content-card-header"><h6 class="content-card-title mb-0"><i class="bi bi-graph-up me-2"></i>Média por Turma/Disciplina</h6></div>
                <div class="content-card-body">
                    @if(count($chartMediasPorTurma) > 0)
                        <div class="chart-container"><canvas id="chartMediasPorTurma" height="200"></canvas></div>
                    @else
                        <div class="text-center py-4">
                            <i class="bi bi-inbox text-muted fs-2"></i>
                            <p class="text-muted mt-2 mb-0">Sem dados suficientes para exibir o gráfico.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Minhas Turmas e Disciplinas -->
    <div class="content-card mb-4">
        <div class="content-card-header d-flex justify-content-between align-items-center">
            <h5 class="content-card-title"><i class="bi bi-journal-text me-2"></i>Minhas Disciplinas e Turmas</h5>
            <a href="{{ route('notas.lancar') }}" class="btn btn-sm btn-modern btn-primary-modern">
                <i class="bi bi-plus-lg me-1"></i>Lançar Notas
            </a>
        </div>
        <div class="content-card-body">
            @if($associacoes->isEmpty())
                <div class="text-center py-4">
                    <i class="bi bi-inbox text-muted fs-1"></i>
                    <p class="text-muted mt-2 mb-0">Nenhuma disciplina/turma atribuída.</p>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Turma</th>
                                <th>Curso</th>
                                <th>Disciplina</th>
                                <th class="text-center">Alunos</th>
                                <th class="text-center">Progresso</th>
                                <th class="text-end">Acções</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($estatisticas as $stat)
                            <tr>
                                <td>
                                    <a href="{{ route('turmas.show', $stat['turma']) }}" class="fw-medium text-decoration-none">
                                        {{ $stat['turma']->nome }}
                                    </a>
                                </td>
                                <td class="text-muted">{{ $stat['turma']->curso->nome }}</td>
                                <td>{{ $stat['disciplina']->nome }}</td>
                                <td class="text-center">{{ $stat['total_alunos'] }}</td>
                                <td class="text-center">
                                    @php
                                        $pct = $stat['total_alunos'] > 0 ? round(($stat['notas_lancadas'] / $stat['total_alunos']) * 100) : 0;
                                    @endphp
                                    <div class="d-flex align-items-center gap-2 justify-content-center">
                                        <div class="progress flex-grow-1" style="height: 6px; max-width: 80px;">
                                            <div class="progress-bar {{ $pct >= 100 ? 'bg-success' : ($pct > 0 ? 'bg-primary' : 'bg-secondary') }}" style="width: {{ $pct }}%"></div>
                                        </div>
                                        <small class="text-muted">{{ $stat['notas_lancadas'] }}/{{ $stat['total_alunos'] }}</small>
                                    </div>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <a href="{{ route('notas.lancar', ['turma_id' => $stat['turma']->id, 'disciplina_id' => $stat['disciplina']->id]) }}" class="btn btn-outline-primary" title="Lançar Notas"><i class="bi bi-pencil-square"></i></a>
                                        <a href="{{ route('pautas.index', ['turma_id' => $stat['turma']->id, 'disciplina_id' => $stat['disciplina']->id]) }}" class="btn btn-outline-secondary" title="Ver Pauta"><i class="bi bi-table"></i></a>
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <!-- Notas Recentes -->
    <div class="content-card">
        <div class="content-card-header">
            <h5 class="content-card-title"><i class="bi bi-clock-history me-2"></i>Notas Lançadas Recentemente</h5>
        </div>
        <div class="content-card-body">
            @if($notasRecentes->isEmpty())
                <div class="text-center py-4">
                    <i class="bi bi-inbox text-muted fs-1"></i>
                    <p class="text-muted mt-2 mb-0">Nenhuma nota lançada recentemente.</p>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Aluno</th>
                                <th>Turma</th>
                                <th>Disciplina</th>
                                <th>Tipo</th>
                                <th class="text-center">Nota</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($notasRecentes as $nota)
                            <tr>
                                <td class="text-muted small">{{ $nota->created_at->format('d/m H:i') }}</td>
                                <td>{{ $nota->aluno->nome }}</td>
                                <td>{{ $nota->turma->nome }}</td>
                                <td>{{ $nota->disciplina->nome }}</td>
                                <td><span class="badge bg-light text-dark">{{ \App\Models\Nota::getTipos()[$nota->tipo] ?? $nota->tipo }}</span></td>
                                <td class="text-center">
                                    <span class="badge {{ $nota->isPositiva() ? 'bg-success' : 'bg-danger' }} px-2">
                                        {{ number_format($nota->valor, 0) }}
                                    </span>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
        Chart.defaults.font.family = "'Inter', sans-serif";
        Chart.defaults.color = '#94a3b8';
        Chart.defaults.borderColor = 'rgba(0,0,0,0.05)';

        @if(count($chartDistribuicaoNotas) > 0)
        {
            const dd = @json($chartDistribuicaoNotas);
            new Chart(document.getElementById('chartDistribuicaoNotas'), {
                type: 'bar',
                data: {
                    labels: dd.map(d => d.label),
                    datasets: [
                        { label: 'Positivas (>=10)', data: dd.map(d => d.positivas), backgroundColor: '#10b981', borderRadius: 4, borderWidth: 0 },
                        { label: 'Negativas (<10)', data: dd.map(d => d.negativas), backgroundColor: '#f43f5e', borderRadius: 4, borderWidth: 0 }
                    ]
                },
                options: { responsive: true, maintainAspectRatio: false, indexAxis: 'y', scales: { x: { stacked: true, beginAtZero: true, grid: { color: 'rgba(0,0,0,0.04)' } }, y: { stacked: true, grid: { display: false } } }, plugins: { legend: { position: 'top' } } }
            });
        }
        @endif

        @if($chartProgresso['data'][0] > 0 || $chartProgresso['data'][1] > 0)
        new Chart(document.getElementById('chartProgresso'), {
            type: 'doughnut',
            data: {
                labels: @json($chartProgresso['labels']),
                datasets: [{ data: @json($chartProgresso['data']), backgroundColor: ['#10b981', 'rgba(148, 163, 184, 0.3)'], borderWidth: 0 }]
            },
            options: { responsive: true, maintainAspectRatio: false, cutout: '65%', plugins: { legend: { position: 'bottom' } } }
        });
        @endif

        @if(count($chartMediasPorTurma) > 0)
        {
            const md = @json($chartMediasPorTurma);
            new Chart(document.getElementById('chartMediasPorTurma'), {
                type: 'bar',
                data: {
                    labels: md.map(d => d.label),
                    datasets: [{
                        label: 'Média',
                        data: md.map(d => d.media),
                        backgroundColor: md.map(d => d.media >= 14 ? '#10b981' : d.media >= 10 ? '#6366f1' : '#f43f5e'),
                        borderRadius: 6,
                        borderWidth: 0
                    }]
                },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, max: 20, ticks: { stepSize: 2 }, grid: { color: 'rgba(0,0,0,0.04)' } }, x: { grid: { display: false } } } }
            });
        }
        @endif
    </script>
    @endpush
</x-app-layout>
