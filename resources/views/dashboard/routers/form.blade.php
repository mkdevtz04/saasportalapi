@extends('dashboard.layout')

@section('title', $router ? 'Edit Router' : 'Add Router')
@section('breadcrumb', $router ? 'Edit Router' : 'Add Router')

@section('content')

<div class="page-header">
    <div>
        <div class="page-title">{{ $router ? 'Edit Router' : 'Add New Router' }}</div>
        <div class="page-sub">
            <a href="{{ route('dashboard.routers.index') }}" style="color:#2563eb;text-decoration:none;">← Back to routers</a>
        </div>
    </div>
</div>

<div class="card" style="max-width:680px;">
    <form method="POST" action="{{ $router ? route('dashboard.routers.update', $router) : route('dashboard.routers.store') }}">
        @csrf
        @if ($router) @method('PUT') @endif

        <div class="form-grid" style="margin-bottom:16px;">
            <div class="field form-full">
                <label>Router Name</label>
                <input type="text" name="name" value="{{ old('name', $router?->name) }}"
                       placeholder="Main Office Router" required>
                @error('name') <span class="error">{{ $message }}</span> @enderror
            </div>

            <div class="field">
                <label>IP Address</label>
                <input type="text" name="router_ip" value="{{ old('router_ip', $router?->router_ip) }}"
                       placeholder="192.168.1.1" required>
                <span class="hint">Must be a private IP (192.168.x.x, 10.x.x.x, etc.)</span>
                @error('router_ip') <span class="error">{{ $message }}</span> @enderror
            </div>

            <div class="field">
                <label>API Port</label>
                <input type="number" name="port" value="{{ old('port', $router?->port ?? 8728) }}"
                       min="1" max="65535">
                <span class="hint">Default MikroTik API port is 8728</span>
                @error('port') <span class="error">{{ $message }}</span> @enderror
            </div>

            <div class="field">
                <label>API Username</label>
                <input type="text" name="username" value="{{ old('username', $router?->username) }}"
                       placeholder="admin" required autocomplete="off">
                @error('username') <span class="error">{{ $message }}</span> @enderror
            </div>

            <div class="field">
                <label>API Password {{ $router ? '(leave blank to keep current)' : '' }}</label>
                <input type="password" name="password"
                       placeholder="{{ $router ? '••••••••' : 'Enter password' }}"
                       {{ $router ? '' : 'required' }} autocomplete="new-password">
                @error('password') <span class="error">{{ $message }}</span> @enderror
            </div>
        </div>

        {{-- Test connection --}}
        <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:16px;margin-bottom:20px;">
            <div style="font-size:13px;font-weight:600;color:#374151;margin-bottom:10px;">Test Connection</div>
            <div style="display:flex;gap:10px;align-items:center;">
                <button type="button" id="testBtn" class="btn btn-secondary btn-sm" onclick="testConnection()">
                    🔗 Test Connection
                </button>
                <span id="testResult" style="font-size:13px;"></span>
            </div>
            <p style="font-size:11px;color:#94a3b8;margin-top:8px;">
                Reads the IP, username, and password entered above to verify the connection.
            </p>
        </div>

        <div style="display:flex;gap:10px;">
            <button type="submit" class="btn btn-primary">
                {{ $router ? 'Save Changes' : 'Add Router' }}
            </button>
            <a href="{{ route('dashboard.routers.index') }}" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

@endsection

@push('scripts')
<script>
function testConnection() {
    const btn    = document.getElementById('testBtn');
    const result = document.getElementById('testResult');
    const ip     = document.querySelector('[name=router_ip]').value;
    const user   = document.querySelector('[name=username]').value;
    const pass   = document.querySelector('[name=password]').value;
    const port   = document.querySelector('[name=port]').value || 8728;

    if (!ip || !user || !pass) {
        result.textContent = '⚠️ Fill in IP, username, and password first.';
        result.style.color = '#d97706';
        return;
    }

    btn.disabled = true;
    btn.textContent = '⏳ Testing…';
    result.textContent = '';

    fetch('{{ route('dashboard.routers.test') }}', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        body: JSON.stringify({ ip, username: user, password: pass, port: parseInt(port) })
    })
    .then(r => r.json())
    .then(data => {
        result.textContent = data.ok ? '✅ ' + data.message : '❌ ' + data.message;
        result.style.color = data.ok ? '#15803d' : '#dc2626';
    })
    .catch(() => {
        result.textContent = '❌ Network error';
        result.style.color = '#dc2626';
    })
    .finally(() => {
        btn.disabled = false;
        btn.textContent = '🔗 Test Connection';
    });
}
</script>
@endpush
