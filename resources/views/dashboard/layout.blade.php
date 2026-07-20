<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Dashboard') — {{ $tenant->name }}</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f0f4f8;
            color: #1e293b;
            min-height: 100vh;
            display: flex;
        }

        /* ── Sidebar ──────────────────────────────── */
        .sidebar {
            width: 240px;
            min-width: 240px;
            background: #1a2332;
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
            border-bottom: 1px solid #2d3f55;
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
            background: #243044;
            color: #e2e8f0;
        }

        .nav-item.active {
            background: #1d3a6e22;
            color: #60a5fa;
            border-left-color: #3b82f6;
        }

        .nav-item .icon { font-size: 16px; width: 20px; text-align: center; }

        .sidebar-footer {
            padding: 12px 0;
            border-top: 1px solid #2d3f55;
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

        .topbar-left { font-weight: 600; color: #334155; font-size: 15px; }
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
            background: #3b82f6;
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

        .page-title { font-size: 22px; font-weight: 700; color: #0f172a; }
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
        .stat-value { font-size: 26px; font-weight: 800; color: #0f172a; margin: 6px 0 4px; letter-spacing: -0.5px; }
        .stat-sub   { font-size: 12px; color: #94a3b8; }
        .stat-icon  { float: right; font-size: 28px; margin-top: -4px; }

        /* ── Tables ─────────────────────────────────── */
        .table-wrap { overflow-x: auto; }

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
            color: #1e293b;
            background: #fff;
            transition: border-color 0.15s;
        }
        .field input:focus, .field select:focus, .field textarea:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px #bfdbfe55;
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
        .btn-primary { background: #2563eb; color: #fff; }
        .btn-primary:hover { background: #1d4ed8; }
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
        .pagination .active span { background: #2563eb; color: #fff; }

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
        @media (max-width: 900px) {
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .form-grid { grid-template-columns: 1fr; }
        }
    </style>
    @stack('head')
</head>
<body>

{{-- Sidebar --}}
<nav class="sidebar">
    <div class="sidebar-brand">
        <div class="brand-name">TrinetPay</div>
        <div class="brand-sub">{{ $tenant->name }}</div>
    </div>

    <div class="sidebar-nav">
        <a href="{{ route('dashboard.home') }}"
           class="nav-item {{ request()->routeIs('dashboard.home') ? 'active' : '' }}">
            <span class="icon">📊</span> Overview
        </a>
        <a href="{{ route('dashboard.transactions') }}"
           class="nav-item {{ request()->routeIs('dashboard.transactions') ? 'active' : '' }}">
            <span class="icon">💳</span> Transactions
        </a>

        <div class="nav-section">Manage</div>
        <a href="{{ route('dashboard.routers.index') }}"
           class="nav-item {{ request()->routeIs('dashboard.routers.*') ? 'active' : '' }}">
            <span class="icon">📡</span> Routers
        </a>
        <a href="{{ route('dashboard.packages.index') }}"
           class="nav-item {{ request()->routeIs('dashboard.packages.*') ? 'active' : '' }}">
            <span class="icon">📦</span> Packages
        </a>
        <a href="{{ route('dashboard.vouchers.index') }}"
           class="nav-item {{ request()->routeIs('dashboard.vouchers.*') ? 'active' : '' }}">
            <span class="icon">🎫</span> Vouchers
        </a>
        <a href="{{ route('dashboard.agents.index') }}"
           class="nav-item {{ request()->routeIs('dashboard.agents.*') ? 'active' : '' }}">
            <span class="icon">👥</span> Agents
        </a>

        <div class="nav-section">Account</div>
        <a href="{{ route('dashboard.wallet') }}"
           class="nav-item {{ request()->routeIs('dashboard.wallet') ? 'active' : '' }}">
            <span class="icon">💰</span> Wallet
        </a>
        <a href="{{ route('dashboard.settings') }}"
           class="nav-item {{ request()->routeIs('dashboard.settings') ? 'active' : '' }}">
            <span class="icon">⚙️</span> Settings
        </a>
    </div>

    <div class="sidebar-footer">
        <form method="POST" action="{{ route('tenant.logout') }}">
            @csrf
            <button type="submit" class="nav-item" style="width:100%;background:none;cursor:pointer;border:none;">
                <span class="icon">🚪</span> Sign out
            </button>
        </form>
    </div>
</nav>

{{-- Main --}}
<div class="main">
    <header class="topbar">
        <div class="topbar-left">
            @yield('breadcrumb', 'Dashboard')
            <span>{{ $tenant->name }}</span>
        </div>
        <div class="topbar-right">
            <a href="//{{ $tenant->subdomain }}.trinetpay.online" target="_blank" class="btn-portal">
                🌐 Live Portal ↗
            </a>
            <div class="user-badge">
                <div class="user-avatar">{{ strtoupper(substr(Auth::guard('tenant')->user()->name, 0, 1)) }}</div>
                <span>{{ Auth::guard('tenant')->user()->name }}</span>
            </div>
        </div>
    </header>

    <main class="content">
        @if (session('success'))
            <div class="alert alert-success">✓ {{ session('success') }}</div>
        @endif

        @if ($errors->any())
            <div class="alert alert-error">
                @foreach ($errors->all() as $e) <div>{{ $e }}</div> @endforeach
            </div>
        @endif

        @yield('content')
    </main>
</div>

@stack('scripts')
</body>
</html>
