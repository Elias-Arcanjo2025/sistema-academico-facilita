<x-guest-layout>
    <div class="alert alert-warning d-flex align-items-start mb-4" role="alert">
        <i class="bi bi-shield-lock-fill me-2 mt-1"></i>
        <div>
            <strong>Alteração obrigatória de palavra-passe</strong>
            <p class="mb-0 small">Por segurança, deve definir uma nova palavra-passe antes de usar o sistema. Não utilize o número de processo como palavra-passe.</p>
        </div>
    </div>

    <h5 class="text-center mb-4 fw-semibold text-dark">
        Definir nova palavra-passe
    </h5>

    <form method="POST" action="{{ route('password.force.update') }}">
        @csrf
        @method('PUT')

        <div class="mb-3">
            <label for="password" class="form-label">
                <i class="bi bi-lock me-1"></i>Nova palavra-passe
            </label>
            <input type="password"
                   class="form-control @error('password') is-invalid @enderror"
                   id="password"
                   name="password"
                   required
                   autofocus
                   autocomplete="new-password">
            @error('password')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-4">
            <label for="password_confirmation" class="form-label">
                <i class="bi bi-lock-fill me-1"></i>Confirmar palavra-passe
            </label>
            <input type="password"
                   class="form-control"
                   id="password_confirmation"
                   name="password_confirmation"
                   required
                   autocomplete="new-password">
        </div>

        <button type="submit" class="btn btn-primary w-100">
            <i class="bi bi-check-lg me-2"></i>Guardar e continuar
        </button>
    </form>

    <form method="POST" action="{{ route('logout') }}" class="text-center mt-3">
        @csrf
        <button type="submit" class="btn btn-link btn-sm text-muted">Terminar sessão</button>
    </form>
</x-guest-layout>
