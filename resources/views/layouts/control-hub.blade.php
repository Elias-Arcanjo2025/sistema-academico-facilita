<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Controlo Facilita')</title>
    <link rel="icon" type="image/png" href="{{ asset('images/facilitaicon.png') }}">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="{{ asset('css/custom.css') }}" rel="stylesheet">
    <link href="{{ asset('css/app-compiled.css') }}" rel="stylesheet">
    <link href="{{ asset('css/design-system.css') }}" rel="stylesheet">
    @include('layouts.partials.theme-init')
    <style>
        :root {
            --hub-bg: #0f172a;
            --hub-panel: #1e293b;
            --hub-accent: #38bdf8;
        }
        body.hub-body {
            min-height: 100vh;
            background: linear-gradient(165deg, #0f172a 0%, #1e293b 50%, #0f172a 100%);
            color: #e2e8f0;
        }
        .hub-shell { display: flex; min-height: 100vh; }
        .hub-sidebar {
            width: 260px;
            background: rgba(15, 23, 42, 0.95);
            border-right: 1px solid rgba(148, 163, 184, 0.15);
            padding: 1.25rem 1rem;
            flex-shrink: 0;
        }
        .hub-brand {
            display: flex;
            align-items: center;
            gap: .75rem;
            text-decoration: none;
            color: #f8fafc;
            margin-bottom: 1.5rem;
            padding: 0 .5rem;
        }
        .hub-brand img { height: 36px; }
        .hub-brand span { font-weight: 600; letter-spacing: .02em; }
        .hub-badge {
            display: inline-block;
            font-size: .65rem;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: #0f172a;
            background: var(--hub-accent);
            border-radius: 999px;
            padding: .15rem .5rem;
            margin-left: .35rem;
        }
        .hub-nav a {
            display: flex;
            align-items: center;
            gap: .65rem;
            padding: .65rem .75rem;
            border-radius: .5rem;
            color: #cbd5e1;
            text-decoration: none;
            margin-bottom: .25rem;
        }
        .hub-nav a:hover, .hub-nav a.active {
            background: rgba(56, 189, 248, 0.12);
            color: #f8fafc;
        }
        .hub-main { flex: 1; display: flex; flex-direction: column; min-width: 0; }
        .hub-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 1.5rem;
            border-bottom: 1px solid rgba(148, 163, 184, 0.12);
            background: rgba(15, 23, 42, 0.4);
        }
        .hub-content {
            padding: 1.25rem 1.5rem 2rem;
            flex: 1;
        }
        .hub-content .border,
        .hub-content .table,
        .hub-content .form-control,
        .hub-content .card,
        .hub-content .bg-white {
            background-color: rgba(30, 41, 59, 0.85) !important;
            color: #e2e8f0;
            border-color: rgba(148, 163, 184, 0.2) !important;
        }
        .hub-content .table-light,
        .hub-content thead.table-light th {
            background-color: rgba(15, 23, 42, 0.8) !important;
            color: #cbd5e1;
            border-color: rgba(148, 163, 184, 0.2) !important;
        }
        .hub-content .text-muted { color: #94a3b8 !important; }
        .hub-content .form-control,
        .hub-content .form-select {
            background: rgba(15, 23, 42, 0.7);
            color: #f1f5f9;
        }
        .hub-content .modal-content {
            background: #1e293b;
            color: #e2e8f0;
        }
        @media (max-width: 991px) {
            .hub-sidebar { display: none; }
        }
    </style>
    @stack('styles')
</head>
<body class="hub-body">
    <div class="hub-shell">
        <aside class="hub-sidebar d-none d-lg-block">
            <a href="{{ route('control.hub.index') }}" class="hub-brand">
                <img src="{{ asset('images/facilitaicon.png') }}" alt="Facilita">
                <div>
                    <div>Facilita <span class="hub-badge">Hub</span></div>
                    <div class="small text-secondary">Controlo central</div>
                </div>
            </a>
            <nav class="hub-nav">
                <a href="{{ route('control.hub.index') }}" class="{{ request()->routeIs('control.hub.index') ? 'active' : '' }}">
                    <i class="bi bi-grid-1x2"></i> Painel
                </a>
                <a href="{{ route('control.hub.escolas.create') }}" class="{{ request()->routeIs('control.hub.escolas.create') ? 'active' : '' }}">
                    <i class="bi bi-building-add"></i> Registar escola
                </a>
                <a href="{{ route('profile.edit') }}" class="{{ request()->routeIs('profile.*') ? 'active' : '' }}">
                    <i class="bi bi-person"></i> Perfil
                </a>
            </nav>
        </aside>

        <div class="hub-main">
            <header class="hub-top">
                <div>
                    @isset($header)
                        <div class="fw-semibold">{{ $header }}</div>
                    @else
                        <div class="fw-semibold">@yield('page-title', 'Controlo Facilita')</div>
                    @endisset
                    <div class="small text-secondary">Instalações escolares · licenças · cobranças</div>
                </div>
                @auth
                    <div class="dropdown">
                        <button class="btn btn-sm btn-outline-light dropdown-toggle" data-bs-toggle="dropdown">
                            {{ auth()->user()->name }}
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="{{ route('profile.edit') }}">Perfil</a></li>
                            <li>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button class="dropdown-item text-danger">Terminar sessão</button>
                                </form>
                            </li>
                        </ul>
                    </div>
                @endauth
            </header>

            <main class="hub-content">
                @if(session('success'))
                    <div class="alert alert-success">{{ session('success') }}</div>
                @endif
                @if(session('error'))
                    <div class="alert alert-danger">{{ session('error') }}</div>
                @endif
                @if(session('info'))
                    <div class="alert alert-info">{{ session('info') }}</div>
                @endif
                @if(session('warning'))
                    <div class="alert alert-warning">{{ session('warning') }}</div>
                @endif

                @hasSection('content')
                    @yield('content')
                @else
                    {{ $slot ?? '' }}
                @endif
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    @stack('scripts')
</body>
</html>
