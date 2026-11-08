@props(['status'])

@if ($status)
    <div {{ $attributes->merge(['class' => 'alert alert-success small']) }}>
        {{ $status }}
    </div>
@endif
