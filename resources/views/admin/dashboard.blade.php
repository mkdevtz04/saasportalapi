@extends('admin.layout')

@section('title', 'Overview')
@section('breadcrumb', 'Platform Overview')

@section('content')

{{-- Stats --}}
<div class="stats-grid">
    <div class="stat-card">
        <span class="stat-icon">🏢</span>
        <div class="stat-label">Total ISPs</div>
        <div class="stat-value">{{ $stats['total'] }}</div>
        <div class="stat-sub">
            {{ $stats['active'] }} active · {{ $stats['trial'] }} trial · {{ $stats['suspended'] }} suspended
        </div>
    </div>
    <div class="stat-card">
        <span class="stat-icon">💰</span>
        <div class="stat-label">Platform Earnings (Month)</div>
        <div class="stat-value">{{ number_format($earnings['month']) }}</div>
        <div class="stat-sub">TZS · Total: {{ number_format($earnings['total']) }} TZS</div>
    </div>
    <div class="stat-card">
        <span class="stat-icon">💸</span>
        <div class="stat-label">Pending Withdrawals</div>
        <div class="stat-value">{{ $pendingWithdrawals->count ?? 0 }}</div>
        <div class="stat-sub">{{ number_format($pendingWithdrawals->total ?? 0) }} TZS outstanding</div>
    </div>
    <div class="stat-card">
        <span class="stat-icon">🆕</span>
        <div class="stat-label">ISP Status</div>
        <div class="stat-value" style="font-size:18px;margin-top:8px;">
            <span class="badge badge-active">{{ $stats['active'] }} Active</span>
            <span class="badge badge-trial" style="margin-left:4px;">{{ $stats['trial'] }} Trial</span>
        </div>
        <div class="stat-sub" style="margin-top:6px;">{{ $stats['suspended'] }} suspended</div>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:18px;">

    {{-- Recent ISPs --}}
    <div class="card">
        <div class="card-title">🏢 Recent ISPs
            <a href="{{ route('admin.tenants.index') }}" style="font-size:12px;color:#4f46e5;font-weight:500;margin-left:auto;">View all →</a>
        </div>
        @if ($recentTenants->isEmpty())
            <p style="color:#94a3b8;font-size:13px;text-align:center;padding:20px 0;">No ISPs yet.</p>
        @else
            <table>
                <tbody>
                @foreach ($recentTenants as $t)
                    <tr>
                        <td>
                            <a href="{{ route('admin.tenants.show', $t) }}" style="font-weight:600;color:#1e293b;text-decoration:none;">{{ $t->name }}</a><br>
                            <span style="color:#94a3b8;font-size:11px;">{{ $t->subdomain }}.trinetpay.online</span>
                        </td>
                        <td style="text-align:right;">
                            <span class="badge badge-{{ $t->status }}">{{ ucfirst($t->status) }}</span><br>
                            <span style="color:#94a3b8;font-size:11px;">{{ $t->created_at->format('d M Y') }}</span>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </div>

    {{-- Recent earnings --}}
    <div class="card">
        <div class="card-title">💰 Recent Platform Earnings</div>
        @if ($recentBilling->isEmpty())
            <p style="color:#94a3b8;font-size:13px;text-align:center;padding:20px 0;">No earnings yet.</p>
        @else
            <table>
                <tbody>
                @foreach ($recentBilling as $log)
                    <tr>
                        <td>
                            <span style="font-weight:500;">{{ $log->tenant?->name ?? '—' }}</span><br>
                            <span style="color:#94a3b8;font-size:11px;">{{ $log->reference }}</span>
                        </td>
                        <td style="text-align:right;">
                            <span style="font-weight:700;color:#15803d;">+{{ number_format($log->amount) }}</span><br>
                            <span style="color:#94a3b8;font-size:11px;">{{ $log->created_at->format('d M, H:i') }}</span>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </div>

</div>

@if ($pendingWithdrawals->count > 0)
    <div class="alert alert-info" style="display:flex;align-items:center;justify-content:space-between;">
        <span>💸 <strong>{{ $pendingWithdrawals->count }}</strong> pending withdrawal{{ $pendingWithdrawals->count != 1 ? 's' : '' }} totalling <strong>TZS {{ number_format($pendingWithdrawals->total) }}</strong></span>
        <a href="{{ route('admin.withdrawals.index') }}" class="btn btn-primary btn-sm">Review</a>
    </div>
@endif

@endsection
