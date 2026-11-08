@php
    $alertas = $briefing['alertas'] ?? [];
    $total = (int) ($briefing['total'] ?? 0);
    $periodo = $briefing['periodo'] ?? 'esta semana';
@endphp

<div class="content-card mb-4 border-start border-4 border-warning">
    <div class="content-card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div>
            <h6 class="content-card-title mb-0">
                <i class="bi bi-people-fill me-2 text-warning"></i>Famílias a contactar
            </h6>
            <small class="text-muted">Briefing dos {{ $periodo }} · prioridade pedagógica</small>
        </div>
        @if($total > 0)
            <span class="badge bg-warning text-dark">{{ $total }} {{ $total === 1 ? 'aluno' : 'alunos' }}</span>
        @endif
    </div>
    <div class="content-card-body">
        @if(empty($alertas))
            <p class="text-muted mb-0">
                <i class="bi bi-check-circle me-1 text-success"></i>
                Nenhuma família exige contacto urgente neste momento. Bom trabalho!
            </p>
        @else
            <div class="list-group list-group-flush">
                @foreach($alertas as $item)
                    <div class="list-group-item px-0 py-3">
                        <div class="d-flex flex-wrap justify-content-between gap-2 mb-1">
                            <div>
                                <a href="{{ $item['url_aluno'] }}" class="fw-semibold text-decoration-none">{{ $item['aluno'] }}</a>
                                <span class="text-muted small">· {{ $item['turma'] }}</span>
                                <div class="small text-muted">{{ $item['encarregado'] }}</div>
                            </div>
                            <div class="d-flex gap-2">
                                @if(!empty($item['url_mensagens']))
                                    <a href="{{ $item['url_mensagens'] }}" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-chat-dots me-1"></i>Mensagem
                                    </a>
                                @endif
                                <a href="{{ $item['url_aluno'] }}" class="btn btn-sm btn-outline-secondary">Ficha</a>
                            </div>
                        </div>
                        <ul class="mb-0 ps-3 small">
                            @foreach($item['motivos'] as $motivo)
                                <li>{{ $motivo }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
            @if($total > count($alertas))
                <p class="small text-muted mt-2 mb-0">A mostrar os {{ count($alertas) }} mais prioritários de {{ $total }}.</p>
            @endif
        @endif
    </div>
</div>
