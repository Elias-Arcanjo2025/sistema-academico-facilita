<x-app-layout>
    <x-slot name="header">Dashboard do Director de Turma</x-slot>

    <!-- Welcome Banner -->
    <div class="welcome-banner">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h2 class="welcome-title">
                    <i class="bi bi-person-badge me-2"></i>Olá, {{ auth()->user()->name }}!
                </h2>
                <p class="welcome-subtitle mb-0">
                    Ano Lectivo {{ $anoLectivo }} &mdash; Direcção de Turma
                </p>
            </div>
            <div class="col-md-4 text-md-end mt-3 mt-md-0">
                <span class="badge bg-white text-primary px-3 py-2 fs-6">
                    <i class="bi bi-people me-1"></i>{{ count($estatisticasTurmas) }} {{ count($estatisticasTurmas) == 1 ? 'Turma' : 'Turmas' }}
                </span>
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

    @isset($briefingFamilias)
        @include('dashboard.partials.briefing-familias', ['briefing' => $briefingFamilias])
    @endisset

    <x-tip-card icon="bi-lightbulb" variant="warning" title="Dica para directores de turma" id="tip-dash-director">
        Enquanto director de turma, pode aceder aos Conselhos de Classe para registar observações e deliberações. Consulte os boletins de notas dos seus alunos na secção "Relatórios → Boletim de Notas".
    </x-tip-card>

    <!-- Resumo das Turmas -->
    <div class="row g-4 mb-4">
        @foreach($estatisticasTurmas as $stat)
        <div class="col-md-6 col-lg-4">
            <div class="content-card h-100">
                <div class="content-card-header bg-primary text-white rounded-top-4">
                    <h5 class="mb-0 text-white">{{ $stat['turma']->nome }}</h5>
                    <small class="opacity-75">{{ $stat['turma']->curso->nome }}</small>
                </div>
                <div class="content-card-body">
                    <div class="row text-center g-2">
                        <div class="col-4">
                            <div class="p-2 rounded-3" style="background: var(--bg-light);">
                                <div class="fw-bold fs-5">{{ $stat['total_alunos'] }}</div>
                                <small class="text-muted">Alunos</small>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="p-2 rounded-3" style="background: var(--bg-light);">
                                <div class="fw-bold fs-5">{{ $stat['total_disciplinas'] }}</div>
                                <small class="text-muted">Disciplinas</small>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="p-2 rounded-3" style="background: var(--bg-light);">
                                <div class="fw-bold fs-5">{{ $stat['alunos_com_final'] }}</div>
                                <small class="text-muted">C/ Final</small>
                            </div>
                        </div>
                    </div>
                    <div class="btn-group btn-group-sm w-100 mt-3">
                        <a href="{{ route('turmas.show', $stat['turma']) }}" class="btn btn-outline-primary"><i class="bi bi-eye me-1"></i>Ver</a>
                        <a href="{{ route('pautas.final-turma', ['turma_id' => $stat['turma']->id]) }}" class="btn btn-outline-success"><i class="bi bi-table me-1"></i>Pauta</a>
                        <a href="{{ route('alunos.index', ['turma_id' => $stat['turma']->id]) }}" class="btn btn-outline-secondary"><i class="bi bi-people me-1"></i>Alunos</a>
                    </div>
                </div>
            </div>
        </div>
        @endforeach
    </div>

    <!-- Gráficos -->
    <h5 class="mb-3 fw-semibold">
        <i class="bi bi-bar-chart-line me-2" style="color: var(--primary-color);"></i>Análise de Desempenho
    </h5>
    <div class="row g-4 mb-4">
        <div class="col-md-6">
            <div class="content-card h-100">
                <div class="content-card-header"><h6 class="content-card-title mb-0"><i class="bi bi-graph-down me-2"></i>Desempenho por Disciplina</h6></div>
                <div class="content-card-body">
                    @if(count($chartDesempenhoDisciplinas) > 0)
                        <div class="chart-container"><canvas id="chartDesempenhoDisciplinas" height="300"></canvas></div>
                    @else
                        <div class="text-center py-4">
                            <i class="bi bi-inbox text-muted fs-2"></i>
                            <p class="text-muted mt-2 mb-0">Sem dados suficientes.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="content-card h-100">
                <div class="content-card-header"><h6 class="content-card-title mb-0"><i class="bi bi-pie-chart me-2"></i>Aprovados vs Reprovados</h6></div>
                <div class="content-card-body d-flex align-items-center justify-content-center">
                    @if($chartAprovacao['data'][0] > 0 || $chartAprovacao['data'][1] > 0)
                        <div class="chart-container"><canvas id="chartAprovacao" height="300"></canvas></div>
                    @else
                        <div class="text-center py-4">
                            <i class="bi bi-inbox text-muted fs-2"></i>
                            <p class="text-muted mt-2 mb-0">Sem dados de aprovação disponíveis.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-12">
            <div class="content-card">
                <div class="content-card-header"><h6 class="content-card-title mb-0"><i class="bi bi-graph-up-arrow me-2"></i>Tendência de Desempenho por Trimestre</h6></div>
                <div class="content-card-body">
                    @if(count($chartTendencia['datasets']) > 0)
                        <div class="chart-container"><canvas id="chartTendencia" height="200"></canvas></div>
                    @else
                        <div class="text-center py-4">
                            <i class="bi bi-inbox text-muted fs-2"></i>
                            <p class="text-muted mt-2 mb-0">Sem dados de tendência disponíveis.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Alunos em Situação Crítica -->
    @if(count($alunosCriticos) > 0)
    <div class="content-card mb-4 border-start border-danger border-3">
        <div class="content-card-header bg-danger bg-opacity-10">
            <h5 class="content-card-title text-danger"><i class="bi bi-exclamation-triangle me-2"></i>Alunos em Situação Crítica</h5>
            <small class="text-muted">Alunos com 3 ou mais disciplinas com nota negativa</small>
        </div>
        <div class="content-card-body">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Aluno</th>
                            <th>N.o Processo</th>
                            <th>Turma</th>
                            <th class="text-center">Negativas</th>
                            <th class="text-end">Acções</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($alunosCriticos as $critico)
                        <tr>
                            <td>
                                <a href="{{ route('alunos.show', $critico['aluno']) }}" class="fw-medium text-decoration-none">{{ $critico['aluno']->nome }}</a>
                            </td>
                            <td class="text-muted">{{ $critico['aluno']->numero_processo }}</td>
                            <td>{{ $critico['turma']->nome }}</td>
                            <td class="text-center"><span class="badge bg-danger px-2">{{ $critico['negativas'] }}</span></td>
                            <td class="text-end">
                                <a href="{{ route('alunos.show', $critico['aluno']) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye me-1"></i>Detalhes</a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif

    <!-- Quick Actions -->
    <h5 class="mb-3 fw-semibold">
        <i class="bi bi-lightning-charge me-2" style="color: var(--warning-color);"></i>Acções Rápidas
    </h5>
    <div class="row g-3">
        <div class="col-6 col-md-3">
            <a href="{{ route('pautas.index') }}" class="quick-action">
                <div class="quick-action-icon stat-icon-primary"><i class="bi bi-table"></i></div>
                <span class="quick-action-label">Ver Pautas</span>
            </a>
        </div>
        <div class="col-6 col-md-3">
            <a href="{{ route('notas.index') }}" class="quick-action">
                <div class="quick-action-icon stat-icon-warning"><i class="bi bi-journal-check"></i></div>
                <span class="quick-action-label">Ver Notas</span>
            </a>
        </div>
        <div class="col-6 col-md-3">
            <a href="{{ route('alunos.index') }}" class="quick-action">
                <div class="quick-action-icon stat-icon-success"><i class="bi bi-people"></i></div>
                <span class="quick-action-label">Gerir Alunos</span>
            </a>
        </div>
        <div class="col-6 col-md-3">
            <a href="{{ route('associacoes.index') }}" class="quick-action">
                <div class="quick-action-icon stat-icon-info"><i class="bi bi-link-45deg"></i></div>
                <span class="quick-action-label">Associações</span>
            </a>
        </div>
    </div>

    @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
        Chart.defaults.font.family = "'Inter', sans-serif";
        Chart.defaults.color = '#94a3b8';
        Chart.defaults.borderColor = 'rgba(0,0,0,0.05)';

        @if(count($chartDesempenhoDisciplinas) > 0)
        {
            const dd = @json($chartDesempenhoDisciplinas);
            new Chart(document.getElementById('chartDesempenhoDisciplinas'), {
                type: 'bar',
                data: {
                    labels: dd.map(d => d.nome),
                    datasets: [{
                        label: 'Média',
                        data: dd.map(d => d.media),
                        backgroundColor: dd.map(d => d.media >= 14 ? '#10b981' : d.media >= 10 ? '#6366f1' : '#f43f5e'),
                        borderRadius: 4,
                        borderWidth: 0
                    }]
                },
                options: { responsive: true, maintainAspectRatio: false, indexAxis: 'y', plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true, max: 20, ticks: { stepSize: 2 }, grid: { color: 'rgba(0,0,0,0.04)' } }, y: { grid: { display: false } } } }
            });
        }
        @endif

        @if($chartAprovacao['data'][0] > 0 || $chartAprovacao['data'][1] > 0)
        new Chart(document.getElementById('chartAprovacao'), {
            type: 'doughnut',
            data: {
                labels: @json($chartAprovacao['labels']),
                datasets: [{ data: @json($chartAprovacao['data']), backgroundColor: ['#10b981', '#f43f5e'], borderWidth: 0 }]
            },
            options: { responsive: true, maintainAspectRatio: false, cutout: '55%', plugins: { legend: { position: 'bottom' } } }
        });
        @endif

        @if(count($chartTendencia['datasets']) > 0)
        {
            const cores = ['#6366f1', '#10b981', '#f43f5e', '#f59e0b', '#8b5cf6'];
            const ds = @json($chartTendencia['datasets']).map((d, i) => ({
                label: d.label, data: d.data,
                borderColor: cores[i % cores.length],
                backgroundColor: cores[i % cores.length] + '1a',
                borderWidth: 2.5, tension: 0.3, fill: true,
                pointRadius: 5, pointHoverRadius: 7,
                pointBackgroundColor: cores[i % cores.length],
                pointBorderColor: '#fff', pointBorderWidth: 2
            }));
            new Chart(document.getElementById('chartTendencia'), {
                type: 'line',
                data: { labels: @json($chartTendencia['labels']), datasets: ds },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'top' }, tooltip: { mode: 'index', intersect: false } }, scales: { y: { beginAtZero: true, max: 20, ticks: { stepSize: 2 }, grid: { color: 'rgba(0,0,0,0.04)' } }, x: { grid: { display: false } } } }
            });
        }
        @endif
    </script>
    @endpush
</x-app-layout>
