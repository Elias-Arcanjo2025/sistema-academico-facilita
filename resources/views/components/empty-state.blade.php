@props([
    'icon' => 'bi-inbox',
    'message' => 'Nenhum registo encontrado.',
    'createUrl' => null,
    'createLabel' => 'Criar primeiro registo',
    'canCreate' => true
])

<div class="text-center py-5">
    <div class="text-muted">
        <i class="{{ $icon }} display-4 d-block mb-3 empty-state-icon"></i>
        <p class="mb-0">{{ $message }}</p>

        @if($createUrl && $canCreate)
            <a href="{{ $createUrl }}" class="btn btn-primary mt-3">
                <i class="bi bi-plus-lg me-1"></i>{{ $createLabel }}
            </a>
        @endif

        {{ $slot }}
    </div>
</div>
