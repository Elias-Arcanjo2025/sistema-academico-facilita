@extends('layouts.portal-aluno')

@section('title', 'Portal do Aluno')

@section('content')
<div class="container-fluid px-3 px-md-4 py-3 py-md-4">
    <div class="row g-3 g-md-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm portal-card">
                <div class="card-body p-3 p-md-4">
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-2">
                        <div>
                            <h2 class="h4 mb-1">Olá, {{ $aluno->nome }}</h2>
                            <p class="text-muted mb-0 small">
                                {{ $aluno->turma?->codigo }} · {{ $aluno->turma?->curso?->nome }} · {{ $aluno->turma?->classe?->codigo }}ª classe
                            </p>
                        </div>
                        <span class="badge bg-primary align-self-start">{{ ucfirst($aluno->situacao_academica) }}</span>
                    </div>
                </div>
            </div>
        </div>

        <x-regras-avaliacao-destaque variant="portal" />

        @isset($saude)
            @include('portal.aluno.partials.saude-turma', ['saude' => $saude])
        @endisset

        @if(!($podeConsultarNotas ?? true))
            <div class="col-12">
                <div class="alert alert-warning border-0 shadow-sm mb-0">
                    <h3 class="h6"><i class="bi bi-lock-fill me-1"></i> Consulta de notas indisponível</h3>
                    <p class="mb-0 small">{{ $mensagemBloqueio }}</p>
                </div>
            </div>
        @else
            <div class="col-6 col-md-3">
                <div class="card border-0 shadow-sm h-100 portal-card">
                    <div class="card-body text-center p-3">
                        <div class="text-muted small">Trimestre actual</div>
                        <div class="fs-3 fw-bold text-primary">{{ $trimestre }}º</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card border-0 shadow-sm h-100 portal-card">
                    <div class="card-body text-center p-3">
                        <div class="text-muted small">Disciplinas</div>
                        <div class="fs-3 fw-bold">{{ $resumo['total_disciplinas'] }}</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card border-0 shadow-sm h-100 portal-card">
                    <div class="card-body text-center p-3">
                        <div class="text-muted small">Negativas (CFD)</div>
                        <div class="fs-3 fw-bold {{ $resumo['negativas'] > 0 ? 'text-danger' : 'text-success' }}">{{ $resumo['negativas'] }}</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card border-0 shadow-sm h-100 portal-card">
                    <div class="card-body text-center p-3">
                        <div class="text-muted small">Nota mínima</div>
                        <div class="fs-3 fw-bold">{{ number_format($notaMinima, 0) }} val.</div>
                    </div>
                </div>
            </div>

            @if(($examesEspeciais ?? collect())->isNotEmpty())
            <div class="col-12">
                <div class="alert alert-info border-0 shadow-sm mb-0">
                    <h3 class="h6"><i class="bi bi-arrow-repeat me-1"></i> Recuperação / recurso</h3>
                    <ul class="small mb-0 ps-3">
                        @foreach($examesEspeciais as $notaExame)
                            @php
                                $aprovado = (float) $notaExame->valor >= $notaMinima;
                                $rotulo = $notaExame->tipo === \App\Models\Nota::TIPO_RECURSO ? 'Recurso' : 'Recuperação';
                            @endphp
                            <li>
                                {{ $notaExame->disciplina?->nome ?? 'Disciplina' }}
                                — {{ $rotulo }}: <strong class="{{ $aprovado ? 'text-success' : 'text-danger' }}">{{ number_format($notaExame->valor, 1) }} val.</strong>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
            @endif

            @if(($graficoNotas ?? collect())->isNotEmpty())
            <div class="col-12">
                <div class="card border-0 shadow-sm portal-card">
                    <div class="card-header bg-transparent border-0">
                        <h3 class="h6 mb-0"><i class="bi bi-bar-chart me-1"></i> Médias trimestrais ({{ $trimestre }}º)</h3>
                    </div>
                    <div class="card-body">
                        <canvas id="portalGraficoNotas" height="120" aria-label="Gráfico das médias trimestrais"></canvas>
                    </div>
                </div>
            </div>
            @endif

            <div class="col-12">
                <div class="card border-0 shadow-sm portal-card">
                    <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <h3 class="h6 mb-0">Resumo das notas</h3>
                        <a href="{{ route('portal.aluno.notas', ['ano_lectivo_id' => $anoActualId, 'periodo' => $trimestre]) }}" class="btn btn-sm btn-outline-primary">Ver detalhe</a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0 align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Disciplina</th>
                                        <th class="d-none d-sm-table-cell">Última nota</th>
                                        <th>Valor</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($notasResumo as $disciplina => $lista)
                                        @php $ultima = $lista->sortByDesc('updated_at')->first(); @endphp
                                        <tr>
                                            <td>{{ $disciplina }}</td>
                                            <td class="d-none d-sm-table-cell"><span class="badge bg-secondary">{{ $ultima?->tipo }}</span></td>
                                            <td class="{{ (float)($ultima?->valor ?? 0) < $notaMinima ? 'text-danger fw-semibold' : '' }}">
                                                {{ $ultima?->valor !== null ? number_format((float)$ultima->valor, 1) : '—' }}
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="3" class="text-center text-muted py-4">Ainda não há notas lançadas.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12">
                <div class="card border-0 shadow-sm portal-card">
                    <div class="card-header bg-transparent border-0">
                        <h3 class="h6 mb-0"><i class="bi bi-file-earmark-pdf me-1"></i> Documentos</h3>
                    </div>
                    <div class="card-body d-flex flex-wrap gap-2">
                        <a href="{{ route('portal.aluno.boletim.pdf', ['ano_lectivo' => $anoLectivoActual]) }}"
                           class="btn btn-portal-pdf btn-sm">
                            <i class="bi bi-file-pdf me-1"></i> Boletim anual (PDF)
                        </a>
                        <a href="{{ route('portal.aluno.boletim.pdf', ['ano_lectivo' => $anoLectivoActual, 'trimestre' => $trimestre]) }}"
                           class="btn btn-portal-doc btn-sm">
                            <i class="bi bi-file-pdf me-1"></i> Boletim {{ $trimestre }}º trim. (PDF)
                        </a>
                    </div>
                </div>
            </div>
        @endif

        <div class="col-12">
            <div class="card border-0 shadow-sm portal-card">
                <div class="card-header bg-transparent border-0">
                    <h3 class="h6 mb-0"><i class="bi bi-calendar3 me-1"></i> Horário e provas</h3>
                </div>
                <div class="card-body d-flex flex-wrap gap-2">
                    <a href="{{ route('portal.aluno.horario') }}" class="btn btn-portal-action btn-sm">
                        <i class="bi bi-calendar-week me-1"></i> Ver horário
                    </a>
                    <a href="{{ route('portal.aluno.horario.pdf') }}" class="btn btn-portal-pdf btn-sm">
                        <i class="bi bi-file-pdf me-1"></i> Horário (PDF)
                    </a>
                    <a href="{{ route('portal.aluno.calendario', ['tab' => 'provas']) }}" class="btn btn-portal-action btn-sm">
                        <i class="bi bi-calendar-event me-1"></i> Ver provas
                    </a>
                    <a href="{{ route('portal.aluno.provas.pdf') }}" class="btn btn-portal-pdf btn-sm">
                        <i class="bi bi-file-pdf me-1"></i> Calendário de provas (PDF)
                    </a>
                </div>
            </div>
        </div>

        <div class="col-12">
            <a href="{{ route('portal.aluno.perfil') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-person-gear me-1"></i> Meu perfil
            </a>
        </div>

        @can('assistente.usar')
        <div class="col-12">
            <div class="card border-0 shadow-sm portal-assistant-card">
                <div class="card-body d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between gap-3 p-3 p-md-4">
                    <div>
                        <h3 class="h6 mb-1"><i class="bi bi-stars me-1"></i> Assistente IA</h3>
                        <p class="mb-0 small text-muted">Pergunte sobre as suas notas, disciplinas ou professores.</p>
                    </div>
                    <button type="button" class="btn btn-primary" onclick="window.dispatchEvent(new Event('assistente:open'))">
                        Abrir assistente
                    </button>
                </div>
            </div>
        </div>
        @endcan
    </div>
</div>
@endsection

@if(($graficoNotas ?? collect())->isNotEmpty())
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function () {
    var ctx = document.getElementById('portalGraficoNotas');
    if (!ctx || typeof Chart === 'undefined') return;
    var dados = @json($graficoNotas);
    var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: dados.map(function (d) { return d.disciplina; }),
            datasets: [{
                label: 'MT',
                data: dados.map(function (d) { return d.valor; }),
                backgroundColor: 'rgba(79, 70, 229, 0.65)',
                borderRadius: 6,
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: {
                y: { min: 0, max: 20, ticks: { color: isDark ? '#94a3b8' : '#64748b' }, grid: { color: isDark ? 'rgba(148,163,184,.15)' : 'rgba(0,0,0,.06)' } },
                x: { ticks: { color: isDark ? '#94a3b8' : '#64748b', maxRotation: 45, minRotation: 0 } }
            }
        }
    });
})();
</script>
@endpush
@endif
