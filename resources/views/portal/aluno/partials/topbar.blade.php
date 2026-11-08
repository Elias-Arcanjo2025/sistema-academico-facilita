<header class="portal-topbar px-3">
    <div class="d-flex align-items-center justify-content-between py-2">
        <div class="d-flex align-items-center gap-2 min-w-0">
            <img src="{{ asset('images/facilitaicon.png') }}" alt="" width="28" height="28" class="flex-shrink-0">
            <div class="min-w-0">
                <div class="fw-semibold small text-truncate">Portal do Aluno</div>
                @auth
                <div class="text-muted text-truncate" style="font-size:.72rem">{{ auth()->user()->name }}</div>
                @endauth
            </div>
        </div>
        @auth
        <div class="d-flex align-items-center gap-1">
            <button type="button"
                    class="btn btn-sm btn-outline-secondary position-relative"
                    data-bs-toggle="offcanvas"
                    data-bs-target="#notificacoesPortal"
                    title="Notificações"
                    aria-label="Notificações{{ ($notificacoesNaoLidas ?? 0) > 0 ? ' ('.$notificacoesNaoLidas.' por ler)' : '' }}">
                <i class="bi bi-bell"></i>
                @if(($notificacoesNaoLidas ?? 0) > 0)
                    <span class="portal-notificacao-count">{{ ($notificacoesNaoLidas ?? 0) > 9 ? '9+' : $notificacoesNaoLidas }}</span>
                @endif
            </button>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="themeTogglePortal" title="Alternar modo escuro/claro">
                <i class="bi bi-moon-fill" id="themeIconPortal"></i>
            </button>
            <a href="{{ route('regras-avaliacao') }}" class="btn btn-sm btn-outline-secondary" title="Regras de avaliação">
                <i class="bi bi-journal-bookmark"></i>
            </a>
            <a href="{{ route('portal.aluno.perfil') }}" class="btn btn-sm btn-outline-secondary" title="Meu perfil">
                <i class="bi bi-person"></i>
            </a>
            <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="offcanvas" data-bs-target="#glossarioNotas" title="Glossário de notas">
                <i class="bi bi-question-circle"></i>
            </button>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="btn btn-sm btn-outline-secondary" title="Sair">
                    <i class="bi bi-box-arrow-right"></i>
                </button>
            </form>
        </div>
        @endauth
    </div>
</header>

@auth
@include('portal.aluno.partials.notificacoes-offcanvas')
@include('portal.aluno.partials.glossario')
@endauth
