@extends('layouts.app')

@section('title', 'Estatísticas de Alunos')

@section('content')
<div class="page-content">
    <!-- Page Header -->
    <div class="page-header mb-4 fade-in-scale">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h2><i class="bi bi-graph-up me-2"></i>Estatísticas de Alunos</h2>
                <p class="text-muted mb-0">Ano Lectivo: {{ $anoLectivo }}</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                @can('consolidarSituacoes', App\Models\Aluno::class)
                    <button type="button" class="btn btn-outline-warning" data-bs-toggle="modal" data-bs-target="#consolidarModal">
                        <i class="bi bi-arrow-clockwise me-2"></i>Consolidar Situações
                    </button>
                @endcan
                <a href="{{ route('estatisticas.mapa-aproveitamento', ['trimestre' => $trimestre]) }}" class="btn btn-outline-success">
                    <i class="bi bi-table me-2"></i>Mapa de Aproveitamento
                </a>
                <a href="{{ route('estatisticas.mapa-aproveitamento', ['trimestre' => \App\Services\EstatisticasService::PERIODO_ANUAL]) }}" class="btn btn-outline-success">
                    <i class="bi bi-calendar-check me-2"></i>Mapa Anual
                </a>
                <a href="{{ route('estatisticas.export-pdf', ['ano_lectivo' => $anoLectivo, 'trimestre' => $trimestre]) }}" class="btn btn-primary" target="_blank">
                    <i class="bi bi-file-earmark-pdf me-2"></i>Exportar PDF
                </a>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show slide-in-bottom" role="alert">
            <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <!-- Selector de Trimestre -->
    <div class="content-card fade-in-scale mb-4">
        <div class="content-card-body py-3">
            <form method="GET" action="{{ route('estatisticas.index') }}" class="d-flex align-items-center gap-3 flex-wrap">
                <label class="fw-semibold mb-0"><i class="bi bi-calendar3 me-1"></i>Trimestre:</label>
                @foreach($trimestresOpts as $val => $label)
                    <div class="form-check form-check-inline mb-0">
                        <input class="form-check-input" type="radio" name="trimestre" id="t{{ $val }}"
                               value="{{ $val }}" {{ $trimestre == $val ? 'checked' : '' }}
                               onchange="this.form.submit()">
                        <label class="form-check-label fw-semibold" for="t{{ $val }}">{{ $label }}</label>
                    </div>
                @endforeach
                @php $metricaStats = $statsGerais['metrica'] ?? \App\Services\EstatisticasService::metricaAproveitamentoLabel($trimestre); @endphp
                <small class="text-muted ms-2">
                    <i class="bi bi-info-circle me-1"></i>
                    Aproveitamento calculado com base em
                    <strong>{{ $metricaStats }}</strong>
                    @if($trimestre === 1) (média trimestral)
                    @elseif($trimestre === 2) (média final dos 2 trimestres)
                    @elseif($trimestre === \App\Services\EstatisticasService::PERIODO_ANUAL) (resultado final do ano — CA por disciplina)
                    @else (classificação anual por disciplina)
                    @endif
                </small>
            </form>
        </div>
    </div>

    <!-- Cards de Resumo -->
    <div class="row g-4 mb-4">
        <div class="col-lg col-md-6">
            <div class="stat-card fade-in-scale stagger-item-1 hover-lift">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-label">Total de Alunos</div>
                        <div class="stat-value count-up">{{ $statsGerais['total_mf'] }}</div>
                        <div class="mt-2">
                            <span class="badge badge-info me-1">MF: {{ $statsGerais['total_mf'] }}</span>
                            <span class="badge badge-warning">F: {{ $statsGerais['total_f'] }}</span>
                        </div>
                    </div>
                    <div class="stat-icon stat-icon-info">
                        <i class="bi bi-people-fill"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg col-md-6">
            <div class="stat-card fade-in-scale stagger-item-2 hover-lift">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-label">Avaliados</div>
                        <div class="stat-value count-up">{{ $statsGerais['avaliados_mf'] }}</div>
                        <div class="mt-2">
                            <span class="badge badge-info me-1">MF: {{ $statsGerais['avaliados_mf'] }}</span>
                            <span class="badge badge-warning">F: {{ $statsGerais['avaliados_f'] }}</span>
                        </div>
                    </div>
                    <div class="stat-icon stat-icon-primary">
                        <i class="bi bi-person-check-fill"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg col-md-6">
            <div class="stat-card fade-in-scale stagger-item-3 hover-lift">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-label">C/Aproveitamento</div>
                        <div class="stat-value count-up text-success">{{ $statsGerais['com_mf'] }}</div>
                        <div class="mt-2">
                            <span class="badge badge-success">{{ number_format($statsGerais['com_pct'], 1, ',') }}%</span>
                            <span class="badge badge-warning ms-1">F: {{ $statsGerais['com_f'] }}</span>
                        </div>
                    </div>
                    <div class="stat-icon stat-icon-success">
                        <i class="bi bi-trophy-fill"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg col-md-6">
            <div class="stat-card fade-in-scale stagger-item-4 hover-lift">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-label">S/Aproveitamento</div>
                        <div class="stat-value count-up text-danger">{{ $statsGerais['sem_mf'] }}</div>
                        <div class="mt-2">
                            <span class="badge badge-danger">{{ number_format($statsGerais['sem_pct'], 1, ',') }}%</span>
                            <span class="badge badge-warning ms-1">F: {{ $statsGerais['sem_f'] }}</span>
                        </div>
                    </div>
                    <div class="stat-icon stat-icon-danger">
                        <i class="bi bi-x-circle-fill"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg col-md-6">
            <div class="stat-card fade-in-scale stagger-item-5 hover-lift">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-label">Desistentes / Transferidos</div>
                        <div class="stat-value count-up text-warning">
                            {{ $statsGerais['desistentes_mf'] + $statsGerais['transferidos_mf'] }}
                        </div>
                        <div class="mt-2">
                            <span class="badge badge-warning me-1">Desist: {{ $statsGerais['desistentes_mf'] }}</span>
                            <span class="badge badge-info">Transf: {{ $statsGerais['transferidos_mf'] }}</span>
                        </div>
                    </div>
                    <div class="stat-icon stat-icon-warning">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if($statsGerais['sem_notas_mf'] > 0)
        <div class="alert alert-warning fade-in-scale mb-4">
            <i class="bi bi-exclamation-triangle me-2"></i>
            <strong>{{ $statsGerais['sem_notas_mf'] }} aluno(s)</strong> ainda não têm {{ $metricaStats ?? 'notas' }} calculadas para o {{ $trimestresOpts[$trimestre] ?? '' }}
            — estão contabilizados em <em>S/Aproveitamento</em> até as notas serem registadas.
        </div>
    @endif

    <!-- Gráficos -->
    <div class="row g-4 mb-4">
        <div class="col-lg-4">
            <div class="content-card fade-in-scale">
                <div class="content-card-header">
                    <h5 class="content-card-title">Distribuição por Aproveitamento</h5>
                </div>
                <div class="content-card-body">
                    <canvas id="chartSituacao" height="300"></canvas>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="content-card fade-in-scale">
                <div class="content-card-header">
                    <h5 class="content-card-title">Alunos por Classe</h5>
                </div>
                <div class="content-card-body">
                    <canvas id="chartPorClasse" height="300"></canvas>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="content-card fade-in-scale">
                <div class="content-card-header">
                    <h5 class="content-card-title">Aproveitamento por Classe</h5>
                </div>
                <div class="content-card-body">
                    <canvas id="chartSituacaoPorClasse" height="300"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabela Detalhada por Classe -->
    <div class="content-card fade-in-scale">
        <div class="content-card-header">
            <h5 class="content-card-title">
                Aproveitamento Detalhado por Classe — {{ $trimestresOpts[$trimestre] ?? '' }}
            </h5>
        </div>
        <div class="content-card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-dark">
                        <tr class="text-center">
                            <th class="text-start">Classe</th>
                            <th>Total</th>
                            <th>Desistentes</th>
                            <th>Avaliados</th>
                            <th>C/Aproveitamento</th>
                            <th>%</th>
                            <th>S/Aproveitamento</th>
                            <th>%</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($statsPorClasse['classes'] as $c)
                            <tr class="text-center">
                                <td class="text-start fw-semibold">{{ $c['nome'] }}</td>
                                <td><span class="badge bg-secondary">{{ $c['total'] }}</span></td>
                                <td><span class="badge bg-warning text-dark">{{ $c['desistentes'] }}</span></td>
                                <td><span class="badge bg-primary">{{ $c['avaliados'] }}</span></td>
                                <td>
                                    <span class="badge bg-success">{{ $c['com_mf'] }}</span>
                                    <small class="text-muted ms-1">F:{{ $c['com_f'] }}</small>
                                </td>
                                <td>
                                    <span class="fw-bold {{ $c['com_pct'] >= 50 ? 'text-success' : 'text-danger' }}">
                                        {{ number_format($c['com_pct'], 1, ',') }}%
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-danger">{{ $c['sem_mf'] }}</span>
                                    <small class="text-muted ms-1">F:{{ $c['sem_f'] }}</small>
                                </td>
                                <td>
                                    <span class="fw-bold {{ $c['sem_pct'] < 50 ? 'text-success' : 'text-danger' }}">
                                        {{ number_format($c['sem_pct'], 1, ',') }}%
                                    </span>
                                </td>
                            </tr>
                        @endforeach

                        <!-- Total Geral -->
                        @if(!empty($statsPorClasse['total_geral']))
                        <tr class="table-dark text-center fw-bold">
                            <td class="text-start">TOTAL GERAL</td>
                            <td>{{ $statsPorClasse['total_geral']['total'] }}</td>
                            <td>{{ $statsPorClasse['total_geral']['desistentes'] }}</td>
                            <td>{{ $statsPorClasse['total_geral']['avaliados'] }}</td>
                            <td>{{ $statsPorClasse['total_geral']['com'] }}</td>
                            <td>{{ number_format($statsPorClasse['total_geral']['com_pct'], 1, ',') }}%</td>
                            <td>{{ $statsPorClasse['total_geral']['sem'] }}</td>
                            <td>{{ number_format($statsPorClasse['total_geral']['sem_pct'], 1, ',') }}%</td>
                        </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Consolidar Situações -->
@can('consolidarSituacoes', App\Models\Aluno::class)
<div class="modal fade" id="consolidarModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-arrow-clockwise me-2"></i>Consolidar Situações Académicas</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="{{ route('estatisticas.consolidar') }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <strong>Apenas para fim do ano lectivo!</strong><br>
                        Esta operação actualiza a <code>situacao_academica</code> dos alunos com as
                        <strong>regras DE 424/25</strong> (pauta T3, 10.ª–12.ª) ou CFD para outras classes.
                        Processa matriculados, aprovados e reprovados — pode ser refeita.
                    </div>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        As estatísticas desta página usam <strong>MT / MFT2 / CA</strong> conforme o trimestre
                        e não dependem desta consolidação.
                    </div>
                    <input type="hidden" name="ano_lectivo_id" value="{{ $anoLectivoId }}">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="confirmar" id="confirmar" required>
                        <label class="form-check-label" for="confirmar">
                            Confirmo que existem notas CFD lançadas e desejo consolidar as situações académicas
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning">
                        <i class="bi bi-check-lg me-2"></i>Consolidar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endcan

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    Chart.defaults.font.family = "'Inter', system-ui, sans-serif";
    Chart.defaults.color = '#64748b';

    // Gráfico de Rosca — Distribuição por Aproveitamento
    new Chart(document.getElementById('chartSituacao').getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: {!! json_encode($chartDados['situacao']['labels']) !!},
            datasets: [{
                data: {!! json_encode($chartDados['situacao']['data']) !!},
                backgroundColor: {!! json_encode($chartDados['situacao']['colors']) !!},
                borderWidth: 0
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom', labels: { padding: 15, usePointStyle: true, font: { size: 12 } } } }
        }
    });

    // Gráfico de Barras — Alunos por Classe
    new Chart(document.getElementById('chartPorClasse').getContext('2d'), {
        type: 'bar',
        data: {
            labels: {!! json_encode($chartDados['porClasse']['labels']) !!},
            datasets: [{ label: 'Total de Alunos', data: {!! json_encode($chartDados['porClasse']['data']) !!}, backgroundColor: '#4f46e5', borderRadius: 8 }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, ticks: { stepSize: 10 } } }
        }
    });

    // Gráfico de Barras Empilhadas — Aproveitamento por Classe
    new Chart(document.getElementById('chartSituacaoPorClasse').getContext('2d'), {
        type: 'bar',
        data: {
            labels: {!! json_encode($chartDados['situacaoPorClasse']['labels']) !!},
            datasets: {!! json_encode($chartDados['situacaoPorClasse']['datasets']) !!}.map(ds => ({
                ...ds, borderRadius: 6, borderSkipped: false
            }))
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom', labels: { padding: 10, usePointStyle: true, font: { size: 11 } } } },
            scales: { x: { stacked: true }, y: { stacked: true, beginAtZero: true, ticks: { stepSize: 10 } } }
        }
    });
});
</script>
@endpush
@endsection
