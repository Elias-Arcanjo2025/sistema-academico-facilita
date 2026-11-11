@php
    $modoActual = $modoLancamento ?? (request('modo') === 'folha' ? 'folha' : 'tipo');
    $filtrosComuns = array_filter([
        'ano_lectivo_id' => $anoLectivoId ?? request('ano_lectivo_id'),
        'turma_id' => $turmaId ?? request('turma_id'),
        'disciplina_id' => $disciplinaId ?? request('disciplina_id'),
        'trimestre' => $trimestre ?? request('trimestre'),
    ], fn ($v) => $v !== null && $v !== '');
@endphp
<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <div class="btn-group" role="group" aria-label="Modo de lançamento">
        <a href="{{ route('notas.lancar', array_merge($filtrosComuns, ['modo' => 'tipo'])) }}"
           class="btn btn-sm {{ $modoActual === 'tipo' ? 'btn-primary' : 'btn-outline-primary' }}">
            <i class="bi bi-list-ol me-1"></i>Por tipo
        </a>
        <a href="{{ route('notas.lancar', array_merge($filtrosComuns, ['modo' => 'folha'])) }}"
           class="btn btn-sm {{ $modoActual === 'folha' ? 'btn-primary' : 'btn-outline-primary' }}">
            <i class="bi bi-table me-1"></i>Folha completa
        </a>
    </div>
    <span class="text-muted small">
        @if($modoActual === 'folha')
            MAC, PP e PT/PG na mesma grelha
        @else
            Um tipo de cada vez (inclui exames)
        @endif
    </span>
</div>
