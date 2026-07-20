<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <title>@yield('title', 'POS') — {{ $tenant->name }}</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f0f4f8;
            color: #1e293b;
            min-height: 100vh;
        }

        .topbar {
            background: #1a2332;
            color: #fff;
            padding: 14px 18px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 50;
        }

        .topbar-brand { font-size: 16px; font-weight: 700; }
        .topbar-brand span { font-size: 12px; font-weight: 400; color: #94a3b8; display: block; }

        .wallet-badge {
            background: #22c55e22;
            border: 1px solid #22c55e44;
            color: #4ade80;
            padding: 6px 14px;
            border-radius: 99px;
            font-size: 14px;
            font-weight: 700;
        }

        .topbar-actions { display: flex; align-items: center; gap: 10px; }

        .logout-btn {
            color: #94a3b8;
            font-size: 12px;
            text-decoration: none;
            background: none;
            border: none;
            cursor: pointer;
        }

        .content { padding: 16px; max-width: 680px; margin: 0 auto; }

        /* Alerts */
        .alert { padding: 12px 14px; border-radius: 8px; font-size: 14px; margin-bottom: 12px; }
        .alert-success { background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; }
        .alert-error   { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }

        /* Cards */
        .card {
            background: #fff;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
            padding: 16px;
            margin-bottom: 12px;
        }

        .card-title {
            font-size: 13px;
            font-weight: 600;
            color: #475569;
            margin-bottom: 12px;
        }

        /* Package grid */
        .package-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 10px;
        }

        .pkg-card {
            background: #fff;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            padding: 14px 12px;
            text-align: center;
            cursor: pointer;
            transition: all 0.15s;
            position: relative;
        }

        .pkg-card:hover { border-color: #3b82f6; transform: translateY(-1px); box-shadow: 0 4px 12px #3b82f611; }
        .pkg-card.disabled { opacity: 0.5; cursor: not-allowed; }

        .pkg-price  { font-size: 22px; font-weight: 800; color: #0f172a; }
        .pkg-tzs    { font-size: 11px; color: #94a3b8; margin-bottom: 4px; }
        .pkg-name   { font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px; }
        .pkg-dur    { font-size: 11px; color: #64748b; margin-bottom: 2px; }
        .pkg-speed  { font-size: 10px; color: #94a3b8; }
        .pkg-avail  {
            display: inline-block;
            margin-top: 8px;
            font-size: 10px;
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 99px;
        }
        .pkg-avail.ok   { background: #dcfce7; color: #15803d; }
        .pkg-avail.none { background: #fee2e2; color: #dc2626; }

        /* Sell modal */
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: #00000066;
            z-index: 100;
            align-items: center;
            justify-content: center;
        }
        .modal-overlay.show { display: flex; }

        .modal {
            background: #fff;
            border-radius: 14px;
            padding: 24px;
            width: 90%;
            max-width: 380px;
        }

        .modal h2 { font-size: 18px; font-weight: 700; margin-bottom: 4px; }
        .modal p  { font-size: 13px; color: #64748b; margin-bottom: 16px; }

        .modal-field { margin-bottom: 14px; }
        .modal-field label { font-size: 12px; font-weight: 600; color: #374151; display: block; margin-bottom: 4px; }
        .modal-field input {
            width: 100%; padding: 10px 12px;
            border: 1px solid #d1d5db; border-radius: 7px; font-size: 14px;
        }

        .modal-actions { display: flex; gap: 8px; }

        .btn {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 10px 18px; border-radius: 8px; font-size: 14px;
            font-weight: 600; cursor: pointer; border: none;
            text-decoration: none; transition: all 0.15s; flex: 1; justify-content: center;
        }
        .btn-primary  { background: #2563eb; color: #fff; }
        .btn-primary:hover { background: #1d4ed8; }
        .btn-primary:disabled { background: #93c5fd; cursor: not-allowed; }
        .btn-secondary { background: #f1f5f9; color: #334155; border: 1px solid #e2e8f0; }

        /* Success screen */
        .success-screen {
            display: none;
            text-align: center;
            padding: 16px 0;
        }
        .success-code {
            font-family: 'Courier New', monospace;
            font-size: 28px;
            font-weight: 800;
            letter-spacing: 4px;
            color: #0f172a;
            background: #f0fdf4;
            border: 2px dashed #22c55e;
            border-radius: 10px;
            padding: 16px;
            margin: 12px 0;
        }

        /* Recent sales */
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        tbody td { padding: 9px 10px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
        tbody tr:last-child td { border-bottom: none; }
        .badge { display: inline-flex; align-items: center; padding: 2px 7px; border-radius: 99px; font-size: 11px; font-weight: 600; }
        .badge-muted { background: #f1f5f9; color: #64748b; }
    </style>
    @stack('head')
</head>
<body>

<div class="topbar">
    <div class="topbar-brand">
        POS — {{ $tenant->name }}
        <span>{{ $agent->name }}</span>
    </div>
    <div class="topbar-actions">
        <div class="wallet-badge" id="walletBadge">
            {{ number_format($wallet->balance ?? 0) }} TZS
        </div>
        <form method="POST" action="{{ route('tenant.logout') }}" style="display:inline;">
            @csrf
            <button type="submit" class="logout-btn">Sign out</button>
        </form>
    </div>
</div>

<div class="content">
    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-error">
            @foreach ($errors->all() as $e) <div>{{ $e }}</div> @endforeach
        </div>
    @endif

    @yield('content')
</div>

@stack('scripts')
</body>
</html>
