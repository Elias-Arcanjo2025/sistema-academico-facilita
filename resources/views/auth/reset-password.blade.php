<x-guest-layout>
    <h5 class="text-center mb-4 fw-semibold" style="color: var(--text-dark);">
        Redefinir palavra-passe
    </h5>

    <form method="POST" action="{{ route('password.store') }}">
        @csrf

        <!-- Password Reset Token -->
        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <!-- Email Address -->
        <div class="mb-3">
            <label for="email" class="form-label">
                <i class="bi bi-envelope me-1"></i>E-mail
            </label>
            <input type="email"
                   class="form-control @error('email') is-invalid @enderror"
                   id="email"
                   name="email"
                   value="{{ old('email', $request->email) }}"
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
                <i class="bi bi-lock me-1"></i>Nova palavra-passe
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
        <button type="submit" class="btn btn-primary w-100">
            <i class="bi bi-check-lg me-2"></i>Redefinir palavra-passe
        </button>
    </form>
</x-guest-layout>
