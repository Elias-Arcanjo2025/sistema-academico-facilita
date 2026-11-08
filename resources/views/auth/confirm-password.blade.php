<x-guest-layout>
    <h5 class="text-center mb-3 fw-semibold" style="color: var(--text-dark);">
        Confirmar palavra-passe
    </h5>

    <div class="text-center mb-4">
        <div class="d-inline-flex align-items-center justify-content-center mb-3"
             style="width: 80px; height: 80px; background: linear-gradient(135deg, rgba(245, 158, 11, 0.1), rgba(251, 191, 36, 0.1)); border-radius: 50%;">
            <i class="bi bi-shield-lock" style="font-size: 2rem; color: #f59e0b;"></i>
        </div>
        <p style="color: var(--text-muted); font-size: 0.9rem;">
            Esta é uma área segura da aplicação. Por favor, confirme
            a sua palavra-passe antes de continuar.
        </p>
    </div>

    <form method="POST" action="{{ route('password.confirm') }}">
        @csrf

        <!-- Password -->
        <div class="mb-4">
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

        <!-- Submit Button -->
        <button type="submit" class="btn btn-primary w-100">
            <i class="bi bi-check-lg me-2"></i>Confirmar
        </button>
    </form>
</x-guest-layout>
