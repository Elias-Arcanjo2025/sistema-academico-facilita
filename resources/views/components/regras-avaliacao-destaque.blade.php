@props(['variant' => 'app'])

@once
@push('styles')
<style>
    .regras-avaliacao-destaque .regras-avaliacao-destaque__card,
    .regras-avaliacao-destaque .portal-card {
        transition: box-shadow .2s ease, transform .2s ease;
    }
    .regras-avaliacao-destaque:hover .regras-avaliacao-destaque__card,
    .regras-avaliacao-destaque:hover .portal-card {
        box-shadow: 0 8px 24px rgba(79, 70, 229, 0.12);
        transform: translateY(-1px);
    }
</style>
@endpush
@endonce

@if($variant === 'portal')
<div class="col-12">
    <a href="{{ route('regras-avaliacao') }}" class="text-decoration-none d-block">
        <div class="card border-0 shadow-sm portal-card overflow-hidden regras-avaliacao-destaque">
            <div class="card-body p-3 p-md-4 d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between gap-3">
                <div class="d-flex gap-3 align-items-start">
                    <div class="rounded-circle bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center flex-shrink-0"
                         style="width: 48px; height: 48px;">
                        <i class="bi bi-journal-bookmark fs-5" aria-hidden="true"></i>
                    </div>
                    <div>
                        <h3 class="h6 mb-1 text-body">Regras de avaliação e aprovação</h3>
                        <p class="text-muted small mb-0">
                            Fórmulas das médias, critérios de transição, recuperação e recurso — tudo explicado no sistema.
                        </p>
                    </div>
                </div>
                <span class="btn btn-primary btn-sm flex-shrink-0">
                    <i class="bi bi-book me-1" aria-hidden="true"></i> Consultar regras
                </span>
            </div>
        </div>
    </a>
</div>
@else
<a href="{{ route('regras-avaliacao') }}" class="text-decoration-none d-block regras-avaliacao-destaque mb-4">
    <div class="content-card mb-0 regras-avaliacao-destaque__card">
        <div class="content-card-body d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between gap-3 py-3">
            <div class="d-flex gap-3 align-items-start">
                <div class="stat-icon stat-icon-primary flex-shrink-0" style="width: 48px; height: 48px; font-size: 1.25rem;">
                    <i class="bi bi-journal-bookmark" aria-hidden="true"></i>
                </div>
                <div>
                    <h3 class="h6 mb-1 text-body fw-semibold">Regras de avaliação e aprovação</h3>
                    <p class="text-muted small mb-0">
                        Consulte as fórmulas das médias, critérios de aprovação/reprovação, transição de classe,
                        recuperação e recurso — conforme o DE 424/25.
                    </p>
                </div>
            </div>
            <span class="btn btn-modern btn-primary-modern btn-sm flex-shrink-0">
                <i class="bi bi-arrow-right me-1" aria-hidden="true"></i> Ver regras completas
            </span>
        </div>
    </div>
</a>
@endif
