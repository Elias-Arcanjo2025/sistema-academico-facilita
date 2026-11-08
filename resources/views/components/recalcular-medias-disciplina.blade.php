@props([
    'turmaId',
    'disciplinaId',
    'trimestre' => null,
    'mostrarMt1' => true,
    'mostrarMt2' => true,
    'mostrarMt3' => false,
    'mostrarMft2' => true,
    'mostrarCf' => false,
    'mostrarCa' => false,
    'rotuloCa' => 'CA',
])

<div class="d-flex gap-2 flex-wrap align-items-center {{ $attributes->get('class') }}">
    <small class="text-muted align-self-center me-1">
        <i class="bi bi-calculator"></i> Recalcular:
    </small>

    @if($mostrarMt1)
        <form action="{{ route('mini-pautas.calcular-medias-trimestre') }}" method="POST" class="d-inline">
            @csrf
            <input type="hidden" name="turma_id" value="{{ $turmaId }}">
            <input type="hidden" name="disciplina_id" value="{{ $disciplinaId }}">
            <input type="hidden" name="trimestre" value="1">
            <button type="submit" class="btn btn-sm btn-outline-secondary" title="Calcular MT1 a partir de MAC1, PP1 e PT1">
                MT1
            </button>
        </form>
    @endif

    @if($mostrarMt2)
        <form action="{{ route('mini-pautas.calcular-medias-trimestre') }}" method="POST" class="d-inline">
            @csrf
            <input type="hidden" name="turma_id" value="{{ $turmaId }}">
            <input type="hidden" name="disciplina_id" value="{{ $disciplinaId }}">
            <input type="hidden" name="trimestre" value="2">
            <button type="submit" class="btn btn-sm btn-outline-secondary" title="Calcular MT2 a partir de MAC2, PP2 e PT2">
                MT2
            </button>
        </form>
    @endif

    @if($mostrarMft2)
        <form action="{{ route('mini-pautas.calcular-mft2') }}" method="POST" class="d-inline">
            @csrf
            <input type="hidden" name="turma_id" value="{{ $turmaId }}">
            <input type="hidden" name="disciplina_id" value="{{ $disciplinaId }}">
            <button type="submit" class="btn btn-sm btn-outline-primary" title="Calcular MFT2 = (MT1+MT2)/2">
                MFT2
            </button>
        </form>
    @endif

    @if($mostrarMt3)
        <form action="{{ route('mini-pautas.calcular-medias-trimestre') }}" method="POST" class="d-inline">
            @csrf
            <input type="hidden" name="turma_id" value="{{ $turmaId }}">
            <input type="hidden" name="disciplina_id" value="{{ $disciplinaId }}">
            <input type="hidden" name="trimestre" value="3">
            <button type="submit" class="btn btn-sm btn-outline-secondary" title="Calcular MT3 = (MAC3+PP3)/2">
                MT3
            </button>
        </form>
    @endif

    @if($mostrarCf)
        <form action="{{ route('mini-pautas.calcular-cf') }}" method="POST" class="d-inline">
            @csrf
            <input type="hidden" name="turma_id" value="{{ $turmaId }}">
            <input type="hidden" name="disciplina_id" value="{{ $disciplinaId }}">
            <button type="submit" class="btn btn-sm btn-outline-primary" title="Calcular CF = (MFT2+MT3)/2">
                CF
            </button>
        </form>
    @endif

    @if($mostrarCa)
        <form action="{{ route('mini-pautas.calcular-ca') }}" method="POST" class="d-inline">
            @csrf
            <input type="hidden" name="turma_id" value="{{ $turmaId }}">
            <input type="hidden" name="disciplina_id" value="{{ $disciplinaId }}">
            <button type="submit" class="btn btn-sm btn-outline-success" title="Calcular {{ $rotuloCa }} = 0,6×CF + 0,4×PG">
                {{ $rotuloCa }}
            </button>
        </form>
    @endif
</div>
