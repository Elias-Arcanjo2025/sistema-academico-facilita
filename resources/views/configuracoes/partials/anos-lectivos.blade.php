<div class="content-card mb-3" id="anos-lectivos-section">
    <div class="content-card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <h6 class="content-card-title mb-0">
            <i class="bi bi-calendar-range me-2"></i>Gestão de Anos Lectivos
        </h6>
        <span class="text-muted small">
            <i class="bi bi-info-circle me-1"></i>
            Apenas um ano pode estar <strong>Actual</strong>. Activar outro muda todo o contexto do sistema.
        </span>
    </div>
    <div class="content-card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Ano Lectivo</th>
                        <th class="text-center">Estado</th>
                        <th class="text-center">Turmas</th>
                        <th class="text-center">Alunos</th>
                        <th class="text-center">Notas</th>
                        <th class="text-center">Período</th>
                        <th class="text-center">Acções</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($anosLectivos as $ano)
                        <tr class="{{ $ano->isActual() ? 'cfg-ano-row--actual' : '' }}">
                            <td>
                                <strong>{{ $ano->nome }}</strong>
                            </td>
                            <td class="text-center">
                                @if($ano->isActual())
                                    <span class="badge cfg-badge-actual rounded-pill">
                                        <i class="bi bi-star-fill me-1"></i>Actual
                                    </span>
                                @elseif($ano->isNovo())
                                    <span class="badge cfg-badge-novo rounded-pill">Novo</span>
                                @else
                                    <span class="badge cfg-badge-anterior rounded-pill">Anterior</span>
                                @endif
                            </td>
                            <td class="text-center">
                                <span class="badge bg-light text-dark border">{{ $ano->turmas_count }}</span>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-light text-dark border">{{ $ano->alunos_count }}</span>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-light text-dark border">{{ number_format($ano->notas_count) }}</span>
                            </td>
                            <td class="text-center text-muted small text-nowrap">
                                @if($ano->data_inicio && $ano->data_fim)
                                    {{ $ano->data_inicio->format('d/m/Y') }} – {{ $ano->data_fim->format('d/m/Y') }}
                                @else
                                    <span class="text-warning" title="Defina as datas ao criar o ano">Sem datas</span>
                                @endif
                            </td>
                            <td class="text-center">
                                @if(! $ano->isActual())
                                    <button type="button"
                                            class="btn btn-sm cfg-btn-activar"
                                            onclick="activarAno('{{ $ano->nome }}', {{ $ano->turmas_count }}, {{ $ano->alunos_count }}, {{ $ano->notas_count }})">
                                        <i class="bi bi-check-circle me-1"></i>Activar
                                    </button>
                                @else
                                    <span class="text-success small fw-semibold">
                                        <i class="bi bi-check-circle-fill me-1"></i>Em uso
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">
                                Nenhum ano lectivo registado. Crie o primeiro abaixo.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="content-card-body border-top py-3">
        <button type="button" class="btn btn-sm cfg-btn-novo-ano" data-bs-toggle="modal" data-bs-target="#novoAnoModal">
            <i class="bi bi-plus-circle me-1"></i>Novo Ano Lectivo
        </button>
        @can('isAdminFull')
            <a href="{{ route('fecho-ano.index') }}" class="btn btn-sm btn-outline-secondary ms-2">
                <i class="bi bi-calendar2-check me-1"></i>Wizard de fecho de ano
            </a>
        @endcan
    </div>
</div>
