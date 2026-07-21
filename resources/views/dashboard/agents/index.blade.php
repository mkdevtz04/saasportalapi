@extends('dashboard.layout')

@section('title', 'Agents')
@section('breadcrumb', 'Agents')

@section('content')

<div class="page-header">
    <div>
        <div class="page-title">Sales Agents</div>
        <div class="page-sub">Agents sell vouchers via the POS system using their wallet balance</div>
    </div>
    <a href="{{ route('dashboard.agents.create') }}" class="btn btn-primary">+ Add Agent</a>
</div>

@if ($agents->isEmpty())
    <div class="card">
        <div class="empty-state">
            <div class="icon"><i class="fa-solid fa-users"></i></div>
            <p>No agents yet. Add agents to let them sell vouchers via mobile POS.</p>
            <a href="{{ route('dashboard.agents.create') }}" class="btn btn-primary" style="margin-top:16px;display:inline-flex;">+ Add First Agent</a>
        </div>
    </div>
@else
    <div class="card">
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Wallet Balance</th>
                        <th>Top Up</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($agents as $agent)
                        <tr>
                            <td style="font-weight:600;">{{ $agent->name }}</td>
                            <td style="color:#64748b;">{{ $agent->email }}</td>
                            <td style="color:#64748b;">{{ $agent->phone ?? '—' }}</td>
                            <td>
                                <span style="font-weight:700;color:{{ ($agent->wallet?->balance ?? 0) > 0 ? '#15803d' : '#94a3b8' }};">
                                    {{ number_format($agent->wallet?->balance ?? 0) }} TZS
                                </span>
                            </td>
                            <td>
                                <form method="POST" action="{{ route('dashboard.agents.topup', $agent) }}"
                                      style="display:flex;gap:6px;align-items:center;">
                                    @csrf
                                    <input type="number" name="amount" placeholder="5000" min="500"
                                           style="width:100px;padding:5px 8px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;">
                                    <button type="submit" class="btn btn-success btn-sm">+ Add</button>
                                </form>
                            </td>
                            <td>
                                <form method="POST" action="{{ route('dashboard.agents.destroy', $agent) }}"
                                      onsubmit="return confirm('Remove {{ $agent->name }}?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-danger btn-sm">Remove</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- POS link instructions --}}
    <div class="card" style="background:#f8fafc;margin-top:0;">
        <div class="card-title"><i class="fa-solid fa-mobile-screen"></i> Agent POS Access</div>
        <p style="font-size:13px;color:#475569;margin-bottom:10px;">
            Share this URL with your agents. They log in using their email and password.
        </p>
        <code style="display:block;background:#1e293b;color:#e2e8f0;padding:10px 14px;border-radius:6px;font-size:13px;">
            https://trinetpay.online/pos
        </code>
    </div>
@endif

@endsection
