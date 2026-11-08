@php
    $score = array_key_exists('score', $saude) && $saude['score'] !== null ? (int) $saude['score'] : null;
    $scoreCls = $score === null ? 'secondary' : ($score >= 70 ? 'success' : ($score >= 50 ? 'warning' : 'danger'));
    $t = $saude['transicao'] ?? [];
    $l = $saude['lancamento'] ?? [];
    $totalT = max(1, (int) ($t['total_alunos'] ?? 0));
    $pctTransita = round(((int) ($t['transita'] ?? 0) / $totalT) * 100);
    $pctDef = round(((int) ($t['deficiencia'] ?? 0) / $totalT) * 100);
    $pctNao = round(((int) ($t['nao_transita'] ?? 0) / $totalT) * 100);
@endphp

<div class="saude-panel mb-4" id="saudeEscolaCard">
    <div class="saude-panel__top">
        <div class="saude-panel__score saude-panel__score--{{ $scoreCls }}">
            <div class="saude-panel__score-ring" style="--score: {{ $score ?? 0 }}">
                <span class="saude-panel__score-num">{{ $score ?? '—' }}</span>
            </div>
            <div>
                <div class="saude-panel__title">Saúde da Escola</div>
                <div class="saude-panel__label">{{ $saude['score_label'] ?? '—' }}@if(!empty($anoLectivo)) · Ano {{ $anoLectivo }}@endif</div>
            </div>
        </div>
        <div class="saude-panel__meta">
            @if(!empty($saude['contas_pendentes']))
                <a href="{{ route('users.pendentes') }}" class="saude-chip saude-chip--warn">
                    <i class="bi bi-exclamation-triangle"></i>
                    {{ $saude['contas_pendentes'] }} pendente(s)
                </a>
            @endif
            @if(empty($saude['trimestre_aberto']))
                <span class="saude-chip"><i class="bi bi-lock"></i> Trim. fechado</span>
            @endif
            <span class="saude-chip">
                <i class="bi bi-pencil-square"></i>
                {{ $saude['notas_hoje'] ?? 0 }} notas hoje
            </span>
        </div>
    </div>

    <div class="saude-panel__grid">
        <div class="saude-metric">
            <div class="saude-metric__head">
                <span>Transição T3</span>
                @if(($t['incompletos'] ?? 0) > 0)
                    <span class="text-muted" title="Pautas incompletas">{{ $t['incompletos'] }} incompletas</span>
                @endif
            </div>
            <div class="saude-bars" aria-hidden="true">
                <span class="saude-bars__seg saude-bars__seg--ok" style="width: {{ $pctTransita }}%"></span>
                <span class="saude-bars__seg saude-bars__seg--warn" style="width: {{ $pctDef }}%"></span>
                <span class="saude-bars__seg saude-bars__seg--bad" style="width: {{ $pctNao }}%"></span>
            </div>
            <div class="saude-metric__stats">
                <span><i class="bi bi-circle-fill text-success"></i> {{ $t['transita'] ?? 0 }} transita</span>
                <span><i class="bi bi-circle-fill text-warning"></i> {{ $t['deficiencia'] ?? 0 }} c/def</span>
                <span><i class="bi bi-circle-fill text-danger"></i> {{ $t['nao_transita'] ?? 0 }} n/trans.</span>
            </div>
        </div>

        <div class="saude-metric">
            <div class="saude-metric__head">
                <span>Cobertura {{ $saude['trimestre_actual'] ?? '' }}.º trim.</span>
                <strong>{{ $l['cobertura_pct'] ?? 0 }}%</strong>
            </div>
            <div class="progress saude-progress">
                <div class="progress-bar" role="progressbar" style="width: {{ min(100, (float) ($l['cobertura_pct'] ?? 0)) }}%"></div>
            </div>
            <div class="saude-metric__foot text-muted">
                {{ number_format($l['notas_avaliacao'] ?? 0) }} avaliações · {{ number_format($l['total_alunos'] ?? 0) }} alunos
            </div>
        </div>
    </div>
</div>

@if(!empty($alertasSeguranca))
<div class="alert alert-warning border-0 py-2 px-3 small mb-4 d-flex flex-wrap align-items-center gap-2">
    <i class="bi bi-shield-exclamation"></i>
    <span class="flex-grow-1">
        @foreach($alertasSeguranca as $alerta)
            <strong>{{ $alerta['titulo'] }}</strong> — {{ $alerta['detalhe'] }}@if(!$loop->last); @endif
        @endforeach
    </span>
    <a href="{{ route('auditoria.index') }}" class="btn btn-sm btn-outline-warning">Auditoria</a>
</div>
@endif
