@extends('admin.layout')

@section('title', $tenant->name)
@section('breadcrumb')
    <a href="{{ route('admin.tenants.index') }}" style="color:#e7eefc;text-decoration:none;">ISPs</a>
    <span style="color:#2561e8;margin:0 6px;">/</span>
    {{ $tenant->name }}
@endsection

@section('content')

<div class="page-header">
    <div>
        <div class="page-title">{{ $tenant->name }}</div>
        <div class="page-sub">{{ \App\Support\TenantUrls::portalLabel($tenant) }}</div>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <form method="POST" action="{{ route('admin.tenants.impersonate', $tenant) }}" onsubmit="return confirm('Open this ISP dashboard as platform support? Every step is recorded.')">
            @csrf
            <button type="submit" class="btn btn-secondary"><i class="fa-solid fa-user-shield"></i> Open as ISP</button>
        </form>
        @if ($tenant->status !== 'suspended')
            <form method="POST" action="{{ route('admin.tenants.suspend', $tenant) }}" onsubmit="return confirm('Suspend this ISP?')">
                @csrf
                <button type="submit" class="btn btn-danger">Suspend</button>
            </form>
        @else
            <form method="POST" action="{{ route('admin.tenants.activate', $tenant) }}">
                @csrf
                <button type="submit" class="btn btn-success">Activate</button>
            </form>
        @endif
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:18px;">

    {{-- Info --}}
    <div class="card">
        <div class="card-title">Account Info</div>
        <dl class="info-list">
            <dt>Status</dt>
            <dd><span class="badge badge-{{ $tenant->status }}">{{ ucfirst($tenant->status) }}</span></dd>
            <dt>Joined</dt>
            <dd>{{ $tenant->created_at->format('d M Y, H:i') }}</dd>
            <dt>Portal URL</dt>
            <dd><a href="{{ \App\Support\TenantUrls::portal($tenant) }}" target="_blank" style="color:#2561e8;">{{ \App\Support\TenantUrls::portalLabel($tenant) }} ↗</a></dd>
        </dl>
    </div>

    {{-- Revenue --}}
    <div class="card">
        <div class="card-title">Revenue Summary</div>
        <div class="stats-grid" style="grid-template-columns:1fr 1fr;gap:10px;">
            <div class="stat-card" style="padding:14px;">
                <div class="stat-label">This Month</div>
                <div class="stat-value" style="font-size:18px;">{{ number_format($monthRevenue) }}</div>
                <div class="stat-sub">TZS</div>
            </div>
            <div class="stat-card" style="padding:14px;">
                <div class="stat-label">Total Transactions</div>
                <div class="stat-value" style="font-size:18px;">{{ number_format($totalTransactions) }}</div>
                <div class="stat-sub">successful</div>
            </div>
            <div class="stat-card" style="padding:14px;">
                <div class="stat-label">Wallet Balance</div>
                <div class="stat-value" style="font-size:18px;">{{ number_format($tenant->wallet?->balance ?? 0) }}</div>
                <div class="stat-sub">TZS</div>
            </div>
            <div class="stat-card" style="padding:14px;">
                <div class="stat-label">Platform Earned</div>
                <div class="stat-value" style="font-size:18px;color:#2561e8;">{{ number_format($platformEarnings) }}</div>
                <div class="stat-sub">TZS from fees</div>
            </div>
        </div>
    </div>

</div>

{{-- Recent Transactions --}}
<div class="card">
    <div class="card-title">Recent Transactions</div>
    @if ($recentTransactions->isEmpty())
        <p style="color:#94a3b8;font-size:13px;text-align:center;padding:20px 0;">No transactions yet.</p>
    @else
        <div class="table-wrap">
            <table>
                <thead><tr><th>Phone</th><th>Package</th><th>Amount</th><th>Status</th><th>Date</th></tr></thead>
                <tbody>
                @foreach ($recentTransactions as $txn)
                    <tr>
                        <td style="font-family:monospace;">{{ $txn->phone }}</td>
                        <td style="color:#64748b;font-size:12px;">{{ $txn->package?->name ?? '—' }}</td>
                        <td><strong>{{ number_format($txn->amount) }}</strong> TZS</td>
                        <td>
                            @if ($txn->status === 'completed')
                                <span class="badge badge-paid">Completed</span>
                            @elseif ($txn->status === 'pending')
                                <span class="badge badge-pending">Pending</span>
                            @else
                                <span class="badge badge-rejected">{{ ucfirst($txn->status) }}</span>
                            @endif
                        </td>
                        <td style="color:#64748b;font-size:12px;">{{ $txn->created_at->format('d M, H:i') }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

{{-- Withdrawal history --}}
<div class="card">
    <div class="card-title">Withdrawal History</div>
    @if ($tenant->withdrawalRequests->isEmpty())
        <p style="color:#94a3b8;font-size:13px;text-align:center;padding:20px 0;">No withdrawal requests.</p>
    @else
        <div class="table-wrap">
            <table>
                <thead><tr><th>Amount</th><th>Mobile</th><th>Status</th><th>Date</th><th>Notes</th></tr></thead>
                <tbody>
                @foreach ($tenant->withdrawalRequests as $wr)
                    <tr>
                        <td><strong>{{ number_format($wr->amount) }}</strong> TZS</td>
                        <td style="font-family:monospace;">{{ $wr->mobile_number }}</td>
                        <td><span class="badge badge-{{ $wr->status }}">{{ ucfirst($wr->status) }}</span></td>
                        <td style="color:#64748b;font-size:12px;">{{ $wr->created_at->format('d M Y') }}</td>
                        <td style="color:#94a3b8;font-size:11px;">{{ $wr->admin_notes ?? '—' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

@endsection
