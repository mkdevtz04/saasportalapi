@extends('dashboard.layout')

@section('title', 'Customer')
@section('breadcrumb', 'Customers')

@section('content')

<div class="page-header">
    <div>
        <div class="page-title">{{ $customer['phone'] ?? $customer['identity'] }}</div>
        <div class="page-sub">
            @if ($customer['active'])
                Has access until {{ $customer['expires_at']->format('d M Y, H:i') }}
            @else
                No access right now
            @endif
            &middot; customer since {{ $customer['first_seen']->format('d M Y') }}
        </div>
    </div>
    <div>
        <a href="{{ route('dashboard.customers.index') }}" class="btn btn-secondary">
            <i class="fa-solid fa-arrow-left"></i> All customers
        </a>
    </div>
</div>

<div class="stats-grid" style="margin-bottom:20px;">
    <div class="stat-card">
        <div class="stat-label">Purchases</div>
        <div class="stat-value">{{ number_format($customer['purchases']) }}</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Spent</div>
        <div class="stat-value">{{ number_format($customer['spent']) }} <span style="font-size:14px;color:#94a3b8;">TZS</span></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Devices</div>
        <div class="stat-value">{{ $customer['devices']->count() }}</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Last bought</div>
        <div class="stat-value" style="font-size:18px;">{{ $customer['last_seen']->diffForHumans() }}</div>
    </div>
</div>

{{-- Devices --}}
<div class="card">
    <div class="card-title"><i class="fa-solid fa-mobile-screen"></i> Devices</div>
    <p style="font-size:12px;color:#94a3b8;margin:-8px 0 14px;">The phones and laptops this customer has bought on</p>

    @if ($customer['devices']->isEmpty())
        <div class="empty-state" style="padding:24px;">
            <p>No device address was recorded for this customer.</p>
        </div>
    @else
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>MAC address</th>
                        <th>Last IP</th>
                        <th>Purchases</th>
                        <th>Last used</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($customer['devices'] as $device)
                        <tr>
                            <td style="font-family:monospace;font-size:13px;">{{ $device['mac'] }}</td>
                            <td style="font-family:monospace;font-size:12px;color:#64748b;">{{ $device['ip'] ?: '—' }}</td>
                            <td>{{ $device['purchases'] }}</td>
                            <td style="color:#64748b;font-size:13px;">{{ $device['last_seen']->format('d M Y H:i') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

{{-- Purchases --}}
<div class="card" style="margin-top:20px;">
    <div class="card-title"><i class="fa-solid fa-receipt"></i> Everything they bought</div>
    <p style="font-size:12px;color:#94a3b8;margin:-8px 0 14px;">Failed and abandoned payments included, so a complaint can be traced</p>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Ref</th>
                    <th>Package</th>
                    <th>Amount</th>
                    <th>Paid</th>
                    <th>Type</th>
                    <th>Token</th>
                    <th>Router</th>
                    <th>Access ran to</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($customer['sales'] as $sale)
                    <tr>
                        <td style="color:#64748b;white-space:nowrap;font-size:13px;">
                            {{ $sale->created_at->format('d M Y H:i') }}
                        </td>
                        <td style="font-family:monospace;font-size:12px;color:#475569;">{{ $sale->reference() }}</td>
                        <td>{{ $sale->package?->name ?? '—' }}</td>
                        <td style="font-weight:600;white-space:nowrap;">{{ number_format($sale->amount) }} TZS</td>
                        <td>
                            @if ($sale->status === 'completed')
                                <span class="badge badge-success">Paid</span>
                            @elseif ($sale->status === 'pending')
                                <span class="badge badge-warning">Pending</span>
                            @else
                                <span class="badge badge-danger">Failed</span>
                            @endif
                        </td>
                        <td style="font-size:12px;color:#64748b;">{{ $sale->channel === 'voucher' ? 'Voucher' : 'Portal' }}</td>
                        <td style="font-family:monospace;font-size:12px;color:#475569;">
                            {{ $sale->voucher_code ?? '—' }}
                            @if ($sale->voucher_code && $voucher = $customer['vouchers']->get($sale->voucher_code))
                                <div style="font-family:inherit;font-size:11px;color:#94a3b8;">
                                    batch {{ $voucher->batch_ref }}@if ($voucher->agent) &middot; sold by {{ $voucher->agent->name }} @endif
                                </div>
                            @endif
                        </td>
                        <td style="font-size:13px;color:#64748b;">{{ $sale->router?->name ?? '—' }}</td>
                        <td style="font-size:13px;color:#64748b;white-space:nowrap;">
                            @if (! $sale->expires_at)
                                —
                            @else
                                {{ $sale->expires_at->format('d M H:i') }}
                                @if ($sale->expires_at->isFuture() && $sale->access_ended_at === null)
                                    <span class="badge badge-success" style="margin-left:4px;">now</span>
                                @endif
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

@endsection
