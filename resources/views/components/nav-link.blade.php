@props(['active'])

@php
$classes = ($active ?? false)
            ? 'nav-link active d-inline-flex align-items-center border-bottom border-primary border-2'
            : 'nav-link d-inline-flex align-items-center border-bottom border-transparent border-2';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
