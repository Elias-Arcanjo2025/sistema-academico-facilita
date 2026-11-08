<x-guest-layout>
    <div class="text-center mb-4">
        <div class="d-inline-flex align-items-center justify-content-center rounded-circle mb-3"
             style="width:56px;height:56px;background:rgba(245,158,11,.15);color:#d97706;">
            <i class="bi bi-hourglass-split fs-4"></i>
        </div>
        <h5 class="fw-semibold text-dark mb-1">Aguardar configuração</h5>
        <p class="text-muted small mb-0">
            O administrador ainda não concluiu a configuração inicial do Facilita
            (perfis de ensino). Volte a tentar mais tarde.
        </p>
    </div>

    <div class="alert alert-warning py-2 px-3 small mb-3">
        <i class="bi bi-shield-lock me-1"></i>
        Só o <strong>administrador</strong> pode concluir este passo.
    </div>

    <a href="{{ route('setup.aguardar') }}" class="btn btn-outline-primary w-100 mb-2">
        <i class="bi bi-arrow-clockwise me-1"></i>Verificar novamente
    </a>

    <form method="POST" action="{{ route('logout') }}" class="text-center">
        @csrf
        <button type="submit" class="btn btn-link btn-sm text-muted">Terminar sessão</button>
    </form>
</x-guest-layout>
