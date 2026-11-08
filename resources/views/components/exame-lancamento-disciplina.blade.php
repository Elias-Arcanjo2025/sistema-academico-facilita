@props([
    'codigo',
    'referencia' => null,
    'rotuloRef' => 'CFD/CA',
    'name',
    'value' => null,
    'passa' => false,
])

<div class="exame-disc-chip">
    <span class="exame-disc-chip__codigo" title="{{ $codigo }}">{{ $codigo }}</span>
    <div class="exame-disc-chip__fluxo">
        @if($referencia !== null)
            <span class="exame-disc-chip__ref">
                <span class="exame-disc-chip__ref-label">{{ $rotuloRef }}</span>
                <span class="exame-disc-chip__ref-valor {{ (float) $referencia < 10 ? 'text-danger' : 'text-success' }}">{{ (int) round($referencia) }}</span>
            </span>
            <i class="bi bi-arrow-right exame-disc-chip__arrow" aria-hidden="true"></i>
        @endif
        <input
            type="number"
            name="{{ $name }}"
            value="{{ $value !== null ? (int) round($value) : '' }}"
            min="0"
            max="20"
            step="1"
            placeholder="—"
            class="form-control form-control-sm exame-disc-chip__input {{ $value !== null ? ($passa ? 'text-success' : 'text-danger') : '' }}"
            inputmode="numeric"
            autocomplete="off">
    </div>
</div>
