@props(['active'])

@php
$classes = ($active ?? false)
            ? 'nav-link active d-block w-100 border-start border-primary border-4 text-start'
            : 'nav-link d-block w-100 border-start border-transparent border-4 text-start';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
