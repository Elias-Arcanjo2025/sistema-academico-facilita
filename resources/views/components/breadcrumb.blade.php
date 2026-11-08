@props(['items' => []])

@if(count($items) > 0)
<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb bg-white rounded shadow-sm py-2 px-3 mb-0">
        <li class="breadcrumb-item">
            <a href="{{ route('dashboard') }}" class="text-decoration-none">
                <i class="bi bi-house-door me-1"></i>
                Início
            </a>
        </li>

        @foreach($items as $item)
            @if($loop->last)
                <li class="breadcrumb-item active" aria-current="page">
                    {{ $item['label'] }}
                </li>
            @else
                <li class="breadcrumb-item">
                    <a href="{{ $item['url'] }}" class="text-decoration-none">
                        {{ $item['label'] }}
                    </a>
                </li>
            @endif
        @endforeach
    </ol>
</nav>
@endif
