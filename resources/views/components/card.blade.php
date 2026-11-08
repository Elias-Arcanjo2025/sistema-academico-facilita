@props([
    'title' => null,
    'icon' => null,
    'headerClass' => '',
    'bodyClass' => '',
    'footer' => null
])

<div {{ $attributes->merge(['class' => 'card']) }}>
    @if($title || $icon)
    <div class="card-header {{ $headerClass }}">
        <h5 class="card-title mb-0">
            @if($icon)
                <i class="{{ $icon }} me-2"></i>
            @endif
            {{ $title }}
        </h5>
    </div>
    @endif

    <div class="card-body {{ $bodyClass }}">
        {{ $slot }}
    </div>

    @if($footer)
    <div class="card-footer">
        {{ $footer }}
    </div>
    @endif
</div>
