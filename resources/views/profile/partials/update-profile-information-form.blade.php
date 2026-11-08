<section>
    <header class="mb-4">
        <p class="text-muted mb-0">
            <i class="bi bi-info-circle me-1"></i>
            Actualize as informações do seu perfil e endereço de email.
        </p>
    </header>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}">
        @csrf
        @method('patch')

        <div class="row g-3">
            <!-- Nome -->
            <div class="col-md-6">
                <label for="name" class="form-label fw-bold">Nome Completo <span class="text-danger">*</span></label>
                <div class="input-group">
                    <span class="input-group-text bg-light">
                        <i class="bi bi-person-fill text-primary"></i>
                    </span>
                    <input type="text"
                           class="form-control @error('name') is-invalid @enderror"
                           id="name"
                           name="name"
                           value="{{ old('name', $user->name) }}"
                           required
                           autofocus
                           autocomplete="name">
                    @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <!-- Email -->
            <div class="col-md-6">
                <label for="email" class="form-label fw-bold">Email <span class="text-danger">*</span></label>
                <div class="input-group">
                    <span class="input-group-text bg-light">
                        <i class="bi bi-envelope-fill text-primary"></i>
                    </span>
                    <input type="email"
                           class="form-control @error('email') is-invalid @enderror"
                           id="email"
                           name="email"
                           value="{{ old('email', $user->email) }}"
                           required
                           autocomplete="username">
                    @error('email')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                    <div class="alert alert-warning mt-2 py-2">
                        <i class="bi bi-exclamation-circle me-1"></i>
                        <small>
                            O seu endereço de email não está verificado.
                            <button form="send-verification" class="btn btn-link btn-sm p-0 align-baseline">
                                Clique aqui para reenviar o email de verificação.
                            </button>
                        </small>
                    </div>

                    @if (session('status') === 'verification-link-sent')
                        <div class="alert alert-success mt-2 py-2">
                            <i class="bi bi-check-circle me-1"></i>
                            <small>Um novo link de verificação foi enviado para o seu email.</small>
                        </div>
                    @endif
                @elseif ($user->hasVerifiedEmail())
                    <div class="form-text text-success">
                        <i class="bi bi-check-circle-fill me-1"></i>Email verificado
                    </div>
                @endif
            </div>

            <!-- Informações Adicionais (Read-Only) -->
            <div class="col-12">
                <hr class="my-4">
                <h6 class="text-muted mb-3">
                    <i class="bi bi-info-square me-2"></i>Informações da Conta
                </h6>
            </div>

            @if($user->numero_bi)
            <div class="col-md-4">
                <label class="form-label text-muted small">Número de BI</label>
                <div class="form-control-plaintext fw-bold">
                    {{ $user->numero_bi }}
                </div>
            </div>
            @endif

            @if($user->numero_telefone)
            <div class="col-md-4">
                <label class="form-label text-muted small">Telefone</label>
                <div class="form-control-plaintext fw-bold">
                    <i class="bi bi-telephone me-1"></i>{{ $user->numero_telefone }}
                </div>
            </div>
            @endif

            <div class="col-md-4">
                <label class="form-label text-muted small">Estado da Conta</label>
                <div class="form-control-plaintext">
                    @if($user->estado === 'activo')
                        <span class="badge bg-success">
                            <i class="bi bi-check-circle me-1"></i>Activa
                        </span>
                    @elseif($user->estado === 'pendente')
                        <span class="badge bg-warning">
                            <i class="bi bi-clock me-1"></i>Pendente
                        </span>
                    @else
                        <span class="badge bg-danger">
                            <i class="bi bi-x-circle me-1"></i>{{ ucfirst($user->estado) }}
                        </span>
                    @endif
                </div>
            </div>

            @if($user->roles->isNotEmpty())
            <div class="col-12">
                <label class="form-label text-muted small">Funções/Cargos</label>
                <div class="form-control-plaintext">
                    @foreach($user->roles as $role)
                        <span class="badge bg-primary me-1">
                            <i class="bi bi-award me-1"></i>
                            {{ ucwords(str_replace('_', ' ', $role->name)) }}
                        </span>
                    @endforeach
                </div>
            </div>
            @endif

            <div class="col-md-6">
                <label class="form-label text-muted small">Data de Registo</label>
                <div class="form-control-plaintext">
                    <i class="bi bi-calendar-check me-1"></i>
                    {{ $user->created_at->format('d/m/Y H:i') }}
                </div>
            </div>

            <div class="col-md-6">
                <label class="form-label text-muted small">Última Actualização</label>
                <div class="form-control-plaintext">
                    <i class="bi bi-calendar-event me-1"></i>
                    {{ $user->updated_at->format('d/m/Y H:i') }}
                </div>
            </div>
        </div>

        <div class="d-flex align-items-center gap-3 mt-4">
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-check-circle me-1"></i>Guardar Alterações
            </button>

            @if (session('status') === 'profile-updated')
                <div class="alert alert-success mb-0 py-2 px-3" role="alert">
                    <i class="bi bi-check-circle-fill me-1"></i>
                    <small>Perfil actualizado com sucesso!</small>
                </div>
            @endif
        </div>
    </form>
</section>

<style>
    .form-control-plaintext {
        padding-top: 0.375rem;
        padding-bottom: 0.375rem;
        margin-bottom: 0;
        font-size: 0.95rem;
    }
</style>
