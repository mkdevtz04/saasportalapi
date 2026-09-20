@extends('dashboard.layout')

@section('title', 'Overview')
@section('breadcrumb', 'Overview')

@push('head')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
@endpush

@section('content')

@if ($offlineRouters->isNotEmpty())
    <div class="alert alert-error">
        <i class="fa-solid fa-triangle-exclamation"></i>
        <strong>{{ $offlineRouters->count() === 1 ? '1 router is' : $offlineRouters->count() . ' routers are' }} offline:</strong>
        {{ $offlineRouters->pluck('name')->implode(', ') }}. Customers cannot connect there until it is back.
        <a href="{{ route('dashboard.routers.index') }}" style="color:inherit;font-weight:700;">Check routers</a>
    </div>
@endif

@if ($renamedRouters->isNotEmpty())
    <div class="alert alert-error">
        <i class="fa-solid fa-triangle-exclamation"></i>
        <strong>Customers cannot log in on {{ $renamedRouters->pluck('name')->implode(', ') }}.</strong>
        The router was renamed. <a href="{{ route('dashboard.routers.edit', $renamedRouters->first()) }}" style="color:inherit;font-weight:700;">See how to fix it</a>
    </div>
@endif

@if ($accessProblems > 0)
    <div class="alert alert-error">
        <i class="fa-solid fa-circle-exclamation"></i>
        <strong>{{ $accessProblems }} {{ $accessProblems === 1 ? 'customer paid' : 'customers paid' }} but {{ $accessProblems === 1 ? 'is' : 'are' }} not online yet</strong> in the last 7 days.
        <a href="{{ route('dashboard.transactions', ['access' => 'failed']) }}" style="color:inherit;font-weight:700;">See who</a>
    </div>
@endif

<div class="stats-grid">
    <div class="stat-card">
        <span class="stat-icon"><i class="fa-solid fa-calendar"></i></span>
        <div class="stat-label">Today's Revenue</div>
        <div class="stat-value">{{ number_format($todayRevenue) }}</div>
        <div class="stat-sub">TZS collected today</div>
    </div>
    <div class="stat-card">
        <span class="stat-icon"><i class="fa-solid fa-arrow-trend-up"></i></span>
        <div class="stat-label">This Month</div>
        <div class="stat-value">{{ number_format($monthRevenue) }}</div>
        <div class="stat-sub">{{ number_format($monthCount) }} transactions</div>
    </div>
    <div class="stat-card">
        <span class="stat-icon"><i class="fa-solid fa-coins"></i></span>
        <div class="stat-label">Wallet Balance</div>
        <div class="stat-value">{{ number_format($wallet?->balance ?? 0) }}</div>
        <div class="stat-sub">
            <a href="{{ route('dashboard.wallet') }}" style="color:#2561e8;text-decoration:none;">Request withdrawal →</a>
        </div>
    </div>
    <div class="stat-card">
        <span class="stat-icon"><i class="fa-solid fa-wifi"></i></span>
        <div class="stat-label">Routers</div>
        <div class="stat-value">{{ ($routerStats['online'] ?? 0) + ($routerStats['offline'] ?? 0) + ($routerStats['unknown'] ?? 0) }}</div>
        <div class="stat-sub">
            {{ $routerStats['online'] ?? 0 }} online
            @if (($routerStats['offline'] ?? 0) > 0)
                · <span style="color:#dc2626">{{ $routerStats['offline'] }} offline</span>
            @endif
        </div>
    </div>
</div>

{{-- Revenue chart --}}
<div class="card">
    <div class="card-title"><i class="fa-solid fa-chart-column"></i> Revenue — Last 7 Days</div>
    <canvas id="revenueChart" height="80"></canvas>
</div>

{{-- Recent transactions --}}
<div class="card">
    <div class="card-title" style="justify-content:space-between;">
            <span><i class="fa-solid fa-clock"></i> Recent Transactions</span>
        <a href="{{ route('dashboard.transactions') }}" class="btn btn-secondary btn-sm">View all</a>
    </div>

    @if ($recentTransactions->isEmpty())
        <div class="empty-state">
                <div class="icon"><i class="fa-solid fa-credit-card"></i></div>
                <p>No transactions yet. Share your portal link to start collecting payments.</p>
        </div>
    @else
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Phone</th>
                        <th>Package</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Token</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($recentTransactions as $tx)
                        <tr>
                            <td style="color:#64748b;white-space:nowrap;">{{ $tx->created_at->format('d M H:i') }}</td>
                            <td>{{ $tx->phone }}</td>
                            <td>{{ $tx->package?->name ?? '—' }}</td>
                            <td style="font-weight:600;">{{ number_format($tx->amount) }} TZS</td>
                            <td>
                                @if ($tx->status === 'completed')
                                    <span class="badge badge-success">Paid</span>
                                @elseif ($tx->status === 'pending')
                                    <span class="badge badge-warning">Pending</span>
                                @else
                                    <span class="badge badge-danger">Failed</span>
                                @endif
                            </td>
                            <td style="font-family:monospace;font-size:12px;color:#475569;">{{ $tx->voucher_code ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

@endsection

@push('scripts')
<script>
const ctx = document.getElementById('revenueChart');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: @json($chartDays),
        datasets: [{
            label: 'Revenue (TZS)',
            data: @json($chartRevenue),
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
            tooltip: {
                callbacks: {
                    label: ctx => 'TZS ' + ctx.raw.toLocaleString()
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: val => 'TZS ' + val.toLocaleString()
                }
            }
        }
    }
});
</script>
@endpush
