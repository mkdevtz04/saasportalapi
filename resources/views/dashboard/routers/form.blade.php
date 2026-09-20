@extends('dashboard.layout')

@php($isApi = $mode === 'api')

@section('title', $router ? 'Router: ' . $router->name : 'Add Router')
@section('breadcrumb', $router ? 'Router' : 'Add Router')

@section('content')

<div class="page-header">
    <div>
        <div class="page-title">{{ $router ? $router->name : 'Add New Router' }}</div>
        <div class="page-sub">
            <a href="{{ route('dashboard.routers.index') }}" style="color:#2561e8;text-decoration:none;">← Back to routers</a>
        </div>
    </div>
</div>

@if (! $isApi)
    {{-- ── One-command setup: the router connects out, works on any RouterOS version ── --}}

    @if (! $router)
        <div class="card" style="max-width:640px;">
            <form method="POST" action="{{ route('dashboard.routers.store') }}">
                @csrf
                <input type="hidden" name="mode" value="{{ $mode }}">
                <div class="field" style="margin-bottom:16px;">
                    <label>Router Name</label>
                    <input type="text" name="name" value="{{ old('name') }}" placeholder="Main Office Router" required maxlength="100">
                    <span class="hint">Only a name is needed. You will get a single command to paste into the router.</span>
                    @error('name') <span class="error">{{ $message }}</span> @enderror
                </div>
                <div style="display:flex;gap:10px;">
                    <button type="submit" class="btn btn-primary">Add Router</button>
                    <a href="{{ route('dashboard.routers.index') }}" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
            <p style="font-size:12px;color:#94a3b8;margin-top:18px;">
                Works with any MikroTik, RouterOS v6 and v7, even behind another router or a mobile connection.
                Nothing needs to be opened on the router. Customers are online a few seconds after they pay.
                <br>
                <a href="{{ route('dashboard.routers.create', ['mode' => 'api']) }}" style="color:#64748b;">Connect by API instead (advanced)</a>
                @if (\App\Models\TenantRouter::radiusIsConfigured())
                    &middot; <a href="{{ route('dashboard.routers.create', ['mode' => 'radius']) }}" style="color:#64748b;">Use FreeRADIUS (advanced)</a>
                @endif
            </p>
        </div>
    @else
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:14px;margin-bottom:18px;">
            <div class="stat-card">
                <div class="stat-label">Status</div>
                <div class="stat-value" style="font-size:18px;">
                    <span class="badge badge-{{ $router->isOnline() ? 'online' : 'offline' }}">{{ $router->isOnline() ? 'Online' : ($router->last_seen_at ? 'Offline' : 'Not connected') }}</span>
                </div>
                <div class="stat-sub">{{ $router->last_seen_at ? 'Last seen ' . $router->last_seen_at->diffForHumans() : 'Waiting for setup' }}</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Customers online</div>
                <div class="stat-value">{{ number_format($router->active_users) }}</div>
                <div class="stat-sub">right now</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">RouterOS</div>
                <div class="stat-value" style="font-size:18px;">{{ $router->routeros_version ?? '-' }}</div>
                <div class="stat-sub">{{ $router->public_ip ? 'from ' . $router->public_ip : 'no address yet' }}</div>
            </div>
        </div>

        @if ($router->identity_ok === false)
            <div class="alert alert-error" style="margin-bottom:16px;">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <strong>The router name was changed, so customers cannot log in.</strong>
                It must be <code>{{ $router->nas_identifier }}</code>. Set it back with
                <code>/system identity set name={{ $router->nas_identifier }}</code>, or paste the setup command below again.
            </div>
        @endif

        @if ($router->provision_note)
            <div class="alert alert-error" style="margin-bottom:16px;">
                <i class="fa-solid fa-triangle-exclamation"></i> {{ $router->provision_note }}.
                Check that the hotspot is already set up on the router (<code>/ip hotspot setup</code>), then paste the command again.
            </div>
        @endif

        <div style="background:#040a17;border:1px solid #2561e8;border-radius:10px;padding:20px;margin-bottom:20px;color:#e7eefc;max-width:900px;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
                <div style="font-size:13px;font-weight:700;color:#2561e8;text-transform:uppercase;letter-spacing:0.5px;">
                    <i class="fa-solid fa-bolt"></i> Setup command
                </div>
                <button type="button" class="btn btn-secondary btn-sm" onclick="copyProvisionCmd(this)" style="background:#2561e8;color:#e7eefc;border:none;">
                    <i class="fa-solid fa-copy"></i> Copy
                </button>
            </div>
            <div style="background:#11111b;border:1px solid #45475a;padding:12px;border-radius:6px;font-family:monospace;font-size:12px;color:#a6e3a1;word-break:break-all;" id="provisionCmd">{{ $command }}</div>
            <p style="font-size:11px;color:#94a3b8;margin-top:8px;margin-bottom:0;">
                Open WinBox, choose New Terminal, paste this and press Enter. It connects the router to the login server,
                sets up the walled garden and installs your branded login page. The hotspot must already exist on the router.
            </p>
        </div>

        <div class="card" style="max-width:900px;">
            <div class="card-title">Router settings</div>
            <form method="POST" action="{{ route('dashboard.routers.update', $router) }}" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
                @csrf @method('PUT')
                <div class="field" style="flex:1;min-width:220px;margin:0;">
                    <label>Router Name</label>
                    <input type="text" name="name" value="{{ old('name', $router->name) }}" required maxlength="100">
                    @error('name') <span class="error">{{ $message }}</span> @enderror
                </div>
                <button type="submit" class="btn btn-primary">Save</button>
            </form>

            <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:20px;padding-top:16px;border-top:1px solid #e2e8f0;">
                <form method="POST" action="{{ route('dashboard.routers.command', $router) }}" onsubmit="return confirm('Restart this router? Customers will be disconnected for about a minute.')">
                    @csrf <input type="hidden" name="type" value="reboot">
                    <button type="submit" class="btn btn-secondary btn-sm" {{ $router->isOnline() ? '' : 'disabled' }}><i class="fa-solid fa-power-off"></i> Reboot router</button>
                </form>
                <form method="POST" action="{{ route('dashboard.routers.command', $router) }}" onsubmit="return confirm('Disconnect every customer on this router?')">
                    @csrf <input type="hidden" name="type" value="kick_all">
                    <button type="submit" class="btn btn-secondary btn-sm" {{ $router->isOnline() ? '' : 'disabled' }}><i class="fa-solid fa-user-slash"></i> Disconnect all customers</button>
                </form>
                <form method="POST" action="{{ route('dashboard.routers.rotate', $router) }}" onsubmit="return confirm('Create new secrets? The router shows as offline until you run the new setup command on it.')">
                    @csrf
                    <button type="submit" class="btn btn-secondary btn-sm"><i class="fa-solid fa-key"></i> Replace secrets</button>
                </form>
            </div>
            <p style="font-size:11px;color:#94a3b8;margin-top:10px;">Commands run within a minute, when the router next checks in.</p>
        </div>
    @endif
@else
    {{-- ── Older way: the platform logs in to a router it can reach over the API ── --}}
    <div class="alert alert-error" style="max-width:680px;margin-bottom:16px;">
        This connection method needs the router to be reachable from the platform and is kept for existing setups.
        @if ($router)
            <form method="POST" action="{{ route('dashboard.routers.switch', $router) }}" style="display:inline;" onsubmit="return confirm('Switch this router to the one-command setup? You will need to paste a new command into it.')">
                @csrf <button type="submit" class="btn btn-primary btn-sm" style="margin-left:8px;">Switch to one-command setup</button>
            </form>
        @endif
    </div>

    <div class="card" style="max-width:680px;">
        <form method="POST" action="{{ $router ? route('dashboard.routers.update', $router) : route('dashboard.routers.store') }}">
            @csrf
            <input type="hidden" name="mode" value="api">
            @if ($router) @method('PUT') @endif

            <div class="form-grid" style="margin-bottom:16px;">
                <div class="field form-full">
                    <label>Router Name</label>
                    <input type="text" name="name" value="{{ old('name', $router?->name) }}" placeholder="Main Office Router" required>
                    @error('name') <span class="error">{{ $message }}</span> @enderror
                </div>

                <div class="field">
                    <label>IP Address</label>
                    <input type="text" name="router_ip" value="{{ old('router_ip', $router?->router_ip) }}" placeholder="192.168.1.1" required>
                    <span class="hint">Must be a private IP (192.168.x.x, 10.x.x.x, etc.)</span>
                    @error('router_ip') <span class="error">{{ $message }}</span> @enderror
                </div>

                <div class="field">
                    <label>API Port</label>
                    <input type="number" name="port" value="{{ old('port', $router?->port ?? 8728) }}" min="1" max="65535">
                    @error('port') <span class="error">{{ $message }}</span> @enderror
                </div>

                <div class="field">
                    <label>API Username</label>
                    <input type="text" name="username" value="{{ old('username', $router?->username) }}" placeholder="Leave blank to create one for you" autocomplete="off">
                    @unless ($router) <span class="hint">Blank: Wifikitaa creates a login named after your business.</span> @endunless
                    @error('username') <span class="error">{{ $message }}</span> @enderror
                </div>

                <div class="field">
                    <label>API Password {{ $router ? '(leave blank to keep current)' : '' }}</label>
                    <x-password-input name="password" placeholder="{{ $router ? '••••••••' : 'Leave blank to generate one' }}"
                           autocomplete="new-password" />
                    @error('password') <span class="error">{{ $message }}</span> @enderror
                </div>
            </div>

            <div style="display:flex;gap:10px;">
                <button type="submit" class="btn btn-primary">{{ $router ? 'Save Changes' : 'Add Router' }}</button>
                <a href="{{ route('dashboard.routers.index') }}" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
@endif

@endsection

@push('scripts')
<script>
function copyProvisionCmd(btn) {
    const text = document.getElementById('provisionCmd').innerText;
    navigator.clipboard.writeText(text).then(() => {
        const original = btn.innerHTML;
        btn.innerHTML = '<i class="fa-solid fa-check"></i> Copied';
        setTimeout(() => { btn.innerHTML = original; }, 2000);
    });
}
</script>
@endpush
