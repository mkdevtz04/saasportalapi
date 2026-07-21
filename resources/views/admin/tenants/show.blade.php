@extends('admin.layout')

@section('title', $tenant->name)
@section('breadcrumb')
    <a href="{{ route('admin.tenants.index') }}" style="color:#818cf8;text-decoration:none;">ISPs</a>
    <span style="color:#4c4a89;margin:0 6px;">/</span>
    {{ $tenant->name }}
@endsection

@section('content')

<div class="page-header">
    <div>
        <div class="page-title">{{ $tenant->name }}</div>
        <div class="page-sub">{{ $tenant->subdomain }}.trinetpay.online</div>
    </div>
    <div style="display:flex;gap:8px;">
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
            @if ($tenant->trial_ends_at)
            <dt>Trial Ends</dt>
            <dd>{{ $tenant->trial_ends_at->format('d M Y') }}
                @if ($tenant->trial_ends_at->isPast())
                    <span style="color:#dc2626;font-size:12px;">(expired)</span>
                @endif
            </dd>
            @endif
            <dt>Portal URL</dt>
            <dd><a href="//{{ $tenant->subdomain }}.trinetpay.online" target="_blank" style="color:#4f46e5;">{{ $tenant->subdomain }}.trinetpay.online ↗</a></dd>
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
                <div class="stat-value" style="font-size:18px;color:#7c3aed;">{{ number_format($platformEarnings) }}</div>
                <div class="stat-sub">TZS from fees</div>
            </div>
        </div>
    </div>

</div>

{{-- Monthly fee --}}
<div class="card">
    <div class="card-title">Monthly Subscription Fee</div>
    <form method="POST" action="{{ route('admin.tenants.set-fee', $tenant) }}" class="form-row">
        @csrf
        <div class="field" style="flex:1;">
            <label>Monthly Fee (TZS)</label>
            <input type="number" name="monthly_fee_tzs" value="{{ $tenant->monthly_fee_tzs ?? '' }}"
                   placeholder="Leave blank to use platform default ({{ number_format(config('platform.monthly_fee_tzs')) }} TZS)">
        </div>
        <button type="submit" class="btn btn-primary">Update Fee</button>
    </form>
    <p style="font-size:11px;color:#94a3b8;margin-top:8px;">Platform default: {{ number_format(config('platform.monthly_fee_tzs')) }} TZS/month</p>
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
                            @if ($txn->status === 'success')
                                <span class="badge badge-paid">Success</span>
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
