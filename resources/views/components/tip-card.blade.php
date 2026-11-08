@props([
    'icon' => 'bi-lightbulb',
    'variant' => 'primary',
    'title' => 'Dica',
    'dismissible' => true,
    'id' => null,
    'compact' => false,
])

@php
    $tipId = $id ?? 'tip-' . md5($title . $slot);
    $variantMap = [
        'primary' => ['bg' => 'rgba(79,70,229,0.06)', 'border' => 'var(--primary-color)', 'icon_color' => 'var(--primary-color)', 'dark_bg' => 'rgba(129,140,248,0.08)'],
        'info' => ['bg' => 'rgba(6,182,212,0.06)', 'border' => '#06b6d4', 'icon_color' => '#06b6d4', 'dark_bg' => 'rgba(34,211,238,0.08)'],
        'success' => ['bg' => 'rgba(16,185,129,0.06)', 'border' => '#10b981', 'icon_color' => '#10b981', 'dark_bg' => 'rgba(52,211,153,0.08)'],
        'warning' => ['bg' => 'rgba(245,158,11,0.06)', 'border' => '#f59e0b', 'icon_color' => '#f59e0b', 'dark_bg' => 'rgba(251,191,36,0.08)'],
    ];
    $v = $variantMap[$variant] ?? $variantMap['primary'];
@endphp

<div class="tip-card tip-card--{{ $variant }} {{ $compact ? 'tip-card--compact' : '' }}"
     id="{{ $tipId }}"
     style="display: none;">
    <div class="tip-card__icon">
        <i class="bi {{ $icon }}"></i>
    </div>
    <div class="tip-card__body">
        @if(!$compact)
            <div class="tip-card__title">{{ $title }}</div>
        @endif
        <div class="tip-card__text">{{ $slot }}</div>
    </div>
    @if($dismissible)
        <button type="button" class="tip-card__close" onclick="dismissTip('{{ $tipId }}')" title="Fechar dica">
            <i class="bi bi-x-lg"></i>
        </button>
    @endif
</div>
