<x-app-layout>
    <x-slot name="header">Página Pública</x-slot>

    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <p class="text-muted mb-0">
            <i class="bi bi-globe2 me-1"></i>Personalize a página inicial e a identidade visual da instituição
        </p>
        <a href="{{ route('home') }}" target="_blank" rel="noopener" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-box-arrow-up-right me-1"></i>Ver página pública
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row g-4">
        <div class="col-lg-3">
            @include('configuracoes.pagina-publica.partials.nav-sidebar')
        </div>
        <div class="col-lg-9">
            @yield('pagina-publica-content')
        </div>
    </div>

    @stack('styles')
    @stack('scripts')
</x-app-layout>
