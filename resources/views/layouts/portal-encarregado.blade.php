<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Portal do Encarregado') — Facilita</title>
    <link rel="icon" type="image/png" href="{{ asset('images/facilitaicon.png') }}">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="{{ asset('css/custom.css') }}" rel="stylesheet">
    @include('layouts.partials.tema-instituicao')
    @stack('styles')
    <style>
        :root {
            --enc-ink: #0f172a;
            --enc-muted: #64748b;
            --enc-line: #e2e8f0;
            --enc-surface: #ffffff;
            --enc-accent: var(--primary-color, #0d9488);
            --enc-accent-soft: var(--primary-soft-bg, rgba(13, 148, 136, .12));
            --enc-accent-dark: var(--primary-dark, #134e4a);
        }
        body {
            background:
                radial-gradient(1200px 420px at 10% -10%, color-mix(in srgb, var(--enc-accent) 18%, transparent), transparent 55%),
                radial-gradient(900px 360px at 100% 0%, color-mix(in srgb, var(--secondary-color, #ea580c) 12%, transparent), transparent 50%),
                #f1f5f9;
            min-height: 100dvh;
            padding-bottom: 4.5rem;
            color: var(--enc-ink);
        }
        body .assistente-root {
            --assistente-offset-bottom: calc(4.5rem + env(safe-area-inset-bottom, 0px));
            --assistente-offset-right: max(0.85rem, env(safe-area-inset-right, 0px));
        }
        body .assistente-root.is-open:not(.is-fullscreen-mobile) {
            bottom: calc(4.5rem + env(safe-area-inset-bottom, 0px));
            top: max(0.65rem, env(safe-area-inset-top, 0px));
        }
        .enc-top {
            background: linear-gradient(135deg, #0f172a 0%, var(--enc-accent-dark) 55%, var(--enc-accent) 100%);
            color: #fff; padding: .85rem 1rem;
        }
        .enc-top__brand {
            display: flex; align-items: center; gap: .65rem;
        }
        .enc-top__logo {
            width: 2rem; height: 2rem; object-fit: contain; border-radius: .4rem;
            background: rgba(255,255,255,.12); padding: .15rem;
        }
        .enc-card, .enc-panel {
            background: var(--enc-surface);
            border-radius: 1rem;
            border: 1px solid var(--enc-line);
            padding: 1rem;
            box-shadow: 0 8px 24px rgba(15, 23, 42, .04);
        }
        .enc-panel__head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: .5rem;
            margin-bottom: .75rem;
            flex-wrap: wrap;
        }
        .enc-bottom { position: fixed; left: 0; right: 0; bottom: 0; background: #fff; border-top: 1px solid #e2e8f0; display: flex; z-index: 1030; }
        .enc-bottom a { flex: 1; text-align: center; padding: .65rem; text-decoration: none; color: #64748b; font-size: .8rem; position: relative; }
        .enc-bottom a.active { color: var(--enc-accent); }
        .enc-badge { position: absolute; top: .35rem; right: calc(50% - 1.1rem); font-size: .6rem; }

        .enc-hero__eyebrow { font-size: .75rem; letter-spacing: .04em; text-transform: uppercase; color: var(--enc-muted); }
        .enc-hero__title { font-size: 1.45rem; font-weight: 700; margin: .2rem 0 .35rem; }
        .enc-hero__sub { color: var(--enc-muted); font-size: .92rem; }
        .enc-section-title { font-size: .95rem; font-weight: 700; margin: 0 0 .65rem; }

        .enc-kpi {
            background: var(--enc-surface);
            border: 1px solid var(--enc-line);
            border-radius: .9rem;
            padding: .85rem .9rem;
            height: 100%;
        }
        .enc-kpi__label { display: block; font-size: .72rem; color: var(--enc-muted); margin-bottom: .2rem; }
        .enc-kpi__value { font-size: 1.45rem; line-height: 1.1; }
        .enc-kpi--warn { border-color: #fecaca; background: #fff7f7; }

        .enc-filho-card {
            display: block;
            text-decoration: none;
            color: inherit;
            background: var(--enc-surface);
            border: 1px solid var(--enc-line);
            border-radius: 1rem;
            padding: 1rem;
            height: 100%;
            box-shadow: 0 8px 24px rgba(15, 23, 42, .04);
            transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
            border-left: 4px solid var(--enc-accent, #0d9488);
        }
        .enc-filho-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 14px 28px rgba(15, 23, 42, .08);
            border-color: color-mix(in srgb, var(--enc-accent) 35%, var(--enc-line));
            color: inherit;
        }
        .enc-filho-card__top { display: flex; align-items: center; gap: .75rem; margin-bottom: .85rem; }
        .enc-filho-card__avatar, .enc-filho-hero__avatar {
            width: 2.6rem; height: 2.6rem; border-radius: .85rem;
            display: grid; place-items: center; font-weight: 700; color: #fff;
            background: var(--enc-accent, #0d9488); flex-shrink: 0;
        }
        .enc-filho-card__nome { font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .enc-filho-card__meta { font-size: .8rem; color: var(--enc-muted); }
        .enc-filho-card__stats {
            display: grid; grid-template-columns: repeat(3, 1fr); gap: .5rem;
            padding: .65rem 0; border-top: 1px dashed var(--enc-line); border-bottom: 1px dashed var(--enc-line);
            margin-bottom: .65rem;
        }
        .enc-filho-card__stats span { display: block; font-size: .68rem; color: var(--enc-muted); }
        .enc-filho-card__stats strong { font-size: .95rem; }
        .enc-filho-card__cta { font-size: .82rem; font-weight: 600; color: var(--enc-accent, #0d9488); }

        .enc-score {
            width: 2.4rem; height: 2.4rem; border-radius: 999px;
            display: grid; place-items: center; font-size: .78rem; font-weight: 700;
            background: var(--enc-accent-soft); color: var(--enc-accent, #0d9488); flex-shrink: 0;
        }

        .enc-chart-wrap { position: relative; height: 220px; }
        .enc-chart-wrap--tall { height: 280px; }
        .enc-alertas { list-style: none; padding: 0; margin: 0; }
        .enc-alertas li { padding: .55rem 0; border-bottom: 1px solid var(--enc-line); }
        .enc-alertas li:last-child { border-bottom: 0; }
        .enc-alertas a { text-decoration: none; color: var(--enc-ink); font-size: .9rem; }
        .enc-alertas a:hover { color: #0f766e; }

        .enc-back { display: inline-block; font-size: .85rem; text-decoration: none; color: var(--enc-muted); margin-bottom: .65rem; }
        .enc-back:hover { color: #0f766e; }
        .enc-filho-hero { display: flex; gap: .85rem; align-items: center; }
        .enc-filho-hero__nome { font-size: 1.25rem; font-weight: 700; margin: 0 0 .2rem; }
        .enc-filho-hero__meta { color: var(--enc-muted); font-size: .88rem; }
        .enc-filho-hero__badge {
            display: inline-block; margin-top: .35rem; font-size: .72rem; font-weight: 600;
            background: var(--enc-accent-soft); color: var(--enc-accent, #0d9488);
            border-radius: 999px; padding: .2rem .55rem;
        }
        .enc-quick {
            display: flex; align-items: center; gap: .65rem;
            text-decoration: none; color: inherit;
            background: var(--enc-surface); border: 1px solid var(--enc-line);
            border-radius: .9rem; padding: .9rem 1rem; height: 100%;
            font-weight: 600; font-size: .92rem;
        }
        .enc-quick i { color: var(--enc-accent, #0d9488); font-size: 1.15rem; }
        .enc-quick:hover { border-color: color-mix(in srgb, var(--enc-accent) 40%, var(--enc-line)); color: inherit; }

        .enc-panel--ok { border-left: 4px solid #0d9488; }
        .enc-acoes { display: flex; flex-direction: column; gap: .55rem; }
        .enc-acao {
            display: flex; align-items: flex-start; gap: .75rem;
            text-decoration: none; color: inherit;
            padding: .7rem .8rem; border-radius: .75rem;
            border: 1px solid var(--enc-line); background: #f8fafc;
            transition: border-color .15s ease, background .15s ease;
        }
        .enc-acao:hover { border-color: color-mix(in srgb, var(--enc-accent) 45%, var(--enc-line)); background: #fff; color: inherit; }
        .enc-acao i {
            width: 1.75rem; height: 1.75rem; border-radius: .55rem;
            display: grid; place-items: center; flex-shrink: 0;
            background: var(--enc-accent-soft); color: var(--enc-accent);
        }
        .enc-acao strong { display: block; font-size: .9rem; }
        .enc-acao small { display: block; color: var(--enc-muted); margin-top: .15rem; line-height: 1.35; }

        .enc-timeline { list-style: none; margin: 0; padding: 0; }
        .enc-timeline__item {
            display: grid; grid-template-columns: 2rem 1fr; gap: .75rem;
            position: relative; padding-bottom: 1rem;
        }
        .enc-timeline__item:last-child { padding-bottom: 0; }
        .enc-timeline__item:not(:last-child)::before {
            content: ''; position: absolute; left: .9rem; top: 1.85rem; bottom: 0;
            width: 2px; background: var(--enc-line);
        }
        .enc-timeline__dot {
            width: 1.85rem; height: 1.85rem; border-radius: 999px;
            display: grid; place-items: center; font-size: .85rem;
            background: #e2e8f0; color: #475569; z-index: 1;
        }
        .enc-timeline__item--warn .enc-timeline__dot { background: #fee2e2; color: #b91c1c; }
        .enc-timeline__item--ok .enc-timeline__dot { background: #ccfbf1; color: #0f766e; }
        .enc-timeline__meta { font-size: .72rem; color: var(--enc-muted); }
        .enc-timeline__title { font-weight: 700; font-size: .92rem; }
        .enc-timeline__text { font-size: .85rem; color: var(--enc-muted); margin-top: .1rem; }
        .enc-timeline__link { font-size: .8rem; font-weight: 600; color: var(--enc-accent); text-decoration: none; }
        .enc-timeline__link:hover { text-decoration: underline; }
        .enc-timeline--compact .enc-timeline__item { padding-bottom: .75rem; }
    </style>
</head>
<body>
    <header class="enc-top d-flex justify-content-between align-items-center">
        <div class="enc-top__brand">
            @if(!empty($instituicao['logo']))
                <img src="{{ asset('storage/'.$instituicao['logo']) }}" alt="" class="enc-top__logo">
            @endif
            <div>
                <div class="fw-semibold">{{ $instituicao['nome'] ?? 'Portal do Encarregado' }}</div>
                <div class="small opacity-75">{{ auth()->user()->name }} · Encarregado</div>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <button type="button" class="btn btn-sm btn-outline-light" data-bs-toggle="offcanvas" data-bs-target="#glossarioNotas" title="Glossário de notas" aria-label="Glossário de notas">
                <i class="bi bi-question-circle"></i>
            </button>
            <a href="{{ route('portal.encarregado.notificacoes') }}" class="btn btn-sm btn-outline-light position-relative" title="Alertas">
                <i class="bi bi-bell"></i>
                @if(($notificacoesNaoLidas ?? 0) > 0)
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                        {{ $notificacoesNaoLidas > 9 ? '9+' : $notificacoesNaoLidas }}
                    </span>
                @endif
            </a>
            <form method="POST" action="{{ route('logout') }}">@csrf
                <button class="btn btn-sm btn-outline-light">Sair</button>
            </form>
        </div>
    </header>
    <main class="p-3">
        @if(session('success'))<div class="alert alert-success py-2">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="alert alert-danger py-2">{{ session('error') }}</div>@endif
        @yield('content')
    </main>
    <nav class="enc-bottom">
        <a href="{{ route('portal.encarregado.dashboard') }}" class="{{ request()->routeIs('portal.encarregado.dashboard') ? 'active' : '' }}">
            <div><i class="bi bi-house"></i></div>Início
        </a>
        <a href="{{ route('portal.encarregado.mensagens.index') }}" class="{{ request()->routeIs('portal.encarregado.mensagens*') ? 'active' : '' }}">
            <div><i class="bi bi-chat-dots"></i></div>Msgs
        </a>
        <a href="{{ route('portal.encarregado.documentos.index') }}" class="{{ request()->routeIs('portal.encarregado.documentos*') ? 'active' : '' }}">
            <div><i class="bi bi-file-earmark-text"></i></div>Docs
        </a>
        <a href="{{ route('portal.encarregado.notificacoes') }}" class="{{ request()->routeIs('portal.encarregado.notificacoes*') ? 'active' : '' }}">
            <div><i class="bi bi-bell"></i></div>Alertas
            @if(($notificacoesNaoLidas ?? 0) > 0)
                <span class="badge bg-danger enc-badge">{{ $notificacoesNaoLidas > 9 ? '9+' : $notificacoesNaoLidas }}</span>
            @endif
        </a>
    </nav>

    @include('partials.glossario-notas')
    @can('assistente.usar')
        <x-assistente-widget />
    @endcan
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script type="module" src="{{ asset('js/app-compiled.js') }}"></script>
    @auth
        @include('layouts.partials.bloqueio-inactividade')
    @endauth
    @stack('scripts')
</body>
</html>
