<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Tickets WiFi') — {{ config('app.name', 'AngeLe') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #0f1f1a;
            --bg-soft: #163029;
            --surface: #1a3a31;
            --surface-2: #214840;
            --border: rgba(120, 200, 170, 0.18);
            --text: #e8f5ef;
            --muted: #8fb5a6;
            --accent: #3dd68c;
            --accent-dim: #2a9d68;
            --warn: #f0b429;
            --danger: #f07178;
            --shadow: 0 18px 50px rgba(0, 0, 0, 0.35);
            --radius: 16px;
            --font: "DM Sans", system-ui, sans-serif;
            --mono: "JetBrains Mono", ui-monospace, monospace;
        }

        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: var(--font);
            color: var(--text);
            background:
                radial-gradient(ellipse 80% 50% at 10% -10%, rgba(61, 214, 140, 0.18), transparent 55%),
                radial-gradient(ellipse 60% 40% at 100% 0%, rgba(45, 140, 200, 0.12), transparent 50%),
                linear-gradient(165deg, var(--bg) 0%, #0c1814 100%);
        }

        a { color: var(--accent); text-decoration: none; }
        a:hover { text-decoration: underline; }

        .wrap {
            width: min(920px, calc(100% - 2rem));
            margin: 0 auto;
            padding: 2rem 0 3.5rem;
        }

        header.app-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .brand {
            display: flex;
            flex-direction: column;
            gap: 0.15rem;
        }

        .brand strong {
            font-size: 1.35rem;
            letter-spacing: -0.02em;
        }

        .brand span {
            color: var(--muted);
            font-size: 0.9rem;
        }

        .panel {
            background: linear-gradient(180deg, var(--surface) 0%, var(--bg-soft) 100%);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 1.5rem;
        }

        .panel + .panel { margin-top: 1.25rem; }

        h1, h2 {
            margin: 0 0 0.75rem;
            letter-spacing: -0.02em;
        }

        h1 { font-size: 1.6rem; }
        h2 { font-size: 1.15rem; }

        p.lead {
            margin: 0 0 1.25rem;
            color: var(--muted);
            line-height: 1.5;
        }

        .alert {
            border-radius: 12px;
            padding: 0.85rem 1rem;
            margin-bottom: 1rem;
            border: 1px solid transparent;
        }
        .alert-success { background: rgba(61, 214, 140, 0.12); border-color: rgba(61, 214, 140, 0.35); color: #b8f0d0; }
        .alert-error { background: rgba(240, 113, 120, 0.12); border-color: rgba(240, 113, 120, 0.35); color: #ffc4c8; }
        .alert-info { background: rgba(240, 180, 41, 0.12); border-color: rgba(240, 180, 41, 0.35); color: #ffe2a0; }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            border: none;
            border-radius: 12px;
            padding: 0.9rem 1.35rem;
            font: inherit;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.15s ease, filter 0.15s ease, background 0.15s ease;
        }
        .btn:active { transform: scale(0.98); }
        .btn-primary {
            background: linear-gradient(180deg, var(--accent) 0%, var(--accent-dim) 100%);
            color: #042316;
            box-shadow: 0 10px 24px rgba(61, 214, 140, 0.25);
        }
        .btn-primary:hover { filter: brightness(1.05); text-decoration: none; }
        .btn-ghost {
            background: transparent;
            color: var(--text);
            border: 1px solid var(--border);
        }
        .btn-danger {
            background: rgba(240, 113, 120, 0.15);
            color: #ffc4c8;
            border: 1px solid rgba(240, 113, 120, 0.35);
        }
        .btn-lg {
            font-size: 1.05rem;
            padding: 1.05rem 1.6rem;
            min-width: 220px;
        }
        .btn:disabled, .btn[disabled] {
            opacity: 0.45;
            cursor: not-allowed;
            filter: none;
            box-shadow: none;
        }

        .upload-row {
            display: flex;
            flex-wrap: wrap;
            gap: 0.85rem;
            align-items: center;
        }

        input[type="file"] {
            color: var(--muted);
            max-width: 100%;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 0.75rem;
            margin: 1.25rem 0;
        }
        .stat {
            background: rgba(0, 0, 0, 0.2);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 0.9rem 1rem;
            text-align: center;
        }
        .stat .n {
            font-family: var(--mono);
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--accent);
        }
        .stat .l {
            color: var(--muted);
            font-size: 0.8rem;
            margin-top: 0.2rem;
        }

        .ticket-card {
            margin-top: 1.25rem;
            padding: 1.5rem;
            border-radius: 14px;
            background:
                linear-gradient(135deg, rgba(61, 214, 140, 0.12), transparent 45%),
                rgba(0, 0, 0, 0.28);
            border: 1px solid rgba(61, 214, 140, 0.35);
            animation: pop 0.35s ease;
        }
        .ticket-card--image {
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        @keyframes pop {
            from { opacity: 0; transform: translateY(8px) scale(0.98); }
            to { opacity: 1; transform: none; }
        }

        .ticket-card .label {
            color: var(--muted);
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            margin-bottom: 0.85rem;
            align-self: flex-start;
        }

        .voucher-wrap {
            width: min(100%, 420px);
        }
        .voucher-img {
            display: block;
            width: 100%;
            height: auto;
            background: #fff;
            border-radius: 4px;
            box-shadow: 0 12px 28px rgba(0, 0, 0, 0.35);
        }
        .voucher-fallback {
            background: #fff;
            color: #111;
            border: 2.5px solid #111;
            border-radius: 2px;
            padding: 0.7rem 0.85rem 0.85rem;
            font-family: Arial, Helvetica, sans-serif;
            box-shadow: 0 12px 28px rgba(0, 0, 0, 0.35);
        }
        .voucher-top {
            display: flex;
            justify-content: space-between;
            font-weight: 700;
            font-size: 1.15rem;
            padding-bottom: 0.45rem;
            border-bottom: 1.5px solid #111;
            margin-bottom: 0.55rem;
        }
        .voucher-labels {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.65rem;
            text-align: center;
            font-size: 0.9rem;
            margin-bottom: 0.35rem;
        }
        .voucher-creds {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.65rem;
            margin-bottom: 0.65rem;
        }
        .voucher-creds .box,
        .voucher-footer {
            border: 2px solid #111;
            text-align: center;
            font-weight: 700;
            font-size: 1.25rem;
            padding: 0.55rem 0.35rem;
        }
        .voucher-footer {
            font-size: 1rem;
            font-weight: 600;
        }

        .voucher-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 1rem;
        }
        .voucher-grid-item {
            padding: 0.65rem;
            border-radius: 12px;
            border: 1px solid var(--border);
            background: rgba(0, 0, 0, 0.18);
        }
        .voucher-grid-item.is-current {
            border-color: rgba(61, 214, 140, 0.55);
            background: rgba(61, 214, 140, 0.08);
        }
        .voucher-grid-item .mini-label {
            color: var(--muted);
            font-size: 0.75rem;
            margin-bottom: 0.45rem;
        }
        .voucher-grid-item .voucher-wrap {
            width: 100%;
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            margin-top: 1.25rem;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.95rem;
        }
        th, td {
            text-align: left;
            padding: 0.75rem 0.5rem;
            border-bottom: 1px solid var(--border);
        }
        th { color: var(--muted); font-weight: 500; }
        .badge {
            display: inline-block;
            padding: 0.2rem 0.55rem;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .badge-ok { background: rgba(61, 214, 140, 0.15); color: var(--accent); }
        .badge-done { background: rgba(143, 181, 166, 0.15); color: var(--muted); }
        .badge-warn { background: rgba(255, 180, 80, 0.15); color: #ffb450; }

        .empty {
            color: var(--muted);
            text-align: center;
            padding: 1.5rem 0.5rem;
        }

        @media (max-width: 640px) {
            .stats { grid-template-columns: 1fr; }
            header.app-header { flex-direction: column; align-items: flex-start; }
        }

        .visual-controls {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            align-items: end;
        }
        .visual-controls label {
            display: flex;
            flex-direction: column;
            gap: 0.3rem;
            color: var(--muted);
            font-size: 0.85rem;
        }
        .visual-controls input[type="number"] {
            width: 5.5rem;
            border-radius: 10px;
            border: 1px solid var(--border);
            background: rgba(0,0,0,0.25);
            color: var(--text);
            padding: 0.55rem 0.65rem;
            font: inherit;
        }
        .visual-preview {
            margin-top: 1.25rem;
            display: grid;
            gap: 1rem;
        }
        .page-wrap {
            position: relative;
            width: min(100%, 720px);
            margin: 0 auto;
            background: #fff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: var(--shadow);
        }
        .page-wrap canvas {
            display: block;
            width: 100%;
            height: auto;
        }
        .page-wrap .grid-overlay {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
        }

        .task-tabs {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-top: 1.15rem;
            padding-top: 1rem;
            border-top: 1px solid var(--border);
        }
        .task-tab {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.55rem 1rem;
            border-radius: 10px;
            border: 1px solid var(--border);
            background: rgba(0, 0, 0, 0.22);
            color: var(--muted);
            text-decoration: none;
            font-weight: 600;
            font-size: 0.92rem;
            transition: border-color .15s, color .15s, background .15s;
        }
        .task-tab:hover {
            color: var(--text);
            border-color: rgba(61, 214, 140, 0.45);
            text-decoration: none;
        }
        .task-tab.is-active {
            color: #04140c;
            background: linear-gradient(135deg, #3dd68c, #24b56f);
            border-color: transparent;
            box-shadow: 0 0 0 1px rgba(61, 214, 140, 0.35), 0 8px 20px rgba(36, 181, 111, 0.25);
        }
        .task-form__grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.85rem;
        }
        .task-form label {
            display: flex;
            flex-direction: column;
            gap: 0.35rem;
            color: var(--muted);
            font-size: 0.9rem;
        }
        .task-form__full { grid-column: 1 / -1; }
        .task-form input,
        .task-form select,
        .task-form textarea {
            border-radius: 10px;
            border: 1px solid var(--border);
            background: rgba(0, 0, 0, 0.25);
            color: var(--text);
            padding: 0.65rem 0.8rem;
            font: inherit;
        }
        .task-form textarea { resize: vertical; min-height: 4rem; }
        @media (max-width: 640px) {
            .task-form__grid { grid-template-columns: 1fr; }
        }

        .header-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.55rem;
            align-items: center;
        }
        .user-chip {
            color: var(--muted);
            font-size: 0.85rem;
        }
        .login-form {
            display: grid;
            gap: 0.9rem;
        }
        .login-form label {
            display: flex;
            flex-direction: column;
            gap: 0.35rem;
            color: var(--muted);
            font-size: 0.9rem;
        }
        .login-form label.check {
            flex-direction: row;
            align-items: center;
            gap: 0.5rem;
        }
        .login-form input[type="email"],
        .login-form input[type="password"] {
            border-radius: 10px;
            border: 1px solid var(--border);
            background: rgba(0,0,0,0.25);
            color: var(--text);
            padding: 0.7rem 0.85rem;
            font: inherit;
        }

        .field {
            display: flex;
            flex-direction: column;
            gap: 0.3rem;
            color: var(--muted);
            font-size: 0.85rem;
        }
        .field select,
        select[name="duration_key"] {
            border-radius: 10px;
            border: 1px solid var(--border);
            background: rgba(0,0,0,0.25);
            color: var(--text);
            padding: 0.65rem 0.8rem;
            font: inherit;
            min-width: 140px;
        }

        .duration-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
            gap: 0.85rem;
            margin-top: 0.5rem;
        }
        .duration-card {
            background: rgba(0,0,0,0.22);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 1rem;
            display: flex;
            flex-direction: column;
            gap: 0.65rem;
        }
        .duration-card.has-stock {
            border-color: rgba(61, 214, 140, 0.35);
        }
        .duration-card.empty-stock {
            opacity: 0.72;
        }
        .duration-card.is-selected {
            border-color: var(--accent);
            box-shadow: 0 0 0 1px rgba(61, 214, 140, 0.35);
        }
        .duration-card__title {
            font-weight: 700;
            font-size: 1.15rem;
        }
        .duration-card__price {
            font-family: var(--mono);
            color: var(--warn);
            font-weight: 700;
            font-size: 1rem;
        }
        .day-header {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            justify-content: space-between;
            align-items: flex-start;
        }
        .day-totals {
            display: grid;
            grid-template-columns: repeat(2, minmax(120px, 1fr));
            gap: 0.65rem;
            min-width: min(100%, 320px);
        }
        tfoot td {
            border-top: 1px solid var(--border);
            padding-top: 0.9rem;
        }
        .duration-card__stock strong {
            font-family: var(--mono);
            font-size: 1.5rem;
            color: var(--accent);
            display: block;
        }
        .duration-card__stock span,
        .duration-card__meta {
            color: var(--muted);
            font-size: 0.8rem;
        }
        .duration-card__list {
            list-style: none;
            margin: 0;
            padding: 0;
            display: grid;
            gap: 0.4rem;
            font-size: 0.82rem;
        }
        .duration-card__list li {
            display: flex;
            flex-direction: column;
            gap: 0.15rem;
            padding-top: 0.35rem;
            border-top: 1px solid var(--border);
        }
        .mini-empty {
            margin: 0;
            color: var(--muted);
            font-size: 0.82rem;
        }
        .row-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.65rem;
            align-items: center;
        }
        .link-danger {
            background: none;
            border: none;
            padding: 0;
            color: var(--danger);
            font: inherit;
            cursor: pointer;
            text-decoration: underline;
        }

        .ticket-carousel {
            display: grid;
            grid-template-columns: auto 1fr auto;
            gap: 0.75rem;
            align-items: center;
            margin-top: 0.5rem;
        }
        .carousel-stage {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.75rem;
            min-width: 0;
        }
        .carousel-meta {
            color: var(--muted);
            font-size: 0.9rem;
            text-align: center;
            min-height: 1.2em;
        }
        .carousel-viewport {
            position: relative;
            width: min(100%, 420px);
            min-height: 180px;
        }
        .carousel-slide {
            display: none;
            width: 100%;
            animation: pop 0.28s ease;
        }
        .carousel-slide.is-active {
            display: block;
        }
        .carousel-slide .voucher-wrap {
            width: 100%;
        }
        .carousel-counter {
            font-family: var(--mono);
            color: var(--accent);
            font-weight: 700;
            font-size: 1rem;
        }
        .carousel-btn {
            width: 52px;
            height: 52px;
            border-radius: 14px;
            border: 1px solid var(--border);
            background: rgba(0, 0, 0, 0.28);
            color: var(--text);
            font-size: 2rem;
            line-height: 1;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: background 0.15s ease, border-color 0.15s ease, transform 0.15s ease;
        }
        .carousel-btn:hover:not(:disabled) {
            border-color: rgba(61, 214, 140, 0.55);
            background: rgba(61, 214, 140, 0.12);
            transform: scale(1.04);
        }
        .carousel-btn:disabled {
            opacity: 0.35;
            cursor: not-allowed;
        }
        @media (max-width: 640px) {
            .ticket-carousel {
                grid-template-columns: 1fr 1fr;
                grid-template-areas:
                    "stage stage"
                    "prev next";
            }
            .carousel-stage { grid-area: stage; }
            .carousel-btn--prev { grid-area: prev; width: 100%; }
            .carousel-btn--next { grid-area: next; width: 100%; }
        }
    </style>
</head>
<body>
    <div class="wrap">
        <header class="app-header">
            <div class="brand">
                <strong>AngeLe WiFi</strong>
                <span>
                    @auth
                        {{ auth()->user()->isAdmin() ? 'Admin · Scan PDF & lots' : 'Vendeur · Ajouter un ticket' }}
                    @else
                        Connexion requise
                    @endauth
                </span>
            </div>
            <div class="header-actions">
                @auth
                    <span class="user-chip">{{ auth()->user()->name }}</span>
                    @if (auth()->user()->isAdmin())
                        <a class="btn btn-ghost" href="{{ route('tickets.index') }}">Lots</a>
                        <a class="btn btn-ghost" href="{{ route('admin.sales') }}">Ventes</a>
                        <a class="btn btn-ghost" href="{{ route('admin.deliveries') }}">Livraisons</a>
                    @else
                        <a class="btn btn-ghost" href="{{ route('user.desk') }}">Mes tickets</a>
                        <a class="btn btn-ghost" href="{{ route('user.daily') }}">Tâche journalière</a>
                    @endif
                    <form action="{{ route('logout') }}" method="POST">
                        @csrf
                        <button type="submit" class="btn btn-ghost">Déconnexion</button>
                    </form>
                @else
                    <a class="btn btn-ghost" href="{{ route('login') }}">Connexion</a>
                @endauth
            </div>
        </header>

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if (session('info'))
            <div class="alert alert-info">{{ session('info') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-error">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        @yield('content')
    </div>
    @stack('scripts')
</body>
</html>
