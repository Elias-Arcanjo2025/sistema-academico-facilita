@extends('layouts.portal-encarregado')

@section('title', $aluno->nome)

@section('content')
@php
    $cor = $card['cor'] ?? '#0d9488';
@endphp

<div class="enc-dash" style="--enc-accent: {{ $cor }}">
    <a href="{{ route('portal.encarregado.dashboard') }}" class="enc-back">&larr; Todos os educandos</a>

    <div class="enc-filho-hero mb-3">
        <div class="enc-filho-hero__avatar" aria-hidden="true">{{ mb_substr($aluno->nome, 0, 1) }}</div>
        <div class="min-w-0">
            <h1 class="enc-filho-hero__nome">{{ $aluno->nome }}</h1>
            <p class="enc-filho-hero__meta mb-0">
                {{ $aluno->turma?->codigo ?? '—' }}
                @if($aluno->turma?->curso?->nome) · {{ $aluno->turma->curso->nome }} @endif
                · {{ $trimestre }}º trimestre
            </p>
            <div class="enc-filho-hero__badge">{{ $card['score_label'] }} · score {{ $card['score'] }}</div>
        </div>
    </div>

    @if(!empty($acoesAgora))
        <div class="enc-panel mb-3">
            <div class="enc-panel__head">
                <strong><i class="bi bi-lightning-charge me-1"></i> O que fazer agora</strong>
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
    @endif

    <div class="row g-2 mb-3">
        <div class="col-6 col-md-3">
            <div class="enc-kpi">
                <span class="enc-kpi__label">Média MT</span>
                <strong class="enc-kpi__value">{{ $podeNotas && $card['media_mt'] !== null ? number_format($card['media_mt'], 1) : '—' }}</strong>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="enc-kpi">
                <span class="enc-kpi__label">Disciplinas c/ MT</span>
                <strong class="enc-kpi__value">{{ $podeNotas ? $card['disciplinas'] : '—' }}</strong>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="enc-kpi {{ ($card['negativas'] ?? 0) > 0 ? 'enc-kpi--warn' : '' }}">
                <span class="enc-kpi__label">Negativas (CFD)</span>
                <strong class="enc-kpi__value">{{ $podeNotas ? $card['negativas'] : '—' }}</strong>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="enc-kpi">
                <span class="enc-kpi__label">Faltas injust.</span>
                <strong class="enc-kpi__value">{{ $card['faltas_injustificadas'] }}</strong>
            </div>
        </div>
    </div>

    @if(! $podeNotas)
        <div class="alert alert-warning">A consulta de notas está temporariamente indisponível para este educando.</div>
    @else
        <div class="row g-2 mb-3">
            @if(($graficoMt ?? collect())->isNotEmpty())
            <div class="col-12 col-lg-7">
                <div class="enc-panel h-100">
                    <div class="enc-panel__head">
                        <strong>Médias por disciplina</strong>
                        <span class="small text-muted">MT · {{ $trimestre }}º</span>
                    </div>
                    <div class="enc-chart-wrap enc-chart-wrap--tall">
                        <canvas id="encFilhoMt" aria-label="Médias por disciplina"></canvas>
                    </div>
                </div>
            </div>
            @endif
            <div class="col-12 {{ ($graficoMt ?? collect())->isNotEmpty() ? 'col-lg-5' : '' }}">
                <div class="enc-panel h-100">
                    <div class="enc-panel__head">
                        <strong>Evolução trimestral</strong>
                        <span class="small text-muted">Média MT</span>
                    </div>
                    <div class="enc-chart-wrap">
                        <canvas id="encFilhoEvolucao" aria-label="Evolução das médias"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="enc-panel mb-3">
            <div class="enc-panel__head">
                <strong>Resumo das notas</strong>
                @if($boletimUrl)
                    <a href="{{ $boletimUrl }}" class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener">
                        <i class="bi bi-file-earmark-pdf"></i> Baixar PDF do boletim
                    </a>
                @endif
            </div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Disciplina</th>
                            <th class="d-none d-sm-table-cell">Última</th>
                            <th>Valor</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($notasResumo as $disciplina => $lista)
                            @php $ultima = $lista->sortByDesc('updated_at')->first(); @endphp
                            <tr>
                                <td>{{ $disciplina }}</td>
                                <td class="d-none d-sm-table-cell"><span class="badge text-bg-light border">{{ $ultima?->tipo }}</span></td>
                                <td class="{{ (float)($ultima?->valor ?? 0) < $notaMinima ? 'text-danger fw-semibold' : '' }}">
                                    {{ $ultima?->valor !== null ? number_format((float) $ultima->valor, 1) : '—' }}
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-muted">Sem notas neste período.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="enc-panel mb-3" id="assinatura-boletim">
            <strong>Assinatura digital do boletim</strong>
            <p class="small text-muted mb-2">Confirme a leitura do boletim. A assinatura fica registada e aparece no PDF descarregado (nome, data e referência).</p>
            <form method="POST" action="{{ route('portal.encarregado.assinar-boletim', $aluno) }}">
                @csrf
                <input type="hidden" name="trimestre" value="{{ $trimestre }}">
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" name="confirmacao" value="1" id="conf" required>
                    <label class="form-check-label" for="conf">Confirmo a leitura do boletim</label>
                </div>
                <button class="btn btn-primary btn-sm">Assinar digitalmente</button>
            </form>
            @if($assinaturas->isNotEmpty())
                <hr>
                <div class="small text-muted">Últimas assinaturas:</div>
                <ul class="small mb-0">
                    @foreach($assinaturas as $a)
                        <li>{{ $a->assinado_em->format('d/m/Y H:i') }} · T{{ $a->trimestre }} · {{ $a->ano_lectivo }}</li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endif

    <div class="row g-2 mb-3">
        <div class="col-12 col-md-6">
            <div class="enc-panel h-100">
                <div class="enc-panel__head">
                    <strong>Assiduidade</strong>
                    <a href="{{ route('portal.encarregado.assiduidade', $aluno) }}" class="btn btn-sm btn-outline-secondary">Ver tudo</a>
                </div>
                <div class="enc-chart-wrap">
                    <canvas id="encFilhoAssid" aria-label="Distribuição de assiduidade"></canvas>
                </div>
                <div class="row g-2 small mt-1">
                    <div class="col-6">Faltas injust.: <strong>{{ $resumoAssiduidade['totais']['falta_injustificada'] ?? 0 }}</strong></div>
                    <div class="col-6">Faltas just.: <strong>{{ $resumoAssiduidade['totais']['falta_justificada'] ?? 0 }}</strong></div>
                    <div class="col-6">Atrasos: <strong>{{ $resumoAssiduidade['totais']['atraso'] ?? 0 }}</strong></div>
                    <div class="col-6">Presenças: <strong>{{ $resumoAssiduidade['totais']['presente'] ?? 0 }}</strong></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6">
            <div class="enc-panel h-100">
                <div class="enc-panel__head">
                    <strong>Ocorrências</strong>
                    <a href="{{ route('portal.encarregado.ocorrencias', $aluno) }}" class="btn btn-sm btn-outline-secondary">Ver tudo</a>
                </div>
                @forelse($ocorrenciasRecentes as $oc)
                    <div class="small border-bottom py-2">
                        <strong>{{ \App\Models\Ocorrencia::tipos()[$oc->tipo] ?? $oc->tipo }}</strong>
                        · {{ $oc->data->format('d/m/Y') }}
                        <div class="text-muted">{{ \Illuminate\Support\Str::limit($oc->descricao, 80) }}</div>
                    </div>
                @empty
                    <p class="small text-muted mb-0">Sem ocorrências.</p>
                @endforelse
            </div>
        </div>
    </div>

    <div class="enc-panel mb-3">
        <div class="enc-panel__head">
            <strong><i class="bi bi-clock-history me-1"></i> Linha do tempo</strong>
            <a href="{{ route('portal.encarregado.filho.timeline', $aluno) }}" class="btn btn-sm btn-outline-secondary">Ver tudo</a>
        </div>
        @if(($timelinePreview ?? collect())->isEmpty())
            <p class="small text-muted mb-0">Ainda sem eventos recentes.</p>
        @else
            <ol class="enc-timeline enc-timeline--compact">
                @foreach($timelinePreview as $evento)
                    <li class="enc-timeline__item enc-timeline__item--{{ $evento['tom'] }}">
                        <div class="enc-timeline__dot"><i class="bi {{ $evento['icone'] }}"></i></div>
                        <div class="enc-timeline__body">
                            <div class="enc-timeline__meta">{{ $evento['data']?->format('d/m/Y') ?? '—' }}</div>
                            <div class="enc-timeline__title">{{ $evento['titulo'] }}</div>
                            <div class="enc-timeline__text">{{ $evento['texto'] }}</div>
                        </div>
                    </li>
                @endforeach
            </ol>
        @endif
    </div>

    <div class="row g-2 mb-3">
        <div class="col-6">
            <a href="{{ route('portal.encarregado.filho.timeline', $aluno) }}" class="enc-quick">
                <i class="bi bi-clock-history"></i>
                <span>Linha do tempo</span>
            </a>
        </div>
        <div class="col-6">
            <a href="{{ route('portal.encarregado.filho.agenda', $aluno) }}" class="enc-quick">
                <i class="bi bi-calendar3"></i>
                <span>Agenda</span>
            </a>
        </div>
        <div class="col-6">
            <a href="{{ route('portal.encarregado.filho.materiais', $aluno) }}" class="enc-quick">
                <i class="bi bi-folder2-open"></i>
                <span>Materiais</span>
            </a>
        </div>
        <div class="col-6">
            <a href="{{ route('portal.encarregado.mensagens.show', $aluno) }}" class="enc-quick">
                <i class="bi bi-chat-dots"></i>
                <span>Mensagens</span>
            </a>
        </div>
        <div class="col-6">
            <a href="{{ route('portal.encarregado.documentos.index') }}" class="enc-quick">
                <i class="bi bi-file-earmark-text"></i>
                <span>Documentos</span>
            </a>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function () {
    if (typeof Chart === 'undefined') return;
    Chart.defaults.font.family = "'Segoe UI', system-ui, sans-serif";
    Chart.defaults.color = '#64748b';
    var accent = @json($cor);
    var notaMin = {{ json_encode((float) $notaMinima) }};

    var mt = @json(($graficoMt ?? collect())->values());
    var elMt = document.getElementById('encFilhoMt');
    if (elMt && mt.length) {
        new Chart(elMt, {
            type: 'bar',
            data: {
                labels: mt.map(function (d) { return d.disciplina; }),
                datasets: [{
                    label: 'MT',
                    data: mt.map(function (d) { return d.valor; }),
                    backgroundColor: mt.map(function (d) {
                        return d.valor < notaMin ? '#dc2626' : accent;
                    }),
                    borderRadius: 6,
                    maxBarThickness: 36,
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { beginAtZero: true, max: 20, grid: { color: 'rgba(15,23,42,.06)' } },
                    y: { grid: { display: false } },
                },
            },
        });
    }

    var evolucao = @json($evolucao);
    var elEv = document.getElementById('encFilhoEvolucao');
    if (elEv) {
        new Chart(elEv, {
            type: 'line',
            data: {
                labels: evolucao.labels,
                datasets: [{
                    label: 'Média MT',
                    data: evolucao.valores,
                    borderColor: accent,
                    backgroundColor: accent + '33',
                    fill: true,
                    tension: 0.35,
                    spanGaps: true,
                    pointRadius: 5,
                    pointBackgroundColor: accent,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, max: 20, grid: { color: 'rgba(15,23,42,.06)' } },
                    x: { grid: { display: false } },
                },
            },
        });
    }

    var assid = @json($assiduidadeChart);
    var elAssid = document.getElementById('encFilhoAssid');
    if (elAssid) {
        var total = assid.valores.reduce(function (a, b) { return a + b; }, 0);
        new Chart(elAssid, {
            type: 'doughnut',
            data: {
                labels: assid.labels,
                datasets: [{
                    data: total > 0 ? assid.valores : [1],
                    backgroundColor: total > 0 ? assid.cores : ['#e2e8f0'],
                    borderWidth: 0,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '62%',
                plugins: {
                    legend: { position: 'bottom', labels: { boxWidth: 10, usePointStyle: true } },
                },
            },
        });
    }
})();
</script>
@endpush
