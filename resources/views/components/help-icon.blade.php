@props([
    'content' => '',
    'title' => null,
    'placement' => 'top',
    'type' => 'tooltip'
])

@if($type === 'popover')
    <i {{ $attributes->merge(['class' => 'bi bi-question-circle text-muted ms-1']) }}
       data-bs-toggle="popover"
       data-bs-trigger="hover focus"
       data-bs-placement="{{ $placement }}"
       data-bs-title="{{ $title }}"
       data-bs-content="{{ $content }}"
       style="cursor: help;"></i>
@else
    <i {{ $attributes->merge(['class' => 'bi bi-question-circle text-muted ms-1']) }}
       data-bs-toggle="tooltip"
       data-bs-placement="{{ $placement }}"
       title="{{ $content }}"
       style="cursor: help;"></i>
@endif
