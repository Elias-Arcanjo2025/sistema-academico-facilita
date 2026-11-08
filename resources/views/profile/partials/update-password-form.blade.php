<section>
    <header class="mb-4">
        <p class="text-muted mb-0">
            <i class="bi bi-shield-check me-1"></i>
            Certifique-se de que a sua conta está a usar uma password longa e aleatória para permanecer segura.
        </p>
    </header>

    <form method="post" action="{{ route('password.update') }}">
        @csrf
        @method('put')

        <div class="row g-3">
            <!-- Password Actual -->
            <div class="col-md-12">
                <label for="update_password_current_password" class="form-label fw-bold">
                    Password Actual <span class="text-danger">*</span>
                </label>
                <div class="input-group">
                    <span class="input-group-text bg-light">
                        <i class="bi bi-lock-fill text-warning"></i>
                    </span>
                    <input type="password"
                           class="form-control @error('current_password', 'updatePassword') is-invalid @enderror"
                           id="update_password_current_password"
                           name="current_password"
                           autocomplete="current-password"
                           placeholder="Introduza a sua password actual"
                           required>
                    @error('current_password', 'updatePassword')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <!-- Nova Password -->
            <div class="col-md-6">
                <label for="update_password_password" class="form-label fw-bold">
                    Nova Password <span class="text-danger">*</span>
                </label>
                <div class="input-group">
                    <span class="input-group-text bg-light">
                        <i class="bi bi-key-fill text-success"></i>
                    </span>
                    <input type="password"
                           class="form-control @error('password', 'updatePassword') is-invalid @enderror"
                           id="update_password_password"
                           name="password"
                           autocomplete="new-password"
                           placeholder="Mínimo 8 caracteres"
                           required>
                    @error('password', 'updatePassword')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="form-text">
                    <i class="bi bi-info-circle me-1"></i>
                    Deve ter pelo menos 8 caracteres
                </div>
            </div>

            <!-- Confirmar Password -->
            <div class="col-md-6">
                <label for="update_password_password_confirmation" class="form-label fw-bold">
                    Confirmar Nova Password <span class="text-danger">*</span>
                </label>
                <div class="input-group">
                    <span class="input-group-text bg-light">
                        <i class="bi bi-key-fill text-success"></i>
                    </span>
                    <input type="password"
                           class="form-control @error('password_confirmation', 'updatePassword') is-invalid @enderror"
                           id="update_password_password_confirmation"
                           name="password_confirmation"
                           autocomplete="new-password"
                           placeholder="Repita a nova password"
                           required>
                    @error('password_confirmation', 'updatePassword')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>

        <!-- Dicas de Segurança -->
        <div class="alert alert-info mt-3">
            <h6 class="alert-heading">
                <i class="bi bi-lightbulb me-2"></i>Dicas para uma Password Segura:
            </h6>
            <ul class="mb-0 small">
                <li>Use pelo menos 8 caracteres (quanto mais longa, melhor)</li>
                <li>Combine letras maiúsculas e minúsculas</li>
                <li>Inclua números e caracteres especiais (@, #, $, etc.)</li>
                <li>Evite palavras comuns ou dados pessoais óbvios</li>
                <li>Não reutilize passwords de outras contas</li>
            </ul>
        </div>

        <div class="d-flex align-items-center gap-3 mt-4">
            <button type="submit" class="btn btn-warning">
                <i class="bi bi-shield-check me-1"></i>Actualizar Password
            </button>

            @if (session('status') === 'password-updated')
                <div class="alert alert-success mb-0 py-2 px-3" role="alert">
                    <i class="bi bi-check-circle-fill me-1"></i>
                    <small>Password actualizada com sucesso!</small>
                </div>
            @endif
        </div>
    </form>
</section>
