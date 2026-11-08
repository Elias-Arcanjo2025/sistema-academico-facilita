<nav class="pp-config-nav">
    <a href="{{ route('configuracoes.index') }}" class="pp-config-nav__back text-muted small d-inline-flex align-items-center mb-3 text-decoration-none">
        <i class="bi bi-arrow-left me-1"></i> Configurações gerais
    </a>
    <div class="list-group pp-config-nav__list shadow-sm">
        <a href="{{ route('configuracoes.pagina-publica.identidade') }}"
           class="list-group-item list-group-item-action {{ request()->routeIs('configuracoes.pagina-publica.identidade') ? 'active' : '' }}">
            <i class="bi bi-palette me-2"></i>Identidade visual
        </a>
        <a href="{{ route('configuracoes.pagina-publica.textos') }}"
           class="list-group-item list-group-item-action {{ request()->routeIs('configuracoes.pagina-publica.textos') ? 'active' : '' }}">
            <i class="bi bi-text-paragraph me-2"></i>Textos e conteúdo
        </a>
        <a href="{{ route('configuracoes.pagina-publica.carousel') }}"
           class="list-group-item list-group-item-action {{ request()->routeIs('configuracoes.pagina-publica.carousel') ? 'active' : '' }}">
            <i class="bi bi-images me-2"></i>Carousel (hero)
        </a>
        <a href="{{ route('configuracoes.pagina-publica.opcoes') }}"
           class="list-group-item list-group-item-action {{ request()->routeIs('configuracoes.pagina-publica.opcoes') ? 'active' : '' }}">
            <i class="bi bi-toggles me-2"></i>Opções de apresentação
        </a>
    </div>
</nav>

@once
@push('styles')
<style>
.pp-config-nav__list { border-radius: 12px; overflow: hidden; border: 1px solid var(--border-color, #e2e8f0); }
.pp-config-nav__list .list-group-item { border: none; border-bottom: 1px solid var(--border-color, #e2e8f0); font-size: .92rem; padding: .85rem 1rem; }
.pp-config-nav__list .list-group-item:last-child { border-bottom: none; }
.pp-config-nav__list .list-group-item.active { background: var(--primary-soft-bg, rgba(79,70,229,.08)); color: var(--primary-color); font-weight: 600; border-left: 3px solid var(--primary-color); }
@media (max-width: 991.98px) {
    .pp-config-nav__list { display: flex; flex-direction: row; overflow-x: auto; flex-wrap: nowrap; }
    .pp-config-nav__list .list-group-item { white-space: nowrap; flex: 0 0 auto; border-bottom: none; border-right: 1px solid var(--border-color, #e2e8f0); }
    .pp-config-nav__back { display: none !important; }
}
</style>
@endpush
@endonce
