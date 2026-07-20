@extends('dashboard.layout')

@section('title', 'Wallet')
@section('breadcrumb', 'Wallet')

@section('content')

<div class="page-header">
    <div>
        <div class="page-title">Wallet & Withdrawals</div>
        <div class="page-sub">Your earnings collected through TrinetPay</div>
    </div>
</div>

{{-- Balance cards --}}
<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;margin-bottom:20px;">
    <div class="stat-card" style="border-left:4px solid #22c55e;">
        <div class="stat-label">Available Balance</div>
        <div class="stat-value" style="color:#15803d;">{{ number_format($wallet->balance ?? 0) }}</div>
        <div class="stat-sub">TZS — ready to withdraw</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Total Earned (Lifetime)</div>
        <div class="stat-value">{{ number_format($wallet->total_earned ?? 0) }}</div>
        <div class="stat-sub">TZS collected from customers</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Pending Withdrawals</div>
        <div class="stat-value">{{ $requests->where('status', 'pending')->count() }}</div>
        <div class="stat-sub">requests being processed</div>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1.6fr;gap:20px;align-items:start;">

    {{-- Request withdrawal form --}}
    <div class="card">
        <div class="card-title">💸 Request Withdrawal</div>

        <div style="background:#fefce8;border:1px solid #fde68a;border-radius:8px;padding:12px 14px;font-size:13px;color:#92400e;margin-bottom:16px;line-height:1.6;">
            Minimum withdrawal: <strong>5,000 TZS</strong><br>
            Processed within <strong>24 hours</strong> to your mobile money.
        </div>

        <form method="POST" action="{{ route('dashboard.wallet.withdraw') }}">
            @csrf

            <div class="field" style="margin-bottom:16px;">
                <label>Amount (TZS)</label>
                <input type="number" name="amount" min="5000" step="500"
                       value="{{ old('amount') }}"
                       placeholder="10000"
                       max="{{ $wallet->balance ?? 0 }}">
                <span class="hint">Available: {{ number_format($wallet->balance ?? 0) }} TZS</span>
                @error('amount') <span class="error">{{ $message }}</span> @enderror
            </div>

            <div class="field" style="margin-bottom:20px;">
                <label>Mobile Number</label>
                <input type="tel" name="mobile_number"
                       value="{{ old('mobile_number', $settings?->withdrawal_number) }}"
                       placeholder="0712 345 678" required>
                <span class="hint">M-Pesa, Airtel, Tigo, or Halotel number</span>
                @error('mobile_number') <span class="error">{{ $message }}</span> @enderror
            </div>

            <button type="submit" class="btn btn-primary" style="width:100%;"
                    {{ ($wallet->balance ?? 0) < 5000 ? 'disabled' : '' }}>
                Request Withdrawal
            </button>

            @if (($wallet->balance ?? 0) < 5000)
                <p style="font-size:12px;color:#dc2626;text-align:center;margin-top:8px;">
                    Balance must be at least 5,000 TZS
                </p>
            @endif
        </form>
    </div>

    {{-- Withdrawal history --}}
    <div class="card">
        <div class="card-title">📋 Withdrawal History</div>

        @if ($requests->isEmpty())
            <div class="empty-state" style="padding:24px;">
                <div class="icon">📋</div>
                <p>No withdrawal requests yet.</p>
            </div>
        @else
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Amount</th>
                            <th>Mobile</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($requests as $req)
                            <tr>
                                <td style="color:#64748b;font-size:13px;white-space:nowrap;">
                                    {{ $req->created_at->format('d M Y') }}
                                </td>
                                <td style="font-weight:600;">{{ number_format($req->amount) }} TZS</td>
                                <td style="font-size:13px;">{{ $req->mobile_number }}</td>
                                <td>
                                    @if ($req->status === 'paid')
                                        <span class="badge badge-success">Paid</span>
                                    @elseif ($req->status === 'pending')
                                        <span class="badge badge-warning">Pending</span>
                                    @elseif ($req->status === 'approved')
                                        <span class="badge badge-success">Approved</span>
                                    @else
                                        <span class="badge badge-danger">Rejected</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div style="margin-top:12px;">
                {{ $requests->links('dashboard.pagination') }}
            </div>
        @endif
    </div>

</div>

@endsection
