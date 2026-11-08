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
    <meta name="description" content="{{ $paginaPublica['meta_descricao'] ?? 'Sistema Académico – Gestão completa de notas, turmas e alunos.' }}">

    <title>{{ !empty($instituicao['nome']) ? $instituicao['nome'] : config('app.name', 'Facilita') }} – Gestão Académica</title>

    <link rel="icon" type="image/png" href="{{ asset('images/facilitaicon.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/facilitaicon.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

    @include('layouts.partials.tema-instituicao')

    <style>
        :root {
            --text: #0f172a;
            --text-muted: #64748b;
            --surface: #f8fafc;
            --hero-nav-offset: 72px;
            --hero-panel-h: 100dvh;
            --hero-carousel-h: 100dvh;
        }

        html { scroll-behavior: smooth; scroll-padding-top: var(--hero-nav-offset); }

        body {
            font-family: 'Inter', sans-serif;
            color: var(--text);
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
        }

        /* ── Navbar ── */
        .nav-main {
            background: rgba(255,255,255,.92);
            backdrop-filter: blur(16px);
            border-bottom: 1px solid rgba(0,0,0,.06);
            padding: .85rem 0;
            position: fixed; top: 0; left: 0; right: 0;
            z-index: 1000;
            transition: background .3s, border-color .3s, box-shadow .3s;
        }
        .nav-main:not(.scrolled) {
            background: rgba(255,255,255,.88);
            backdrop-filter: blur(16px);
            border-bottom: 1px solid rgba(0,0,0,.06);
            box-shadow: 0 1px 12px rgba(0,0,0,.04);
        }
        .nav-main:not(.scrolled) .nav-link-c {
            color: var(--text) !important;
        }
        .nav-main:not(.scrolled) .nav-link-c:hover {
            color: var(--primary) !important;
            background: color-mix(in srgb, var(--primary) 7%, transparent);
        }
        .nav-main:not(.scrolled) .btn-enter {
            border-color: var(--primary);
            color: var(--primary) !important;
        }
        .nav-main:not(.scrolled) .btn-enter:hover {
            background: var(--primary);
            color: #fff !important;
        }
        .nav-main:not(.scrolled) .btn-go {
            background: var(--primary);
            color: #fff !important;
        }
        .nav-main:not(.scrolled) .nav-menu-btn i {
            color: var(--primary) !important;
        }
        .nav-main.scrolled { box-shadow: 0 4px 24px rgba(0,0,0,.08); }
        .nav-brand { display: inline-flex; align-items: center; text-decoration: none; line-height: 1; }
        .nav-brand img { height: 38px; width: auto; max-width: 190px; object-fit: contain; display: block; }
        .nav-link-c { color: var(--text) !important; font-weight: 500; font-size: .95rem; padding: .4rem .9rem !important; border-radius: 8px; transition: .2s; }
        .nav-link-c:hover { color: var(--primary) !important; background: color-mix(in srgb, var(--primary) 7%, transparent); }
        .btn-enter { border: 1.5px solid var(--primary); color: var(--primary) !important; font-weight: 600; padding: .45rem 1.4rem !important; border-radius: 10px; background: transparent; transition: .25s; }
        .btn-enter:hover { background: var(--primary); color: #fff !important; }
        .btn-go { background: var(--primary); color: #fff !important; font-weight: 600; padding: .45rem 1.4rem !important; border-radius: 10px; border: none; transition: .25s; }
        .btn-go:hover { background: var(--primary-dark); transform: translateY(-1px); box-shadow: 0 8px 20px color-mix(in srgb, var(--primary) 35%, transparent); }

        /* ── Hero ── */
        .hero {
            min-height: 100dvh;
            height: 100dvh;
            display: flex;
            flex-direction: column;
            background: linear-gradient(160deg, var(--hero-inicio) 0%, var(--hero-meio) 35%, var(--primary) 70%, var(--hero-fim) 100%);
            position: relative;
            overflow: hidden;
            padding: 0;
        }
        .hero::after {
            content: '';
            position: absolute; inset: 0;
            background: radial-gradient(circle at 70% 30%, color-mix(in srgb, var(--hero-fim) 40%, transparent) 0%, transparent 50%),
                        radial-gradient(circle at 20% 80%, color-mix(in srgb, var(--accent) 25%, transparent) 0%, transparent 40%);
        }
        .hero-layout {
            position: relative;
            z-index: 2;
            flex: 1;
            display: flex;
            flex-direction: column;
            width: 100%;
            min-height: 0;
        }
        .hero-layout__content {
            display: flex;
            align-items: center;
            padding: calc(var(--hero-nav-offset) + 1.25rem) 1.5rem 3.5rem;
        }
        .hero-layout__visual {
            flex: 1;
            min-height: 0;
        }
        .hero-content {
            display: flex;
            flex-direction: column;
            justify-content: center;
            width: 100%;
            max-width: 560px;
        }
        .hero-badge {
            display: inline-flex; align-items: center; gap: .5rem;
            background: rgba(255,255,255,.1); border: 1px solid rgba(255,255,255,.15);
            padding: .4rem 1rem; border-radius: 50px; font-size: .85rem;
            color: rgba(255,255,255,.9); margin-bottom: 1.5rem;
            backdrop-filter: blur(8px);
        }
        .hero-badge .dot { width: 8px; height: 8px; border-radius: 50%; background: #34d399; animation: pulse 2s infinite; }
        @keyframes pulse { 0%,100% { opacity: 1; } 50% { opacity: .4; } }
        .hero h1 { font-size: clamp(2.2rem, 5vw, 3.6rem); font-weight: 800; color: #fff; line-height: 1.15; letter-spacing: -.03em; margin-bottom: 1.5rem; }
        .hero h1 .gradient-text { background: linear-gradient(135deg, var(--hero-claro), var(--accent)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .hero-sub { font-size: 1.15rem; color: rgba(255,255,255,.8); line-height: 1.75; margin-bottom: 2.5rem; max-width: 520px; }
        .hero-actions { display: flex; gap: 1rem; flex-wrap: wrap; }
        .btn-hero-w { background: #fff; color: var(--primary); font-weight: 700; padding: .9rem 2rem; border-radius: 12px; border: none; font-size: 1rem; transition: .3s; box-shadow: 0 8px 32px rgba(0,0,0,.18); }
        .btn-hero-w:hover { transform: translateY(-2px); box-shadow: 0 12px 40px rgba(0,0,0,.25); color: var(--primary-dark); }
        .btn-hero-o { background: transparent; color: #fff; font-weight: 600; padding: .85rem 2rem; border-radius: 12px; border: 2px solid rgba(255,255,255,.3); font-size: 1rem; transition: .3s; }
        .btn-hero-o:hover { background: rgba(255,255,255,.1); border-color: rgba(255,255,255,.5); color: #fff; }

        .hero-visual {
            position: relative;
            width: 100%;
            height: 100%;
            min-height: var(--hero-carousel-h);
            display: flex;
            align-items: stretch;
        }
        .glass-card {
            background: rgba(255,255,255,.1);
            backdrop-filter: blur(16px); border: 1px solid rgba(255,255,255,.15);
            border-radius: 20px; padding: 2rem;
            width: 100%;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .glass-stat { text-align: center; padding: 1rem; }
        .glass-stat .num { font-size: 2rem; font-weight: 800; color: #fff; line-height: 1; }
        .glass-stat .lbl { font-size: .8rem; color: rgba(255,255,255,.7); margin-top: .35rem; }

        /* Hero sem carousel */
        .hero--sem-carousel .hero-layout {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100dvh;
            max-width: 920px;
            margin: 0 auto;
            padding: 0 1.25rem;
        }
        .hero--sem-carousel .hero-layout__content {
            width: 100%;
            padding: calc(var(--hero-nav-offset) + 1rem) 0 3.5rem;
            text-align: center;
        }
        .hero--sem-carousel .hero-content {
            max-width: none;
            align-items: center;
        }
        .hero--sem-carousel .hero-badge { margin-left: auto; margin-right: auto; }
        .hero--sem-carousel .hero-sub {
            max-width: 640px;
            margin-left: auto;
            margin-right: auto;
        }
        .hero--sem-carousel .hero-actions { justify-content: center; }
        .hero--sem-carousel .hero-scroll-hint { left: 50%; }
        .hero--sem-carousel::after {
            background: radial-gradient(circle at 50% 20%, color-mix(in srgb, var(--hero-fim) 35%, transparent) 0%, transparent 45%),
                        radial-gradient(circle at 15% 85%, color-mix(in srgb, var(--accent) 22%, transparent) 0%, transparent 40%),
                        radial-gradient(circle at 85% 75%, color-mix(in srgb, var(--primary) 18%, transparent) 0%, transparent 38%);
        }
        .hero-fallback {
            width: 100%;
            max-width: 680px;
            margin: 2rem auto 0;
        }
        .hero-fallback__logo img {
            max-height: 72px;
            max-width: min(280px, 80vw);
            object-fit: contain;
            filter: drop-shadow(0 8px 24px rgba(0,0,0,.2));
        }
        .hero-fallback__instituicao {
            color: rgba(255,255,255,.88);
            font-size: .95rem;
            font-weight: 600;
            line-height: 1.45;
            margin: 0 0 1rem;
        }
        .hero-highlights {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: .65rem;
        }
        .hero-highlight {
            display: inline-flex;
            align-items: center;
            gap: .45rem;
            padding: .55rem .95rem;
            border-radius: 50px;
            background: rgba(255,255,255,.12);
            border: 1px solid rgba(255,255,255,.18);
            color: rgba(255,255,255,.92);
            font-size: .82rem;
            font-weight: 500;
            backdrop-filter: blur(8px);
            transition: background .25s, transform .25s;
        }
        .hero-highlight i { font-size: 1rem; opacity: .95; }
        .hero-highlight:hover {
            background: rgba(255,255,255,.18);
            transform: translateY(-2px);
        }
        .hero-fallback__contactos {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: .75rem 1.25rem;
            margin-top: 1.25rem;
            font-size: .82rem;
            color: rgba(255,255,255,.75);
        }
        .hero-fallback__contactos span {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
        }

        /* Hero carousel */
        .hero-carousel {
            width: 100%;
            max-width: 100%;
            height: 100%;
            min-height: var(--hero-carousel-h);
        }
        .hero-carousel .carousel-inner,
        .hero-carousel .carousel-item {
            height: 100%;
        }
        .hero-carousel__inner {
            border: 1px solid rgba(255,255,255,.2);
            background: rgba(0,0,0,.15);
            min-height: 100%;
            height: 100%;
            border-radius: 16px;
            box-shadow: 0 16px 48px rgba(0,0,0,.22);
        }
        .hero-carousel__frame {
            width: 100%;
            height: 100%;
            min-height: var(--hero-carousel-h);
            max-height: none;
            overflow: hidden;
            border-radius: 16px;
        }
        .hero-carousel__img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .hero-carousel__caption {
            background: linear-gradient(transparent, rgba(0,0,0,.78));
            left: 0; right: 0; bottom: 0;
            padding: 2.5rem 1.25rem 1.25rem;
            text-align: left;
        }
        .hero-carousel__caption h5 { font-weight: 700; font-size: 1.15rem; text-shadow: 0 2px 8px rgba(0,0,0,.45); }
        .hero-carousel__caption p { font-size: .95rem; opacity: .95; }
        .hero-carousel__indicators [data-bs-target] {
            width: 10px; height: 10px; border-radius: 50%;
            background-color: rgba(255,255,255,.45);
            border: none;
        }
        .hero-carousel__indicators .active { background-color: #fff; transform: scale(1.15); }
        .hero-carousel__control { width: 10%; opacity: .9; }
        .hero-carousel.carousel-fade .carousel-item {
            opacity: 0;
            transition: opacity .85s ease-in-out;
        }
        .hero-carousel.carousel-fade .carousel-item.active {
            opacity: 1;
        }

        @media (min-width: 992px) {
            .hero-layout {
                display: grid;
                grid-template-columns: 1fr 1fr;
                align-items: stretch;
                height: 100dvh;
            }
            .hero-layout__content {
                padding: calc(var(--hero-nav-offset) + 2rem) clamp(1.5rem, 4vw, 3.5rem) 3rem;
                min-height: 100dvh;
            }
            .hero-layout__visual {
                height: 100dvh;
                min-height: 100dvh;
            }
            .hero-visual,
            .hero-carousel,
            .hero-carousel__inner,
            .hero-carousel__frame {
                min-height: 100dvh;
                height: 100dvh;
            }
            .hero-scroll-hint {
                left: 25%;
            }
            .hero-carousel__inner,
            .hero-carousel__frame {
                border-radius: 0;
                border: none;
                box-shadow: none;
            }
            .hero-carousel__inner {
                background: rgba(0,0,0,.12);
                border-left: 1px solid rgba(255,255,255,.12);
            }
            .glass-card {
                border-radius: 0;
                border-left: 1px solid rgba(255,255,255,.12);
            }
        }

        .hero-scroll-hint {
            position: absolute;
            bottom: 1rem;
            left: 50%;
            transform: translateX(-50%);
            z-index: 3;
            color: rgba(255,255,255,.7);
            text-decoration: none;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: .15rem;
            font-size: .78rem;
            font-weight: 500;
            letter-spacing: .04em;
            transition: color .25s;
            animation: hero-scroll-bounce 2.2s ease-in-out infinite;
        }
        .hero-scroll-hint i { font-size: 1.1rem; }
        .hero-scroll-hint:hover { color: #fff; }
        @keyframes hero-scroll-bounce {
            0%, 100% { transform: translateX(-50%) translateY(0); opacity: .85; }
            50% { transform: translateX(-50%) translateY(7px); opacity: 1; }
        }
        .orbit-dot {
            position: absolute; width: 12px; height: 12px; border-radius: 50%;
            animation: orbit 8s linear infinite;
        }
        .orbit-dot-1 { top: 10%; right: 20%; background: #34d399; animation-delay: 0s; }
        .orbit-dot-2 { bottom: 15%; left: 15%; background: #f472b6; animation-delay: 2s; }
        .orbit-dot-3 { top: 40%; right: 5%; background: #fbbf24; animation-delay: 4s; }
        @keyframes orbit { 0%,100% { transform: translate(0,0); } 25% { transform: translate(10px,-15px); } 50% { transform: translate(-5px,10px); } 75% { transform: translate(15px,5px); } }

        /* ── Stats ── */
        .stats-bar {
            background: #fff; padding: 2.5rem 0;
            border-bottom: 1px solid #e2e8f0;
            margin-top: -1px;
        }
        .stat-pill {
            display: flex; align-items: center; gap: .75rem;
            padding: .75rem 1.25rem; border-radius: 14px;
            background: var(--surface); transition: .3s;
        }
        .stat-pill:hover { transform: translateY(-2px); box-shadow: 0 4px 16px rgba(0,0,0,.06); }
        .stat-pill .ico { width: 44px; height: 44px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.15rem; color: #fff; flex-shrink: 0; }
        .stat-pill .val { font-size: 1.5rem; font-weight: 700; color: var(--text); line-height: 1; }
        .stat-pill .desc { font-size: .78rem; color: var(--text-muted); }

        .sec-tag {
            display: inline-block; font-size: .78rem; font-weight: 600;
            color: var(--primary);
            background: color-mix(in srgb, var(--primary) 10%, transparent);
            padding: .4rem 1.1rem; border-radius: 50px; margin-bottom: 1rem;
            letter-spacing: .06em; text-transform: uppercase;
        }
        .sec-title { font-size: clamp(1.6rem, 3vw, 2.5rem); font-weight: 800; letter-spacing: -.02em; margin-bottom: .75rem; }
        .sec-sub { font-size: 1.05rem; color: var(--text-muted); max-width: 620px; margin: 0 auto 2rem; line-height: 1.75; }
        .features .sec-sub { margin-bottom: 2.25rem; }

        /* Scroll reveal */
        .reveal {
            opacity: 0;
            transform: translateY(32px);
            transition: opacity .65s cubic-bezier(.22, 1, .36, 1), transform .65s cubic-bezier(.22, 1, .36, 1);
        }
        .reveal.is-visible {
            opacity: 1;
            transform: translateY(0);
        }
        .reveal-delay-1 { transition-delay: .08s; }
        .reveal-delay-2 { transition-delay: .16s; }
        .reveal-delay-3 { transition-delay: .24s; }
        .reveal-delay-4 { transition-delay: .32s; }
        .reveal-delay-5 { transition-delay: .4s; }

        /* ── Features ── */
        .features {
            padding: clamp(2rem, 4vw, 3.5rem) 0 clamp(4rem, 8vw, 6.5rem);
            background: linear-gradient(180deg, #fff 0%, var(--surface) 100%);
            position: relative;
            overflow: hidden;
        }
        .features::before {
            content: '';
            position: absolute; inset: 0;
            background-image: radial-gradient(circle at 1px 1px, color-mix(in srgb, var(--primary) 7%, transparent) 1px, transparent 0);
            background-size: 28px 28px;
            opacity: .45;
            pointer-events: none;
        }
        .features .container { position: relative; z-index: 1; }
        .feat-card {
            border: 1px solid #e2e8f0;
            border-radius: 20px;
            padding: 0;
            height: 100%;
            transition: transform .35s cubic-bezier(.22, 1, .36, 1), box-shadow .35s, border-color .35s;
            position: relative;
            overflow: hidden;
            background: rgba(255,255,255,.85);
            backdrop-filter: blur(8px);
            cursor: default;
            display: flex;
            flex-direction: column;
        }
        .feat-card::before {
            content: '';
            position: absolute; top: 0; left: 0; right: 0; height: 3px;
            background: linear-gradient(90deg, var(--primary), var(--accent));
            transform: scaleX(0); transform-origin: left;
            transition: transform .4s cubic-bezier(.22, 1, .36, 1);
        }
        .feat-card::after {
            content: '';
            position: absolute;
            width: 180px; height: 180px;
            border-radius: 50%;
            background: color-mix(in srgb, var(--primary) 6%, transparent);
            top: -90px; right: -90px;
            transition: transform .5s ease;
            pointer-events: none;
        }
        .feat-card:hover {
            border-color: color-mix(in srgb, var(--primary) 35%, #e2e8f0);
            transform: translateY(-8px);
            box-shadow: 0 20px 48px color-mix(in srgb, var(--primary) 12%, transparent);
        }
        .feat-card:hover::before { transform: scaleX(1); }
        .feat-card:hover::after { transform: scale(1.4); }
        .feat-ico {
            width: 58px; height: 58px; border-radius: 16px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.45rem; margin-bottom: 1.35rem;
            transition: transform .4s cubic-bezier(.34, 1.56, .64, 1);
            position: relative; z-index: 1;
        }
        .feat-card:hover .feat-ico { transform: scale(1.1) rotate(-4deg); }
        .feat-card:hover .feat-preview { transform: scale(1.03); }
        .feat-body { padding: 1.35rem 1.75rem 1.85rem; position: relative; z-index: 1; flex: 1; }
        .feat-card h3 { font-size: 1.12rem; font-weight: 700; margin-bottom: .65rem; }
        .feat-card p { font-size: .93rem; color: var(--text-muted); line-height: 1.75; margin: 0; }

        /* Feature mock previews */
        .feat-preview {
            height: 148px;
            overflow: hidden;
            border-bottom: 1px solid #e2e8f0;
            background: linear-gradient(145deg, var(--surface) 0%, color-mix(in srgb, var(--primary) 5%, #fff) 100%);
            transition: transform .45s cubic-bezier(.22, 1, .36, 1);
            transform-origin: center top;
        }
        .feat-mock--window {
            margin: 1rem 1.25rem 0;
            border-radius: 10px 10px 0 0;
            background: #fff;
            box-shadow: 0 8px 24px color-mix(in srgb, var(--primary) 10%, transparent);
            border: 1px solid #e2e8f0;
            height: calc(100% + 12px);
        }
        .feat-mock__bar {
            display: flex; gap: 5px; padding: .5rem .65rem;
            background: color-mix(in srgb, var(--primary) 8%, #f1f5f9);
            border-bottom: 1px solid #e2e8f0;
        }
        .feat-mock__bar span {
            width: 8px; height: 8px; border-radius: 50%;
            background: color-mix(in srgb, var(--primary) 35%, #cbd5e1);
        }
        .feat-mock__bar span:first-child { background: #f87171; }
        .feat-mock__bar span:nth-child(2) { background: #fbbf24; }
        .feat-mock__bar span:nth-child(3) { background: #34d399; }
        .feat-mock__body { padding: .65rem .75rem; font-size: .62rem; }
        .feat-mock-table__head,
        .feat-mock-table__row {
            display: grid;
            grid-template-columns: 2fr repeat(4, 1fr);
            gap: .25rem;
            padding: .2rem 0;
        }
        .feat-mock-table__head { color: var(--text-muted); font-weight: 600; border-bottom: 1px solid #e2e8f0; margin-bottom: .15rem; }
        .feat-mock-table__row strong { color: var(--primary); }
        .feat-mock-list__item {
            display: flex; align-items: center; gap: .5rem;
            padding: .35rem 0;
            border-bottom: 1px solid #f1f5f9;
            font-size: .68rem;
        }
        .feat-mock-list__item i { color: var(--primary); font-size: .85rem; }
        .feat-mock-list__item small { display: block; color: var(--text-muted); font-size: .58rem; }
        .feat-mock--doc {
            margin: 1.1rem auto 0;
            width: 72%;
            height: calc(100% - .5rem);
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 10px 28px color-mix(in srgb, var(--primary) 12%, transparent);
            padding: .85rem;
            position: relative;
            border: 1px solid #e2e8f0;
        }
        .feat-mock-doc__header {
            width: 40%; height: 8px; border-radius: 4px;
            background: color-mix(in srgb, var(--primary) 25%, #e2e8f0);
            margin-bottom: .65rem;
        }
        .feat-mock-doc__line {
            height: 5px; border-radius: 3px; background: #e2e8f0; margin-bottom: .4rem;
        }
        .feat-mock-doc__line.w-80 { width: 80%; }
        .feat-mock-doc__line.w-100 { width: 100%; }
        .feat-mock-doc__line.w-60 { width: 60%; }
        .feat-mock-doc__grid {
            display: grid; grid-template-columns: repeat(4, 1fr); gap: .3rem; margin-top: .5rem;
        }
        .feat-mock-doc__grid span {
            height: 18px; border-radius: 3px;
            background: color-mix(in srgb, var(--primary) 10%, #f1f5f9);
        }
        .feat-mock-doc__badge {
            position: absolute; bottom: .6rem; right: .6rem;
            font-size: .62rem; font-weight: 700; color: #ef4444;
            display: flex; align-items: center; gap: .2rem;
        }
        .feat-mock--chart {
            padding: 1.25rem 1.5rem 1rem;
            height: 100%;
            display: flex; flex-direction: column; justify-content: flex-end;
        }
        .feat-mock-chart__bars {
            display: flex; align-items: flex-end; gap: .45rem; height: 72px; margin-bottom: .65rem;
        }
        .feat-mock-chart__bars span {
            flex: 1; height: var(--h);
            border-radius: 4px 4px 0 0;
            background: linear-gradient(180deg, var(--accent), var(--primary));
            opacity: .85;
            animation: feat-bar-grow 1.2s ease-out backwards;
        }
        .feat-mock-chart__bars span:nth-child(2) { animation-delay: .1s; opacity: .7; }
        .feat-mock-chart__bars span:nth-child(3) { animation-delay: .2s; opacity: .75; }
        .feat-mock-chart__bars span:nth-child(4) { animation-delay: .3s; }
        .feat-mock-chart__bars span:nth-child(5) { animation-delay: .4s; opacity: .65; }
        @keyframes feat-bar-grow {
            from { transform: scaleY(0); transform-origin: bottom; }
            to { transform: scaleY(1); }
        }
        .feat-mock-chart__legend {
            display: flex; gap: 1rem; font-size: .62rem; color: var(--text-muted);
        }
        .feat-mock-chart__legend span { display: flex; align-items: center; gap: .3rem; }
        .feat-mock-chart__legend i {
            width: 8px; height: 8px; border-radius: 2px;
            background: var(--primary); display: inline-block;
        }
        .feat-mock--security {
            display: flex; align-items: center; justify-content: center;
            gap: 1.25rem; height: 100%; padding: 1rem 1.5rem;
        }
        .feat-mock-shield {
            width: 64px; height: 64px; border-radius: 50%;
            background: color-mix(in srgb, #ef4444 12%, #fff);
            color: #ef4444; font-size: 1.75rem;
            display: flex; align-items: center; justify-content: center;
            box-shadow: 0 8px 20px color-mix(in srgb, #ef4444 15%, transparent);
        }
        .feat-mock-log { font-size: .62rem; color: var(--text-muted); }
        .feat-mock-log div {
            display: flex; align-items: center; gap: .35rem;
            padding: .25rem 0;
        }
        .feat-mock-log span {
            width: 6px; height: 6px; border-radius: 50%; flex-shrink: 0;
        }
        .feat-mock-log .ok { background: #10b981; }
        .feat-mock-log .warn { background: #f59e0b; }
        .feat-mock--transition {
            display: flex; flex-wrap: wrap; align-items: center; justify-content: center;
            gap: .5rem 1rem; height: 100%; padding: 1.25rem;
        }
        .feat-mock-year {
            font-size: .75rem; font-weight: 700; padding: .4rem .75rem;
            border-radius: 8px; background: #fff;
            border: 1px solid #e2e8f0; color: var(--text-muted);
        }
        .feat-mock-year--new {
            border-color: color-mix(in srgb, var(--primary) 40%, #e2e8f0);
            color: var(--primary);
            background: color-mix(in srgb, var(--primary) 8%, #fff);
        }
        .feat-mock-arrow { color: var(--accent); font-size: 1.35rem; }
        .feat-mock-transfer {
            width: 100%; display: flex; justify-content: center; gap: .75rem;
            font-size: .62rem; color: var(--text-muted);
        }
        .feat-mock-transfer span {
            display: flex; align-items: center; gap: .2rem;
            background: #fff; padding: .25rem .5rem; border-radius: 6px;
            border: 1px solid #e2e8f0;
        }
        .feat-mock-transfer i { color: #10b981; }
        .feat-mock--chat {
            height: 100%;
            display: flex; flex-direction: column; justify-content: center;
            gap: .45rem; padding: 1rem 1.35rem;
        }
        .feat-mock-bubble {
            max-width: 82%;
            padding: .42rem .7rem;
            border-radius: 12px;
            font-size: .66rem;
            line-height: 1.45;
            box-shadow: 0 2px 8px rgba(0,0,0,.05);
        }
        .feat-mock-bubble--in {
            align-self: flex-start;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-bottom-left-radius: 4px;
            color: var(--text);
        }
        .feat-mock-bubble--out {
            align-self: flex-end;
            background: linear-gradient(135deg, var(--primary), color-mix(in srgb, var(--primary) 70%, var(--accent)));
            color: #fff;
            border-bottom-right-radius: 4px;
        }
        .feat-mock-badge {
            margin-left: auto;
            font-size: .56rem; font-weight: 700;
            padding: .18rem .45rem; border-radius: 50px;
            background: #f1f5f9; color: var(--text-muted);
            white-space: nowrap; flex-shrink: 0;
        }
        .feat-mock-badge--ok { background: color-mix(in srgb, #10b981 14%, #fff); color: #059669; }
        .feat-mock-badge--warn { background: color-mix(in srgb, #f59e0b 14%, #fff); color: #b45309; }
        [data-theme="dark"] .feat-mock-bubble--in { background: #1e293b; border-color: #334155; color: #e2e8f0; }
        [data-theme="dark"] .feat-mock-badge { background: #1e293b; }
        [data-theme="dark"] .feat-preview { background: linear-gradient(145deg, #0f172a 0%, #1e293b 100%); border-bottom-color: #334155; }
        [data-theme="dark"] .feat-mock--window,
        [data-theme="dark"] .feat-mock--doc,
        [data-theme="dark"] .feat-mock-year { background: #1e293b; border-color: #334155; }
        [data-theme="dark"] .feat-mock-doc__line { background: #334155; }
        [data-theme="dark"] .feat-mock-transfer span { background: #0f172a; border-color: #334155; }

        /* ── About ── */
        .about {
            padding: clamp(4rem, 8vw, 6rem) 0;
            background: linear-gradient(135deg,
                color-mix(in srgb, var(--primary) 6%, #f0f9ff) 0%,
                color-mix(in srgb, var(--accent) 8%, #ede9fe) 100%);
            position: relative;
        }
        .about-card {
            background: rgba(255,255,255,.92);
            border-radius: 24px;
            padding: clamp(1.75rem, 4vw, 2.75rem);
            box-shadow: 0 4px 24px rgba(0,0,0,.04);
            border: 1px solid rgba(255,255,255,.8);
            height: 100%;
            transition: transform .35s cubic-bezier(.22, 1, .36, 1), box-shadow .35s;
        }
        .about-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 24px 56px color-mix(in srgb, var(--primary) 10%, transparent);
        }
        .about-card h3 { font-size: clamp(1.15rem, 2.5vw, 1.35rem); font-weight: 700; margin-bottom: 1rem; }
        .check-list { list-style: none; padding: 0; margin: 0; }
        .check-list li {
            padding: .55rem 0;
            display: flex; align-items: start; gap: .75rem;
            color: var(--text-muted); font-size: .95rem;
            border-bottom: 1px solid color-mix(in srgb, var(--primary) 6%, transparent);
        }
        .check-list li:last-child { border-bottom: none; }
        .check-list li i {
            color: #10b981; margin-top: .2rem; flex-shrink: 0;
            font-size: 1.05rem;
        }
        .role-card {
            padding: 1.15rem 1.25rem;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            background: var(--surface);
            height: 100%;
            transition: transform .3s, border-color .3s, box-shadow .3s, background .3s;
        }
        .role-card:hover {
            transform: translateY(-4px);
            border-color: color-mix(in srgb, var(--primary) 30%, #e2e8f0);
            box-shadow: 0 12px 32px color-mix(in srgb, var(--primary) 8%, transparent);
            background: #fff;
        }
        .role-card__ico {
            width: 42px; height: 42px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.15rem; margin-bottom: .75rem;
            transition: transform .35s cubic-bezier(.34, 1.56, .64, 1);
        }
        .role-card:hover .role-card__ico { transform: scale(1.08); }
        .role-card strong { display: block; font-size: .92rem; margin-bottom: .35rem; }
        .role-card small { color: var(--text-muted); line-height: 1.55; font-size: .82rem; }

        /* ── CTA ── */
        .cta {
            padding: clamp(4rem, 8vw, 6rem) 0;
            background: linear-gradient(135deg, var(--hero-inicio) 0%, var(--primary) 50%, var(--hero-fim) 100%);
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        .cta::before {
            content: '';
            position: absolute; inset: 0;
            background: radial-gradient(circle at 20% 50%, rgba(255,255,255,.12) 0%, transparent 45%),
                        radial-gradient(circle at 80% 30%, color-mix(in srgb, var(--accent) 25%, transparent) 0%, transparent 40%);
            pointer-events: none;
        }
        .cta .container { position: relative; z-index: 1; }
        .cta h2 { font-size: clamp(1.5rem, 3vw, 2.35rem); font-weight: 800; color: #fff; margin-bottom: .85rem; letter-spacing: -.02em; }
        .cta p { color: rgba(255,255,255,.82); font-size: 1.05rem; margin-bottom: 2rem; max-width: 540px; margin-left: auto; margin-right: auto; line-height: 1.75; }
        .btn-cta {
            background: #fff; color: var(--primary); font-weight: 700;
            padding: .95rem 2.5rem; border-radius: 14px; border: none;
            font-size: 1.05rem; transition: transform .3s, box-shadow .3s;
            box-shadow: 0 8px 32px rgba(0,0,0,.2);
        }
        .btn-cta:hover {
            transform: translateY(-3px) scale(1.02);
            box-shadow: 0 16px 40px rgba(0,0,0,.28);
            color: var(--primary-dark);
        }

        /* Nav active section */
        .nav-link-c.is-active {
            color: var(--primary) !important;
            background: color-mix(in srgb, var(--primary) 10%, transparent);
        }
        .nav-link-c--aluno {
            font-weight: 600;
        }
        .nav-link-c--aluno.is-active {
            color: var(--accent) !important;
            background: color-mix(in srgb, var(--accent) 12%, transparent);
        }
        .nav-link-c--aluno:hover:not(.is-active) {
            background: color-mix(in srgb, var(--accent) 8%, transparent) !important;
            color: var(--accent) !important;
        }

        /* Audience gateway — removido; substituído por escola-strip */

        /* Aluno zone */
        .aluno-zone {
            padding: 1.75rem 0 1.25rem;
            background: #fff;
        }
        .aluno-panel {
            padding: clamp(1.25rem, 3vw, 1.75rem);
            border-radius: 20px;
            border: 1px solid color-mix(in srgb, var(--accent) 22%, #e2e8f0);
            background: linear-gradient(135deg,
                color-mix(in srgb, var(--accent) 4%, #fff) 0%,
                color-mix(in srgb, var(--primary) 3%, #fff) 100%);
            box-shadow: 0 8px 32px color-mix(in srgb, var(--accent) 8%, transparent);
        }
        .sec-tag--aluno {
            background: color-mix(in srgb, var(--accent) 12%, transparent);
            color: var(--accent);
            margin-bottom: .65rem;
        }
        .aluno-panel__title {
            font-size: clamp(1.35rem, 2.8vw, 1.85rem);
            font-weight: 800;
            letter-spacing: -.02em;
            margin-bottom: .5rem;
            line-height: 1.2;
        }
        .aluno-panel__sub {
            font-size: .95rem;
            color: var(--text-muted);
            line-height: 1.6;
            margin-bottom: 1.1rem;
            max-width: 36ch;
        }
        .aluno-benefits-row {
            display: flex;
            flex-direction: column;
            gap: .65rem;
        }
        .aluno-benefit--compact {
            display: flex;
            align-items: flex-start;
            gap: .85rem;
            text-align: left;
            padding: .85rem 1rem;
            border-radius: 14px;
            border: 1px solid #e2e8f0;
            background: rgba(255,255,255,.92);
            transition: border-color .25s, box-shadow .25s;
        }
        .aluno-benefit--compact:hover {
            border-color: color-mix(in srgb, var(--accent) 35%, #e2e8f0);
            box-shadow: 0 6px 20px color-mix(in srgb, var(--accent) 8%, transparent);
        }
        .aluno-benefit--compact .aluno-benefit__ico {
            width: 42px; height: 42px; border-radius: 12px;
            font-size: 1.1rem; margin-bottom: 0; flex-shrink: 0;
        }
        .aluno-benefit--compact h4 {
            font-size: .92rem; font-weight: 700; margin-bottom: .15rem;
        }
        .aluno-benefit--compact p {
            font-size: .8rem; color: var(--text-muted); line-height: 1.45; margin: 0;
        }
        .btn-aluno-portal {
            background: linear-gradient(135deg, var(--accent), var(--primary));
            color: #fff !important;
            font-weight: 700;
            padding: .75rem 1.65rem;
            border-radius: 12px;
            border: none;
            font-size: .98rem;
            transition: transform .3s, box-shadow .3s;
            box-shadow: 0 6px 24px color-mix(in srgb, var(--accent) 30%, transparent);
        }
        .btn-aluno-portal:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 28px color-mix(in srgb, var(--accent) 40%, transparent);
            color: #fff !important;
        }
        .aluno-zone__hint {
            font-size: .8rem;
            color: var(--text-muted);
            margin: .65rem 0 0;
        }

        /* Escola strip */
        .escola-strip {
            padding: 0 0 1.5rem;
            background: #fff;
        }
        .escola-strip__link {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: .85rem 1.15rem;
            border-radius: 14px;
            border: 1px solid #e2e8f0;
            background: var(--surface);
            text-decoration: none;
            color: inherit;
            transition: border-color .25s, background .25s, transform .25s;
        }
        .escola-strip__link:hover {
            border-color: color-mix(in srgb, var(--primary) 30%, #e2e8f0);
            background: color-mix(in srgb, var(--primary) 4%, var(--surface));
            color: inherit;
            transform: translateY(-1px);
        }
        .escola-strip__ico {
            width: 40px; height: 40px; border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            background: color-mix(in srgb, var(--primary) 12%, transparent);
            color: var(--primary); font-size: 1.1rem; flex-shrink: 0;
        }
        .escola-strip__text {
            flex: 1;
            display: flex; flex-direction: column; gap: .1rem;
        }
        .escola-strip__text strong { font-size: .92rem; }
        .escola-strip__text small { color: var(--text-muted); font-size: .8rem; }
        .escola-strip__arrow { color: var(--primary); opacity: .7; }
        .btn-hero-aluno {
            background: linear-gradient(135deg, var(--accent), color-mix(in srgb, var(--primary) 80%, var(--accent)));
            color: #fff !important;
            font-weight: 700; padding: .9rem 2rem; border-radius: 12px; border: none;
            font-size: 1rem; transition: .3s;
            box-shadow: 0 8px 28px color-mix(in srgb, var(--accent) 35%, transparent);
        }
        .btn-hero-aluno:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 36px color-mix(in srgb, var(--accent) 45%, transparent);
            color: #fff !important;
        }

        /* Dual CTA */
        .cta-dual { padding: clamp(4rem, 8vw, 6rem) 0; background: var(--surface); }
        .cta-dual__intro { text-align: center; margin-bottom: 2.5rem; }
        .cta-box {
            height: 100%;
            padding: 2.25rem 2rem;
            border-radius: 22px;
            border: 1px solid #e2e8f0;
            background: #fff;
            text-align: center;
            transition: transform .35s, box-shadow .35s;
        }
        .cta-box:hover { transform: translateY(-6px); box-shadow: 0 20px 48px color-mix(in srgb, var(--primary) 8%, transparent); }
        .cta-box--aluno { border-top: 4px solid var(--accent); }
        .cta-box--escola { border-top: 4px solid var(--primary); }
        .cta-box__ico {
            width: 52px; height: 52px; border-radius: 14px;
            display: inline-flex; align-items: center; justify-content: center;
            font-size: 1.35rem; margin-bottom: 1rem;
        }
        .cta-box--aluno .cta-box__ico { background: color-mix(in srgb, var(--accent) 12%, transparent); color: var(--accent); }
        .cta-box--escola .cta-box__ico { background: color-mix(in srgb, var(--primary) 12%, transparent); color: var(--primary); }
        .cta-box h3 { font-size: 1.2rem; font-weight: 800; margin-bottom: .65rem; }
        .cta-box p { color: var(--text-muted); font-size: .95rem; line-height: 1.65; margin-bottom: 1.5rem; }
        .btn-cta-aluno {
            background: linear-gradient(135deg, var(--accent), var(--primary));
            color: #fff !important; font-weight: 700;
            padding: .85rem 2rem; border-radius: 12px; border: none;
            transition: transform .3s, box-shadow .3s;
        }
        .btn-cta-aluno:hover { transform: translateY(-2px); color: #fff !important; box-shadow: 0 10px 28px color-mix(in srgb, var(--accent) 30%, transparent); }
        .btn-cta-escola {
            background: var(--primary); color: #fff !important; font-weight: 700;
            padding: .85rem 2rem; border-radius: 12px; border: none;
            transition: transform .3s, box-shadow .3s;
        }
        .btn-cta-escola:hover { transform: translateY(-2px); background: var(--primary-dark); color: #fff !important; }

        [data-theme="dark"] .aluno-zone { background: #0f172a; }
        [data-theme="dark"] .aluno-panel {
            background: linear-gradient(135deg, #1e293b 0%, color-mix(in srgb, var(--primary) 12%, #1e293b) 100%);
            border-color: #334155;
        }
        [data-theme="dark"] .aluno-benefit--compact { background: #0f172a; border-color: #334155; }
        [data-theme="dark"] .aluno-benefit--compact h4 { color: #e2e8f0; }
        [data-theme="dark"] .aluno-panel__title { color: #e2e8f0; }
        [data-theme="dark"] .escola-strip { background: #0f172a; }
        [data-theme="dark"] .escola-strip__link { background: #1e293b; border-color: #334155; }
        [data-theme="dark"] .escola-strip__text strong { color: #e2e8f0; }
        [data-theme="dark"] .cta-dual { background: #0f172a; }
        [data-theme="dark"] .cta-box { background: #1e293b; border-color: #334155; }
        [data-theme="dark"] .cta-box h3 { color: #e2e8f0; }

        /* ── Secção IA ── */
        .ia-section {
            position: relative;
            padding: clamp(4rem, 8vw, 6.5rem) 0;
            background:
                radial-gradient(900px 420px at 85% 10%, color-mix(in srgb, var(--accent) 22%, transparent), transparent 55%),
                radial-gradient(700px 380px at 10% 90%, color-mix(in srgb, var(--primary) 30%, transparent), transparent 50%),
                linear-gradient(150deg, #0f172a 0%, #111c33 55%, #0f172a 100%);
            color: #e2e8f0;
            overflow: hidden;
        }
        .ia-section::before {
            content: '';
            position: absolute; inset: 0;
            background-image: radial-gradient(circle at 1px 1px, rgba(148,163,184,.12) 1px, transparent 0);
            background-size: 26px 26px;
            pointer-events: none;
        }
        .ia-section .container { position: relative; z-index: 1; }
        .sec-tag--ia {
            background: linear-gradient(135deg, color-mix(in srgb, var(--accent) 25%, transparent), color-mix(in srgb, var(--primary) 25%, transparent));
            color: #fff;
            border: 1px solid rgba(255,255,255,.18);
        }
        .ia-title { font-size: clamp(1.7rem, 3.4vw, 2.6rem); font-weight: 800; color: #fff; letter-spacing: -.02em; margin-bottom: 1rem; }
        .ia-title .gradient-text {
            background: linear-gradient(120deg, #7dd3fc, #c4b5fd, #f0abfc);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        }
        .ia-sub { color: rgba(226,232,240,.78); font-size: 1.05rem; line-height: 1.8; max-width: 520px; margin-bottom: 1.75rem; }
        .ia-list { display: flex; flex-direction: column; gap: .75rem; }
        .ia-item {
            display: flex; align-items: flex-start; gap: .85rem;
            padding: .85rem 1rem; border-radius: 14px;
            background: rgba(255,255,255,.05);
            border: 1px solid rgba(255,255,255,.09);
            transition: background .3s, border-color .3s, transform .3s;
        }
        .ia-item:hover {
            background: rgba(255,255,255,.09);
            border-color: rgba(255,255,255,.2);
            transform: translateX(4px);
        }
        .ia-item__ico {
            width: 40px; height: 40px; border-radius: 11px; flex-shrink: 0;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.1rem; color: #fff;
            background: linear-gradient(135deg, color-mix(in srgb, var(--accent) 65%, #6366f1), color-mix(in srgb, var(--primary) 80%, #4f46e5));
            box-shadow: 0 6px 18px rgba(99,102,241,.35);
        }
        .ia-item strong { display: block; color: #fff; font-size: .95rem; margin-bottom: .1rem; }
        .ia-item small { color: rgba(226,232,240,.68); font-size: .82rem; line-height: 1.5; }

        .ia-chat {
            background: rgba(15,23,42,.65);
            border: 1px solid rgba(255,255,255,.12);
            border-radius: 22px;
            box-shadow: 0 24px 64px rgba(0,0,0,.45);
            overflow: hidden;
            backdrop-filter: blur(12px);
            max-width: 440px;
            margin: 0 auto;
        }
        .ia-chat__head {
            display: flex; align-items: center; gap: .65rem;
            padding: .85rem 1.1rem;
            border-bottom: 1px solid rgba(255,255,255,.08);
            background: rgba(255,255,255,.04);
        }
        .ia-chat__avatar {
            width: 34px; height: 34px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            background: linear-gradient(135deg, #818cf8, #c084fc);
            color: #fff; font-size: 1rem;
            box-shadow: 0 0 0 3px rgba(129,140,248,.25);
        }
        .ia-chat__head strong { color: #fff; font-size: .92rem; display: block; }
        .ia-chat__head small { color: #34d399; font-size: .72rem; display: flex; align-items: center; gap: .3rem; }
        .ia-chat__head small::before { content: ''; width: 7px; height: 7px; border-radius: 50%; background: #34d399; animation: pulse 2s infinite; }
        .ia-chat__body { padding: 1.1rem; display: flex; flex-direction: column; gap: .7rem; min-height: 260px; }
        .ia-msg { max-width: 85%; padding: .6rem .85rem; border-radius: 14px; font-size: .84rem; line-height: 1.55; }
        .ia-msg--user { align-self: flex-end; background: linear-gradient(135deg, color-mix(in srgb, var(--primary) 85%, #6366f1), #6366f1); color: #fff; border-bottom-right-radius: 4px; }
        .ia-msg--bot { align-self: flex-start; background: rgba(255,255,255,.08); color: #e2e8f0; border: 1px solid rgba(255,255,255,.09); border-bottom-left-radius: 4px; }
        .ia-msg--bot .ia-msg__meta { display: block; margin-top: .35rem; color: #94a3b8; font-size: .7rem; }
        .ia-typing { display: inline-flex; gap: .25rem; align-self: flex-start; padding: .6rem .85rem; background: rgba(255,255,255,.08); border-radius: 14px; border-bottom-left-radius: 4px; }
        .ia-typing span { width: 6px; height: 6px; border-radius: 50%; background: #94a3b8; animation: ia-typing 1.2s infinite; }
        .ia-typing span:nth-child(2) { animation-delay: .18s; }
        .ia-typing span:nth-child(3) { animation-delay: .36s; }
        @keyframes ia-typing { 0%, 60%, 100% { transform: translateY(0); opacity: .5; } 30% { transform: translateY(-4px); opacity: 1; } }
        .ia-chips { display: flex; flex-wrap: wrap; gap: .5rem; margin-top: 1.5rem; }
        .ia-chip {
            display: inline-flex; align-items: center; gap: .4rem;
            padding: .45rem .85rem; border-radius: 50px;
            font-size: .78rem; font-weight: 500;
            color: rgba(226,232,240,.9);
            background: rgba(255,255,255,.06);
            border: 1px solid rgba(255,255,255,.12);
        }
        .ia-chip i { color: #a5b4fc; }

        /* ── Secção Encarregado ── */
        .enc-section {
            padding: clamp(4rem, 8vw, 6rem) 0;
            background: linear-gradient(180deg, var(--surface) 0%, #fff 100%);
        }
        .enc-grid-card {
            height: 100%;
            padding: 1.35rem 1.4rem;
            border-radius: 18px;
            border: 1px solid #e2e8f0;
            background: #fff;
            transition: transform .32s cubic-bezier(.22,1,.36,1), box-shadow .32s, border-color .32s;
        }
        .enc-grid-card:hover {
            transform: translateY(-6px);
            border-color: color-mix(in srgb, var(--primary) 32%, #e2e8f0);
            box-shadow: 0 16px 40px color-mix(in srgb, var(--primary) 10%, transparent);
        }
        .enc-grid-card__ico {
            width: 46px; height: 46px; border-radius: 13px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.2rem; margin-bottom: .85rem;
            transition: transform .35s cubic-bezier(.34,1.56,.64,1);
        }
        .enc-grid-card:hover .enc-grid-card__ico { transform: scale(1.1) rotate(-4deg); }
        .enc-grid-card strong { display: block; font-size: .98rem; margin-bottom: .3rem; }
        .enc-grid-card p { font-size: .85rem; color: var(--text-muted); line-height: 1.6; margin: 0; }
        .btn-enc-portal {
            background: linear-gradient(135deg, var(--primary), color-mix(in srgb, var(--primary) 65%, var(--accent)));
            color: #fff !important; font-weight: 700;
            padding: .85rem 2rem; border-radius: 12px; border: none;
            font-size: 1rem; transition: transform .3s, box-shadow .3s;
            box-shadow: 0 8px 26px color-mix(in srgb, var(--primary) 32%, transparent);
        }
        .btn-enc-portal:hover { transform: translateY(-2px); box-shadow: 0 12px 34px color-mix(in srgb, var(--primary) 42%, transparent); color: #fff !important; }

        /* ── Secção Perfis ── */
        .perfis-section {
            padding: clamp(4rem, 8vw, 6rem) 0;
            background: #fff;
        }

        [data-theme="dark"] .ia-section::before { opacity: .6; }
        [data-theme="dark"] .enc-section { background: linear-gradient(180deg, #1e293b 0%, #0f172a 100%); }
        [data-theme="dark"] .enc-grid-card { background: #1e293b; border-color: #334155; }
        [data-theme="dark"] .enc-grid-card strong { color: #e2e8f0; }
        [data-theme="dark"] .perfis-section { background: #0f172a; }

        /* ── Footer ── */
        .footer { background: #0f172a; color: #fff; padding: 3.5rem 0 1.5rem; }
        .footer-brand { margin-bottom: .75rem; }
        .footer-brand img { height: 36px; width: auto; max-width: 170px; object-fit: contain; display: block; }
        .offcanvas-brand img { height: 34px; width: auto; max-width: 160px; object-fit: contain; display: block; }
        .footer-text { color: rgba(255,255,255,.55); font-size: .9rem; line-height: 1.7; }
        .footer-title { font-weight: 600; font-size: .9rem; margin-bottom: 1rem; color: rgba(255,255,255,.9); text-transform: uppercase; letter-spacing: .06em; }
        .footer-links { list-style: none; padding: 0; }
        .footer-links li { margin-bottom: .5rem; }
        .footer-links a { color: rgba(255,255,255,.55); text-decoration: none; font-size: .9rem; transition: .2s; }
        .footer-links a:hover { color: var(--accent); }
        .footer hr { border-color: rgba(255,255,255,.08); margin: 2rem 0 1.5rem; }
        .footer-copy { color: rgba(255,255,255,.4); font-size: .82rem; }

        /* ── Dark Mode ── */
        [data-theme="dark"] { --text: #e2e8f0; --text-muted: #94a3b8; --surface: #1e293b; }
        [data-theme="dark"] .nav-main { background: rgba(15,23,42,.92); border-bottom-color: rgba(255,255,255,.06); }
        [data-theme="dark"] .nav-main:not(.scrolled) {
            background: rgba(15,23,42,.88);
            border-bottom-color: rgba(255,255,255,.08);
        }
        [data-theme="dark"] .nav-main:not(.scrolled) .nav-link-c { color: #e2e8f0 !important; }
        [data-theme="dark"] .nav-link-c { color: #e2e8f0 !important; }
        [data-theme="dark"] .nav-link-c:hover { background: rgba(129,140,248,.1); color: #818cf8 !important; }
        [data-theme="dark"] .btn-enter { border-color: #818cf8; color: #818cf8 !important; }
        [data-theme="dark"] .btn-enter:hover { background: #818cf8; color: #fff !important; }
        [data-theme="dark"] .stats-bar { background: #1e293b; border-bottom-color: #334155; }
        [data-theme="dark"] .stat-pill { background: #0f172a; }
        [data-theme="dark"] .stat-pill .val { color: #e2e8f0; }
        [data-theme="dark"] .features { background: linear-gradient(180deg, #0f172a 0%, #1e293b 100%); }
        [data-theme="dark"] .features::before {
            background-image: radial-gradient(circle at 1px 1px, rgba(129,140,248,.15) 1px, transparent 0);
        }
        [data-theme="dark"] .feat-card { border-color: #334155; background: rgba(30,41,59,.9); }
        [data-theme="dark"] .feat-card:hover { border-color: #818cf8; box-shadow: 0 20px 48px rgba(0,0,0,.35); }
        [data-theme="dark"] .feat-card h3 { color: #e2e8f0; }
        [data-theme="dark"] .about {
            background: linear-gradient(135deg, #0f172a 0%, color-mix(in srgb, var(--primary) 15%, #1e1b4b) 100%);
        }
        [data-theme="dark"] .about-card { background: rgba(30,41,59,.95); border-color: #334155; box-shadow: 0 4px 24px rgba(0,0,0,.2); }
        [data-theme="dark"] .about-card h3 { color: #e2e8f0; }
        [data-theme="dark"] .role-card { background: #0f172a; border-color: #334155; }
        [data-theme="dark"] .role-card:hover { background: #1e293b; border-color: #818cf8; }
        [data-theme="dark"] .check-list li { border-bottom-color: #334155; }
        [data-theme="dark"] .sec-tag { background: rgba(129,140,248,.12); color: #818cf8; }
        [data-theme="dark"] .offcanvas { background: #1e293b !important; }
        [data-theme="dark"] .offcanvas a { color: #e2e8f0 !important; }

        .welcome-theme-toggle {
            position: fixed; bottom: 1.5rem; right: 1.5rem; z-index: 1050;
            background: color-mix(in srgb, var(--primary) 90%, transparent); backdrop-filter: blur(10px);
            border: none; color: #fff; width: 48px; height: 48px;
            border-radius: 50%; display: flex; align-items: center; justify-content: center;
            cursor: pointer; transition: .3s; font-size: 1.2rem;
            box-shadow: 0 4px 20px color-mix(in srgb, var(--primary) 40%, transparent);
        }
        .welcome-theme-toggle:hover { transform: scale(1.1); box-shadow: 0 6px 28px color-mix(in srgb, var(--primary) 50%, transparent); }
        [data-theme="dark"] .welcome-theme-toggle { background: rgba(251,191,36,.9); color: #1e293b; box-shadow: 0 4px 20px rgba(251,191,36,.3); }

        /* ── Responsive ── */
        @media (max-width: 991.98px) {
            :root { --hero-carousel-h: min(50dvh, 420px); --hero-panel-h: auto; }
            .hero {
                height: auto;
                min-height: 100dvh;
            }
            .hero-layout__content {
                padding: calc(var(--hero-nav-offset) + 1rem) 1.25rem 1rem;
            }
            .hero-layout__visual {
                padding: 0 1.25rem 3.5rem;
            }
            .hero h1 { font-size: clamp(1.85rem, 6vw, 2.6rem); margin-bottom: 1rem; }
            .hero-sub { font-size: 1rem; margin-bottom: 1.5rem; }
        }
        @media (max-width: 767px) {
            .stat-pill { justify-content: center; }
            .features, .about { padding: 3.5rem 0; }
            .feat-body { padding: 1.25rem 1.35rem 1.5rem; }
            .feat-preview { height: 128px; }
            .about-card { padding: 1.5rem; }
            .role-card { padding: 1rem; }
            .nav-main .container { padding-left: 1rem; padding-right: 1rem; }
        }
        @media (prefers-reduced-motion: reduce) {
            .reveal { opacity: 1; transform: none; transition: none; }
            .feat-card, .about-card, .role-card, .btn-cta { transition: none; }
            .hero-carousel.carousel-fade .carousel-item { transition: none; }
            .feat-mock-chart__bars span { animation: none; }
        }
    </style>
</head>
<body>
@php
    $logoUrl = ($paginaPublica['usar_logo_instituicao'] ?? false) && !empty($instituicao['logo'])
        ? \App\Support\PublicStorage::url($instituicao['logo'])
        : asset('images/facilita.png');
    $logoAlt = !empty($instituicao['nome']) ? $instituicao['nome'] : 'Facilita — Sistema Académico';
    $urlPortalAluno = auth()->check() && auth()->user()->hasRole('aluno')
        ? route('portal.aluno.dashboard')
        : route('login');
    $urlAreaEscola = auth()->check() && ! auth()->user()->hasRole('aluno')
        ? route('dashboard')
        : route('login');
    $portalAlunoActivo = $portalAlunoActivo ?? true;
    $mostrarRegisto = ($registoPublicoActivo ?? true) && ($mostrarRegistoPublico ?? true) && Route::has('register');
@endphp

<!-- Navbar -->
<nav class="nav-main" id="navbar">
    <div class="container-fluid px-3 px-lg-5">
        <div class="d-flex justify-content-between align-items-center">
            <a class="nav-brand" href="/">
                <img src="{{ $logoUrl }}" alt="{{ $logoAlt }}">
            </a>

            <div class="d-flex align-items-center gap-2">
                @if($portalAlunoActivo)
                <a href="#portal-aluno" class="nav-link-c nav-link-c--aluno d-none d-md-inline-block" data-nav-section="portal-aluno">Portal do Aluno</a>
                @endif
                <a href="#funcionalidades" class="nav-link-c d-none d-md-inline-block" data-nav-section="funcionalidades">Funcionalidades</a>
                <a href="#inteligencia-artificial" class="nav-link-c d-none d-lg-inline-block" data-nav-section="inteligencia-artificial"><i class="bi bi-stars me-1"></i>IA</a>
                <a href="#portal-encarregado" class="nav-link-c d-none d-lg-inline-block" data-nav-section="portal-encarregado">Encarregados</a>
                <a href="#sobre" class="nav-link-c d-none d-md-inline-block" data-nav-section="sobre">Sobre</a>
                @if (Route::has('login'))
                    @auth
                        <a href="{{ route('dashboard') }}" class="btn btn-go ms-1">
                            <i class="bi bi-speedometer2 me-1"></i>Dashboard
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="btn btn-enter d-none d-sm-inline-block">Entrar</a>
                        @if ($mostrarRegisto)
                            <a href="{{ route('register') }}" class="btn btn-go d-none d-sm-inline-block">Registar</a>
                        @endif
                    @endauth
                @endif

                <button class="btn d-md-none border-0 p-1 nav-menu-btn" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileMenu" aria-label="Menu">
                    <i class="bi bi-list" style="font-size: 1.5rem; color: var(--primary);"></i>
                </button>
            </div>
        </div>
    </div>
</nav>

<!-- Mobile Offcanvas Menu -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="mobileMenu">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title fw-bold offcanvas-brand">
            <img src="{{ $logoUrl }}" alt="{{ $logoAlt }}">
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Fechar"></button>
    </div>
    <div class="offcanvas-body">
        <div class="d-flex flex-column gap-2">
            @if($portalAlunoActivo)
            <a href="#portal-aluno" class="btn btn-light text-start" data-bs-dismiss="offcanvas">
                <i class="bi bi-mortarboard-fill me-2"></i>Portal do Aluno
            </a>
            @endif
            <a href="#funcionalidades" class="btn btn-light text-start" data-bs-dismiss="offcanvas">
                <i class="bi bi-grid-fill me-2"></i>Funcionalidades
            </a>
            <a href="#inteligencia-artificial" class="btn btn-light text-start" data-bs-dismiss="offcanvas">
                <i class="bi bi-stars me-2"></i>Inteligência Artificial
            </a>
            <a href="#portal-encarregado" class="btn btn-light text-start" data-bs-dismiss="offcanvas">
                <i class="bi bi-people-fill me-2"></i>Encarregados
            </a>
            <a href="#perfis" class="btn btn-light text-start" data-bs-dismiss="offcanvas">
                <i class="bi bi-person-lines-fill me-2"></i>Perfis de Acesso
            </a>
            <a href="#sobre" class="btn btn-light text-start" data-bs-dismiss="offcanvas">
                <i class="bi bi-info-circle-fill me-2"></i>Sobre
            </a>
            <hr>
            @if (Route::has('login'))
                @auth
                    <a href="{{ route('dashboard') }}" class="btn btn-go w-100">
                        <i class="bi bi-speedometer2 me-1"></i>Dashboard
                    </a>
                @else
                    <a href="{{ route('login') }}" class="btn btn-enter w-100">
                        <i class="bi bi-box-arrow-in-right me-1"></i>Entrar
                    </a>
                    @if ($mostrarRegisto)
                        <a href="{{ route('register') }}" class="btn btn-go w-100">
                            <i class="bi bi-person-plus me-1"></i>Registar
                        </a>
                    @endif
                @endauth
            @endif
        </div>
    </div>
</div>

<!-- Hero -->
@php
    use App\Support\PaginaPublicaConfig;

    $mostrarCarouselHero = ($paginaPublica['carousel_activo'] ?? false)
        && count($paginaPublica['carousel_slides'] ?? []) >= PaginaPublicaConfig::CAROUSEL_MINIMO;
@endphp
<section class="hero {{ $mostrarCarouselHero ? '' : 'hero--sem-carousel' }}">
    <div class="orbit-dot orbit-dot-1"></div>
    <div class="orbit-dot orbit-dot-2"></div>
    <div class="orbit-dot orbit-dot-3"></div>
    <div class="hero-layout">
        <div class="hero-layout__content">
            <div class="hero-content">
                @if($paginaPublica['mostrar_ano_lectivo'])
                <div class="hero-badge">
                    <span class="dot"></span>
                    {{ $paginaPublica['badge'] }}
                </div>
                @endif
                <h1>{{ $paginaPublica['titulo'] }}<br><span class="gradient-text">{{ $paginaPublica['destaque'] }}</span></h1>
                <p class="hero-sub">{{ $paginaPublica['subtitulo'] }}</p>
                <div class="hero-actions">
                    @auth
                        @if(auth()->user()->hasRole('aluno'))
                            <a href="{{ route('portal.aluno.dashboard') }}" class="btn btn-hero-aluno">
                                <i class="bi bi-mortarboard me-2"></i>O meu portal
                            </a>
                        @else
                            <a href="{{ route('dashboard') }}" class="btn btn-hero-w">
                                <i class="bi bi-speedometer2 me-2"></i>Aceder ao Sistema
                            </a>
                        @endif
                    @else
                        @if($portalAlunoActivo)
                        <a href="{{ $urlPortalAluno }}" class="btn btn-hero-aluno">
                            <i class="bi bi-mortarboard me-2"></i>Portal do Aluno
                        </a>
                        @endif
                        <a href="{{ $urlAreaEscola }}" class="btn btn-hero-w">
                            <i class="bi bi-building me-2"></i>Área da Escola
                        </a>
                        @if ($mostrarRegisto)
                            <a href="{{ route('register') }}" class="btn btn-hero-o">
                                <i class="bi bi-person-plus me-2"></i>Criar Conta
                            </a>
                        @endif
                    @endauth
                </div>
                @unless($mostrarCarouselHero)
                    @include('welcome.partials.hero-fallback')
                @endunless
            </div>
        </div>
        @if($mostrarCarouselHero)
        <div class="hero-layout__visual">
            <div class="hero-visual">
                @include('welcome.partials.hero-carousel', [
                    'mostrarCarousel' => true,
                    'slides' => $paginaPublica['carousel_slides'] ?? [],
                    'intervalo' => ($paginaPublica['carousel_intervalo'] ?? 5) * 1000,
                ])
            </div>
        </div>
        @endif
    </div>
    <a href="{{ $portalAlunoActivo ? '#portal-aluno' : '#funcionalidades' }}" class="hero-scroll-hint" aria-label="{{ $portalAlunoActivo ? 'Ver portal do aluno' : 'Ver funcionalidades' }}">
        <span>{{ $portalAlunoActivo ? 'Portal do Aluno' : 'Funcionalidades' }}</span>
        <i class="bi bi-chevron-down"></i>
    </a>
</section>

@include('welcome.partials.aluno-zone')

<!-- Stats Bar (mobile-friendly) -->
@isset($stats)
<section class="stats-bar d-lg-none">
    <div class="container">
        <div class="row g-2">
            <div class="col-6">
                <div class="stat-pill">
                    <div class="ico" style="background: linear-gradient(135deg, #6366f1, #8b5cf6);">
                        <i class="bi bi-people-fill"></i>
                    </div>
                    <div>
                        <div class="val">{{ number_format($stats['alunos'] ?? 0) }}</div>
                        <div class="desc">Alunos</div>
                    </div>
                </div>
            </div>
            <div class="col-6">
                <div class="stat-pill">
                    <div class="ico" style="background: linear-gradient(135deg, #06b6d4, #0284c7);">
                        <i class="bi bi-person-workspace"></i>
                    </div>
                    <div>
                        <div class="val">{{ $stats['professores'] ?? 0 }}</div>
                        <div class="desc">Professores</div>
                    </div>
                </div>
            </div>
            <div class="col-6">
                <div class="stat-pill">
                    <div class="ico" style="background: linear-gradient(135deg, #10b981, #059669);">
                        <i class="bi bi-collection-fill"></i>
                    </div>
                    <div>
                        <div class="val">{{ $stats['turmas'] ?? 0 }}</div>
                        <div class="desc">Turmas</div>
                    </div>
                </div>
            </div>
            <div class="col-6">
                <div class="stat-pill">
                    <div class="ico" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
                        <i class="bi bi-mortarboard-fill"></i>
                    </div>
                    <div>
                        <div class="val">{{ $stats['cursos'] ?? 0 }}</div>
                        <div class="desc">Cursos</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
@endisset

<!-- Features -->
<section class="features" id="funcionalidades">
    <div class="container">
        <div class="text-center reveal">
            <span class="sec-tag">Para a escola</span>
            <h2 class="sec-title">{{ $paginaPublica['func_titulo'] }}</h2>
            <p class="sec-sub">{{ $paginaPublica['func_subtitulo'] }}</p>
        </div>
        <div class="row g-4">
            <div class="col-lg-4 col-md-6 reveal reveal-delay-1">
                <div class="feat-card">
                    @include('welcome.partials.feat-preview', ['tipo' => 'notas'])
                    <div class="feat-body">
                        <div class="feat-ico" style="background: color-mix(in srgb, var(--primary) 8%, transparent); color: var(--primary);">
                            <i class="bi bi-journal-bookmark-fill"></i>
                        </div>
                        <h3>Lançamento de Notas</h3>
                        <p>Lançamento de MAC, PP e PT com cálculo automático da Média Trimestral. Suporte a lançamento individual e em lote.</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 col-md-6 reveal reveal-delay-2">
                <div class="feat-card">
                    @include('welcome.partials.feat-preview', ['tipo' => 'turmas'])
                    <div class="feat-body">
                        <div class="feat-ico" style="background: color-mix(in srgb, var(--accent) 8%, transparent); color: var(--accent);">
                            <i class="bi bi-people-fill"></i>
                        </div>
                        <h3>Gestão de Turmas</h3>
                        <p>Organização de turmas por curso, classe, sala e turno. Atribuição de professores e directores de turma.</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 col-md-6 reveal reveal-delay-3">
                <div class="feat-card">
                    @include('welcome.partials.feat-preview', ['tipo' => 'boletins'])
                    <div class="feat-body">
                        <div class="feat-ico" style="background: rgba(16,185,129,.08); color: #10b981;">
                            <i class="bi bi-file-earmark-pdf-fill"></i>
                        </div>
                        <h3>Boletins e Pautas</h3>
                        <p>Geração automática de boletins trimestrais e anuais, mini-pautas e pautas finais em PDF e Excel.</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 col-md-6 reveal reveal-delay-1">
                <div class="feat-card">
                    @include('welcome.partials.feat-preview', ['tipo' => 'stats'])
                    <div class="feat-body">
                        <div class="feat-ico" style="background: rgba(245,158,11,.08); color: #f59e0b;">
                            <i class="bi bi-graph-up-arrow"></i>
                        </div>
                        <h3>Estatísticas e Relatórios</h3>
                        <p>Dashboards por perfil com métricas de aprovação, desempenho por disciplina, género e curso.</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 col-md-6 reveal reveal-delay-2">
                <div class="feat-card">
                    @include('welcome.partials.feat-preview', ['tipo' => 'seguranca'])
                    <div class="feat-body">
                        <div class="feat-ico" style="background: rgba(239,68,68,.08); color: #ef4444;">
                            <i class="bi bi-shield-lock-fill"></i>
                        </div>
                        <h3>Segurança e Auditoria</h3>
                        <p>Controlo de acesso por perfil (Admin, Director, Coordenador, Professor) com registo completo de actividades.</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 col-md-6 reveal reveal-delay-3">
                <div class="feat-card">
                    @include('welcome.partials.feat-preview', ['tipo' => 'transicao'])
                    <div class="feat-body">
                        <div class="feat-ico" style="background: rgba(168,85,247,.08); color: #a855f7;">
                            <i class="bi bi-arrow-repeat"></i>
                        </div>
                        <h3>Transição de Ano Lectivo</h3>
                        <p>Assistente guiado que transfere turmas, promove alunos aprovados, mantém repetentes e preserva encarregados e dados no novo ano.</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 col-md-6 reveal reveal-delay-1">
                <div class="feat-card">
                    @include('welcome.partials.feat-preview', ['tipo' => 'mensagens'])
                    <div class="feat-body">
                        <div class="feat-ico" style="background: rgba(6,182,212,.08); color: #06b6d4;">
                            <i class="bi bi-chat-dots-fill"></i>
                        </div>
                        <h3>Mensagens Escola–Família</h3>
                        <p>Chat seguro entre professores, directores e encarregados, e mensagens internas entre colaboradores — tudo num só lugar.</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 col-md-6 reveal reveal-delay-2">
                <div class="feat-card">
                    @include('welcome.partials.feat-preview', ['tipo' => 'secretaria'])
                    <div class="feat-body">
                        <div class="feat-ico" style="background: rgba(99,102,241,.08); color: #6366f1;">
                            <i class="bi bi-inbox-fill"></i>
                        </div>
                        <h3>Secretaria Digital</h3>
                        <p>Matrículas, declarações de frequência e matrícula, fichas e cartões de estudante em PDF. Os encarregados pedem pelo portal e acompanham o estado em tempo real.</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 col-md-6 reveal reveal-delay-3">
                <div class="feat-card">
                    @include('welcome.partials.feat-preview', ['tipo' => 'presencas'])
                    <div class="feat-body">
                        <div class="feat-ico" style="background: rgba(16,185,129,.08); color: #10b981;">
                            <i class="bi bi-clipboard-check-fill"></i>
                        </div>
                        <h3>Assiduidade e Disciplina</h3>
                        <p>Registo de presenças por aula, justificação de faltas e ocorrências disciplinares com notificação automática aos encarregados.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Inteligência Artificial -->
<section class="ia-section" id="inteligencia-artificial">
    <div class="container">
        <div class="row g-5 align-items-center">
            <div class="col-lg-6 reveal">
                <span class="sec-tag sec-tag--ia"><i class="bi bi-stars me-1"></i> Inteligência Artificial</span>
                <h2 class="ia-title">Um assistente inteligente<br><span class="gradient-text">para cada perfil da escola</span></h2>
                <p class="ia-sub">A IA está integrada em todo o sistema: responde a perguntas com os dados reais da escola, antecipa problemas e poupa horas de trabalho à equipa.</p>
                <div class="ia-list">
                    <div class="ia-item">
                        <div class="ia-item__ico"><i class="bi bi-chat-square-text"></i></div>
                        <div>
                            <strong>Assistente com dados reais</strong>
                            <small>Direcção, coordenadores, professores e secretaria fazem perguntas em linguagem natural e recebem respostas com os dados do ano lectivo.</small>
                        </div>
                    </div>
                    <div class="ia-item">
                        <div class="ia-item__ico"><i class="bi bi-people"></i></div>
                        <div>
                            <strong>Briefing do Director de Turma</strong>
                            <small>Todas as semanas a IA identifica alunos que precisam de atenção — faltas, ocorrências, notas em queda — e sugere as famílias a contactar.</small>
                        </div>
                    </div>
                    <div class="ia-item">
                        <div class="ia-item__ico"><i class="bi bi-magic"></i></div>
                        <div>
                            <strong>Geração de conteúdos</strong>
                            <small>Materiais académicos e calendários de provas gerados com IA, prontos a rever e publicar.</small>
                        </div>
                    </div>
                    <div class="ia-item">
                        <div class="ia-item__ico"><i class="bi bi-graph-up-arrow"></i></div>
                        <div>
                            <strong>Radar académico</strong>
                            <small>Alertas automáticos de desempenho e assiduidade para a direcção agir antes que seja tarde.</small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6 reveal reveal-delay-2">
                <div class="ia-chat" aria-hidden="true">
                    <div class="ia-chat__head">
                        <div class="ia-chat__avatar"><i class="bi bi-stars"></i></div>
                        <div>
                            <strong>Assistente Facilita</strong>
                            <small>online</small>
                        </div>
                    </div>
                    <div class="ia-chat__body">
                        <div class="ia-msg ia-msg--user">Quais alunos da minha turma precisam de contacto com o encarregado esta semana?</div>
                        <div class="ia-msg ia-msg--bot">
                            Identifiquei 3 alunos na 11ª A: <br>
                            • <b>Ana M.</b> — 4 faltas não justificadas<br>
                            • <b>João K.</b> — média desceu para 9,8 a Matemática<br>
                            • <b>Maria S.</b> — 1 ocorrência disciplinar por resolver
                            <span class="ia-msg__meta">Com base nos dados de 2025-2026</span>
                        </div>
                        <div class="ia-msg ia-msg--user">Prepara uma mensagem para o encarregado da Ana.</div>
                        <div class="ia-typing"><span></span><span></span><span></span></div>
                    </div>
                </div>
                <div class="ia-chips justify-content-center">
                    <span class="ia-chip"><i class="bi bi-shield-check"></i> Dados da própria escola</span>
                    <span class="ia-chip"><i class="bi bi-person-lock"></i> Respeita o perfil de acesso</span>
                    <span class="ia-chip"><i class="bi bi-lightning-charge"></i> Disponível 24/7</span>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Portal do Encarregado -->
<section class="enc-section" id="portal-encarregado">
    <div class="container">
        <div class="text-center reveal">
            <span class="sec-tag">Para as famílias</span>
            <h2 class="sec-title">Portal do Encarregado de Educação</h2>
            <p class="sec-sub">Acompanhe a vida escolar do seu educando sem sair de casa: notas, faltas, mensagens e documentos, com resumo semanal directo no WhatsApp.</p>
        </div>
        <div class="row g-4">
            <div class="col-md-6 col-lg-4 reveal reveal-delay-1">
                <div class="enc-grid-card">
                    <div class="enc-grid-card__ico" style="background: rgba(37,211,102,.12); color: #25d366;">
                        <i class="bi bi-whatsapp"></i>
                    </div>
                    <strong>Resumo semanal no WhatsApp</strong>
                    <p>Todas as semanas recebe um resumo com notas lançadas, faltas e avisos importantes de cada educando.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-4 reveal reveal-delay-2">
                <div class="enc-grid-card">
                    <div class="enc-grid-card__ico" style="background: color-mix(in srgb, var(--primary) 12%, transparent); color: var(--primary);">
                        <i class="bi bi-chat-left-text-fill"></i>
                    </div>
                    <strong>Mensagens com a escola</strong>
                    <p>Fale directamente com o director de turma e professores num chat seguro, sem depender de recados em papel.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-4 reveal reveal-delay-3">
                <div class="enc-grid-card">
                    <div class="enc-grid-card__ico" style="background: rgba(99,102,241,.12); color: #6366f1;">
                        <i class="bi bi-file-earmark-arrow-down-fill"></i>
                    </div>
                    <strong>Pedidos de documentos</strong>
                    <p>Peça declarações de frequência ou matrícula, ficha do aluno e cartão de estudante pelo portal, e receba um aviso quando estiverem prontos.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-4 reveal reveal-delay-1">
                <div class="enc-grid-card">
                    <div class="enc-grid-card__ico" style="background: rgba(16,185,129,.12); color: #10b981;">
                        <i class="bi bi-journal-check"></i>
                    </div>
                    <strong>Notas e boletins</strong>
                    <p>Consulte as avaliações trimestrais e assine o boletim digitalmente, com registo da confirmação.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-4 reveal reveal-delay-2">
                <div class="enc-grid-card">
                    <div class="enc-grid-card__ico" style="background: rgba(245,158,11,.12); color: #f59e0b;">
                        <i class="bi bi-calendar2-x-fill"></i>
                    </div>
                    <strong>Assiduidade em tempo real</strong>
                    <p>Veja presenças e faltas por disciplina e seja notificado assim que uma falta é registada.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-4 reveal reveal-delay-3">
                <div class="enc-grid-card">
                    <div class="enc-grid-card__ico" style="background: color-mix(in srgb, var(--accent) 12%, transparent); color: var(--accent);">
                        <i class="bi bi-bell-fill"></i>
                    </div>
                    <strong>O que fazer agora</strong>
                    <p>O portal destaca as acções pendentes: boletins por assinar, faltas por justificar e documentos prontos.</p>
                </div>
            </div>
        </div>
        <div class="text-center mt-4 reveal">
            @auth
                @if(auth()->user()->hasRole('encarregado'))
                    <a href="{{ route('portal.encarregado.dashboard') }}" class="btn btn-enc-portal">
                        <i class="bi bi-box-arrow-in-right me-2"></i>Ir para o meu portal
                    </a>
                @endif
            @else
                <a href="{{ route('login') }}" class="btn btn-enc-portal">
                    <i class="bi bi-people-fill me-2"></i>Entrar como encarregado
                </a>
                <p class="aluno-zone__hint mt-2">Utilize as credenciais fornecidas pela escola.</p>
            @endauth
        </div>
    </div>
</section>

<!-- About -->
<section class="about" id="sobre">
    <div class="container">
        <div class="row g-4 align-items-stretch">
            <div class="col-lg-6 reveal">
                <div class="about-card">
                    <span class="sec-tag">Sobre a Instituição</span>
                    <h3 class="mt-2">{{ $paginaPublica['sobre_titulo'] }}</h3>
                    <p style="color: var(--text-muted); line-height: 1.8;">
                        {{ $paginaPublica['sobre_texto'] }}
                    </p>
                    <ul class="check-list mt-3">
                        @foreach($paginaPublica['sobre_itens'] as $item)
                        <li><i class="bi bi-check-circle-fill"></i>{{ $item }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
            <div class="col-lg-6 reveal reveal-delay-2">
                <div class="about-card">
                    <span class="sec-tag">Novidades</span>
                    <h3 class="mt-2">Um sistema em constante evolução</h3>
                    <p style="color: var(--text-muted); line-height: 1.8;">
                        As funcionalidades mais recentes aproximam a escola das famílias e dão superpoderes à equipa.
                    </p>
                    <ul class="check-list mt-3">
                        <li><i class="bi bi-stars" style="color: #8b5cf6;"></i>Assistente com Inteligência Artificial em todos os perfis</li>
                        <li><i class="bi bi-whatsapp" style="color: #25d366;"></i>Resumo semanal para encarregados via WhatsApp</li>
                        <li><i class="bi bi-chat-dots-fill" style="color: #06b6d4;"></i>Mensagens escola–família e entre colaboradores</li>
                        <li><i class="bi bi-inbox-fill" style="color: #6366f1;"></i>Pedidos de documentos self-service no portal</li>
                        <li><i class="bi bi-person-badge" style="color: #f59e0b;"></i>Briefing semanal do Director de Turma com IA</li>
                        <li><i class="bi bi-arrow-repeat" style="color: #a855f7;"></i>Transição de ano lectivo guiada, com dados preservados</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Perfis de Acesso -->
<section class="perfis-section" id="perfis">
    <div class="container">
        <div class="text-center reveal">
            <span class="sec-tag">Perfis de Acesso</span>
            <h2 class="sec-title">Cada utilizador vê o que precisa</h2>
            <p class="sec-sub">Do administrador ao encarregado de educação, o sistema adapta o dashboard, os menus e o assistente de IA ao papel de cada pessoa.</p>
        </div>
        <div class="row g-3">
            <div class="col-6 col-md-4 col-lg-3 reveal reveal-delay-1">
                <div class="role-card">
                    <div class="role-card__ico" style="background: color-mix(in srgb, var(--primary) 12%, transparent); color: var(--primary);">
                        <i class="bi bi-person-gear"></i>
                    </div>
                    <strong>Administrador</strong>
                    <small>Gestão total do sistema, utilizadores, permissões e configurações.</small>
                </div>
            </div>
            <div class="col-6 col-md-4 col-lg-3 reveal reveal-delay-1">
                <div class="role-card">
                    <div class="role-card__ico" style="background: rgba(239,68,68,.12); color: #ef4444;">
                        <i class="bi bi-award"></i>
                    </div>
                    <strong>Direcção Geral</strong>
                    <small>Visão executiva da escola com indicadores, radar académico e relatórios.</small>
                </div>
            </div>
            <div class="col-6 col-md-4 col-lg-3 reveal reveal-delay-2">
                <div class="role-card">
                    <div class="role-card__ico" style="background: rgba(14,165,233,.12); color: #0ea5e9;">
                        <i class="bi bi-diagram-3"></i>
                    </div>
                    <strong>Subdirector Pedagógico</strong>
                    <small>Supervisão pedagógica, horários, pautas e conselhos de notas.</small>
                </div>
            </div>
            <div class="col-6 col-md-4 col-lg-3 reveal reveal-delay-2">
                <div class="role-card">
                    <div class="role-card__ico" style="background: rgba(16,185,129,.12); color: #10b981;">
                        <i class="bi bi-mortarboard"></i>
                    </div>
                    <strong>Coordenadores</strong>
                    <small>Acompanhamento de cursos e disciplinas com estatísticas próprias.</small>
                </div>
            </div>
            <div class="col-6 col-md-4 col-lg-3 reveal reveal-delay-3">
                <div class="role-card">
                    <div class="role-card__ico" style="background: rgba(245,158,11,.12); color: #f59e0b;">
                        <i class="bi bi-person-badge"></i>
                    </div>
                    <strong>Director de Turma</strong>
                    <small>Gestão da turma com briefing IA das famílias a contactar cada semana.</small>
                </div>
            </div>
            <div class="col-6 col-md-4 col-lg-3 reveal reveal-delay-3">
                <div class="role-card">
                    <div class="role-card__ico" style="background: color-mix(in srgb, var(--accent) 12%, transparent); color: var(--accent);">
                        <i class="bi bi-person-workspace"></i>
                    </div>
                    <strong>Professor</strong>
                    <small>Lançamento de notas, presenças, mini-pautas e materiais com IA.</small>
                </div>
            </div>
            <div class="col-6 col-md-4 col-lg-3 reveal reveal-delay-4">
                <div class="role-card">
                    <div class="role-card__ico" style="background: rgba(99,102,241,.12); color: #6366f1;">
                        <i class="bi bi-inbox"></i>
                    </div>
                    <strong>Secretaria</strong>
                    <small>Matrículas, emissão de documentos e resposta aos pedidos do portal.</small>
                </div>
            </div>
            <div class="col-6 col-md-4 col-lg-3 reveal reveal-delay-4">
                <div class="role-card">
                    <div class="role-card__ico" style="background: rgba(100,116,139,.12); color: #64748b;">
                        <i class="bi bi-search"></i>
                    </div>
                    <strong>Inspecção</strong>
                    <small>Auditoria de pautas certificadas e conformidade dos registos.</small>
                </div>
            </div>
            <div class="col-6 col-md-4 col-lg-3 reveal reveal-delay-5">
                <div class="role-card">
                    <div class="role-card__ico" style="background: rgba(168,85,247,.12); color: #a855f7;">
                        <i class="bi bi-backpack2"></i>
                    </div>
                    <strong>Aluno</strong>
                    <small>Portal com notas, boletins, horário, provas e materiais de estudo.</small>
                </div>
            </div>
            <div class="col-6 col-md-4 col-lg-3 reveal reveal-delay-5">
                <div class="role-card">
                    <div class="role-card__ico" style="background: rgba(37,211,102,.12); color: #25d366;">
                        <i class="bi bi-people"></i>
                    </div>
                    <strong>Encarregado</strong>
                    <small>Acompanhamento dos educandos, mensagens, documentos e WhatsApp.</small>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- CTA dual -->
<section class="cta-dual">
    <div class="container">
        <div class="cta-dual__intro reveal">
            <h2 class="sec-title mb-2">{{ $paginaPublica['cta_titulo'] }}</h2>
            <p class="sec-sub mb-0">{{ $paginaPublica['cta_texto'] }}</p>
        </div>
        <div class="row g-4 justify-content-center">
            @if($portalAlunoActivo)
            <div class="col-md-6 col-lg-5 reveal reveal-delay-1">
                <div class="cta-box cta-box--aluno">
                    <div class="cta-box__ico"><i class="bi bi-mortarboard-fill"></i></div>
                    <h3>{{ $paginaPublica['cta_aluno_titulo'] }}</h3>
                    <p>{{ $paginaPublica['cta_aluno_texto'] }}</p>
                    @auth
                        @if(auth()->user()->hasRole('aluno'))
                            <a href="{{ route('portal.aluno.dashboard') }}" class="btn btn-cta-aluno">
                                <i class="bi bi-box-arrow-in-right me-2"></i>Ir para o portal
                            </a>
                        @else
                            <a href="{{ route('login') }}" class="btn btn-cta-aluno">
                                <i class="bi bi-mortarboard me-2"></i>Portal do Aluno
                            </a>
                        @endif
                    @else
                        <a href="{{ $urlPortalAluno }}" class="btn btn-cta-aluno">
                            <i class="bi bi-mortarboard me-2"></i>Entrar como aluno
                        </a>
                    @endauth
                </div>
            </div>
            @endif
            <div class="col-md-6 col-lg-5 reveal reveal-delay-2">
                <div class="cta-box cta-box--escola">
                    <div class="cta-box__ico"><i class="bi bi-building"></i></div>
                    <h3>{{ $paginaPublica['cta_escola_titulo'] }}</h3>
                    <p>{{ $paginaPublica['cta_escola_texto'] }}</p>
                    @auth
                        @if(!auth()->user()->hasRole('aluno'))
                            <a href="{{ route('dashboard') }}" class="btn btn-cta-escola">
                                <i class="bi bi-speedometer2 me-2"></i>Ir para o dashboard
                            </a>
                        @else
                            <a href="{{ route('login') }}" class="btn btn-cta-escola">
                                <i class="bi bi-building me-2"></i>Área institucional
                            </a>
                        @endif
                    @else
                        <a href="{{ $urlAreaEscola }}" class="btn btn-cta-escola">
                            <i class="bi bi-building me-2"></i>Entrar na área da escola
                        </a>
                    @endauth
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Footer -->
<footer class="footer">
    <div class="container">
        <div class="row">
            <div class="col-lg-4 mb-4 mb-lg-0">
                <div class="footer-brand">
                    <img src="{{ $logoUrl }}" alt="{{ $logoAlt }}">
                </div>
                <p class="footer-text">{{ $paginaPublica['footer_texto'] }}</p>
            </div>
            <div class="col-lg-2 col-md-4 mb-4 mb-md-0">
                <h6 class="footer-title">Navegação</h6>
                <ul class="footer-links">
                    @if($portalAlunoActivo)
                    <li><a href="#portal-aluno">Portal do Aluno</a></li>
                    @endif
                    <li><a href="#funcionalidades">Funcionalidades</a></li>
                    <li><a href="#inteligencia-artificial">Inteligência Artificial</a></li>
                    <li><a href="#portal-encarregado">Encarregados</a></li>
                    <li><a href="#perfis">Perfis de Acesso</a></li>
                    <li><a href="#sobre">Sobre</a></li>
                    <li><a href="{{ route('login') }}">Iniciar Sessão</a></li>
                </ul>
            </div>
            <div class="col-lg-2 col-md-4 mb-4 mb-md-0">
                <h6 class="footer-title">Sistema</h6>
                <ul class="footer-links">
                    @auth
                        <li><a href="{{ route('notas.lancar') }}">Lançar Notas</a></li>
                        <li><a href="{{ route('pautas.index') }}">Ver Pautas</a></li>
                        <li><a href="{{ route('relatorios.boletins') }}">Boletins</a></li>
                    @else
                        <li><a href="{{ route('login') }}">Lançar Notas</a></li>
                        <li><a href="{{ route('login') }}">Ver Pautas</a></li>
                        <li><a href="{{ route('login') }}">Boletins</a></li>
                    @endauth
                </ul>
            </div>
            <div class="col-lg-4 col-md-4">
                <h6 class="footer-title">Contacto</h6>
                <p class="footer-text">
                    @if($paginaPublica['mostrar_contactos'])
                        @if(!empty($instituicao['email']))
                            <i class="bi bi-envelope me-2"></i>{{ $instituicao['email'] }}<br>
                        @endif
                        @if(!empty($instituicao['telefone']))
                            <i class="bi bi-telephone me-2"></i>{{ $instituicao['telefone'] }}<br>
                        @endif
                        @if(!empty($instituicao['endereco']))
                            <i class="bi bi-geo-alt me-2"></i>{{ $instituicao['endereco'] }}<br>
                        @endif
                        @if(!empty($instituicao['website']))
                            <i class="bi bi-globe me-2"></i><a href="{{ $instituicao['website'] }}" class="text-reset" target="_blank" rel="noopener">{{ $instituicao['website'] }}</a>
                        @endif
                    @else
                        <span class="text-muted">Contactos disponíveis após autenticação.</span>
                    @endif
                </p>
            </div>
        </div>
        <hr>
        <div class="text-center">
            <p class="footer-copy mb-0">
                &copy; {{ date('Y') }} {{ config('app.name') }}. Todos os direitos reservados.
            </p>
        </div>
    </div>
</footer>

<button class="welcome-theme-toggle" id="themeToggleWelcome" title="Alternar modo escuro/claro">
    <i class="bi bi-moon-fill" id="themeIconWelcome"></i>
</button>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function() {
    var navbar = document.getElementById('navbar');
    var navLinks = document.querySelectorAll('[data-nav-section]');

    window.addEventListener('scroll', function() {
        navbar.classList.toggle('scrolled', window.scrollY > 20);
    }, { passive: true });

    if ('IntersectionObserver' in window) {
        var revealObserver = new IntersectionObserver(function(entries) {
            entries.forEach(function(entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    revealObserver.unobserve(entry.target);
                }
            });
        }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });

        document.querySelectorAll('.reveal').forEach(function(el) {
            revealObserver.observe(el);
        });

        var sectionIds = [@if($portalAlunoActivo) 'portal-aluno', @endif 'funcionalidades', 'inteligencia-artificial', 'portal-encarregado', 'perfis', 'sobre'];
        var sections = sectionIds.map(function(id) {
            var el = document.getElementById(id);
            return el ? { id: id, el: el } : null;
        }).filter(Boolean);

        function setActiveNav(id) {
            navLinks.forEach(function(link) {
                link.classList.toggle('is-active', !!id && link.dataset.navSection === id);
            });
        }

        function resolveActiveSection() {
            if (!sections.length) return;
            var offset = parseInt(getComputedStyle(document.documentElement).getPropertyValue('--hero-nav-offset'), 10) || 72;
            var probe = window.scrollY + offset + 96;
            var activeId = null;

            sections.forEach(function(section) {
                if (section.el.offsetTop <= probe) {
                    activeId = section.id;
                }
            });

            setActiveNav(activeId);
        }

        var navTick = false;
        window.addEventListener('scroll', function() {
            if (!navTick) {
                navTick = true;
                requestAnimationFrame(function() {
                    resolveActiveSection();
                    navTick = false;
                });
            }
        }, { passive: true });

        window.addEventListener('hashchange', resolveActiveSection);

        navLinks.forEach(function(link) {
            link.addEventListener('click', function() {
                setActiveNav(link.dataset.navSection);
            });
        });

        resolveActiveSection();
        if (location.hash) {
            setTimeout(resolveActiveSection, 150);
            setTimeout(resolveActiveSection, 500);
        }
    } else {
        document.querySelectorAll('.reveal').forEach(function(el) {
            el.classList.add('is-visible');
        });
    }

    var toggle = document.getElementById('themeToggleWelcome');
    var icon = document.getElementById('themeIconWelcome');
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
</body>
</html>
