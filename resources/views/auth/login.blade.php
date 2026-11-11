<x-guest-layout>
    <!-- Session Status -->
    @if (session('status'))
        <div class="alert alert-success d-flex align-items-center" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>
            {{ session('status') }}
        </div>
    @endif

    <h5 class="text-center mb-4 fw-semibold text-dark">
        Entrar na sua conta
    </h5>

    <div class="alert alert-info py-2 px-3 small mb-3" role="note">
        <i class="bi bi-info-circle me-1"></i>
        <strong>Alunos:</strong> use o <strong>número de processo</strong> como utilizador e a palavra-passe temporária fornecida pela escola. No primeiro acesso, deverá definir uma nova palavra-passe.
    </div>

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <!-- Email / Processo -->
        <div class="mb-3">
            <label for="email" class="form-label">
                <i class="bi bi-person-badge me-1"></i>E-mail ou N.º de processo
            </label>
            <input type="text"
                   class="form-control @error('email') is-invalid @enderror"
                   id="email"
                   name="email"
                   value="{{ old('email') }}"
                   placeholder="seu@email.com ou 2024001"
                   required
                   autofocus
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
                       placeholder="A sua palavra-passe"
                       required
                       autocomplete="current-password">
                <button type="button" class="toggle-btn" tabindex="-1">
                    <i class="bi bi-eye"></i>
                </button>
            </div>
            @error('password')
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
        </div>

        <!-- Remember Me -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="remember_me" name="remember">
                <label class="form-check-label" for="remember_me">
                    Lembrar-me
                </label>
            </div>
            @if (Route::has('password.request'))
                <a href="{{ route('password.request') }}" class="auth-link small">
                    Esqueceu a palavra-passe?
                </a>
            @endif
        </div>

        <!-- Submit Button -->
        <button type="submit" class="btn btn-primary w-100 mb-3" id="loginBtn">
            <span class="btn-text"><i class="bi bi-box-arrow-in-right me-2"></i>Entrar</span>
            <span class="btn-loader d-none">
                <span class="spinner-border spinner-border-sm me-2" role="status"></span>A entrar...
            </span>
        </button>

        <!-- Register Link -->
        @if (Route::has('register'))
            <div class="auth-divider">
                <span>ou</span>
            </div>
            <div class="text-center">
                <p class="mb-0 text-muted">
                    Ainda não tem conta?
                    <a href="{{ route('register') }}" class="auth-link fw-semibold">Criar conta</a>
                </p>
            </div>
        @endif
    </form>

    @push('scripts')
    <script>
        document.querySelector('form').addEventListener('submit', function() {
            const btn = document.getElementById('loginBtn');
            btn.querySelector('.btn-text').classList.add('d-none');
            btn.querySelector('.btn-loader').classList.remove('d-none');
            btn.disabled = true;
        });
    </script>
    @endpush
</x-guest-layout>
