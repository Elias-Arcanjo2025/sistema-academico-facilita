<x-guest-layout>
    <h5 class="text-center mb-4 fw-semibold" style="color: var(--text-dark);">
        Criar nova conta
    </h5>

    <form method="POST" action="{{ route('register') }}">
        @csrf

        <!-- Name -->
        <div class="mb-3">
            <label for="name" class="form-label">
                <i class="bi bi-person me-1"></i>Nome completo
            </label>
            <input type="text"
                   class="form-control @error('name') is-invalid @enderror"
                   id="name"
                   name="name"
                   value="{{ old('name') }}"
                   placeholder="O seu nome completo"
                   required
                   autofocus
                   autocomplete="name">
            @error('name')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <!-- Email Address -->
        <div class="mb-3">
            <label for="email" class="form-label">
                <i class="bi bi-envelope me-1"></i>E-mail
            </label>
            <input type="email"
                   class="form-control @error('email') is-invalid @enderror"
                   id="email"
                   name="email"
                   value="{{ old('email') }}"
                   placeholder="seu@email.com"
                   required
                   autocomplete="username">
            @error('email')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <!-- Password -->
        <div class="mb-3">
            <label for="password" class="form-label">
                <i class="bi bi-lock me-1"></i>Palavra-passe
            </label>
            <div class="password-toggle">
                <input type="password"
                       class="form-control @error('password') is-invalid @enderror"
                       id="password"
                       name="password"
                       placeholder="Mínimo 8 caracteres"
                       required
                       autocomplete="new-password">
                <button type="button" class="toggle-btn" tabindex="-1">
                    <i class="bi bi-eye"></i>
                </button>
            </div>
            @error('password')
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
        </div>

        <!-- Confirm Password -->
        <div class="mb-4">
            <label for="password_confirmation" class="form-label">
                <i class="bi bi-lock-fill me-1"></i>Confirmar palavra-passe
            </label>
            <div class="password-toggle">
                <input type="password"
                       class="form-control @error('password_confirmation') is-invalid @enderror"
                       id="password_confirmation"
                       name="password_confirmation"
                       placeholder="Repita a palavra-passe"
                       required
                       autocomplete="new-password">
                <button type="button" class="toggle-btn" tabindex="-1">
                    <i class="bi bi-eye"></i>
                </button>
            </div>
            @error('password_confirmation')
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
        </div>

        <!-- Submit Button -->
        <button type="submit" class="btn btn-primary w-100 mb-3">
            <i class="bi bi-person-plus me-2"></i>Criar conta
        </button>

        <!-- Login Link -->
        <div class="auth-divider">
            <span>ou</span>
        </div>
        <div class="text-center">
            <p class="mb-0" style="color: var(--text-muted);">
                Já tem uma conta?
                <a href="{{ route('login') }}" class="auth-link">Entrar</a>
            </p>
        </div>
    </form>
</x-guest-layout>
