<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Admin') — Wifikitaa</title>
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

        /* ── Sidebar ──────────────────────────── */
        .sidebar {
            width: 220px; min-width: 220px;
            background: #040a17;
            display: flex; flex-direction: column;
            min-height: 100vh; position: sticky; top: 0; height: 100vh; overflow-y: auto;
        }
        .sidebar-brand { padding: 20px 20px 16px; border-bottom: 1px solid #2561e8; }
        .brand-name { color: #e7eefc; font-size: 17px; font-weight: 800; letter-spacing: -0.3px; }
        .brand-sub  { color: #6b7280; font-size: 10px; margin-top: 2px; text-transform: uppercase; letter-spacing: 0.5px; }
        .sidebar-nav { flex: 1; padding: 12px 0; }
        .nav-section {
            padding: 6px 16px 2px; font-size: 10px; font-weight: 600;
            color: #2561e8; text-transform: uppercase; letter-spacing: 0.8px; margin-top: 8px;
        }
        .nav-item {
            display: flex; align-items: center; gap: 10px;
            padding: 9px 20px; color: #e7eefc; text-decoration: none;
            font-size: 13px; font-weight: 500; transition: all 0.15s;
            border-left: 3px solid transparent;
        }
        .nav-item:hover  { background: #040a17; color: #e7eefc; }
        .nav-item.active { background: #2561e8; color: #e7eefc; border-left-color: #e7eefc; }
        .nav-item .icon  { font-size: 15px; width: 18px; text-align: center; }
        .sidebar-footer  { padding: 12px 0; border-top: 1px solid #2561e8; }

        /* ── Main ─────────────────────────────── */
        .main { flex: 1; display: flex; flex-direction: column; min-height: 100vh; }
        .topbar {
            background: #040a17; border-bottom: 1px solid #2561e8;
            padding: 0 28px; height: 56px;
            display: flex; align-items: center; justify-content: space-between;
            position: sticky; top: 0; z-index: 50;
        }
        .topbar-left  { font-weight: 600; color: #e7eefc; font-size: 14px; display: flex; align-items: center; gap: 10px; min-width: 0; }

        /* Only on small screens, where the sidebar becomes a drawer. */
        .nav-toggle {
            display: none; align-items: center; justify-content: center;
            width: 34px; height: 34px; flex: 0 0 auto;
            background: none; border: 1px solid #2561e8; border-radius: 8px;
            color: #e7eefc; font-size: 15px; cursor: pointer;
        }
        .sidebar-overlay {
            display: none; position: fixed; inset: 0;
            background: rgba(4, 10, 23, 0.6); z-index: 150;
        }
        .sidebar-overlay.is-open { display: block; }
        .topbar-right { display: flex; align-items: center; gap: 10px; }
        .admin-badge  { color: #e7eefc; font-size: 12px; }

        .content { padding: 28px; flex: 1; }
        .page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 22px; }
        .page-title  { font-size: 21px; font-weight: 700; color: #040a17; }
        .page-sub    { font-size: 13px; color: #64748b; margin-top: 2px; }

        /* ── Cards + stats (shared with dashboard) ── */
        .card { background: #fff; border-radius: 10px; border: 1px solid #e2e8f0; padding: 22px; margin-bottom: 18px; }
        .card-title { font-size: 13px; font-weight: 600; color: #475569; margin-bottom: 14px; }
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; margin-bottom: 18px; }
        .stat-card  { background: #fff; border-radius: 10px; border: 1px solid #e2e8f0; padding: 18px; }
        .stat-label { font-size: 11px; color: #64748b; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
        .stat-value { font-size: 24px; font-weight: 800; color: #040a17; margin: 5px 0 3px; }
        .stat-sub   { font-size: 11px; color: #94a3b8; }
        .stat-icon  { float: right; font-size: 26px; margin-top: -4px; }

        /* ── Tables ─────────────────────────────── */
        .table-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; }
        .table-wrap table { min-width: 620px; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        thead th {
            text-align: left; padding: 9px 12px;
            font-size: 10px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;
            color: #64748b; background: #f8fafc; border-bottom: 1px solid #e2e8f0;
        }
        tbody td { padding: 11px 12px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
        tbody tr:last-child td { border-bottom: none; }
        tbody tr:hover td { background: #f8fafc; }

        /* ── Badges ─────────────────────────────── */
        .badge { display: inline-flex; align-items: center; padding: 2px 8px; border-radius: 99px; font-size: 11px; font-weight: 600; }
        .badge-active    { background: #dcfce7; color: #15803d; }
        .badge-onboarding { background: #fef9c3; color: #a16207; }
        .badge-suspended { background: #fee2e2; color: #dc2626; }
        .badge-pending   { background: #fef9c3; color: #a16207; }
        .badge-approved  { background: #e7eefc; color: #040a17; }
        .badge-paid      { background: #dcfce7; color: #15803d; }
        .badge-rejected  { background: #fee2e2; color: #dc2626; }
        .badge-muted     { background: #f1f5f9; color: #64748b; }

        /* ── Buttons ─────────────────────────────── */
        .btn {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 7px 14px; border-radius: 7px; font-size: 13px;
            font-weight: 600; cursor: pointer; border: none; text-decoration: none; transition: all 0.15s;
        }
        .btn-primary   { background: #2561e8; color: #fff; }
        .btn-primary:hover { background: #040a17; }
        .btn-success   { background: #dcfce7; color: #15803d; }
        .btn-success:hover { background: #bbf7d0; }
        .btn-danger    { background: #fee2e2; color: #dc2626; }
        .btn-danger:hover { background: #fecaca; }
        .btn-secondary { background: #f1f5f9; color: #334155; border: 1px solid #e2e8f0; }
        .btn-secondary:hover { background: #e2e8f0; }
        .btn-warning   { background: #fef9c3; color: #a16207; }
        .btn-warning:hover { background: #fef08a; }
        .btn-sm { padding: 4px 10px; font-size: 12px; }

        /* ── Filters ─────────────────────────────── */
        .filter-bar { display: flex; gap: 8px; margin-bottom: 16px; flex-wrap: wrap; align-items: center; }
        .filter-bar input, .filter-bar select {
            padding: 7px 12px; border: 1px solid #d1d5db; border-radius: 7px;
            font-size: 13px; color: #040a17; background: #fff;
        }
        .filter-bar input:focus, .filter-bar select:focus { outline: none; border-color: #2561e8; }

        /* ── Status tabs ─────────────────────────── */
        .status-tabs { display: flex; gap: 4px; margin-bottom: 16px; flex-wrap: wrap; }
        .status-tab {
            padding: 5px 14px; border-radius: 99px; font-size: 12px; font-weight: 600;
            text-decoration: none; color: #64748b; background: #f1f5f9; border: 1px solid #e2e8f0;
        }
        .status-tab.active { background: #2561e8; color: #fff; border-color: #2561e8; }

        /* ── Alerts ──────────────────────────────── */
        .alert { padding: 11px 14px; border-radius: 8px; font-size: 13px; margin-bottom: 14px; }
        .alert-success { background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; }
        .alert-error   { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
        .alert-info    { background: #eff6ff; color: #040a17; border: 1px solid #e7eefc; }

        /* ── Forms ──────────────────────────────── */
        .field { display: flex; flex-direction: column; gap: 5px; }
        .field label { font-size: 12px; font-weight: 600; color: #374151; }
        .field input, .field select, .field textarea {
            padding: 8px 11px; border: 1px solid #d1d5db; border-radius: 7px;
            font-size: 13px; color: #040a17; background: #fff;
        }
        .field input:focus, .field select:focus, .field textarea:focus {
            outline: none; border-color: #2561e8; box-shadow: 0 0 0 3px #2561e822;
        }
        .form-row { display: flex; gap: 12px; align-items: flex-end; }

        /* ── Reject form toggle ──────────────────── */
        .reject-form { display: none; margin-top: 8px; background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 12px; }
        .reject-form textarea { width: 100%; padding: 7px 10px; border: 1px solid #fca5a5; border-radius: 6px; font-size: 12px; resize: vertical; }

        /* ── Info list ───────────────────────────── */
        .info-list dt { font-size: 11px; color: #94a3b8; font-weight: 600; text-transform: uppercase; letter-spacing: 0.4px; }
        .info-list dd { font-size: 14px; color: #040a17; margin-bottom: 14px; font-weight: 500; }

        @media (max-width: 900px) {
            .sidebar {
                position: fixed; top: 0; left: 0; bottom: 0;
                height: 100%; z-index: 200;
                transform: translateX(-100%);
                transition: transform 0.22s ease;
                box-shadow: 0 0 40px rgba(4, 10, 23, 0.5);
            }
            .sidebar.is-open { transform: translateX(0); }
            .nav-toggle { display: inline-flex; }

            .topbar { padding: 0 14px; }
            .content { padding: 18px 14px; }
            .page-header { flex-wrap: wrap; gap: 12px; }
            .stats-grid { grid-template-columns: 1fr 1fr; }
        }

        @media (max-width: 560px) {
            .stats-grid { grid-template-columns: 1fr; }
            .content { padding: 14px 12px; }
            .admin-badge { display: none; }
        }
    </style>
</head>
<body>

<nav class="sidebar">
    <div class="sidebar-brand">
        <div class="brand-name">Wifikitaa</div>
        <div class="brand-sub">Platform Admin</div>
    </div>
    <div class="sidebar-nav">
        <a href="{{ route('admin.dashboard') }}"
           class="nav-item {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
             <span class="icon"><i class="fa-solid fa-chart-column"></i></span> Overview
        </a>
        <a href="{{ route('admin.tenants.index') }}"
           class="nav-item {{ request()->routeIs('admin.tenants.*') ? 'active' : '' }}">
             <span class="icon"><i class="fa-solid fa-building"></i></span> ISPs
        </a>
        <a href="{{ route('admin.withdrawals.index') }}"
           class="nav-item {{ request()->routeIs('admin.withdrawals.*') ? 'active' : '' }}">
             <span class="icon"><i class="fa-solid fa-money-bill-transfer"></i></span> Withdrawals
        </a>
        <a href="{{ route('admin.reconciliation') }}"
           class="nav-item {{ request()->routeIs('admin.reconciliation*') ? 'active' : '' }}">
             <span class="icon"><i class="fa-solid fa-scale-balanced"></i></span> Reconciliation
        </a>
        <a href="{{ route('admin.audit') }}"
           class="nav-item {{ request()->routeIs('admin.audit') ? 'active' : '' }}">
             <span class="icon"><i class="fa-solid fa-clipboard-list"></i></span> Audit trail
        </a>
    </div>
    <div class="sidebar-footer">
        <form method="POST" action="{{ route('admin.logout') }}">
            @csrf
            <button type="submit" class="nav-item" style="width:100%;background:none;cursor:pointer;border:none;">
                 <span class="icon"><i class="fa-solid fa-right-from-bracket"></i></span> Sign out
            </button>
        </form>
    </div>
</nav>

<div class="main">
    <header class="topbar">
        <div class="topbar-left">
            <button type="button" class="nav-toggle" id="navToggle" aria-label="Open menu" aria-expanded="false">
                <i class="fa-solid fa-bars"></i>
            </button>
            @yield('breadcrumb', 'Dashboard')
        </div>
        <div class="topbar-right">
            <span class="admin-badge"><i class="fa-solid fa-bolt"></i> {{ Auth::guard('admin')->user()->name }}</span>
        </div>
    </header>

    <main class="content">
        @if (session('success'))
            <div class="alert alert-success"><i class="fa-solid fa-check"></i> {{ session('success') }}</div>
        @endif
        @if (session('info'))
            <div class="alert alert-info">ℹ {{ session('info') }}</div>
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
        sidebar.addEventListener('click', function (e) { if (e.target.closest('a')) { setOpen(false); } });
    })();
</script>

@stack('scripts')
</body>
</html>
