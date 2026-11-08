@props([
    'anoLectivoId' => null,
    'anosLectivos' => [],
    'name' => 'ano_lectivo_id',
    'label' => 'Ano Lectivo',
    'showAllOption' => false,
    'size' => 'md' // 'sm', 'md', 'lg'
])

@php
    $sizeClass = match($size) {
        'sm' => 'form-select-sm',
        'lg' => 'form-select-lg',
        default => ''
    };
@endphp

<div {{ $attributes->merge(['class' => 'mb-3']) }}>
    <label for="{{ $name }}" class="form-label">
        <i class="bi bi-calendar-range me-1"></i>{{ $label }}
    </label>
    <select
        name="{{ $name }}"
        id="{{ $name }}"
        class="form-select {{ $sizeClass }}"
        onchange="this.form.submit()"
    >
        @if($showAllOption)
            <option value="">Todos os anos</option>
        @endif

        @foreach($anosLectivos as $id => $nome)
            <option value="{{ $id }}" {{ $anoLectivoId == $id ? 'selected' : '' }}>
                {{ $nome }}
                @if($id == \App\Models\Configuracao::getAnoLectivoActualId())
                    (Actual)
                @endif
            </option>
        @endforeach
    </select>
</div>
