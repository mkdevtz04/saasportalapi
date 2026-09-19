@extends('admin.layout')

@section('title', 'Audit trail')
@section('breadcrumb', 'Audit trail')

@section('content')

<div class="page-header">
    <div>
        <div class="page-title">Audit trail</div>
        <div class="page-sub">Who did what. Entries can never be edited or deleted.</div>
    </div>
</div>

<div class="card" style="padding:16px 24px;">
    <form method="GET" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;">
        <div class="field" style="flex:1;min-width:180px;">
            <label>ISP</label>
            <select name="isp">
                <option value="">All</option>
                @foreach ($tenants as $t)
                    <option value="{{ $t->id }}" {{ (string) request('isp') === (string) $t->id ? 'selected' : '' }}>{{ $t->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="field" style="flex:1;min-width:180px;">
            <label>Action starts with</label>
            <input type="text" name="action" value="{{ request('action') }}" placeholder="withdrawal, tenant, router, session">
        </div>
        <button type="submit" class="btn btn-primary">Filter</button>
        @if (request()->hasAny(['isp', 'action']))
            <a href="{{ route('admin.audit') }}" class="btn btn-secondary">Clear</a>
        @endif
    </form>
</div>

<div class="card" style="padding:0;">
    <div class="table-wrap">
        <table>
            <thead><tr><th>When (EAT)</th><th>Who</th><th>ISP</th><th>Action</th><th>Details</th><th>From</th></tr></thead>
            <tbody>
            @forelse ($logs as $log)
                <tr>
                    <td style="white-space:nowrap;color:#64748b;font-size:12px;">{{ $log->created_at->timezone('Africa/Dar_es_Salaam')->format('d M Y H:i:s') }}</td>
                    <td style="font-size:12px;">
                        <span class="badge badge-{{ $log->actor_type === 'admin' ? 'trial' : 'active' }}" style="margin-right:4px;">{{ $log->actor_type }}</span>
                        {{ $log->actor_label }}
                    </td>
                    <td>{{ $log->tenant?->name ?? '—' }}</td>
                    <td style="font-family:monospace;font-size:12px;">{{ $log->action }}</td>
                    <td style="font-size:12px;color:#475569;max-width:340px;">
                        @foreach (($log->meta ?? []) as $key => $value)
                            <span style="white-space:nowrap;"><strong>{{ $key }}</strong>: {{ is_scalar($value) ? $value : json_encode($value) }}</span>@if (! $loop->last) · @endif
                        @endforeach
                    </td>
                    <td style="font-family:monospace;font-size:11px;color:#94a3b8;">{{ $log->ip }}</td>
                </tr>
            @empty
                <tr><td colspan="6" style="text-align:center;color:#94a3b8;padding:32px 0;">Nothing recorded yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

{{ $logs->links('dashboard.pagination') }}

@endsection
