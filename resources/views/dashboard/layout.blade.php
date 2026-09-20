<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Dashboard') — {{ $tenant->name }}</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f0f4f8;
            color: #040a17;
            min-height: 100vh;
            display: flex;
        }

        /* ── Sidebar ──────────────────────────────── */
        .sidebar {
            width: 240px;
            min-width: 240px;
            background: #040a17;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            position: sticky;
            top: 0;
            height: 100vh;
            overflow-y: auto;
        }

        .sidebar-brand {
            padding: 20px 20px 16px;
            border-bottom: 1px solid #040a17;
        }

        .brand-name {
            color: #fff;
            font-size: 18px;
            font-weight: 700;
            letter-spacing: -0.3px;
        }

        .brand-sub {
            color: #64748b;
            font-size: 11px;
            margin-top: 2px;
        }

        .sidebar-nav {
            flex: 1;
            padding: 12px 0;
        }

        .nav-section {
            padding: 6px 16px 2px;
            font-size: 10px;
            font-weight: 600;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            margin-top: 8px;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 9px 20px;
            color: #94a3b8;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.15s;
            border-left: 3px solid transparent;
        }

        .nav-item:hover {
            background: #040a17;
            color: #e2e8f0;
        }

        .nav-item.active {
            background: #2561e822;
            color: #2561e8;
            border-left-color: #2561e8;
        }

        .nav-item .icon { font-size: 16px; width: 20px; text-align: center; }

        .sidebar-footer {
            padding: 12px 0;
            border-top: 1px solid #040a17;
        }

        /* ── Main ─────────────────────────────────── */
        .main {
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            overflow-x: hidden;
        }

        .topbar {
            background: #fff;
            border-bottom: 1px solid #e2e8f0;
            padding: 0 28px;
            height: 58px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 50;
        }

        .topbar-left { font-weight: 600; color: #334155; font-size: 15px; display: flex; align-items: center; gap: 10px; min-width: 0; }
        .topbar-left > span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

        /* Only on small screens, where the sidebar becomes a drawer. */
        .nav-toggle {
            display: none; align-items: center; justify-content: center;
            width: 36px; height: 36px; flex: 0 0 auto;
            background: none; border: 1px solid #e2e8f0; border-radius: 8px;
            color: #040a17; font-size: 16px; cursor: pointer;
        }
        .nav-toggle:hover { background: #f1f5f9; }

        .sidebar-overlay {
            display: none; position: fixed; inset: 0;
            background: rgba(4, 10, 23, 0.55); z-index: 150;
        }
        .sidebar-overlay.is-open { display: block; }
        .topbar-left span { color: #94a3b8; font-weight: 400; margin-left: 6px; font-size: 13px; }

        .topbar-right {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .btn-portal {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            background: #f0fdf4;
            color: #16a34a;
            border: 1px solid #bbf7d0;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.15s;
        }

        .btn-portal:hover { background: #dcfce7; }

        .user-badge {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #64748b;
            font-size: 13px;
        }

        .user-avatar {
            width: 32px;
            height: 32px;
            background: #2561e8;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-weight: 700;
            font-size: 13px;
        }

        .content { padding: 28px; flex: 1; }

        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
        }

        .page-title { font-size: 22px; font-weight: 700; color: #040a17; }
        .page-sub { font-size: 13px; color: #64748b; margin-top: 2px; }

        /* ── Cards ──────────────────────────────────── */
        .card {
            background: #fff;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
            padding: 24px;
            margin-bottom: 20px;
        }

        .card-title {
            font-size: 14px;
            font-weight: 600;
            color: #475569;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* ── Stats grid ─────────────────────────────── */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 20px;
        }

        .stat-card {
            background: #fff;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
            padding: 20px;
        }

        .stat-label { font-size: 12px; color: #64748b; font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px; }
        .stat-value { font-size: 26px; font-weight: 800; color: #040a17; margin: 6px 0 4px; letter-spacing: -0.5px; }
        .stat-sub   { font-size: 12px; color: #94a3b8; }
        .stat-icon  { float: right; font-size: 28px; margin-top: -4px; }

        /* ── Tables ─────────────────────────────────── */
        .table-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; }
        .table-wrap table { min-width: 620px; }

        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        thead th {
            text-align: left;
            padding: 10px 14px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #64748b;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
        }
        tbody td { padding: 12px 14px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
        tbody tr:last-child td { border-bottom: none; }
        tbody tr:hover td { background: #f8fafc; }

        /* ── Badges ─────────────────────────────────── */
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 2px 9px;
            border-radius: 99px;
            font-size: 11px;
            font-weight: 600;
        }

        .badge-success  { background: #dcfce7; color: #15803d; }
        .badge-warning  { background: #fef9c3; color: #a16207; }
        .badge-danger   { background: #fee2e2; color: #dc2626; }
        .badge-muted    { background: #f1f5f9; color: #64748b; }
        .badge-online   { background: #dcfce7; color: #15803d; }
        .badge-offline  { background: #fee2e2; color: #dc2626; }
        .badge-unknown  { background: #f1f5f9; color: #64748b; }

        /* ── Forms ──────────────────────────────────── */
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .form-grid.cols-3 { grid-template-columns: 1fr 1fr 1fr; }
        .form-full { grid-column: 1 / -1; }

        .field { display: flex; flex-direction: column; gap: 6px; }
        .field label { font-size: 13px; font-weight: 600; color: #374151; }
        .field input, .field select, .field textarea {
            padding: 9px 12px;
            border: 1px solid #d1d5db;
            border-radius: 7px;
            font-size: 14px;
            color: #040a17;
            background: #fff;
            transition: border-color 0.15s;
        }
        .field input:focus, .field select:focus, .field textarea:focus {
            outline: none;
            border-color: #2561e8;
            box-shadow: 0 0 0 3px #2561e833;
        }
        .field .hint { font-size: 11px; color: #94a3b8; }
        .field .error { font-size: 12px; color: #dc2626; }

        /* ── Buttons ─────────────────────────────────── */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 9px 18px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            text-decoration: none;
            transition: all 0.15s;
        }
        .btn-primary { background: #2561e8; color: #fff; }
        .btn-primary:hover { background: #040a17; }
        .btn-secondary { background: #f1f5f9; color: #334155; border: 1px solid #e2e8f0; }
        .btn-secondary:hover { background: #e2e8f0; }
        .btn-danger { background: #fee2e2; color: #dc2626; }
        .btn-danger:hover { background: #fecaca; }
        .btn-sm { padding: 5px 12px; font-size: 12px; }
        .btn-success { background: #dcfce7; color: #15803d; }
        .btn-success:hover { background: #bbf7d0; }

        /* ── Alerts ──────────────────────────────────── */
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 14px;
            margin-bottom: 16px;
        }
        .alert-success { background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; }
        .alert-error   { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }

        /* ── Pagination ──────────────────────────────── */
        .pagination { display: flex; gap: 4px; justify-content: center; margin-top: 16px; }
        .pagination a, .pagination span {
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 500;
            text-decoration: none;
        }
        .pagination a { background: #f1f5f9; color: #334155; }
        .pagination a:hover { background: #e2e8f0; }
        .pagination .active span { background: #2561e8; color: #fff; }

        /* ── Toggle ──────────────────────────────────── */
        .toggle {
            position: relative;
            display: inline-block;
            width: 40px;
            height: 22px;
        }
        .toggle input { opacity: 0; width: 0; height: 0; }
        .toggle-slider {
            position: absolute;
            inset: 0;
            background: #cbd5e1;
            border-radius: 99px;
            cursor: pointer;
            transition: background 0.2s;
        }
        .toggle-slider::before {
            content: '';
            position: absolute;
            left: 3px;
            top: 3px;
            width: 16px;
            height: 16px;
            background: #fff;
            border-radius: 50%;
            transition: transform 0.2s;
        }
        .toggle input:checked + .toggle-slider { background: #22c55e; }
        .toggle input:checked + .toggle-slider::before { transform: translateX(18px); }

        /* ── Empty state ─────────────────────────────── */
        .empty-state {
            text-align: center;
            padding: 48px 20px;
            color: #94a3b8;
        }
        .empty-state .icon { font-size: 40px; margin-bottom: 12px; }
        .empty-state p { font-size: 14px; }

        /* ── Responsive ──────────────────────────────── */
        /* The sidebar becomes a drawer: it slides in over the page instead of
           taking a quarter of a phone screen away from the content. */
        @media (max-width: 900px) {
            .sidebar {
                position: fixed; top: 0; left: 0; bottom: 0;
                height: 100%; z-index: 200;
                transform: translateX(-100%);
                transition: transform 0.22s ease;
                box-shadow: 0 0 40px rgba(4, 10, 23, 0.45);
            }
            .sidebar.is-open { transform: translateX(0); }
            .nav-toggle { display: inline-flex; }

            .topbar { padding: 0 14px; }
            .content { padding: 18px 14px; }
            .page-header { flex-wrap: wrap; gap: 12px; }

            .stats-grid { grid-template-columns: 1fr 1fr; }
            .form-grid, .form-grid.cols-3 { grid-template-columns: 1fr; }
        }

        @media (max-width: 560px) {
            .stats-grid { grid-template-columns: 1fr; }
            .page-title { font-size: 19px; }
            .topbar-left { font-size: 14px; }
            .topbar-left > span { display: none; }
            .btn-portal .btn-portal-label { display: none; }
            .user-badge span { display: none; }
            .card { padding: 16px 14px; }
            .content { padding: 14px 12px; }
        }
    </style>
    @stack('head')
</head>
<body>

{{-- Sidebar --}}
<nav class="sidebar">
    <div class="sidebar-brand">
        <div class="brand-name">Wifikitaa</div>
        <div class="brand-sub">{{ $tenant->name }}</div>
    </div>

    <div class="sidebar-nav">
        <a href="{{ route('dashboard.home') }}"
           class="nav-item {{ request()->routeIs('dashboard.home') ? 'active' : '' }}">
             <span class="icon"><i class="fa-solid fa-chart-column"></i></span> Overview
        </a>
        <a href="{{ route('dashboard.transactions') }}"
           class="nav-item {{ request()->routeIs('dashboard.transactions') ? 'active' : '' }}">
             <span class="icon"><i class="fa-solid fa-credit-card"></i></span> Transactions
        </a>
        <a href="{{ route('dashboard.sessions') }}"
           class="nav-item {{ request()->routeIs('dashboard.sessions') ? 'active' : '' }}">
             <span class="icon"><i class="fa-solid fa-signal"></i></span> Live sessions
        </a>
        <a href="{{ route('dashboard.reports') }}"
           class="nav-item {{ request()->routeIs('dashboard.reports*') ? 'active' : '' }}">
             <span class="icon"><i class="fa-solid fa-chart-line"></i></span> Reports
        </a>

        <div class="nav-section">Manage</div>
        <a href="{{ route('dashboard.routers.index') }}"
           class="nav-item {{ request()->routeIs('dashboard.routers.*') ? 'active' : '' }}">
             <span class="icon"><i class="fa-solid fa-wifi"></i></span> Routers
        </a>
        <a href="{{ route('dashboard.packages.index') }}"
           class="nav-item {{ request()->routeIs('dashboard.packages.*') ? 'active' : '' }}">
             <span class="icon"><i class="fa-solid fa-box"></i></span> Packages
        </a>
        <a href="{{ route('dashboard.vouchers.index') }}"
           class="nav-item {{ request()->routeIs('dashboard.vouchers.*') ? 'active' : '' }}">
             <span class="icon"><i class="fa-solid fa-ticket"></i></span> Vouchers
        </a>
        <a href="{{ route('dashboard.agents.index') }}"
           class="nav-item {{ request()->routeIs('dashboard.agents.*') ? 'active' : '' }}">
             <span class="icon"><i class="fa-solid fa-users"></i></span> Agents
        </a>

        <div class="nav-section">Account</div>
        <a href="{{ route('dashboard.wallet') }}"
           class="nav-item {{ request()->routeIs('dashboard.wallet') ? 'active' : '' }}">
             <span class="icon"><i class="fa-solid fa-coins"></i></span> Wallet
        </a>
        <a href="{{ route('dashboard.settings') }}"
           class="nav-item {{ request()->routeIs('dashboard.settings') ? 'active' : '' }}">
             <span class="icon"><i class="fa-solid fa-gear"></i></span> Settings
        </a>
        <a href="{{ route('profile.edit') }}"
           class="nav-item {{ request()->routeIs('profile.*') ? 'active' : '' }}">
             <span class="icon"><i class="fa-solid fa-user"></i></span> My account
        </a>
    </div>

    <div class="sidebar-footer">
        <form method="POST" action="{{ route('tenant.logout') }}">
            @csrf
            <button type="submit" class="nav-item" style="width:100%;background:none;cursor:pointer;border:none;">
                 <span class="icon"><i class="fa-solid fa-right-from-bracket"></i></span> Sign out
            </button>
        </form>
    </div>
</nav>

{{-- Main --}}
<div class="main">
    <header class="topbar">
        <div class="topbar-left">
            <button type="button" class="nav-toggle" id="navToggle" aria-label="Open menu" aria-expanded="false">
                <i class="fa-solid fa-bars"></i>
            </button>
            @yield('breadcrumb', 'Dashboard')
            <span>{{ $tenant->name }}</span>
        </div>
        <div class="topbar-right">
                 <a href="{{ \App\Support\TenantUrls::portal($tenant) }}" target="_blank" class="btn-portal">
                 <i class="fa-solid fa-globe"></i> <span class="btn-portal-label">Live Portal</span> <i class="fa-solid fa-arrow-up-right-from-square"></i>
             </a>
            <div class="user-badge">
                <div class="user-avatar">{{ strtoupper(substr(Auth::guard('tenant')->user()->name, 0, 1)) }}</div>
                <span>{{ Auth::guard('tenant')->user()->name }}</span>
            </div>
        </div>
    </header>

    <main class="content">
        @if (session('impersonated_by'))
            <div class="alert alert-error" style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
                <span><i class="fa-solid fa-user-shield"></i> Platform support is viewing this account. Withdrawals and payout changes are switched off.</span>
                <form method="POST" action="{{ route('impersonation.stop') }}">
                    @csrf
                    <button type="submit" class="btn btn-secondary btn-sm">Exit support view</button>
                </form>
            </div>
        @endif

        @if (session('success'))
            <div class="alert alert-success"><i class="fa-solid fa-check"></i> {{ session('success') }}</div>
        @endif

        @if ($errors->any())
            <div class="alert alert-error">
                @foreach ($errors->all() as $e) <div>{{ $e }}</div> @endforeach
            </div>
        @endif

        @yield('content')
    </main>
</div>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<script>
    (function () {
        var toggle  = document.getElementById('navToggle');
        var sidebar = document.querySelector('.sidebar');
        var overlay = document.getElementById('sidebarOverlay');

        if (! toggle || ! sidebar || ! overlay) { return; }

        function setOpen(open) {
            sidebar.classList.toggle('is-open', open);
            overlay.classList.toggle('is-open', open);
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            toggle.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
        }

        toggle.addEventListener('click', function () { setOpen(! sidebar.classList.contains('is-open')); });
        overlay.addEventListener('click', function () { setOpen(false); });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { setOpen(false); } });

        // Tapping a link navigates away, so the drawer must not be left open behind the new page.
        sidebar.addEventListener('click', function (e) { if (e.target.closest('a')) { setOpen(false); } });
    })();
</script>

@stack('scripts')
</body>
</html>
