@extends('dashboard.layout')

@section('title', 'Customers')
@section('breadcrumb', 'Customers')

@section('content')

<div class="page-header">
    <div>
        <div class="page-title">Customers</div>
        <div class="page-sub">Everyone who has bought WiFi from {{ $tenant->name }}</div>
    </div>
    <div>
        <a href="{{ route('dashboard.customers.export', request()->query()) }}" class="btn btn-secondary">
            <i class="fa-solid fa-file-csv"></i> Export CSV
        </a>
    </div>
</div>

{{-- Totals --}}
<div class="stats-grid" style="margin-bottom:20px;">
    <div class="stat-card">
        <div class="stat-label">Customers</div>
        <div class="stat-value">{{ number_format($summary['customers']) }}</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">With access now</div>
        <div class="stat-value" style="color:#15803d;">{{ number_format($summary['active']) }}</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Total bought</div>
        <div class="stat-value">{{ number_format($summary['spent']) }} <span style="font-size:14px;color:#94a3b8;">TZS</span></div>
    </div>
    @if ($summary['unidentified'] > 0)
        <div class="stat-card">
            <div class="stat-label">Sales naming nobody</div>
            <div class="stat-value" style="color:#b45309;">{{ number_format($summary['unidentified']) }}</div>
            <div style="font-size:11px;color:#94a3b8;margin-top:4px;">
                Voucher cards redeemed with no phone number or device address, so they belong to no customer here.
            </div>
        </div>
    @endif
</div>

{{-- Filters --}}
<div class="card" style="padding:16px 24px;">
    <form method="GET" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;">
        <div class="field" style="flex:2;min-width:200px;">
            <label>Search</label>
            <input type="text" name="search" value="{{ $filters['search'] }}"
                   placeholder="Phone, MAC address, IP or voucher code" maxlength="60">
        </div>
        <div class="field" style="flex:1;min-width:150px;">
            <label>Access</label>
            <select name="status">
                <option value="">Everyone</option>
                <option value="active"  {{ $filters['status'] === 'active'  ? 'selected' : '' }}>Has access now</option>
                <option value="expired" {{ $filters['status'] === 'expired' ? 'selected' : '' }}>Expired</option>
            </select>
        </div>
        @if ($routers->count() > 1)
            <div class="field" style="flex:1;min-width:150px;">
                <label>Router</label>
                <select name="router">
                    <option value="">All routers</option>
                    @foreach ($routers as $router)
                        <option value="{{ $router->id }}" {{ $filters['router'] === $router->id ? 'selected' : '' }}>
                            {{ $router->name }}
                        </option>
                    @endforeach
                </select>
            </div>
        @endif
        <div style="display:flex;gap:8px;">
            <button type="submit" class="btn btn-primary">Search</button>
            @if ($filters['search'] || $filters['status'] || $filters['router'])
                <a href="{{ route('dashboard.customers.index') }}" class="btn btn-secondary">Clear</a>
            @endif
        </div>
    </form>
</div>

<div class="card">
    @if ($customers->isEmpty())
        <div class="empty-state">
            <div class="icon"><i class="fa-solid fa-users"></i></div>
            <p>
                @if ($filters['search'] || $filters['status'] || $filters['router'])
                    No customer matches that search.
                @else
                    No customers yet. They will appear here as soon as the first person buys WiFi.
                @endif
            </p>
        </div>
    @else
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Customer</th>
                        <th>Device</th>
                        <th>Purchases</th>
                        <th>Spent</th>
                        <th>Access</th>
                        <th>First seen</th>
                        <th>Last seen</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($customers as $customer)
                        <tr>
                            <td style="font-weight:600;">
                                {{ $customer->phone ?? 'Device only' }}
                                @unless ($customer->phone)
                                    <div style="font-size:11px;color:#94a3b8;font-weight:400;">
                                        Bought with no phone number
                                    </div>
                                @endunless
                            </td>
                            <td style="font-family:monospace;font-size:12px;color:#475569;">
                                {{ $customer->mac ?: '—' }}
                                @if ($customer->devices > 1)
                                    <div style="font-family:inherit;font-size:11px;color:#94a3b8;">
                                        +{{ $customer->devices - 1 }} more
                                    </div>
                                @endif
                            </td>
                            <td>{{ number_format($customer->purchases) }}</td>
                            <td style="font-weight:600;white-space:nowrap;">{{ number_format($customer->spent) }} TZS</td>
                            <td>
                                @if ($customer->active)
                                    <span class="badge badge-success">Active</span>
                                    <div style="font-size:11px;color:#64748b;margin-top:3px;">
                                        until {{ $customer->expires_at->format('d M H:i') }}
                                    </div>
                                @else
                                    <span class="badge badge-muted">Expired</span>
                                @endif
                            </td>
                            <td style="color:#64748b;font-size:13px;white-space:nowrap;">
                                {{ $customer->first_seen?->format('d M Y') ?? '—' }}
                            </td>
                            <td style="color:#64748b;font-size:13px;white-space:nowrap;">
                                {{ $customer->last_seen?->diffForHumans() ?? '—' }}
                            </td>
                            <td>
                                <a href="{{ route('dashboard.customers.show', ['id' => $customer->identity]) }}"
                                   class="btn btn-secondary" style="padding:5px 12px;font-size:12px;">View</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div style="margin-top:16px;">
            {{ $customers->links('dashboard.pagination') }}
        </div>
    @endif
</div>

@endsection
