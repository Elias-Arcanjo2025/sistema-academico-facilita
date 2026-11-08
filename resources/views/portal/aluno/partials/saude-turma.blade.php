@php
    $score = (int) ($saude['score'] ?? 0);
    $scoreCls = $score >= 70 ? 'success' : ($score >= 50 ? 'warning' : 'danger');
@endphp

<div class="col-12">
    <div class="card border-0 shadow-sm portal-card">
        <div class="card-body p-3">
            <div class="d-flex flex-wrap align-items-center gap-3 mb-2">
                <div class="saude-panel__score saude-panel__score--{{ $scoreCls }} mb-0">
                    <div class="saude-panel__score-ring" style="--score: {{ $score }}">
                        <span class="saude-panel__score-num">{{ $score }}</span>
                    </div>
                    <div>
                        <div class="fw-semibold">{{ $saude['titulo'] ?? 'Saúde da minha turma' }}</div>
                        <div class="small text-muted">{{ $saude['score_label'] ?? '' }} · {{ $saude['turma'] ?? '' }}</div>
                    </div>
                </div>
            </div>
            <div class="row g-2 small">
                <div class="col-6 col-md-3">
                    <span class="text-muted d-block">Negativas</span>
                    <strong class="{{ ($saude['negativas_aluno'] ?? 0) > 0 ? 'text-danger' : 'text-success' }}">
                        {{ $saude['negativas_aluno'] ?? 0 }}
                    </strong>
                </div>
                @if(!empty($saude['resultado_transicao']))
                <div class="col-6 col-md-3">
                    <span class="text-muted d-block">Transição T3</span>
                    <strong>{{ $saude['resultado_transicao'] }}</strong>
                </div>
                @endif
                <div class="col-6 col-md-3">
                    <span class="text-muted d-block">Cobertura turma</span>
                    <strong>{{ $saude['cobertura_turma_pct'] ?? 0 }}%</strong>
                </div>
                <div class="col-6 col-md-3">
                    <span class="text-muted d-block">Colegas</span>
                    <strong>{{ $saude['total_colegas'] ?? 0 }}</strong>
                </div>
            </div>
        </div>
    </div>
</div>

@push('styles')
<link href="{{ asset('css/dashboard-admin.css') }}" rel="stylesheet">
@endpush
