<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <script>
        (function() {
            var t = localStorage.getItem('theme');
            if (!t) t = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
            document.documentElement.setAttribute('data-theme', t);
        })();
    </script>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Facilita') }}</title>

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="{{ asset('images/facilitaicon.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/facilitaicon.png') }}">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

    <!-- SweetAlert2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">

    <style>
        :root {
            --primary-color: #4f46e5;
            --primary-dark: #4338ca;
            --secondary-color: #06b6d4;
            --accent-color: #8b5cf6;
            --text-dark: #1e293b;
            --text-muted: #64748b;
            --bg-light: #f8fafc;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--accent-color) 100%);
            min-height: 100dvh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.25rem 1rem;
        }

        .auth-container {
            width: 100%;
            max-width: 960px;
        }

        .auth-card {
            background: white;
            border-radius: 24px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.18);
            overflow: hidden;
            display: grid;
        }

        /* Painel lateral de marca (desktop) */
        .auth-side {
            display: none;
            position: relative;
            background: linear-gradient(150deg, var(--primary-dark) 0%, var(--primary-color) 55%, var(--accent-color) 100%);
            color: #fff;
            padding: 2.5rem 2.25rem;
            flex-direction: column;
            justify-content: space-between;
            overflow: hidden;
        }
        .auth-side::after {
            content: '';
            position: absolute; inset: 0;
            background: radial-gradient(circle at 80% 15%, rgba(255,255,255,.14) 0%, transparent 45%),
                        radial-gradient(circle at 10% 90%, rgba(255,255,255,.08) 0%, transparent 40%);
            pointer-events: none;
        }
        .auth-side > * { position: relative; z-index: 1; }
        .auth-side__brand {
            display: flex;
            align-items: center;
            gap: .85rem;
        }
        .auth-side__logo {
            width: 52px; height: 52px; border-radius: 14px;
            background: rgba(255,255,255,.18);
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        }
        .auth-side__logo img { width: 36px; height: 36px; object-fit: contain; }
        .auth-side__brand strong { font-size: 1.15rem; display: block; line-height: 1.2; }
        .auth-side__brand small { color: rgba(255,255,255,.75); font-size: .8rem; }
        .auth-side__mid { margin: 2rem 0; }
        .auth-side__instituicao {
            font-size: .82rem;
            font-weight: 600;
            color: rgba(255,255,255,.85);
            text-transform: uppercase;
            letter-spacing: .04em;
            line-height: 1.5;
            margin-bottom: 1.25rem;
        }
        .auth-side h2 {
            font-size: 1.6rem;
            font-weight: 800;
            letter-spacing: -.02em;
            line-height: 1.25;
            margin-bottom: 1.5rem;
        }
        .auth-side__feats { list-style: none; display: flex; flex-direction: column; gap: .85rem; }
        .auth-side__feats li {
            display: flex; align-items: flex-start; gap: .75rem;
            font-size: .88rem; color: rgba(255,255,255,.88); line-height: 1.5;
        }
        .auth-side__feats i {
            width: 30px; height: 30px; border-radius: 9px; flex-shrink: 0;
            background: rgba(255,255,255,.16);
            display: flex; align-items: center; justify-content: center;
            font-size: .9rem;
        }
        .auth-side__badge {
            display: inline-flex; align-items: center; gap: .5rem;
            padding: .4rem .9rem; border-radius: 50px;
            background: rgba(255,255,255,.12);
            border: 1px solid rgba(255,255,255,.2);
            font-size: .78rem; color: rgba(255,255,255,.9);
            align-self: flex-start;
        }
        .auth-side__badge .dot { width: 7px; height: 7px; border-radius: 50%; background: #34d399; }

        /* Cabeçalho compacto (mobile / tablet) */
        .auth-header {
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
            padding: 1.1rem 1.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: .75rem;
            text-align: left;
        }
        .auth-logo {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 44px;
            height: 44px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 13px;
            flex-shrink: 0;
        }
        .auth-logo-img {
            width: 30px;
            height: 30px;
            object-fit: contain;
        }
        .auth-title {
            color: white;
            font-size: 1.15rem;
            font-weight: 700;
            margin-bottom: 0;
            line-height: 1.2;
        }
        .auth-subtitle {
            color: rgba(255, 255, 255, 0.85);
            font-size: 0.8rem;
            margin-bottom: 0;
        }

        .auth-body {
            padding: 1.75rem 1.75rem 2rem;
        }

        @media (min-width: 992px) {
            .auth-card { grid-template-columns: minmax(0, 5fr) minmax(0, 6fr); }
            .auth-side { display: flex; }
            .auth-header { display: none; }
            .auth-main {
                display: flex;
                flex-direction: column;
                justify-content: center;
            }
            .auth-body { padding: 2.25rem 2.75rem; }
        }

        .form-label {
            font-weight: 500;
            color: var(--text-dark);
            margin-bottom: 0.5rem;
        }

        .form-control {
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            padding: 0.75rem 1rem;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1);
        }

        .form-control::placeholder {
            color: #94a3b8;
        }

        .input-group-text {
            background: var(--bg-light);
            border: 2px solid #e2e8f0;
            border-right: none;
            border-radius: 12px 0 0 12px;
            padding: 0.75rem 1rem;
            color: var(--text-muted);
        }

        .input-group .form-control {
            border-left: none;
            border-radius: 0 12px 12px 0;
        }

        .input-group:focus-within .input-group-text {
            border-color: var(--primary-color);
        }

        .input-group:focus-within .form-control {
            border-color: var(--primary-color);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
            border: none;
            border-radius: 12px;
            padding: 0.875rem 1.5rem;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(79, 70, 229, 0.35);
            background: linear-gradient(135deg, var(--primary-dark), var(--primary-color));
        }

        .btn-primary:active {
            transform: translateY(0);
        }

        .form-check-input {
            width: 1.25em;
            height: 1.25em;
            border: 2px solid #e2e8f0;
            border-radius: 6px;
        }

        .form-check-input:checked {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .form-check-label {
            color: var(--text-muted);
            font-size: 0.9rem;
        }

        .auth-link {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
        }

        .auth-link:hover {
            color: var(--primary-dark);
            text-decoration: underline;
        }

        .auth-divider {
            display: flex;
            align-items: center;
            margin: 1.5rem 0;
            color: var(--text-muted);
            font-size: 0.875rem;
        }

        .auth-divider::before,
        .auth-divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: #e2e8f0;
        }

        .auth-divider span {
            padding: 0 1rem;
        }

        .auth-footer {
            text-align: center;
            padding: 1.5rem 2rem;
            background: var(--bg-light);
            border-top: 1px solid #e2e8f0;
        }

        .auth-footer p {
            margin: 0;
            color: var(--text-muted);
            font-size: 0.9rem;
        }

        .back-home {
            display: inline-flex;
            align-items: center;
            color: rgba(255, 255, 255, 0.9);
            text-decoration: none;
            font-weight: 500;
            margin-bottom: 1.5rem;
            transition: color 0.3s ease;
        }

        .back-home:hover {
            color: white;
        }

        .back-home i {
            margin-right: 0.5rem;
        }

        .alert {
            border: none;
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }

        .alert-danger {
            background: #fef2f2;
            color: #dc2626;
        }

        .alert-success {
            background: #ecfdf5;
            color: #059669;
        }

        .invalid-feedback {
            color: #dc2626;
            font-size: 0.85rem;
            margin-top: 0.5rem;
        }

        .is-invalid {
            border-color: #dc2626 !important;
        }

        .is-invalid:focus {
            box-shadow: 0 0 0 4px rgba(220, 38, 38, 0.1) !important;
        }

        /* Password toggle */
        .password-toggle {
            position: relative;
        }

        .password-toggle .toggle-btn {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            padding: 0;
            z-index: 10;
        }

        .password-toggle .toggle-btn:hover {
            color: var(--primary-color);
        }

        .password-toggle .form-control {
            padding-right: 3rem;
        }

        /* Dark mode */
        [data-theme="dark"] body {
            background: linear-gradient(135deg, #1e1b4b 0%, #312e81 100%);
        }

        [data-theme="dark"] .auth-card {
            background: #1e293b;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.4);
        }

        [data-theme="dark"] .auth-body h5,
        [data-theme="dark"] .auth-body .form-label,
        [data-theme="dark"] .auth-body .text-dark {
            color: #e2e8f0 !important;
        }

        [data-theme="dark"] .form-control {
            background: #0f172a;
            border-color: #475569;
            color: #e2e8f0;
        }

        [data-theme="dark"] .form-control::placeholder {
            color: #64748b;
        }

        [data-theme="dark"] .form-control:focus {
            background: #0f172a;
            border-color: #818cf8;
            color: #e2e8f0;
            box-shadow: 0 0 0 4px rgba(129, 140, 248, 0.15);
        }

        [data-theme="dark"] .form-check-label {
            color: #94a3b8;
        }

        [data-theme="dark"] .form-check-input {
            background-color: #0f172a;
            border-color: #475569;
        }

        [data-theme="dark"] .auth-link {
            color: #a5b4fc;
        }

        [data-theme="dark"] .auth-link:hover {
            color: #c7d2fe;
        }

        [data-theme="dark"] .auth-divider::before,
        [data-theme="dark"] .auth-divider::after {
            background: #334155;
        }

        [data-theme="dark"] .auth-divider {
            color: #64748b;
        }

        [data-theme="dark"] .auth-footer {
            background: #0f172a;
            border-top-color: #334155;
        }

        [data-theme="dark"] .auth-footer p,
        [data-theme="dark"] .text-muted {
            color: #94a3b8 !important;
        }

        [data-theme="dark"] .alert-danger {
            background: rgba(239, 68, 68, 0.1);
            color: #f87171;
        }

        [data-theme="dark"] .alert-success {
            background: rgba(16, 185, 129, 0.1);
            color: #34d399;
        }

        .theme-toggle-guest {
            position: fixed;
            top: 1rem;
            right: 1rem;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: white;
            width: 42px;
            height: 42px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 1.1rem;
            z-index: 100;
        }

        .theme-toggle-guest:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: rotate(20deg);
        }
    </style>
    @include('layouts.partials.tema-instituicao')
</head>
<body>
    <button class="theme-toggle-guest" id="themeToggleGuest" title="Alternar modo escuro/claro">
        <i class="bi bi-moon-fill" id="themeIconGuest"></i>
    </button>
    <div class="auth-container">
        <a href="{{ url('/') }}" class="back-home">
            <i class="bi bi-arrow-left"></i>
            Voltar ao início
        </a>

        <div class="auth-card">
            <aside class="auth-side" aria-hidden="true">
                <div class="auth-side__brand">
                    <div class="auth-side__logo">
                        <img src="{{ asset('images/facilitaicon.png') }}" alt="Facilita">
                    </div>
                    <div>
                        <strong>Facilita</strong>
                        <small>Sistema Académico Inteligente</small>
                    </div>
                </div>
                <div class="auth-side__mid">
                    @if(!empty($instituicao['nome']))
                        <p class="auth-side__instituicao">{{ $instituicao['nome'] }}</p>
                    @endif
                    <h2>A vida da tua escola,<br>num só lugar.</h2>
                    <ul class="auth-side__feats">
                        <li><i class="bi bi-journal-check"></i>Notas, boletins e pautas sempre actualizados</li>
                        <li><i class="bi bi-chat-dots"></i>Mensagens entre a escola e as famílias</li>
                        <li><i class="bi bi-stars"></i>Assistente com Inteligência Artificial</li>
                        <li><i class="bi bi-shield-lock"></i>Acesso seguro adaptado a cada perfil</li>
                    </ul>
                </div>
                @if(!empty($paginaPublica['badge']) && ($paginaPublica['mostrar_ano_lectivo'] ?? true))
                    <span class="auth-side__badge"><span class="dot"></span>{{ $paginaPublica['badge'] }}</span>
                @endif
            </aside>

            <div class="auth-main">
                <div class="auth-header">
                    <div class="auth-logo">
                        <img src="{{ asset('images/facilitaicon.png') }}" alt="Facilita" class="auth-logo-img">
                    </div>
                    <div>
                        <h1 class="auth-title">Facilita</h1>
                        <p class="auth-subtitle">Sistema Académico</p>
                    </div>
                </div>

                <div class="auth-body">
                    {{ $slot }}
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- SweetAlert2 Helper Functions (versão simplificada para guest) -->
    <script>
        window.showNotification = function(type, message, title = null) {
            const titles = {success: 'Sucesso!', error: 'Erro!', warning: 'Atenção!', info: 'Informação'};
            const colors = {success: '#198754', error: '#dc3545', warning: '#ffc107', info: '#0dcaf0'};

            Swal.fire({
                icon: type,
                title: title || titles[type],
                text: message,
                iconColor: colors[type],
                confirmButtonColor: colors[type],
                timer: 5000,
                timerProgressBar: true
            });
        };

        // Auto-inicializar notificações de sessão
        document.addEventListener('DOMContentLoaded', () => {
            const notificationElements = document.querySelectorAll('[data-notification]');
            notificationElements.forEach(element => {
                const type = element.dataset.notificationType;
                const message = element.dataset.notificationMessage;
                if (type && message) {
                    showNotification(type, message);
                }
            });
        });
    </script>

    <script>
        // Password visibility toggle
        document.querySelectorAll('.password-toggle').forEach(function(container) {
            const toggle = container.querySelector('.toggle-btn');
            const input = container.querySelector('input');

            if (toggle && input) {
                toggle.addEventListener('click', function() {
                    const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
                    input.setAttribute('type', type);
                    this.querySelector('i').classList.toggle('bi-eye');
                    this.querySelector('i').classList.toggle('bi-eye-slash');
                });
            }
        });
    </script>

    <script>
        (function() {
            var toggle = document.getElementById('themeToggleGuest');
            var icon = document.getElementById('themeIconGuest');
            var current = document.documentElement.getAttribute('data-theme') || 'light';
            if (icon) icon.className = current === 'dark' ? 'bi bi-sun-fill' : 'bi bi-moon-fill';
            if (toggle) {
                toggle.addEventListener('click', function() {
                    var now = document.documentElement.getAttribute('data-theme');
                    var next = now === 'dark' ? 'light' : 'dark';
                    document.documentElement.setAttribute('data-theme', next);
                    localStorage.setItem('theme', next);
                    icon.className = next === 'dark' ? 'bi bi-sun-fill' : 'bi bi-moon-fill';
                });
            }
        })();
    </script>

    @stack('scripts')
</body>
</html>
