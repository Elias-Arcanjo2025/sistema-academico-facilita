<x-guest-layout>
    <div class="text-center py-4">
        <div class="mb-3">
            <i class="bi bi-hourglass-split display-4 text-warning"></i>
        </div>
        <h1 class="h4 mb-2">Sessão expirada</h1>
        <p class="text-muted mb-3">
            A página expirou por segurança (token CSRF). Actualize e tente de novo.
        </p>
        <div class="d-flex flex-wrap gap-2 justify-content-center">
            <button type="button" class="btn btn-outline-secondary" onclick="window.location.reload()">Actualizar página</button>
            <a href="{{ route('login') }}" class="btn btn-primary">Iniciar sessão</a>
        </div>
    </div>
</x-guest-layout>
