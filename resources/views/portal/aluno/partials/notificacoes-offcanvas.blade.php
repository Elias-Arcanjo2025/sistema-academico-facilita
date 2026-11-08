<div class="offcanvas offcanvas-end portal-notificacoes-offcanvas" tabindex="-1" id="notificacoesPortal" aria-labelledby="notificacoesPortalLabel">
    <div class="offcanvas-header border-bottom">
        <h5 class="offcanvas-title" id="notificacoesPortalLabel">
            Notificações
            @if(($notificacoesNaoLidas ?? 0) > 0)
                <span class="badge bg-danger ms-1">{{ $notificacoesNaoLidas > 99 ? '99+' : $notificacoesNaoLidas }}</span>
            @endif
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Fechar"></button>
    </div>
    <div class="offcanvas-body p-0 d-flex flex-column">
        @if(($notificacoesNaoLidas ?? 0) > 0)
            <div class="px-3 py-2 border-bottom bg-body-tertiary">
                <form method="POST" action="{{ route('portal.aluno.notificacoes.marcar-todas-lidas') }}">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-link text-decoration-none p-0">
                        Marcar todas como lidas
                    </button>
                </form>
            </div>
        @endif

        <div class="flex-grow-1 overflow-auto">
            @forelse($notificacoesRecentes ?? [] as $notificacao)
                @include('portal.aluno.partials.notificacao-item', ['notificacao' => $notificacao, 'compact' => true])
            @empty
                <div class="text-center text-muted small py-5 px-3">
                    <i class="bi bi-bell-slash fs-2 d-block mb-2 opacity-50"></i>
                    Sem notificações por agora.
                </div>
            @endforelse
        </div>

        <div class="border-top p-3 mt-auto">
            {{-- Sem data-bs-dismiss: Bootstrap faz preventDefault em <a> e bloqueia a navegação. --}}
            <a href="{{ route('portal.aluno.notificacoes') }}" class="btn btn-sm btn-outline-primary w-100">
                Ver todas
            </a>
        </div>
    </div>
</div>
