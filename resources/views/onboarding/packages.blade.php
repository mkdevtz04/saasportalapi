<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Package Setup — TrinetPay</title>
    @include('onboarding._styles')
    <style>
        .pkg-row { display:grid; grid-template-columns:2fr 1fr 1fr 1fr 1fr 1fr auto; gap:8px; align-items:end; margin-bottom:10px; padding:14px 16px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; }
        .pkg-row .field { margin-bottom:0; }
        .pkg-row label { font-size:11px; }
        .pkg-row input { padding:8px 10px; font-size:14px; }
        .pkg-header { display:grid; grid-template-columns:2fr 1fr 1fr 1fr 1fr 1fr auto; gap:8px; padding:0 16px; margin-bottom:4px; }
        .pkg-header span { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:#64748b; }
        .btn-remove { background:none; border:1px solid #fca5a5; color:#e53e3e; border-radius:6px; padding:8px 10px; cursor:pointer; font-size:16px; line-height:1; margin-top:20px; }
        .btn-remove:hover { background:#fff5f5; }
        .btn-add { background:#f0f9ff; border:1.5px dashed #60a5fa; color:#2563eb; border-radius:8px; padding:10px 20px; font-size:14px; font-weight:600; cursor:pointer; margin-top:8px; }
        .btn-add:hover { background:#dbeafe; }
        .profile-hint { font-size:11px; color:#64748b; margin-top:6px; }
        @media(max-width:768px){
            .pkg-header { display:none; }
            .pkg-row { grid-template-columns:1fr; }
            .pkg-row label { display:block; }
        }
    </style>
</head>
<body>
<div class="wizard-wrap">
    @include('onboarding._steps', ['current' => 2])

    <div class="card" style="max-width:860px">
        <div class="card-header">
            <div class="step-icon">📦</div>
            <div>
                <h2>Set Up Your Packages</h2>
                <p class="sub">Define the WiFi packages your customers will see. You can edit these anytime from your dashboard.</p>
            </div>
        </div>

        @if ($errors->any())
            <div class="alert-error">
                @foreach ($errors->all() as $e) <div>{{ $e }}</div> @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('onboarding.packages.store') }}" id="pkg-form">
            @csrf

            <div class="pkg-header">
                <span>Package Name</span>
                <span>Price (TZS)</span>
                <span>Duration (hrs)</span>
                <span>Speed ↓ Mbps</span>
                <span>Speed ↑ Mbps</span>
                <span>MikroTik Profile</span>
                <span></span>
            </div>

            <div id="packages-list">
                @foreach($defaults as $i => $pkg)
                <div class="pkg-row" id="row-{{ $i }}">
                    <div class="field">
                        <label>Name</label>
                        <input type="text" name="packages[{{ $i }}][name]" value="{{ $pkg['name'] }}" required>
                    </div>
                    <div class="field">
                        <label>Price</label>
                        <input type="number" name="packages[{{ $i }}][price]" value="{{ $pkg['price'] }}" min="1" required>
                    </div>
                    <div class="field">
                        <label>Hours</label>
                        <input type="number" name="packages[{{ $i }}][duration_hours]" value="{{ $pkg['duration_hours'] }}" min="1" required>
                    </div>
                    <div class="field">
                        <label>Down</label>
                        <input type="number" name="packages[{{ $i }}][speed_down_mbps]" value="{{ $pkg['speed_down_mbps'] }}" min="1" required>
                    </div>
                    <div class="field">
                        <label>Up</label>
                        <input type="number" name="packages[{{ $i }}][speed_up_mbps]" value="{{ $pkg['speed_up_mbps'] }}" min="1" required>
                    </div>
                    <div class="field">
                        <label>Profile</label>
                        <input type="text" name="packages[{{ $i }}][mikrotik_profile]" value="{{ $pkg['mikrotik_profile'] }}" placeholder="bronze" pattern="[a-zA-Z0-9_-]+" required>
                    </div>
                    <button type="button" class="btn-remove" onclick="removeRow({{ $i }})" title="Remove">✕</button>
                </div>
                @endforeach
            </div>

            <button type="button" class="btn-add" onclick="addRow()">+ Add Package</button>

            <div class="profile-hint info-box" style="margin-top:16px;">
                <strong>MikroTik Profile</strong> must match a Hotspot User Profile name on your router.<br>
                In Winbox: IP → Hotspot → User Profiles → create profiles with matching names (e.g. <code>bronze</code>, <code>silver</code>, <code>gold</code>) and set the rate limits there.
            </div>

            <button type="submit" class="btn-primary" style="margin-top:24px;">Save Packages &amp; Continue →</button>
        </form>
    </div>
</div>

<script>
let rowCount = {{ count($defaults) }};

function addRow() {
    const i = rowCount++;
    const html = `<div class="pkg-row" id="row-${i}">
        <div class="field"><label>Name</label><input type="text" name="packages[${i}][name]" placeholder="Custom" required></div>
        <div class="field"><label>Price</label><input type="number" name="packages[${i}][price]" placeholder="500" min="1" required></div>
        <div class="field"><label>Hours</label><input type="number" name="packages[${i}][duration_hours]" placeholder="6" min="1" required></div>
        <div class="field"><label>Down</label><input type="number" name="packages[${i}][speed_down_mbps]" placeholder="1" min="1" required></div>
        <div class="field"><label>Up</label><input type="number" name="packages[${i}][speed_up_mbps]" placeholder="1" min="1" required></div>
        <div class="field"><label>Profile</label><input type="text" name="packages[${i}][mikrotik_profile]" placeholder="custom" pattern="[a-zA-Z0-9_-]+" required></div>
        <button type="button" class="btn-remove" onclick="removeRow(${i})" title="Remove">✕</button>
    </div>`;
    document.getElementById('packages-list').insertAdjacentHTML('beforeend', html);
}

function removeRow(i) {
    const rows = document.querySelectorAll('.pkg-row');
    if (rows.length <= 1) return alert('You need at least one package.');
    document.getElementById('row-' + i).remove();
}
</script>
</body>
</html>
