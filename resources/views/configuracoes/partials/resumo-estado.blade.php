@php
    use App\Models\AnoLectivo;
    use App\Models\Configuracao;

    $anoActual = AnoLectivo::getActual();
    $trimestreActual = (int) ($configuracoes['ano_lectivo']['trimestre_actual'] ?? Configuracao::getTrimestreActual());
    $notaMinima = (float) ($configuracoes['avaliacao']['nota_minima_aprovacao'] ?? 10);
    $maxReprov = (int) ($configuracoes['avaliacao']['max_disciplinas_reprovacao'] ?? 2);
    $maxDesist = (int) ($configuracoes['avaliacao']['max_disciplinas_sem_nota_desistente'] ?? 3);
    $tipoMedia = $configuracoes['avaliacao']['tipo_calculo_media'] ?? 'aritmetica';
@endphp

<div class="content-card mb-3 cfg-estado-card">
    <div class="content-card-body py-3">
        <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
            <div>
                <h6 class="mb-1 fw-bold" style="color: #4338ca;">
                    <i class="bi bi-speedometer2 me-1"></i>Estado actual do sistema
                </h6>
                <span class="text-muted small">
                    Resumo das regras em vigor para o ano lectivo activo
                </span>
            </div>
            <div class="d-flex flex-wrap gap-2 align-items-center">
                @if($anoActual)
                    <span class="badge cfg-ano-pill px-3 py-2">
                        <i class="bi bi-calendar-range me-1"></i>
                        {{ $anoActual->nome }}
                        @if($anoActual->data_inicio && $anoActual->data_fim)
                            <span class="opacity-75 ms-1">
                                ({{ $anoActual->data_inicio->format('d/m/Y') }} – {{ $anoActual->data_fim->format('d/m/Y') }})
                            </span>
                        @endif
                    </span>
                    <a href="#anos-lectivos-section" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-arrow-down-circle me-1"></i>Gerir anos
                    </a>
                @else
                    <span class="badge bg-danger px-3 py-2">
                        <i class="bi bi-exclamation-triangle me-1"></i>Sem ano lectivo activo
                    </span>
                    <a href="#anos-lectivos-section" class="btn btn-sm btn-danger">
                        <i class="bi bi-plus-circle me-1"></i>Criar / activar
                    </a>
                @endif
            </div>
        </div>

        <div class="row g-2 mt-2">
            @foreach([1 => 'Set–Nov', 2 => 'Jan–Mar', 3 => 'Abr–Jun'] as $t => $periodo)
                @php
                    $eActual = $t === $trimestreActual;
                    $aberto = Configuracao::trimestreAbertoParaLancamento($t);
                @endphp
                <div class="col-md-4">
                    <div class="cfg-trim-card p-3 h-100 {{ $eActual ? 'cfg-trim-card--actual' : 'cfg-trim-card--inactivo' }}">
                        <div class="d-flex justify-content-between align-items-center">
                            <strong class="small">{{ $t }}º Trimestre</strong>
                            @if($eActual)
                                <span class="badge rounded-pill" style="background:#6366f1;">Actual</span>
                            @endif
                        </div>
                        <div class="small text-muted mt-1">{{ $periodo }}</div>
                        <div class="mt-2">
                            @if($eActual && $aberto)
                                <span class="badge rounded-pill bg-success-subtle text-success border border-success-subtle">
                                    <i class="bi bi-unlock me-1"></i>Lançamento aberto
                                </span>
                            @elseif($eActual)
                                <span class="badge rounded-pill bg-warning-subtle text-warning-emphasis border border-warning-subtle">
                                    <i class="bi bi-lock me-1"></i>Lançamento fechado
                                </span>
                            @else
                                <span class="badge rounded-pill bg-secondary-subtle text-secondary border">
                                    <i class="bi bi-lock-fill me-1"></i>Bloqueado
                                </span>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="row g-2 mt-3 small">
            <div class="col-sm-6 col-lg-3">
                <div class="cfg-stat-card cfg-stat-card--nota p-3 h-100">
                    <span class="text-muted d-block mb-1">Nota mínima</span>
                    <span class="cfg-stat-value cfg-stat-value--indigo" id="resumo-nota-minima">{{ number_format($notaMinima, 1, ',', '') }}</span>
                    <span class="text-muted"> valores</span>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="cfg-stat-card cfg-stat-card--reprov p-3 h-100">
                    <span class="text-muted d-block mb-1">Máx. negativas (geral)</span>
                    <span class="cfg-stat-value cfg-stat-value--amber" id="resumo-max-reprov">{{ $maxReprov }}</span>
                    <span class="text-muted"> → reprova se &gt;</span>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="cfg-stat-card cfg-stat-card--desist p-3 h-100">
                    <span class="text-muted d-block mb-1">Máx. sem nota</span>
                    <span class="cfg-stat-value cfg-stat-value--slate" id="resumo-max-desist">{{ $maxDesist }}</span>
                    <span class="text-muted"> → desistente se &gt;</span>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="cfg-stat-card cfg-stat-card--media p-3 h-100">
                    <span class="text-muted d-block mb-1">Média final</span>
                    <span class="cfg-stat-value" style="font-size:1.1rem;">{{ $tipoMedia === 'ponderada' ? 'Ponderada' : 'Aritmética' }}</span>
                </div>
            </div>
        </div>
    </div>
</div>
