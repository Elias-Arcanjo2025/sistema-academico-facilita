<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Licença suspensa — Facilita</title>
    <link rel="icon" type="image/png" href="{{ asset('images/facilitaicon.png') }}">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(160deg, #0f172a 0%, #1e293b 45%, #334155 100%);
            color: #e2e8f0;
            font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
        }
        .card-block {
            max-width: 520px;
            width: 100%;
            background: rgba(15, 23, 42, 0.85);
            border: 1px solid rgba(148, 163, 184, 0.25);
            border-radius: 1rem;
            padding: 2rem;
            box-shadow: 0 20px 50px rgba(0,0,0,.35);
        }
        .icon {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            display: grid;
            place-items: center;
            background: rgba(239, 68, 68, 0.15);
            color: #f87171;
            font-size: 1.75rem;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <div class="card-block text-center">
        <div class="icon mx-auto"><i class="bi bi-shield-lock"></i></div>
        <h1 class="h4 mb-3">{{ ($gracePeriod ?? false) ? 'Licença por validar' : 'Acesso suspenso' }}</h1>
        <p class="mb-4 text-secondary">{{ $message }}</p>
        @if($state->checked_at)
            <p class="small text-secondary mb-4">Última verificação: {{ $state->checked_at->format('d/m/Y H:i') }}</p>
        @endif
        <div class="d-flex gap-2 justify-content-center flex-wrap">
            <a href="{{ route('control.license.blocked') }}" class="btn btn-primary btn-sm">
                <i class="bi bi-arrow-clockwise me-1"></i> Verificar novamente
            </a>
            @auth
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="btn btn-outline-light btn-sm">Terminar sessão</button>
                </form>
            @else
                <a href="{{ route('login') }}" class="btn btn-outline-light btn-sm">Ir para login</a>
            @endauth
        </div>
        <p class="small text-secondary mt-4 mb-0">Facilita — suporte de licenciamento</p>
    </div>
</body>
</html>
