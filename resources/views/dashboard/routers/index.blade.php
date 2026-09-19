@extends('dashboard.layout')

@section('title', 'Routers')
@section('breadcrumb', 'Routers')

@section('content')

<div class="page-header">
    <div>
        <div class="page-title">Router Fleet</div>
        <div class="page-sub">MikroTik routers connected to {{ $tenant->name }}</div>
    </div>
    <a href="{{ route('dashboard.routers.create') }}" class="btn btn-primary">+ Add Router</a>
</div>

@if ($routers->isEmpty())
    <div class="card">
        <div class="empty-state">
            <div class="icon"><i class="fa-solid fa-wifi"></i></div>
            <p>No routers added yet. Add your first MikroTik router to get started.</p>
            <a href="{{ route('dashboard.routers.create') }}" class="btn btn-primary" style="margin-top:16px;display:inline-flex;">+ Add Router</a>
        </div>
    </div>
@else
    <div class="card">
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Connection</th>
                        <th>Status</th>
                        <th>Customers online</th>
                        <th>Last seen</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($routers as $router)
                        @php($online = $router->isRadius() ? $router->isOnline() : $router->status === 'online')
                        <tr>
                            <td>
                                <a href="{{ route('dashboard.routers.edit', $router) }}" style="font-weight:600;color:#0f172a;text-decoration:none;">{{ $router->name }}</a><br>
                                <span style="font-family:monospace;font-size:11px;color:#94a3b8;">{{ $router->nas_identifier }}</span>
                            </td>
                            <td style="font-size:12px;color:#64748b;">
                                @if ($router->isRadius())
                                    One-command
                                    @if ($router->routeros_version) <br>RouterOS {{ $router->routeros_version }} @endif
                                @else
                                    API {{ $router->router_ip }}:{{ $router->port }}
                                @endif
                            </td>
                            <td>
                                @if ($router->provision_status === 'failed')
                                    <span class="badge badge-danger" title="{{ $router->provision_note }}">Setup incomplete</span>
                                @elseif ($router->isRadius() && ! $router->last_seen_at)
                                    <span class="badge badge-unknown">Not connected</span>
                                @else
                                    <span class="badge badge-{{ $online ? 'online' : 'offline' }}">{{ $online ? 'Online' : 'Offline' }}</span>
                                @endif
                            </td>
                            <td>{{ $router->isRadius() ? number_format($router->active_users) : '-' }}</td>
                            <td style="color:#64748b;font-size:13px;">
                                {{ $router->last_seen_at ? $router->last_seen_at->diffForHumans() : 'Never' }}
                            </td>
                            <td>
                                <div style="display:flex;gap:6px;flex-wrap:wrap;">
                                    @if ($router->isRadius())
                                        <button type="button" class="btn btn-secondary btn-sm"
                                                data-command="{{ $commands[$router->id] ?? '' }}"
                                                onclick="copyProvisionCmd(this)" title="Copy the setup command">
                                            <i class="fa-solid fa-bolt" style="color:#eab308;"></i> Setup command
                                        </button>
                                    @endif
                                    <a href="{{ route('dashboard.routers.edit', $router) }}" class="btn btn-secondary btn-sm">Manage</a>
                                    <form method="POST" action="{{ route('dashboard.routers.destroy', $router) }}"
                                          onsubmit="return confirm('Remove this router?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-danger btn-sm">Remove</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif

@endsection

@push('scripts')
<script>
function copyProvisionCmd(btn) {
    const cmd = btn.dataset.command;
    navigator.clipboard.writeText(cmd).then(() => {
        const original = btn.innerHTML;
        btn.innerHTML = '<i class="fa-solid fa-check"></i> Copied';
        setTimeout(() => { btn.innerHTML = original; }, 2000);
    });
}
</script>
@endpush
