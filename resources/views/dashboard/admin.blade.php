<x-app-layout>
    <x-slot name="header">Dashboard Administrativo</x-slot>

    @push('styles')
        <link href="{{ asset('css/dashboard-admin.css') }}" rel="stylesheet">
    @endpush

    <div class="dash-admin">
        <!-- Welcome Banner -->
        <div class="welcome-banner">
            <div class="row align-items-center gy-2">
                <div class="col-md-8">
                    <h2 class="welcome-title mb-0">
                        <i class="bi bi-shield-check me-2"></i>Olá, {{ auth()->user()->name }}!
                    </h2>
                    <p class="welcome-subtitle mb-0">
                        Painel Administrativo &mdash; Ano Lectivo {{ $anoLectivo }}
                    </p>
                </div>
                <div class="col-md-4 text-md-end">
                    <span class="badge bg-white text-primary px-3 py-2">
                        <i class="bi bi-calendar3 me-1"></i>{{ now()->format('d/m/Y') }}
                    </span>
                </div>
            </div>
        </div>

        <x-regras-avaliacao-destaque />

        @include('dashboard.partials.saude-escola')

        <!-- Stats Cards -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-md-4 col-xl-2">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-value">{{ $totalCursos }}</div>
                            <div class="stat-label">Cursos</div>
                        </div>
                        <div class="stat-icon stat-icon-primary"><i class="bi bi-mortarboard"></i></div>
                    </div>
                    <a href="{{ route('cursos.index') }}" class="d-block mt-2 small text-decoration-none">Ver todos <i class="bi bi-arrow-right"></i></a>
                </div>
            </div>
            <div class="col-6 col-md-4 col-xl-2">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-value">{{ $totalTurmas }}</div>
                            <div class="stat-label">Turmas</div>
                        </div>
                        <div class="stat-icon stat-icon-success"><i class="bi bi-people"></i></div>
                    </div>
                    <a href="{{ route('turmas.index') }}" class="d-block mt-2 small text-decoration-none">Ver todas <i class="bi bi-arrow-right"></i></a>
                </div>
            </div>
            <div class="col-6 col-md-4 col-xl-2">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-value">{{ number_format($totalAlunos) }}</div>
                            <div class="stat-label">Alunos</div>
                        </div>
                        <div class="stat-icon stat-icon-info"><i class="bi bi-person-badge"></i></div>
                    </div>
                    <a href="{{ route('alunos.index') }}" class="d-block mt-2 small text-decoration-none">Ver todos <i class="bi bi-arrow-right"></i></a>
                </div>
            </div>
            <div class="col-6 col-md-4 col-xl-2">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-value">{{ $totalProfessores }}</div>
                            <div class="stat-label">Professores</div>
                        </div>
                        <div class="stat-icon stat-icon-warning"><i class="bi bi-person-workspace"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-xl-2">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-value">{{ $totalDisciplinas }}</div>
                            <div class="stat-label">Disciplinas</div>
                        </div>
                        <div class="stat-icon" style="background: linear-gradient(135deg, rgba(111, 66, 193, 0.1), rgba(111, 66, 193, 0.2)); color: #6f42c1;"><i class="bi bi-book"></i></div>
                    </div>
                    <a href="{{ route('disciplinas.index') }}" class="d-block mt-2 small text-decoration-none">Ver todas <i class="bi bi-arrow-right"></i></a>
                </div>
            </div>
            <div class="col-6 col-md-4 col-xl-2">
                @if($contasPendentes > 0)
                <div class="stat-card border-start border-warning border-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-value text-warning">{{ $contasPendentes }}</div>
                            <div class="stat-label">Pendentes</div>
                        </div>
                        <div class="stat-icon stat-icon-danger"><i class="bi bi-exclamation-triangle"></i></div>
                    </div>
                    <a href="{{ route('users.pendentes') }}" class="d-block mt-2 small text-decoration-none text-warning fw-semibold">Aprovar <i class="bi bi-arrow-right"></i></a>
                </div>
                @else
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-value text-success"><i class="bi bi-check-circle"></i></div>
                            <div class="stat-label">Sem Pendentes</div>
                        </div>
                    </div>
                </div>
                @endif
            </div>
        </div>

        <x-tip-card icon="bi-lightbulb" variant="primary" title="Dica" id="tip-dash-admin" compact>
            Use o menu lateral para navegar. Em Análise encontra estatísticas e relatórios detalhados.
        </x-tip-card>

        <div class="row g-4 mb-4">
            <!-- Estatisticas por Curso -->
            <div class="col-lg-6">
                <div class="content-card h-100">
                    <div class="content-card-header">
                        <h5 class="content-card-title"><i class="bi bi-mortarboard me-2"></i>Estatísticas por Curso</h5>
                    </div>
                    <div class="content-card-body">
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead>
                                    <tr>
                                        <th>Curso</th>
                                        <th class="text-center">Turmas</th>
                                        <th class="text-center">Alunos</th>
                                        <th class="text-center">Disc.</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($estatisticasCursos as $stat)
                                    <tr>
                                        <td>
                                            <a href="{{ route('cursos.show', $stat['curso']) }}" class="text-decoration-none fw-medium">
                                                {{ Str::limit($stat['curso']->nome, 25) }}
                                            </a>
                                        </td>
                                        <td class="text-center">{{ $stat['total_turmas'] }}</td>
                                        <td class="text-center">{{ $stat['total_alunos'] }}</td>
                                        <td class="text-center">{{ $stat['total_disciplinas'] }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Atividade Recente -->
            <div class="col-lg-6">
                <div class="content-card h-100">
                    <div class="content-card-header d-flex justify-content-between align-items-center">
                        <h5 class="content-card-title"><i class="bi bi-clock-history me-2"></i>Actividade Recente</h5>
                        <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-1">{{ $notasHoje }} notas hoje</span>
                    </div>
                    <div class="content-card-body" style="max-height: 300px; overflow-y: auto;">
                        @if($actividadeRecente->isEmpty())
                            <p class="text-muted mb-0">Nenhuma actividade recente.</p>
                        @else
                            @foreach($actividadeRecente as $activity)
                            <div class="d-flex align-items-start gap-3 py-2 {{ !$loop->last ? 'border-bottom' : '' }}">
                                <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 36px; height: 36px; background: var(--bg-light);">
                                    <i class="bi bi-activity text-primary small"></i>
                                </div>
                                <div class="flex-grow-1 min-width-0">
                                    <div class="small text-truncate">{{ $activity->description }}</div>
                                    <div class="d-flex justify-content-between mt-1">
                                        <small class="text-muted">{{ $activity->causer?->name ?? 'Sistema' }}</small>
                                        <small class="text-muted">{{ $activity->created_at->format('d/m H:i') }}</small>
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Gráficos -->
        <h5 class="section-title">
            <i class="bi bi-bar-chart-line me-2" style="color: var(--primary-color);"></i>Análise Visual
        </h5>
        <div class="row g-4 mb-4">
            <div class="col-md-6">
                <div class="content-card h-100">
                    <div class="content-card-header"><h6 class="content-card-title mb-0"><i class="bi bi-mortarboard me-2"></i>Alunos por Curso</h6></div>
                    <div class="content-card-body"><div class="chart-container"><canvas id="chartAlunosCurso" height="250"></canvas></div></div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="content-card h-100">
                    <div class="content-card-header"><h6 class="content-card-title mb-0"><i class="bi bi-people me-2"></i>Top 10 Turmas</h6></div>
                    <div class="content-card-body"><div class="chart-container"><canvas id="chartTurmas" height="250"></canvas></div></div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="content-card h-100">
                    <div class="content-card-header"><h6 class="content-card-title mb-0"><i class="bi bi-graph-up me-2"></i>Actividade de Lançamento (7 dias)</h6></div>
                    <div class="content-card-body"><div class="chart-container"><canvas id="chartNotasDiarias" height="250"></canvas></div></div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="content-card h-100">
                    <div class="content-card-header"><h6 class="content-card-title mb-0"><i class="bi bi-gender-ambiguous me-2"></i>Distribuição por Género</h6></div>
                    <div class="content-card-body d-flex align-items-center justify-content-center"><div class="chart-container"><canvas id="chartGenero" height="250"></canvas></div></div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <h5 class="section-title">
            <i class="bi bi-lightning-charge me-2" style="color: var(--warning-color);"></i>Acções Rápidas
        </h5>
        <div class="row g-3 mb-4">
            <div class="col-6 col-md-4 col-lg-2">
                <a href="{{ route('cursos.create') }}" class="quick-action">
                    <div class="quick-action-icon stat-icon-primary"><i class="bi bi-plus-lg"></i></div>
                    <span class="quick-action-label">Novo Curso</span>
                </a>
            </div>
            <div class="col-6 col-md-4 col-lg-2">
                <a href="{{ route('turmas.create') }}" class="quick-action">
                    <div class="quick-action-icon stat-icon-success"><i class="bi bi-plus-lg"></i></div>
                    <span class="quick-action-label">Nova Turma</span>
                </a>
            </div>
            <div class="col-6 col-md-4 col-lg-2">
                <a href="{{ route('alunos.create') }}" class="quick-action">
                    <div class="quick-action-icon stat-icon-info"><i class="bi bi-plus-lg"></i></div>
                    <span class="quick-action-label">Novo Aluno</span>
                </a>
            </div>
            <div class="col-6 col-md-4 col-lg-2">
                <a href="{{ route('associacoes.create') }}" class="quick-action">
                    <div class="quick-action-icon stat-icon-warning"><i class="bi bi-link-45deg"></i></div>
                    <span class="quick-action-label">Associar Prof.</span>
                </a>
            </div>
            <div class="col-6 col-md-4 col-lg-2">
                <a href="{{ route('pautas.index') }}" class="quick-action">
                    <div class="quick-action-icon stat-icon-danger"><i class="bi bi-table"></i></div>
                    <span class="quick-action-label">Ver Pautas</span>
                </a>
            </div>
            <div class="col-6 col-md-4 col-lg-2">
                <a href="{{ route('ocupacao.index') }}" class="quick-action">
                    <div class="quick-action-icon" style="background: linear-gradient(135deg, rgba(111, 66, 193, 0.1), rgba(111, 66, 193, 0.2)); color: #6f42c1;"><i class="bi bi-building"></i></div>
                    <span class="quick-action-label">Ocupação</span>
                </a>
            </div>
        </div>
    </div>

    @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
        Chart.defaults.font.family = "'Inter', sans-serif";
        Chart.defaults.color = '#94a3b8';
        Chart.defaults.borderColor = 'rgba(0,0,0,0.05)';

        new Chart(document.getElementById('chartAlunosCurso'), {
            type: 'bar',
            data: {
                labels: @json($chartAlunosCurso['labels']),
                datasets: [{
                    label: 'Alunos',
                    data: @json($chartAlunosCurso['data']),
                    backgroundColor: 'rgba(79, 70, 229, 0.8)',
                    borderRadius: 6,
                    borderWidth: 0,
                    borderSkipped: false,
                }]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 }, grid: { color: 'rgba(0,0,0,0.04)' } }, x: { grid: { display: false } } } }
        });

        new Chart(document.getElementById('chartTurmas'), {
            type: 'doughnut',
            data: {
                labels: @json($chartTurmas['labels']),
                datasets: [{ data: @json($chartTurmas['data']), backgroundColor: ['#6366f1','#22d3ee','#f59e0b','#10b981','#f43f5e','#8b5cf6','#06b6d4','#84cc16','#ec4899','#a78bfa'], borderWidth: 0 }]
            },
            options: { responsive: true, maintainAspectRatio: false, cutout: '55%', plugins: { legend: { position: 'right', labels: { boxWidth: 12, padding: 8, font: { size: 11 } } } } }
        });

        new Chart(document.getElementById('chartNotasDiarias'), {
            type: 'line',
            data: {
                labels: @json($chartNotasDiarias['labels']),
                datasets: [{
                    label: 'Notas',
                    data: @json($chartNotasDiarias['data']),
                    backgroundColor: 'rgba(79, 70, 229, 0.1)',
                    borderColor: '#6366f1',
                    borderWidth: 2.5,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 5,
                    pointHoverRadius: 7,
                    pointBackgroundColor: '#6366f1',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2
                }]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 }, grid: { color: 'rgba(0,0,0,0.04)' } }, x: { grid: { display: false } } } }
        });

        new Chart(document.getElementById('chartGenero'), {
            type: 'doughnut',
            data: {
                labels: @json($chartGenero['labels']),
                datasets: [{ data: @json($chartGenero['data']), backgroundColor: ['#6366f1', '#ec4899'], borderWidth: 0, hoverOffset: 8 }]
            },
            options: { responsive: true, maintainAspectRatio: false, cutout: '60%', plugins: { legend: { position: 'bottom', labels: { padding: 15, font: { size: 13 } } } } }
        });
    </script>
    @endpush
</x-app-layout>
