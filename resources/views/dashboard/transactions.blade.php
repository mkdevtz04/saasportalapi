@extends('dashboard.layout')

@section('title', 'Transactions')
@section('breadcrumb', 'Transactions')

@section('content')

<div class="page-header">
    <div>
        <div class="page-title">Transactions</div>
        <div class="page-sub">All customer payments for {{ $tenant->name }}</div>
    </div>
</div>

{{-- Filters --}}
<div class="card" style="padding:16px 24px;">
    <form method="GET" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;">
        <div class="field" style="flex:1;min-width:140px;">
            <label>Status</label>
            <select name="status">
                <option value="">All statuses</option>
                <option value="completed" {{ request('status') === 'completed' ? 'selected' : '' }}>Paid</option>
                <option value="pending"   {{ request('status') === 'pending'   ? 'selected' : '' }}>Pending</option>
                <option value="failed"    {{ request('status') === 'failed'    ? 'selected' : '' }}>Failed</option>
            </select>
        </div>
        <div class="field" style="flex:1;min-width:160px;">
            <label>Phone</label>
            <input type="text" name="phone" value="{{ request('phone') }}" placeholder="07xx xxx xxx">
        </div>
        <div class="field" style="flex:1;min-width:160px;">
            <label>Date</label>
            <input type="date" name="date" value="{{ request('date') }}">
        </div>
        <div style="display:flex;gap:8px;">
            <button type="submit" class="btn btn-primary">Filter</button>
            @if (request()->hasAny(['status','phone','date']))
                <a href="{{ route('dashboard.transactions') }}" class="btn btn-secondary">Clear</a>
            @endif
        </div>
    </form>
</div>

<div class="card">
    @if ($transactions->isEmpty())
        <div class="empty-state">
            <div class="icon">🔍</div>
            <p>No transactions found for the selected filters.</p>
        </div>
    @else
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Phone</th>
                        <th>Package</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Token</th>
                        <th>Expires</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($transactions as $tx)
                        <tr>
                            <td style="color:#64748b;white-space:nowrap;font-size:13px;">
                                {{ $tx->created_at->format('d M Y H:i') }}
                            </td>
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
                            <td style="font-family:monospace;font-size:12px;color:#475569;">
                                {{ $tx->voucher_code ?? '—' }}
                            </td>
                            <td style="color:#64748b;font-size:13px;">
                                {{ $tx->expires_at ? $tx->expires_at->format('d M H:i') : '—' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div style="margin-top:16px;">
            {{ $transactions->links('dashboard.pagination') }}
        </div>
    @endif
</div>

@endsection
