@props([
    'type' => 'info',
    'dismissible' => true,
    'icon' => null
])

@php
$icons = [
    'success' => 'bi-check-circle-fill',
    'danger' => 'bi-exclamation-triangle-fill',
    'warning' => 'bi-exclamation-circle-fill',
    'info' => 'bi-info-circle-fill',
];

$alertIcon = $icon ?? $icons[$type] ?? 'bi-info-circle-fill';
@endphp

<div {{ $attributes->merge(['class' => "alert alert-{$type} alert-modern" . ($dismissible ? ' alert-dismissible fade show' : '')]) }} role="alert">
    <i class="bi {{ $alertIcon }}"></i>
    <div>
        {{ $slot }}
    </div>
    @if($dismissible)
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    @endif
</div>
