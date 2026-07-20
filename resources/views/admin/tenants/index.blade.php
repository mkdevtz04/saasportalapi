@extends('admin.layout')

@section('title', 'ISPs')
@section('breadcrumb', 'ISP Management')

@section('content')

<div class="page-header">
    <div>
        <div class="page-title">ISPs</div>
        <div class="page-sub">All registered internet service providers</div>
    </div>
</div>

{{-- Status tabs --}}
<div class="status-tabs">
    @foreach (['all' => 'All', 'active' => 'Active', 'trial' => 'Trial', 'suspended' => 'Suspended'] as $val => $label)
        <a href="{{ route('admin.tenants.index', array_merge(request()->except('status', 'page'), $val !== 'all' ? ['status' => $val] : [])) }}"
           class="status-tab {{ (request('status', 'all') === $val) ? 'active' : '' }}">
            {{ $label }}
        </a>
    @endforeach
</div>

{{-- Search --}}
<form method="GET" class="filter-bar">
    @if (request('status')) <input type="hidden" name="status" value="{{ request('status') }}"> @endif
    <input type="text" name="q" value="{{ request('q') }}" placeholder="Search by name or subdomain…" style="flex:1;min-width:200px;">
    <button type="submit" class="btn btn-primary btn-sm">Search</button>
    @if (request('q')) <a href="{{ route('admin.tenants.index', request()->except('q', 'page')) }}" class="btn btn-secondary btn-sm">Clear</a> @endif
</form>

<div class="card" style="padding:0;">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>ISP</th>
                    <th>Status</th>
                    <th>Joined</th>
                    <th>Trial / Billing</th>
                    <th>Wallet Balance</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            @forelse ($tenants as $tenant)
                <tr>
                    <td>
                        <a href="{{ route('admin.tenants.show', $tenant) }}" style="font-weight:600;color:#0f172a;text-decoration:none;">{{ $tenant->name }}</a><br>
                        <span style="color:#94a3b8;font-size:11px;">{{ $tenant->subdomain }}.trinetpay.online</span>
                    </td>
                    <td><span class="badge badge-{{ $tenant->status }}">{{ ucfirst($tenant->status) }}</span></td>
                    <td style="color:#64748b;font-size:12px;">{{ $tenant->created_at->format('d M Y') }}</td>
                    <td style="font-size:12px;color:#64748b;">
                        @if ($tenant->status === 'trial' && $tenant->trial_ends_at)
                            Trial ends {{ $tenant->trial_ends_at->format('d M Y') }}<br>
                            @if ($tenant->trial_ends_at->isPast())
                                <span style="color:#dc2626;font-size:11px;">Expired</span>
                            @else
                                <span style="color:#16a34a;font-size:11px;">{{ $tenant->trial_ends_at->diffForHumans() }}</span>
                            @endif
                        @elseif ($tenant->next_billing_at)
                            Next billing {{ $tenant->next_billing_at->format('d M Y') }}
                        @else
                            —
                        @endif
                    </td>
                    <td>
                        <strong>{{ number_format($tenant->wallet?->balance ?? 0) }}</strong>
                        <span style="color:#94a3b8;font-size:11px;">TZS</span>
                    </td>
                    <td>
                        <a href="{{ route('admin.tenants.show', $tenant) }}" class="btn btn-secondary btn-sm">View</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" style="text-align:center;color:#94a3b8;padding:32px 0;">No ISPs found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

{{ $tenants->links('dashboard.pagination') }}

@endsection
