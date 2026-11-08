@props([
    'icon' => 'bi-bar-chart',
    'iconClass' => 'stat-icon-primary',
    'value' => '0',
    'label' => '',
    'link' => null
])

<div class="stat-card">
    <div class="d-flex align-items-center">
        <div class="stat-icon {{ $iconClass }}">
            <i class="{{ $icon }}"></i>
        </div>
        <div class="ms-3 flex-grow-1">
            <div class="stat-value">{{ $value }}</div>
            <div class="stat-label">{{ $label }}</div>
        </div>
    </div>
    @if($link)
        <div class="mt-2">
            <a href="{{ $link }}" class="stat-link">
                Ver detalhes <i class="bi bi-arrow-right"></i>
            </a>
        </div>
    @endif
</div>
