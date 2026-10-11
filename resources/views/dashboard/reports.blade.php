@extends('dashboard.layout')

@section('title', 'Reports')
@section('breadcrumb', 'Reports')

@if ($hasUsageData)
@push('head')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
@endpush
@endif

@section('content')

<div class="page-header">
    <div>
        <div class="page-title">Sales report</div>
        <div class="page-sub">{{ $from->format('d M Y') }} to {{ $to->format('d M Y') }}, East Africa Time</div>
    </div>
    <a href="{{ route('dashboard.reports.export', ['from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d')]) }}" class="btn btn-secondary">
        <i class="fa-solid fa-file-csv"></i> Download for Excel
    </a>
</div>

<div class="card" style="padding:16px 24px;">
    <form method="GET" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;">
        <div class="field" style="flex:1;min-width:150px;">
            <label>From</label>
            <input type="date" name="from" value="{{ $from->format('Y-m-d') }}">
        </div>
        <div class="field" style="flex:1;min-width:150px;">
            <label>To</label>
            <input type="date" name="to" value="{{ $to->format('Y-m-d') }}">
        </div>
        <button type="submit" class="btn btn-primary">Show</button>
        <a href="{{ route('dashboard.reports') }}" class="btn btn-secondary">This month</a>
    </form>
    @error('from') <span class="error">{{ $message }}</span> @enderror
    @error('to') <span class="error">{{ $message }}</span> @enderror
</div>

<div class="stats-grid">
    <div class="stat-card">
        <span class="stat-icon"><i class="fa-solid fa-credit-card"></i></span>
        <div class="stat-label">Portal payments</div>
        <div class="stat-value">{{ number_format($totals['portal']) }}</div>
        <div class="stat-sub">TZS from {{ number_format($totals['portal_count']) }} payments, paid into your wallet</div>
    </div>
    <div class="stat-card">
        <span class="stat-icon"><i class="fa-solid fa-ticket"></i></span>
        <div class="stat-label">Voucher sales</div>
        <div class="stat-value">{{ number_format($totals['voucher']) }}</div>
        <div class="stat-sub">TZS from {{ number_format($totals['voucher_count']) }} vouchers, cash you collected</div>
    </div>
    <div class="stat-card">
        <span class="stat-icon"><i class="fa-solid fa-money-bill-transfer"></i></span>
        <div class="stat-label">Withdrawn</div>
        <div class="stat-value">{{ number_format($withdrawals->net ?? 0) }}</div>
        <div class="stat-sub">TZS received, after {{ number_format($withdrawals->fees ?? 0) }} TZS in fees</div>
    </div>
    <div class="stat-card">
        <span class="stat-icon"><i class="fa-solid fa-coins"></i></span>
        <div class="stat-label">Total sales</div>
        <div class="stat-value">{{ number_format($totals['portal'] + $totals['voucher']) }}</div>
        <div class="stat-sub">TZS, both types</div>
    </div>
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:18px;">
    <div class="card">
        <div class="card-title"><i class="fa-solid fa-box"></i> By package</div>
        @if (empty($byPackage))
            <p style="color:#94a3b8;font-size:13px;">No sales in this period.</p>
        @else
            <div class="table-wrap"><table>
                <thead><tr><th>Package</th><th>Sold</th><th>Amount</th></tr></thead>
                <tbody>
                @foreach ($byPackage as $name => $row)
                    <tr><td>{{ $name }}</td><td>{{ number_format($row['count']) }}</td><td style="font-weight:600;">{{ number_format($row['amount']) }} TZS</td></tr>
                @endforeach
                </tbody>
            </table></div>
        @endif
    </div>

    <div class="card">
        <div class="card-title"><i class="fa-solid fa-wifi"></i> By router</div>
        @if (empty($byRouter))
            <p style="color:#94a3b8;font-size:13px;">No sales in this period.</p>
        @else
            <div class="table-wrap"><table>
                <thead><tr><th>Router</th><th>Sold</th><th>Amount</th></tr></thead>
                <tbody>
                @foreach ($byRouter as $name => $row)
                    <tr><td>{{ $name }}</td><td>{{ number_format($row['count']) }}</td><td style="font-weight:600;">{{ number_format($row['amount']) }} TZS</td></tr>
                @endforeach
                </tbody>
            </table></div>
        @endif
    </div>
</div>

<div class="card">
    <div class="card-title"><i class="fa-solid fa-calendar-days"></i> By day</div>
    <div class="table-wrap"><table>
        <thead><tr><th>Day</th><th>Portal payments</th><th>Vouchers</th><th>Total</th></tr></thead>
        <tbody>
        @foreach (array_reverse($byDay, true) as $day => $row)
            @if ($row['portal'] + $row['voucher'] > 0)
                <tr>
                    <td>{{ \Illuminate\Support\Carbon::parse($day)->format('D d M Y') }}</td>
                    <td>{{ number_format($row['portal']) }}</td>
                    <td>{{ number_format($row['voucher']) }}</td>
                    <td style="font-weight:600;">{{ number_format($row['portal'] + $row['voucher']) }} TZS</td>
                </tr>
            @endif
        @endforeach
        </tbody>
    </table></div>
</div>

<div class="card">
    <div class="card-title"><i class="fa-solid fa-gauge-high"></i> Bandwidth — data used in this period</div>
    @if (! $hasUsageData)
        <p style="color:#94a3b8;font-size:13px;">
            No usage history yet. This fills in on its own as your agent-mode routers poll in —
            there is nothing to set up. Routers connected through RADIUS or the old API mode are
            not tracked here yet.
        </p>
    @else
        <canvas id="bandwidthChart" height="80"></canvas>
    @endif
</div>

@if ($hasUsageData)
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:18px;">
    <div class="card">
        <div class="card-title"><i class="fa-solid fa-wifi"></i> By router</div>
        @if (empty($byRouterBytes))
            <p style="color:#94a3b8;font-size:13px;">No usage in this period.</p>
        @else
            <div class="table-wrap"><table>
                <thead><tr><th>Router</th><th>Data used</th></tr></thead>
                <tbody>
                @foreach ($byRouterBytes as $name => $bytes)
                    <tr><td>{{ $name }}</td><td style="font-weight:600;">{{ \App\Support\Format::bytes($bytes) }}</td></tr>
                @endforeach
                </tbody>
            </table></div>
        @endif
    </div>
    <div class="stat-card" style="align-self:start;">
        <span class="stat-icon"><i class="fa-solid fa-database"></i></span>
        <div class="stat-label">Total data used</div>
        <div class="stat-value">{{ \App\Support\Format::bytes($totalBytes) }}</div>
        <div class="stat-sub">Across your agent-mode routers, this period</div>
    </div>
</div>

@push('scripts')
<script>
function formatBytes(bytes) {
    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    bytes = Math.max(0, Number(bytes));
    let i = 0;
    while (bytes >= 1024 && i < units.length - 1) { bytes /= 1024; i++; }
    return (i === 0 ? bytes.toFixed(0) : bytes.toFixed(1)) + ' ' + units[i];
}

const bwCtx = document.getElementById('bandwidthChart');
new Chart(bwCtx, {
    type: 'bar',
    data: {
        labels: @json(array_map(fn ($d) => \Illuminate\Support\Carbon::parse($d)->format('d M'), array_keys($byDayBytes))),
        datasets: [{
            label: 'Data used',
            data: @json(array_values($byDayBytes)),
            backgroundColor: '#2561e822',
            borderColor: '#2561e8',
            borderWidth: 2,
            borderRadius: 6,
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { display: false },
            tooltip: { callbacks: { label: ctx => formatBytes(ctx.raw) } }
        },
        scales: {
            y: { beginAtZero: true, ticks: { callback: val => formatBytes(val) } }
        }
    }
});
</script>
@endpush
@endif

@endsection
