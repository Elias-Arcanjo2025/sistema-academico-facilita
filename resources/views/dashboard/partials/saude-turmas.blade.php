@php
    $titulo = $saude['titulo'] ?? 'Saúde da Escola';
    $score = (int) ($saude['score'] ?? 0);
    $scoreCls = $score >= 70 ? 'success' : ($score >= 50 ? 'warning' : 'danger');
    $t = $saude['transicao'] ?? [];
    $l = $saude['lancamento'] ?? [];
    $totalT = max(1, (int) ($t['total_alunos'] ?? 0));
    $pctTransita = $totalT > 0 ? round(((int) ($t['transita'] ?? 0) / $totalT) * 100) : 0;
    $pctDef = $totalT > 0 ? round(((int) ($t['deficiencia'] ?? 0) / $totalT) * 100) : 0;
    $pctNao = $totalT > 0 ? round(((int) ($t['nao_transita'] ?? 0) / $totalT) * 100) : 0;
    $anoLectivo = $saude['ano_lectivo'] ?? ($anoLectivo ?? '');
@endphp

<div class="saude-panel mb-4">
    <div class="saude-panel__top">
        <div class="saude-panel__score saude-panel__score--{{ $scoreCls }}">
            <div class="saude-panel__score-ring" style="--score: {{ $score }}">
                <span class="saude-panel__score-num">{{ $score }}</span>
            </div>
            <div>
                <div class="saude-panel__title">{{ $titulo }}</div>
                <div class="saude-panel__label">
                    {{ $saude['score_label'] ?? '—' }}
                    @if(!empty($saude['escopo']))
                        · {{ $saude['escopo'] }}
                    @endif
                    @if($anoLectivo)
                        · Ano {{ $anoLectivo }}
                    @endif
                </div>
            </div>
        </div>
        <div class="saude-panel__meta">
            @if(!empty($saude['contas_pendentes']))
                <a href="{{ route('users.pendentes') }}" class="saude-chip saude-chip--warn">
                    <i class="bi bi-exclamation-triangle"></i>
                    {{ $saude['contas_pendentes'] }} pendente(s)
                </a>
            @endif
            @if(isset($saude['trimestre_aberto']) && ! $saude['trimestre_aberto'])
                <span class="saude-chip"><i class="bi bi-lock"></i> Trim. fechado</span>
            @endif
            @if(isset($saude['notas_hoje']))
                <span class="saude-chip">
                    <i class="bi bi-pencil-square"></i>
                    {{ $saude['notas_hoje'] }} notas hoje
                </span>
            @endif
        </div>
    </div>

    @if(($t['total_alunos'] ?? 0) > 0 || ($l['total_alunos'] ?? 0) > 0)
    <div class="saude-panel__grid">
        @if(($t['total_alunos'] ?? 0) > 0)
        <div class="saude-metric">
            <div class="saude-metric__head">
                <span>Transição T3</span>
                @if(($t['incompletos'] ?? 0) > 0)
                    <span class="text-muted">{{ $t['incompletos'] }} incompletas</span>
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
        @endif

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
    @else
        <p class="text-muted small mb-0 mt-2">Sem turmas no seu escopo para este ano lectivo.</p>
    @endif
</div>
