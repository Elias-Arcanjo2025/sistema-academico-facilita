@props([
    'titulo' => 'Filtros',
    'formAction',
    'formMethod' => 'GET',
    'formId' => null,
    'limparUrl',
    'botaoTexto' => 'Visualizar',
    'resumo' => null,
    'instrucao' => 'Seleccione os filtros acima para continuar.',
])

<div {{ $attributes->merge(['class' => 'card shadow-sm mb-4 filtros-pauta-card']) }}>
    <div class="card-header filtros-pauta-card-header py-3">
        <h5 class="mb-0">
            <i class="bi bi-funnel text-primary me-2"></i>{{ $titulo }}
        </h5>
    </div>
    <div class="card-body">
        <form action="{{ $formAction }}" method="{{ $formMethod }}" @if($formId) id="{{ $formId }}" @endif>
            <div class="row g-3 align-items-end">
                {{ $slot }}
            </div>

            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mt-3 pt-3 border-top">
                <p class="text-muted small mb-0">
                    @if($resumo)
                        <i class="bi bi-check-circle-fill text-success me-1"></i>{!! $resumo !!}
                    @else
                        <i class="bi bi-info-circle me-1"></i>{{ $instrucao }}
                    @endif
                </p>
                <div class="d-flex flex-shrink-0 gap-2">
                    <a href="{{ $limparUrl }}" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-x-lg me-1"></i>Limpar
                    </a>
                    <button type="submit" class="btn btn-primary btn-sm px-3">
                        <i class="bi bi-search me-1"></i>{{ $botaoTexto }}
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
