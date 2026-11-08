<x-app-layout>
    <x-slot name="header">Dashboard do Coordenador de Curso</x-slot>

    @php
        // Badge que indica a origem dos dados (Notas Finais ou Médias Trimestrais)
        $fonteBadge = function (?string $fonte): string {
            if ($fonte === 'final') {
                return '<span class="badge bg-success-subtle text-success border border-success-subtle ms-2" data-bs-toggle="tooltip" title="Cálculo baseado em Notas Finais (CFD/CA/CF) — ano lectivo concluído">'
                     . '<i class="bi bi-check2-circle me-1"></i>Notas Finais</span>';
            }
            if ($fonte === 'mt') {
                return '<span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle ms-2" data-bs-toggle="tooltip" title="Cálculo baseado em Médias Trimestrais — ano lectivo em curso">'
                     . '<i class="bi bi-hourglass-split me-1"></i>Médias Trimestrais</span>';
            }
            return '';
        };
    @endphp

    <!-- Welcome Banner -->
    <div class="welcome-banner">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h2 class="welcome-title">
                    <i class="bi bi-diagram-3 me-2"></i>Olá, {{ auth()->user()->name }}!
                </h2>
                <p class="welcome-subtitle mb-0">
                    Ano Lectivo {{ $anoLectivo }} &mdash; Coordenação de Curso
                </p>
            </div>
            <div class="col-md-4 text-md-end mt-3 mt-md-0">
                <span class="badge bg-white text-primary px-3 py-2 fs-6">
                    <i class="bi bi-mortarboard me-1"></i>{{ count($estatisticasCursos) }} {{ count($estatisticasCursos) == 1 ? 'Curso' : 'Cursos' }}
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

    <x-tip-card icon="bi-lightbulb" variant="success" title="Dica para coordenadores" id="tip-dash-coordenador">
        Como coordenador de curso, pode acompanhar o desempenho dos alunos através das Mini-Pautas e Pautas. Utilize a secção "Estatísticas" para obter uma visão geral do rendimento académico do seu curso.
    </x-tip-card>

    <!-- Resumo dos Cursos -->
    <div class="row g-4 mb-4">
        @foreach($estatisticasCursos as $stat)
        <div class="col-md-6">
            <div class="content-card h-100">
                <div class="content-card-header bg-success text-white rounded-top-4">
                    <h5 class="mb-0 text-white">{{ $stat['curso']->nome }}</h5>
                    <small class="opacity-75">{{ $stat['curso']->codigo }}</small>
                </div>
                <div class="content-card-body">
                    <div class="row text-center g-2">
                        <div class="col-3">
                            <div class="p-2 rounded-3" style="background: var(--bg-light);">
                                <div class="fw-bold fs-5">{{ $stat['total_turmas'] }}</div>
                                <small class="text-muted">Turmas</small>
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="p-2 rounded-3" style="background: var(--bg-light);">
                                <div class="fw-bold fs-5">{{ $stat['total_alunos'] }}</div>
                                <small class="text-muted">Alunos</small>
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="p-2 rounded-3" style="background: var(--bg-light);">
                                <div class="fw-bold fs-5">{{ $stat['total_disciplinas'] }}</div>
                                <small class="text-muted">Disciplinas</small>
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="p-2 rounded-3 position-relative" style="background: var(--bg-light);">
                                @if($stat['taxa_aprovacao'] !== null)
                                    <div class="fw-bold fs-5 {{ $stat['taxa_aprovacao'] >= 50 ? 'text-success' : 'text-danger' }}">{{ $stat['taxa_aprovacao'] }}%</div>
                                @else
                                    <div class="fw-bold fs-5 text-muted">-</div>
                                @endif
                                <small class="text-muted d-block">Aprovação</small>
                                @if(!empty($stat['fonte_referencia']))
                                    @php
                                        $isMt = $stat['fonte_referencia'] === 'mt';
                                    @endphp
                                    <small class="d-block mt-1">
                                        <span class="badge {{ $isMt ? 'bg-warning-subtle text-warning-emphasis' : 'bg-success-subtle text-success' }} small"
                                              data-bs-toggle="tooltip"
                                              title="{{ $isMt ? 'Calculado a partir de Médias Trimestrais (ano em curso)' : 'Calculado a partir de Notas Finais (ano concluído)' }}">
                                            <i class="bi {{ $isMt ? 'bi-hourglass-split' : 'bi-check2-circle' }}"></i>
                                            {{ $isMt ? 'MT' : 'Final' }}
                                        </span>
                                    </small>
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="btn-group btn-group-sm w-100 mt-3">
                        <a href="{{ route('cursos.show', $stat['curso']) }}" class="btn btn-outline-success"><i class="bi bi-eye me-1"></i>Ver Curso</a>
                        <a href="{{ route('turmas.index', ['curso_id' => $stat['curso']->id]) }}" class="btn btn-outline-primary"><i class="bi bi-collection me-1"></i>Turmas</a>
                        <a href="{{ route('disciplinas.index', ['curso_id' => $stat['curso']->id]) }}" class="btn btn-outline-secondary"><i class="bi bi-book me-1"></i>Disciplinas</a>
                    </div>
                </div>
            </div>
        </div>
        @endforeach
    </div>

    <!-- Gráficos -->
    <h5 class="mb-3 fw-semibold d-flex align-items-center flex-wrap">
        <i class="bi bi-bar-chart-line me-2" style="color: var(--primary-color);"></i>Análise de Desempenho
        {!! $fonteBadge($fonteGlobal) !!}
    </h5>
    @if($fonteGlobal === 'mt')
        <div class="alert alert-warning d-flex align-items-start py-2 px-3 mb-3" role="alert">
            <i class="bi bi-info-circle me-2 mt-1"></i>
            <small class="mb-0">
                <strong>Ano lectivo em curso:</strong> os indicadores de aprovação e médias por disciplina estão a ser calculados a partir das <strong>Médias Trimestrais (MT)</strong> já lançadas — ainda não existem Notas Finais. Quando as Classificações Finais (CFD/CA/CF) forem geradas no fim do ano, os indicadores passarão a usá-las automaticamente.
            </small>
        </div>
    @endif
    <div class="row g-4 mb-4">
        <div class="col-md-6">
            <div class="content-card h-100">
                <div class="content-card-header"><h6 class="content-card-title mb-0"><i class="bi bi-bar-chart me-2"></i>Comparativo entre Turmas</h6></div>
                <div class="content-card-body">
                    @if(count($chartComparativoTurmas) > 0)
                        <div class="chart-container"><canvas id="chartComparativoTurmas" height="250"></canvas></div>
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
                <div class="content-card-header">
                    <h6 class="content-card-title mb-0">
                        <i class="bi bi-percent me-2"></i>Taxa de Aprovação por Turma
                        {!! $fonteBadge($fonteGlobal) !!}
                    </h6>
                </div>
                <div class="content-card-body">
                    @if(count($chartTaxaAprovacao) > 0)
                        <div class="chart-container"><canvas id="chartTaxaAprovacao" height="250"></canvas></div>
                    @else
                        <div class="text-center py-4">
                            <i class="bi bi-inbox text-muted fs-2"></i>
                            <p class="text-muted mt-2 mb-0">Sem dados de aprovação disponíveis.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="content-card h-100">
                <div class="content-card-header"><h6 class="content-card-title mb-0"><i class="bi bi-pie-chart me-2"></i>Distribuição de Notas</h6></div>
                <div class="content-card-body d-flex align-items-center justify-content-center">
                    @if(array_sum($chartDistribuicaoNotas['data']) > 0)
                        <div class="chart-container"><canvas id="chartDistribuicaoNotas" height="250"></canvas></div>
                    @else
                        <div class="text-center py-4">
                            <i class="bi bi-inbox text-muted fs-2"></i>
                            <p class="text-muted mt-2 mb-0">Sem dados de notas disponíveis.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="content-card h-100">
                <div class="content-card-header">
                    <h6 class="content-card-title mb-0">
                        <i class="bi bi-trophy me-2"></i>Top 5 Melhores e Piores Disciplinas
                        {!! $fonteBadge($fonteGlobal) !!}
                    </h6>
                </div>
                <div class="content-card-body">
                    @if(count($chartTopBottomDisciplinas) > 0)
                        <div class="chart-container"><canvas id="chartTopBottomDisciplinas" height="250"></canvas></div>
                    @else
                        <div class="text-center py-4">
                            <i class="bi bi-inbox text-muted fs-2"></i>
                            <p class="text-muted mt-2 mb-0">Sem dados suficientes.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Desempenho por Disciplina -->
    @if(count($mediasPorDisciplina) > 0)
    <div class="content-card mb-4">
        <div class="content-card-header">
            <h5 class="content-card-title"><i class="bi bi-bar-chart me-2"></i>Desempenho por Disciplina</h5>
            <small class="text-muted">Ordenado por média (menor para maior)</small>
        </div>
        <div class="content-card-body">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Disciplina</th>
                            <th>Curso</th>
                            <th class="text-center">Média</th>
                            <th class="text-center">Total Notas</th>
                            <th class="text-center">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach(array_slice($mediasPorDisciplina, 0, 10) as $item)
                        <tr>
                            <td class="fw-medium">{{ $item['disciplina']->nome }}</td>
                            <td class="text-muted">{{ $item['curso']->nome }}</td>
                            <td class="text-center">
                                <span class="badge {{ $item['media'] >= 10 ? 'bg-success' : 'bg-danger' }} px-2 fs-6">
                                    {{ number_format($item['media'], 1) }}
                                </span>
                            </td>
                            <td class="text-center">{{ $item['total_notas'] }}</td>
                            <td class="text-center">
                                @if($item['media'] >= 14)
                                    <span class="badge bg-success bg-opacity-10 text-success">Excelente</span>
                                @elseif($item['media'] >= 10)
                                    <span class="badge bg-primary bg-opacity-10 text-primary">Bom</span>
                                @elseif($item['media'] >= 8)
                                    <span class="badge bg-warning bg-opacity-10 text-warning">Atenção</span>
                                @else
                                    <span class="badge bg-danger bg-opacity-10 text-danger">Crítico</span>
                                @endif
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
            <a href="{{ route('associacoes.index') }}" class="quick-action">
                <div class="quick-action-icon stat-icon-warning"><i class="bi bi-link-45deg"></i></div>
                <span class="quick-action-label">Associações</span>
            </a>
        </div>
        <div class="col-6 col-md-3">
            <a href="{{ route('disciplinas.create') }}" class="quick-action">
                <div class="quick-action-icon stat-icon-success"><i class="bi bi-plus-lg"></i></div>
                <span class="quick-action-label">Nova Disciplina</span>
            </a>
        </div>
        <div class="col-6 col-md-3">
            <a href="{{ route('directores-turma.index') }}" class="quick-action">
                <div class="quick-action-icon stat-icon-info"><i class="bi bi-person-badge"></i></div>
                <span class="quick-action-label">Directores</span>
            </a>
        </div>
    </div>

    @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
        Chart.defaults.font.family = "'Inter', sans-serif";
        Chart.defaults.color = '#94a3b8';
        Chart.defaults.borderColor = 'rgba(0,0,0,0.05)';

        @if(count($chartComparativoTurmas) > 0)
        {
            const cd = @json($chartComparativoTurmas);
            new Chart(document.getElementById('chartComparativoTurmas'), {
                type: 'bar',
                data: {
                    labels: cd.map(d => d.nome),
                    datasets: [{
                        label: 'Média Geral',
                        data: cd.map(d => d.media),
                        backgroundColor: cd.map(d => d.media >= 14 ? '#10b981' : d.media >= 10 ? '#6366f1' : '#f43f5e'),
                        borderRadius: 6, borderWidth: 0
                    }]
                },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, max: 20, ticks: { stepSize: 2 }, grid: { color: 'rgba(0,0,0,0.04)' } }, x: { grid: { display: false } } } }
            });
        }
        @endif

        @if(count($chartTaxaAprovacao) > 0)
        {
            const td = @json($chartTaxaAprovacao);
            new Chart(document.getElementById('chartTaxaAprovacao'), {
                type: 'bar',
                data: {
                    labels: td.map(d => d.nome),
                    datasets: [{
                        label: 'Taxa de Aprovação (%)',
                        data: td.map(d => d.taxa),
                        backgroundColor: td.map(d => d.taxa >= 70 ? '#10b981' : d.taxa >= 50 ? '#f59e0b' : '#f43f5e'),
                        borderRadius: 4, borderWidth: 0
                    }]
                },
                options: { responsive: true, maintainAspectRatio: false, indexAxis: 'y', plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true, max: 100, ticks: { callback: v => v + '%' }, grid: { color: 'rgba(0,0,0,0.04)' } }, y: { grid: { display: false } } } }
            });
        }
        @endif

        @if(array_sum($chartDistribuicaoNotas['data']) > 0)
        new Chart(document.getElementById('chartDistribuicaoNotas'), {
            type: 'doughnut',
            data: {
                labels: @json($chartDistribuicaoNotas['labels']),
                datasets: [{ data: @json($chartDistribuicaoNotas['data']), backgroundColor: ['#10b981', '#6366f1', '#22d3ee', '#f59e0b', '#f43f5e'], borderWidth: 0 }]
            },
            options: { responsive: true, maintainAspectRatio: false, cutout: '50%', plugins: { legend: { position: 'bottom', labels: { padding: 10, font: { size: 11 } } } } }
        });
        @endif

        @if(count($chartTopBottomDisciplinas) > 0)
        {
            const dd = @json($chartTopBottomDisciplinas);
            new Chart(document.getElementById('chartTopBottomDisciplinas'), {
                type: 'bar',
                data: {
                    labels: dd.map(d => d.nome),
                    datasets: [{
                        label: 'Média',
                        data: dd.map(d => d.media),
                        backgroundColor: dd.map(d => d.tipo === 'bottom' ? '#f43f5e' : '#10b981'),
                        borderRadius: 4, borderWidth: 0
                    }]
                },
                options: { responsive: true, maintainAspectRatio: false, indexAxis: 'y', plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true, max: 20, ticks: { stepSize: 2 }, grid: { color: 'rgba(0,0,0,0.04)' } }, y: { grid: { display: false } } } }
            });
        }
        @endif
    </script>
    @endpush
</x-app-layout>
