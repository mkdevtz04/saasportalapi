@extends('admin.layout')

@section('title', 'Reconciliation')
@section('breadcrumb', 'Reconciliation')

@section('content')

<div class="page-header">
    <div>
        <div class="page-title">Reconciliation</div>
        <div class="page-sub">Check this before paying any withdrawal.</div>
    </div>
</div>

@if ($shortfall)
    <div class="alert alert-error"><strong>The platform owes more than it collected.</strong> The money owed to ISPs is higher than the expected PalmPesa balance. Do not pay withdrawals until this is explained.</div>
@endif
@if ($problems > 0)
    <div class="alert alert-error"><strong>{{ $problems }} ISP wallet(s) do not add up.</strong> See the table below and do not pay their withdrawals until reviewed.</div>
@endif
@if (! $shortfall && $problems === 0)
    <div class="alert alert-success">Every wallet is explained by portal payments and the ledger, and the platform is not short.</div>
@endif

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-label">Collected from customers</div>
        <div class="stat-value">{{ number_format($platform['collected']) }}</div>
        <div class="stat-sub">TZS, confirmed portal payments</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Paid out to ISPs</div>
        <div class="stat-value">{{ number_format($platform['paid_out']) }}</div>
        <div class="stat-sub">TZS, withdrawals marked paid, after fees</div>
    </div>
    <div class="stat-card" style="border-left:4px solid #6366f1;">
        <div class="stat-label">Expected PalmPesa balance</div>
        <div class="stat-value">{{ number_format($platform['expected_cash']) }}</div>
        <div class="stat-sub">TZS before PalmPesa's own charges. Compare with the real balance.</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Owed to ISPs</div>
        <div class="stat-value">{{ number_format($platform['owed_total']) }}</div>
        <div class="stat-sub">{{ number_format($platform['owed_wallets']) }} in wallets, {{ number_format($platform['owed_open']) }} in open withdrawals</div>
    </div>
    <div class="stat-card" style="border-left:4px solid {{ $platform['platform_money'] < 0 ? '#dc2626' : '#16a34a' }};">
        <div class="stat-label">Platform's own money</div>
        <div class="stat-value" style="color:{{ $platform['platform_money'] < 0 ? '#dc2626' : '#15803d' }};">{{ number_format($platform['platform_money']) }}</div>
        <div class="stat-sub">TZS. Expected balance minus what is owed. Must not be negative.</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Fees earned</div>
        <div class="stat-value">{{ number_format($platform['fees_earned']) }}</div>
        <div class="stat-sub">TZS paid, plus {{ number_format($platform['fees_pending']) }} in open withdrawals</div>
    </div>
</div>

<div class="card">
    <div class="card-title">Payments for PalmPesa</div>
    <form method="GET" action="{{ route('admin.reconciliation.export') }}" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;">
        <div class="field"><label>From</label><input type="date" name="from" value="{{ now()->subDays(30)->format('Y-m-d') }}"></div>
        <div class="field"><label>To</label><input type="date" name="to" value="{{ now()->format('Y-m-d') }}"></div>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-file-csv"></i> Download list</button>
    </form>
    <p style="font-size:12px;color:#94a3b8;margin-top:10px;">Every confirmed payment with its PalmPesa order id, to check line by line against the PalmPesa statement.</p>
</div>

<div class="card" style="padding:0;">
    <div style="padding:16px 20px;font-weight:700;">ISP wallets</div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>ISP</th><th>Portal paid</th><th>Withdrawn</th><th>Balance</th><th>Most it can hold</th><th>Unbacked</th><th>Ledger gap</th><th>Result</th></tr>
            </thead>
            <tbody>
            @forelse ($tenants as $row)
                <tr>
                    <td><a href="{{ route('admin.tenants.show', $row['tenant_id']) }}" style="font-weight:600;color:#0f172a;text-decoration:none;">{{ $row['name'] }}</a></td>
                    <td>{{ number_format($row['collected']) }}</td>
                    <td>{{ number_format($row['withdrawn']) }}</td>
                    <td><strong>{{ number_format($row['balance']) }}</strong></td>
                    <td>{{ number_format($row['ceiling']) }}</td>
                    <td style="color:{{ $row['excess'] > 0 ? '#dc2626' : '#94a3b8' }};">{{ $row['excess'] > 0 ? number_format($row['excess']) : '-' }}</td>
                    <td style="color:{{ $row['drift'] !== 0 ? '#dc2626' : '#94a3b8' }};">{{ $row['drift'] !== 0 ? number_format($row['drift']) : '-' }}</td>
                    <td>
                        @if ($row['result'] === 'ok')
                            <span class="badge badge-active">OK</span>
                        @else
                            <span class="badge badge-suspended">{{ $row['result'] }}</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" style="text-align:center;color:#94a3b8;padding:24px 0;">No wallets yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection
