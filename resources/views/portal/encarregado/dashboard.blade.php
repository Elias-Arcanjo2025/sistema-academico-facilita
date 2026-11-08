@extends('layouts.portal-encarregado')

@section('title', 'Início')

@section('content')
@php
    $d = $dashboard;
    $filhosCards = $d['filhos'];
@endphp

<div class="enc-dash">
    <div class="enc-hero mb-3">
        <div class="enc-hero__eyebrow">{{ $d['ano_lectivo'] ?? 'Ano lectivo' }} · {{ $d['trimestre'] }}º trimestre</div>
        <h1 class="enc-hero__title">Olá, {{ explode(' ', $encarregado->nome)[0] ?? $encarregado->nome }}</h1>
        <p class="enc-hero__sub mb-0">Acompanhe o ritmo académico dos seus educandos num só ecrã.</p>
    </div>

    @if($filhosCards->isEmpty())
        <div class="enc-panel text-muted">Ainda não há educandos associados. Contacte a secretaria.</div>
    @else
        @php $rs = $resumoSemanalPreview ?? null; @endphp
        @if($rs)
            <a href="{{ route('portal.encarregado.resumo-semanal') }}" class="enc-panel d-block text-decoration-none text-reset mb-3" style="border-left: 4px solid var(--enc-accent);">
                <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
                    <div>
                        <div class="enc-hero__eyebrow mb-1">Esta semana · {{ $rs['periodo']['rotulo'] }}</div>
                        <strong>Resumo semanal dos educandos</strong>
                        <div class="small text-muted mt-1">
                            {{ $rs['totais']['faltas'] }} falta(s) ·
                            {{ $rs['totais']['ocorrencias'] }} ocorrência(s) ·
                            {{ $rs['totais']['mensagens'] }} mensagem(ns)
                        </div>
                    </div>
                    <span class="btn btn-sm btn-outline-secondary">Abrir <i class="bi bi-arrow-right"></i></span>
                </div>
            </a>
        @endif

        @if(!empty($acoesAgora))
            <div class="enc-panel mb-3">
                <div class="enc-panel__head">
                    <strong><i class="bi bi-lightning-charge me-1"></i> O que fazer agora</strong>
                    <span class="small text-muted">Prioridades</span>
                </div>
                <div class="enc-acoes">
                    @foreach($acoesAgora as $acao)
                        <a href="{{ $acao['url'] }}" class="enc-acao">
                            <i class="bi {{ $acao['icone'] }}"></i>
                            <span>
                                <strong>{{ $acao['titulo'] }}</strong>
                                <small>{{ $acao['texto'] }}</small>
                            </span>
                        </a>
                    @endforeach
                </div>
            </div>
        @else
            <div class="enc-panel mb-3 enc-panel--ok">
                <strong><i class="bi bi-check-circle me-1"></i> Tudo em dia</strong>
                <p class="small text-muted mb-0 mt-1">Não há acções urgentes. Continue a acompanhar o resumo semanal e o dashboard de cada educando.</p>
            </div>
        @endif

        <div class="row g-2 mb-3">
            <div class="col-6 col-md-3">
                <div class="enc-kpi">
                    <span class="enc-kpi__label">Educandos</span>
                    <strong class="enc-kpi__value">{{ $d['totais']['educandos'] }}</strong>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="enc-kpi">
                    <span class="enc-kpi__label">Média geral (MT)</span>
                    <strong class="enc-kpi__value">{{ $d['totais']['media_geral'] !== null ? number_format($d['totais']['media_geral'], 1) : '—' }}</strong>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="enc-kpi {{ $d['totais']['negativas'] > 0 ? 'enc-kpi--warn' : '' }}">
                    <span class="enc-kpi__label">Negativas (CFD)</span>
                    <strong class="enc-kpi__value">{{ $d['totais']['negativas'] }}</strong>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="enc-kpi">
                    <span class="enc-kpi__label">Faltas (ano)</span>
                    <strong class="enc-kpi__value">{{ $d['totais']['faltas'] }}</strong>
                </div>
            </div>
        </div>

        <h2 class="enc-section-title">Os seus educandos</h2>
        <div class="row g-2 mb-3">
            @foreach($filhosCards as $filho)
                <div class="col-12 col-md-6">
                    <a href="{{ $filho['url'] }}" class="enc-filho-card" style="--enc-accent: {{ $filho['cor'] }}">
                        <div class="enc-filho-card__top">
                            <div class="enc-filho-card__avatar" aria-hidden="true">{{ mb_substr($filho['nome'], 0, 1) }}</div>
                            <div class="flex-grow-1 min-w-0">
                                <div class="enc-filho-card__nome">{{ $filho['nome'] }}</div>
                                <div class="enc-filho-card__meta">{{ $filho['processo'] }} · {{ $filho['turma'] }}</div>
                            </div>
                            <div class="enc-score" title="{{ $filho['score_label'] }}">
                                <span>{{ $filho['score'] }}</span>
                            </div>
                        </div>
                        <div class="enc-filho-card__stats">
                            <div>
                                <span>Média MT</span>
                                <strong>{{ $filho['pode_notas'] && $filho['media_mt'] !== null ? number_format($filho['media_mt'], 1) : '—' }}</strong>
                            </div>
                            <div>
                                <span>Negativas</span>
                                <strong class="{{ $filho['negativas'] > 0 ? 'text-danger' : '' }}">{{ $filho['pode_notas'] ? $filho['negativas'] : '—' }}</strong>
                            </div>
                            <div>
                                <span>Faltas</span>
                                <strong>{{ $filho['faltas_injustificadas'] + $filho['faltas_justificadas'] }}</strong>
                            </div>
                        </div>
                        <div class="enc-filho-card__cta">
                            Ver dashboard <i class="bi bi-arrow-right"></i>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>

        <div class="row g-2 mb-3">
            @if(!empty($d['grafico_medias']['labels']))
            <div class="col-12 col-lg-6">
                <div class="enc-panel h-100">
                    <div class="enc-panel__head">
                        <strong>Médias por educando</strong>
                        <span class="small text-muted">MT · {{ $d['trimestre'] }}º trim.</span>
                    </div>
                    <div class="enc-chart-wrap">
                        <canvas id="encChartMedias" aria-label="Gráfico de médias por educando"></canvas>
                    </div>
                </div>
            </div>
            @endif
            <div class="col-12 {{ !empty($d['grafico_medias']['labels']) ? 'col-lg-6' : '' }}">
                <div class="enc-panel h-100">
                    <div class="enc-panel__head">
                        <strong>Assiduidade comparada</strong>
                        <span class="small text-muted">Ano actual</span>
                    </div>
                    <div class="enc-chart-wrap">
                        <canvas id="encChartFaltas" aria-label="Gráfico de faltas por educando"></canvas>
                    </div>
                </div>
            </div>
        </div>

        @if(!empty($d['alertas']))
            <div class="enc-panel mb-2">
                <div class="enc-panel__head">
                    <strong><i class="bi bi-exclamation-circle me-1"></i> Pontos de atenção</strong>
                </div>
                <ul class="enc-alertas mb-0">
                    @foreach($d['alertas'] as $alerta)
                        <li>
                            <a href="{{ $alerta['url'] }}">{{ $alerta['texto'] }}</a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    @endif
</div>
@endsection

@if(($filhosCards ?? collect())->isNotEmpty())
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function () {
    if (typeof Chart === 'undefined') return;
    Chart.defaults.font.family = "'Segoe UI', system-ui, sans-serif";
    Chart.defaults.color = '#64748b';

    var medias = @json($d['grafico_medias']);
    var faltas = @json($d['grafico_faltas']);

    var elMedias = document.getElementById('encChartMedias');
    if (elMedias && medias.labels && medias.labels.length) {
        new Chart(elMedias, {
            type: 'bar',
            data: {
                labels: medias.labels,
                datasets: [{
                    label: 'Média MT',
                    data: medias.valores,
                    backgroundColor: medias.cores,
                    borderRadius: 8,
                    maxBarThickness: 48,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    annotation: undefined,
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 20,
                        grid: { color: 'rgba(15,23,42,.06)' },
                        ticks: { stepSize: 5 },
                    },
                    x: { grid: { display: false } },
                },
            },
        });
    }

    var elFaltas = document.getElementById('encChartFaltas');
    if (elFaltas && faltas.labels && faltas.labels.length) {
        new Chart(elFaltas, {
            type: 'bar',
            data: {
                labels: faltas.labels,
                datasets: [
                    { label: 'Injustificadas', data: faltas.injustificadas, backgroundColor: '#dc2626', borderRadius: 6, maxBarThickness: 28 },
                    { label: 'Justificadas', data: faltas.justificadas, backgroundColor: '#ca8a04', borderRadius: 6, maxBarThickness: 28 },
                    { label: 'Atrasos', data: faltas.atrasos, backgroundColor: '#ea580c', borderRadius: 6, maxBarThickness: 28 },
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, usePointStyle: true } } },
                scales: {
                    x: { stacked: true, grid: { display: false } },
                    y: { stacked: true, beginAtZero: true, ticks: { precision: 0 }, grid: { color: 'rgba(15,23,42,.06)' } },
                },
            },
        });
    }
})();
</script>
@endpush
@endif
