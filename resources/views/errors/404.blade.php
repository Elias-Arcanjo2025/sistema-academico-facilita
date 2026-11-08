<x-guest-layout>
    <div class="text-center py-4">
        <div class="mb-3">
            <i class="bi bi-compass display-4 text-primary"></i>
        </div>
        <h1 class="h4 mb-2">Página não encontrada</h1>
        <p class="text-muted mb-3">
            O endereço que pediu não existe ou foi movido. Verifique o link ou volte ao início.
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
                <a href="{{ url('/') }}" class="btn btn-outline-secondary">Página pública</a>
                <a href="{{ route('login') }}" class="btn btn-primary">Iniciar sessão</a>
            @endauth
        </div>
    </div>
</x-guest-layout>
