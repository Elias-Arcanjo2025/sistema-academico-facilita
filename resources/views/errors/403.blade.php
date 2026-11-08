<x-guest-layout>
    <div class="text-center py-4">
        <div class="mb-3">
            <i class="bi bi-shield-lock display-4 text-warning"></i>
        </div>
        <h1 class="h4 mb-2">Acesso não permitido</h1>
        <p class="text-muted mb-3">
            {{ $exception->getMessage() ?: 'Não tem permissão para esta área. O menu mostra apenas o que o seu cargo permite.' }}
        </p>
        <div class="d-flex flex-wrap gap-2 justify-content-center">
            @auth
                @if(auth()->user()->hasRole('aluno'))
                    <a href="{{ route('portal.aluno.dashboard') }}" class="btn btn-primary">Voltar ao portal</a>
                @elseif(auth()->user()->hasRole('encarregado'))
                    <a href="{{ route('portal.encarregado.dashboard') }}" class="btn btn-primary">Voltar ao portal</a>
                @else
                    <a href="{{ route('dashboard') }}" class="btn btn-primary">Ir para o painel</a>
                @endif
            @else
                <a href="{{ route('login') }}" class="btn btn-primary">Iniciar sessão</a>
            @endauth
        </div>
        <p class="small text-muted mt-4 mb-0">
            Se acredita que deveria ter acesso, contacte a direcção ou o administrador do sistema.
        </p>
    </div>
</x-guest-layout>
