<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Router Setup — TrinetPay Onboarding</title>
    @include('onboarding._styles')
</head>
<body>
<div class="wizard-wrap">
    @include('onboarding._steps', ['current' => 1])

    <div class="card">
        <div class="card-header">
            <div class="step-icon">📡</div>
            <div>
                <h2>Connect Your Router</h2>
                <p class="sub">Enter your MikroTik router details so we can provision hotspot users automatically.</p>
            </div>
        </div>

        @if ($errors->any())
            <div class="alert-error">
                @foreach ($errors->all() as $e) <div>{{ $e }}</div> @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('onboarding.router.store') }}" id="router-form">
            @csrf

            <div class="field">
                <label>Router Name <span class="hint-inline">(e.g. Main Office Router)</span></label>
                <input type="text" name="name" value="{{ old('name', 'Main Router') }}" placeholder="Main Router" required>
            </div>

            <div class="field-row">
                <div class="field">
                    <label>Router IP Address</label>
                    <input type="text" name="router_ip" id="router_ip" value="{{ old('router_ip', '192.168.88.1') }}" placeholder="192.168.88.1" required>
                    <span class="hint">Private IP only (192.168.x.x, 10.x.x.x)</span>
                </div>
                <div class="field" style="max-width:120px">
                    <label>API Port</label>
                    <input type="number" name="port" id="port" value="{{ old('port', 8728) }}" min="1" max="65535" required>
                </div>
            </div>

            <div class="field-row">
                <div class="field">
                    <label>API Username</label>
                    <input type="text" name="username" id="username" value="{{ old('username') }}" placeholder="admin" required autocomplete="off">
                </div>
                <div class="field">
                    <label>API Password</label>
                    <input type="password" name="password" id="password" value="{{ old('password') }}" placeholder="••••••••" required autocomplete="new-password">
                </div>
            </div>

            <div class="test-row">
                <button type="button" class="btn-test" id="test-btn" onclick="testConnection()">
                    Test Connection
                </button>
                <span id="test-result"></span>
            </div>

            <div class="info-box">
                <strong>Before you continue:</strong> Make sure the RouterOS API is enabled on your router.
                Run this in your MikroTik terminal:<br>
                <code>/ip service enable api</code>
            </div>

            <button type="submit" class="btn-primary">Save Router &amp; Continue →</button>
        </form>
    </div>
</div>

<script>
async function testConnection() {
    const btn = document.getElementById('test-btn');
    const result = document.getElementById('test-result');
    btn.disabled = true;
    btn.textContent = 'Testing...';
    result.textContent = '';
    result.className = '';

    try {
        const res = await fetch('{{ route('onboarding.test-router') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({
                router_ip: document.getElementById('router_ip').value,
                username:  document.getElementById('username').value,
                password:  document.getElementById('password').value,
                port:      document.getElementById('port').value,
            })
        });
        const data = await res.json();
        result.textContent = (data.success ? '✓ ' : '✗ ') + data.message;
        result.className = data.success ? 'test-ok' : 'test-fail';
    } catch (e) {
        result.textContent = '✗ Request failed. Check your connection.';
        result.className = 'test-fail';
    } finally {
        btn.disabled = false;
        btn.textContent = 'Test Connection';
    }
}
</script>
</body>
</html>
