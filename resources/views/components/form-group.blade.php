@props([
    'label' => null,
    'name' => null,
    'required' => false,
    'error' => null,
    'help' => null,
    'type' => 'text'
])

<div {{ $attributes->merge(['class' => 'mb-3']) }}>
    @if($label)
        <label for="{{ $name }}" class="form-label">
            {{ $label }}
            @if($required)
                <span class="text-danger">*</span>
            @endif
        </label>
    @endif

    {{ $slot }}

    @if($error && $errors->has($name))
        <div class="invalid-feedback d-block">
            {{ $errors->first($name) }}
        </div>
    @elseif($help)
        <div class="form-text">
            {{ $help }}
        </div>
    @endif
</div>
