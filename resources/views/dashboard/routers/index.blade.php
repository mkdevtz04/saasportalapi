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
    @if ($routers->contains(fn ($router) => $router->agentNeedsUpdate()))
        <div class="card" style="border-left:3px solid #b45309;background:#fffbeb;">
            <div class="card-title" style="color:#78350f;">
                <i class="fa-solid fa-arrow-up-right-dots" style="color:#b45309;"></i> An improved script is available for some routers
            </div>
            <p style="font-size:14px;color:#78350f;line-height:1.7;margin:0 0 12px;">
                A router keeps the script it was given at setup, so improvements reach it only when you
                send them. The newer script holds each customer's time limit as a deadline on the router
                itself, so a six-hour package ends six hours after it was bought even if the customer
                switched their phone off in between. Until you update, those deadlines are still kept —
                by us, a minute at a time — so <strong>nothing is broken while you wait</strong>.
            </p>
            <p style="font-size:14px;color:#78350f;line-height:1.7;margin:0;">
                Use the <strong>Update script</strong> button on each flagged router. It replaces the
                script and nothing else: nobody is disconnected, nobody loses time they paid for, and
                your Wi-Fi name and password are untouched. Customers already online keep the old limits
                until their current session ends.
            </p>
            <p style="font-size:13px;color:#92400e;line-height:1.6;margin:12px 0 0;padding-top:12px;border-top:1px solid #fde68a;">
                <i class="fa-solid fa-circle-info"></i>
                Do <em>not</em> re-run the full <strong>Setup command</strong> for this. That one starts a
                router from a clean slate — it signs every customer out and resets the Wi-Fi name and
                password — which is right for a new router and disruptive on a working one.
            </p>
        </div>
    @endif

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
                        @php($online = $router->runsAgent() ? $router->isOnline() : $router->status === 'online')
                        <tr>
                            <td>
                                <a href="{{ route('dashboard.routers.edit', $router) }}" style="font-weight:600;color:#040a17;text-decoration:none;">{{ $router->name }}</a><br>
                                <span style="font-family:monospace;font-size:11px;color:#94a3b8;">{{ $router->nas_identifier }}</span>
                            </td>
                            <td style="font-size:12px;color:#64748b;">
                                @if ($router->runsAgent())
                                    {{ $router->isRadius() ? 'FreeRADIUS' : 'One-command' }}
                                    @if ($router->routeros_version) <br>RouterOS {{ $router->routeros_version }} @endif
                                @else
                                    API {{ $router->router_ip }}:{{ $router->port }}
                                @endif
                            </td>
                            <td>
                                @if ($router->provision_status === 'failed')
                                    <span class="badge badge-danger" title="{{ $router->provision_note }}">Setup incomplete</span>
                                @elseif ($router->runsAgent() && ! $router->last_seen_at)
                                    <span class="badge badge-unknown">Not connected</span>
                                @else
                                    <span class="badge badge-{{ $online ? 'online' : 'offline' }}">{{ $online ? 'Online' : 'Offline' }}</span>
                                @endif

                                @if ($router->agentNeedsUpdate())
                                    <div style="margin-top:5px;">
                                        <span class="badge badge-warning" title="Use the Update script button. No customer is disconnected.">Update available</span>
                                    </div>
                                @endif
                            </td>
                            <td>{{ $router->runsAgent() ? number_format($router->active_users) : '-' }}</td>
                            <td style="color:#64748b;font-size:13px;">
                                {{ $router->last_seen_at ? $router->last_seen_at->diffForHumans() : 'Never' }}
                            </td>
                            <td>
                                <div style="display:flex;gap:6px;flex-wrap:wrap;">
                                    @if ($router->runsAgent())
                                        @if ($router->agentNeedsUpdate())
                                            <button type="button" class="btn btn-primary btn-sm"
                                                    data-command="{{ $agentUpdates[$router->id] ?? '' }}"
                                                    onclick="copyProvisionCmd(this)"
                                                    title="Replaces the script only. Nobody is disconnected.">
                                                <i class="fa-solid fa-arrow-up"></i> Update script
                                            </button>
                                        @endif
                                        <button type="button" class="btn btn-secondary btn-sm"
                                                data-command="{{ $commands[$router->id] ?? '' }}"
                                                onclick="copyProvisionCmd(this)"
                                                title="Full setup. Signs customers out and resets the Wi-Fi name.">
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
