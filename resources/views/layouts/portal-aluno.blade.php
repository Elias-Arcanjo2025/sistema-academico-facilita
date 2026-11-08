<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('layouts.partials.theme-init')
    @auth
    @php $temaConta = auth()->user()->preferencias['tema'] ?? null; @endphp
    @if($temaConta && $temaConta !== 'system')
    <script>
        (function () {
            var t = @json($temaConta);
            document.documentElement.setAttribute('data-theme', t);
            document.documentElement.setAttribute('data-bs-theme', t);
            try { localStorage.setItem('theme', t); } catch (e) {}
        })();
    </script>
    @endif
    @endauth
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Portal do Aluno') — {{ config('app.name') }}</title>
    <link rel="icon" type="image/png" href="{{ asset('images/facilitaicon.png') }}">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    @php
        $manifestUrl = route('portal.manifest');
    @endphp
    @include('layouts.partials.pwa-head', ['manifestUrl' => $manifestUrl])
    <link href="{{ asset('css/custom.css') }}" rel="stylesheet">
    <link href="{{ asset('css/design-system.css') }}" rel="stylesheet">
    <link href="{{ asset('css/portal-aluno.css') }}" rel="stylesheet">
    @include('layouts.partials.tema-instituicao')
    <style>
        :root { --portal-nav-h: 56px; --portal-bottom-h: 64px; }
        body.portal-aluno-body {
            min-height: 100dvh;
            background: var(--bg-body, #f4f6fb);
            color: var(--text-dark, #1e293b);
            padding-bottom: calc(var(--portal-bottom-h) + env(safe-area-inset-bottom));
        }
        .portal-topbar {
            position: sticky; top: 0; z-index: 100;
            background: color-mix(in srgb, var(--bg-card, #fff) 92%, transparent);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--border-subtle, rgba(0,0,0,.06));
            min-height: var(--portal-nav-h);
        }
        .portal-main { max-width: 960px; margin: 0 auto; }
        .portal-bottom-nav {
            position: fixed; left: 0; right: 0; bottom: 0; z-index: 100;
            display: flex; justify-content: space-around; align-items: stretch;
            min-height: var(--portal-bottom-h);
            padding-bottom: env(safe-area-inset-bottom);
            background: color-mix(in srgb, var(--bg-card, #fff) 96%, transparent);
            border-top: 1px solid var(--border-subtle, rgba(0,0,0,.08));
            backdrop-filter: blur(12px);
        }
        .portal-bottom-nav a {
            flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center;
            gap: .15rem; text-decoration: none; color: var(--text-muted, #64748b); font-size: .68rem; font-weight: 500;
            padding: .45rem .25rem;
        }
        .portal-bottom-nav a.active { color: var(--primary-color, #4f46e5); }
        .portal-bottom-nav a i { font-size: 1.25rem; }
        .portal-card { border-radius: 14px; }
        .portal-aluno-body .assistente-root {
            --assistente-offset-bottom: calc(var(--portal-bottom-h) + env(safe-area-inset-bottom, 0px) + 0.65rem);
            --assistente-offset-right: max(0.85rem, env(safe-area-inset-right, 0px));
        }
        .portal-aluno-body .assistente-root.is-open:not(.is-fullscreen-mobile) {
            bottom: calc(var(--portal-bottom-h) + env(safe-area-inset-bottom, 0px) + 0.5rem);
            top: max(0.65rem, env(safe-area-inset-top, 0px));
        }
    </style>
    @stack('styles')
</head>
<body class="portal-aluno-body">
    @auth
        @include('portal.aluno.partials.pwa-install')
    @endauth
    @include('portal.aluno.partials.topbar')

    <main class="portal-main px-3 py-3">
        @isset($controlLicenseWarning)
            @if($controlLicenseWarning)
                <div class="alert alert-{{ $controlLicenseWarning['level'] === 'danger' ? 'danger' : 'warning' }} py-2 small" role="alert">
                    <strong>{{ $controlLicenseWarning['level'] === 'danger' ? 'Pagamento em atraso' : 'Cobrança Facilita' }}:</strong>
                    {{ $controlLicenseWarning['message'] }}
                </div>
            @endif
        @endisset
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show py-2 small" role="alert">
                {{ session('success') }}
                <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="alert"></button>
            </div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show py-2 small" role="alert">
                {{ session('error') }}
                <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="alert"></button>
            </div>
        @endif
        @yield('content')
    </main>

    @auth
    @include('portal.aluno.partials.bottom-nav')
    @endauth

    @can('assistente.usar')
        <x-assistente-widget />
    @endcan

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script type="module" src="{{ asset('js/app-compiled.js') }}"></script>
    @auth
        @include('layouts.partials.bloqueio-inactividade')
    @endauth
    @include('layouts.partials.theme-toggle-script')
    @auth
    <script src="{{ asset('js/facilita-pwa.js') }}" defer></script>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        if (window.facilitaPwa && window.facilitaPwa.initInstallBanner) {
            window.facilitaPwa.initInstallBanner('portalPwaInstall', 'portalPwaInstallBtn', 'portalPwaInstallDismiss', 'portal_pwa_install_dismissed');
        }
    });
    </script>
    @endauth
    @stack('scripts')
</body>
</html>
