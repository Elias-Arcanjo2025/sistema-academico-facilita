<div class="offcanvas offcanvas-end" tabindex="-1" id="notificacoesStaff" aria-labelledby="notificacoesStaffLabel">
    <div class="offcanvas-header border-bottom">
        <h5 class="offcanvas-title" id="notificacoesStaffLabel">
            <i class="bi bi-bell me-1"></i> Notificações
            @if(($notificacoesNaoLidas ?? 0) > 0)
                <span class="badge bg-danger ms-1">{{ $notificacoesNaoLidas > 99 ? '99+' : $notificacoesNaoLidas }}</span>
            @endif
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Fechar"></button>
    </div>
    <div class="offcanvas-body p-0 d-flex flex-column">
        @if(($notificacoesNaoLidas ?? 0) > 0)
            <div class="p-2 border-bottom">
                <form method="POST" action="{{ route('notificacoes.marcar-todas-lidas') }}">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-outline-secondary w-100">Marcar todas como lidas</button>
                </form>
            </div>
        @endif
        <div class="list-group list-group-flush flex-grow-1 overflow-auto">
            @forelse($notificacoesRecentes ?? [] as $notificacao)
                @include('notificacoes.partials.item', ['notificacao' => $notificacao, 'compact' => true])
            @empty
                <div class="p-4 text-center text-muted small">Sem notificações.</div>
            @endforelse
        </div>
        <div class="p-2 border-top">
            {{-- Sem data-bs-dismiss: Bootstrap faz preventDefault em <a> e bloqueia a navegação. --}}
            <a href="{{ route('notificacoes.index') }}" class="btn btn-sm btn-outline-primary w-100">
                Ver todas
            </a>
        </div>
    </div>
</div>
