@props([
    'title' => 'Informação',
    'icon' => 'bi-info-circle',
    'type' => 'info'
])

@php
$typeClasses = [
    'info' => 'alert-info',
    'warning' => 'alert-warning',
    'success' => 'alert-success',
    'danger' => 'alert-danger',
];
$typeIcons = [
    'info' => 'bi-info-circle',
    'warning' => 'bi-exclamation-triangle',
    'success' => 'bi-check-circle',
    'danger' => 'bi-x-circle',
];
@endphp

<div {{ $attributes->merge(['class' => 'alert ' . ($typeClasses[$type] ?? 'alert-info') . ' border-0 rounded-3']) }}>
    <div class="d-flex align-items-start">
        <i class="{{ $typeIcons[$type] ?? $icon }} fs-5 me-3"></i>
        <div class="flex-grow-1">
            @if($title)
                <h6 class="alert-heading mb-2">{{ $title }}</h6>
            @endif
            <div class="mb-0">
                {{ $slot }}
            </div>
        </div>
    </div>
</div>
