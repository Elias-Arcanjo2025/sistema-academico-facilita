<x-guest-layout>
    <h5 class="text-center mb-3 fw-semibold" style="color: var(--text-dark);">
        Recuperar palavra-passe
    </h5>

    <p class="text-center mb-4" style="color: var(--text-muted); font-size: 0.9rem;">
        Esqueceu a sua palavra-passe? Sem problema. Indique o seu e-mail
        e enviaremos um link para criar uma nova.
    </p>

    <!-- Session Status -->
    @if (session('status'))
        <div class="alert alert-success d-flex align-items-center" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>
            {{ session('status') }}
        </div>
    @endif

    <form method="POST" action="{{ route('password.email') }}">
        @csrf

        <!-- Email Address -->
        <div class="mb-4">
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
                   autofocus>
            @error('email')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <!-- Submit Button -->
        <button type="submit" class="btn btn-primary w-100 mb-3">
            <i class="bi bi-send me-2"></i>Enviar link de recuperação
        </button>

        <!-- Back to Login -->
        <div class="text-center">
            <a href="{{ route('login') }}" class="auth-link">
                <i class="bi bi-arrow-left me-1"></i>Voltar ao login
            </a>
        </div>
    </form>
</x-guest-layout>
