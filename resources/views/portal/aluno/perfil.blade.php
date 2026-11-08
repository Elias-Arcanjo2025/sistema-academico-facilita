@extends('layouts.portal-aluno')

@section('title', 'Meu Perfil')

@section('content')
<div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h5 mb-0">Meu perfil</h1>
    <a href="{{ route('portal.aluno.dashboard') }}" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i> Voltar
    </a>
</div>

<div class="card border-0 shadow-sm portal-card mb-3">
    <div class="card-body p-3 p-md-4">
        <h2 class="h6 text-muted mb-3">Dados da conta</h2>
        <dl class="row mb-0 small">
            <dt class="col-sm-4">Nome</dt>
            <dd class="col-sm-8 fw-semibold">{{ $aluno->nome }}</dd>

            <dt class="col-sm-4">N.º de processo</dt>
            <dd class="col-sm-8">
                <span class="fw-semibold">{{ $aluno->numero_processo }}</span>
                <div class="text-muted mt-1">Utiliza este número para iniciar sessão no portal.</div>
            </dd>

            <dt class="col-sm-4">Turma</dt>
            <dd class="col-sm-8">{{ $aluno->turma?->codigo ?? '—' }} · {{ $aluno->turma?->curso?->nome ?? '' }}</dd>

            <dt class="col-sm-4">Estado</dt>
            <dd class="col-sm-8">
                @if($user->estado === 'activo')
                    <span class="badge bg-success">Activa</span>
                @else
                    <span class="badge bg-secondary">{{ ucfirst($user->estado) }}</span>
                @endif
            </dd>

            <dt class="col-sm-4">Último acesso</dt>
            <dd class="col-sm-8">{{ $user->last_login_at?->format('d/m/Y H:i') ?? '—' }}</dd>
        </dl>
    </div>
</div>

<div class="card border-0 shadow-sm portal-card mb-3">
    <div class="card-header bg-transparent border-0 pb-0">
        <h2 class="h6 mb-0"><i class="bi bi-journal-text me-1"></i> Regras de avaliação</h2>
    </div>
    <div class="card-body p-3 p-md-4">
        <p class="text-muted small mb-3">
            Saiba como são calculadas as médias, quais os critérios de aprovação e o que significa
            transitar, transitar com deficiência ou ir a exame de recuperação/recurso.
        </p>
        <a href="{{ route('regras-avaliacao') }}" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-book me-1"></i> Consultar regras
        </a>
    </div>
</div>

<div class="card border-0 shadow-sm portal-card mb-3">
    <div class="card-header bg-transparent border-0 pb-0">
        <h2 class="h6 mb-0"><i class="bi bi-sliders me-1"></i> Preferências</h2>
    </div>
    <div class="card-body p-3 p-md-4">
        <form method="post" action="{{ route('portal.aluno.perfil.preferencias') }}">
            @csrf
            @method('patch')
            <label class="form-label small" for="tema">Tema da interface</label>
            <select name="tema" id="tema" class="form-select form-select-sm mb-3" onchange="this.form.submit()">
                @php $temaPref = $user->preferencias['tema'] ?? 'system'; @endphp
                <option value="light" @selected($temaPref === 'light')>Claro</option>
                <option value="dark" @selected($temaPref === 'dark')>Escuro</option>
                <option value="system" @selected($temaPref === 'system')>Seguir o sistema</option>
            </select>
            <p class="text-muted small mb-0">A preferência é guardada na sua conta e aplicada neste dispositivo.</p>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm portal-card">
    <div class="card-header bg-transparent border-0 pb-0">
        <h2 class="h6 mb-0"><i class="bi bi-shield-lock me-1"></i> Alterar palavra-passe</h2>
    </div>
    <div class="card-body p-3 p-md-4">
        <p class="text-muted small">Escolhe uma palavra-passe segura e diferente do número de processo.</p>

        <form method="post" action="{{ route('password.update') }}">
            @csrf
            @method('put')

            <div class="mb-3">
                <label for="current_password" class="form-label small">Palavra-passe actual</label>
                <input type="password" name="current_password" id="current_password"
                       class="form-control form-control-sm @error('current_password', 'updatePassword') is-invalid @enderror"
                       required autocomplete="current-password">
                @error('current_password', 'updatePassword')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-3">
                <label for="password" class="form-label small">Nova palavra-passe</label>
                <input type="password" name="password" id="password"
                       class="form-control form-control-sm @error('password', 'updatePassword') is-invalid @enderror"
                       required autocomplete="new-password" minlength="8">
                @error('password', 'updatePassword')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-3">
                <label for="password_confirmation" class="form-label small">Confirmar nova palavra-passe</label>
                <input type="password" name="password_confirmation" id="password_confirmation"
                       class="form-control form-control-sm" required autocomplete="new-password">
            </div>

            <button type="submit" class="btn btn-primary btn-sm">
                <i class="bi bi-check-lg me-1"></i> Actualizar palavra-passe
            </button>

            @if (session('status') === 'password-updated')
                <div class="alert alert-success mt-3 mb-0 py-2 small">
                    Palavra-passe actualizada com sucesso.
                </div>
            @endif
        </form>
    </div>
</div>
@endsection
