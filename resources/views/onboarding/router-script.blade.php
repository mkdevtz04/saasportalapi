<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Router Setup Script — TrinetPay</title>
    @include('onboarding._styles')
    <style>
        .script-box { background: #1e1e2e; border-radius: 8px; padding: 20px 24px; margin: 20px 0; overflow-x: auto; }
        .script-box code { font-family: 'Courier New', monospace; font-size: 13px; color: #cdd6f4; white-space: pre; display: block; line-height: 1.7; }
        .script-box code .comment { color: #6c7086; }
        .script-box code .cmd { color: #89dceb; }
        .script-box code .val { color: #a6e3a1; }
        .copy-btn { float: right; background: #313244; color: #cdd6f4; border: none; padding: 5px 12px; border-radius: 4px; font-size: 12px; cursor: pointer; margin-top: -4px; }
        .copy-btn:hover { background: #45475a; }
        .success-badge { display: inline-flex; align-items: center; gap: 8px; background: #e8fff0; color: #166534; border: 1px solid #bbf7d0; border-radius: 8px; padding: 10px 16px; font-size: 14px; font-weight: 600; margin-bottom: 20px; }
        .router-summary { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px 20px; margin-bottom: 20px; }
        .router-summary dt { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .6px; color: #64748b; margin-top: 10px; }
        .router-summary dd { font-size: 15px; color: #111; font-weight: 600; }
        .router-summary dt:first-child { margin-top: 0; }
    </style>
</head>
<body>
<div class="wizard-wrap">
    @include('onboarding._steps', ['current' => 1])

    <div class="card">
        <div class="card-header">
            <div class="step-icon">✅</div>
            <div>
                <h2>Router Saved!</h2>
                <p class="sub">Paste the script below into your MikroTik terminal to finish the setup.</p>
            </div>
        </div>

        <div class="success-badge">
            <span>📡</span> {{ $router->name }} — {{ $router->router_ip }}:{{ $router->port }}
        </div>

        <dl class="router-summary">
            <dt>NAS Identifier</dt>
            <dd><code style="background:#f1f5f9;padding:2px 8px;border-radius:4px;font-size:14px;">{{ $router->nas_identifier }}</code></dd>
            <dt>API Username</dt>
            <dd>{{ $router->username }}</dd>
        </dl>

        <p style="font-size:14px;color:#555;margin-bottom:8px;">
            Open your MikroTik router terminal (Winbox → New Terminal) and paste this script:
        </p>

        <div class="script-box">
            <button class="copy-btn" onclick="copyScript()">Copy</button>
            <code id="script-content"><span class="comment"># TrinetPay Setup Script — paste into MikroTik terminal</span>

<span class="comment"># 1. Enable API service</span>
<span class="cmd">/ip service</span> <span class="val">enable api
/ip service set api port=8728</span>

<span class="comment"># 2. Walled garden — allow portal and payment gateway without login</span>
<span class="cmd">/ip hotspot walled-garden</span> <span class="val">add dst-host={{ $tenant->subdomain }}.trinetpay.online
/ip hotspot walled-garden add dst-host=trinetpay.online
/ip hotspot walled-garden add dst-host=palmpesa.drmlelwa.co.tz</span>

<span class="comment"># 3. Set your hotspot redirect URL to TrinetPay portal</span>
<span class="cmd">/ip hotspot profile</span> <span class="val">set [find] login-by=http-chap \
  html-directory-override=""</span>

<span class="comment"># 4. Note your NAS identifier for multi-router setups:</span>
<span class="comment">#    {{ $router->nas_identifier }}</span>
</code>
        </div>

        <div class="info-box">
            <strong>Already enabled API?</strong> You can skip steps 1 and just run the walled-garden rules (step 2) and the profile redirect (step 3).
        </div>

        <a href="{{ route('onboarding.packages') }}" class="btn-primary" style="display:block;text-align:center;text-decoration:none;margin-top:24px;">
            Continue to Step 2: Packages →
        </a>

        <a href="{{ route('onboarding.router') }}?retry=1" onclick="clearSession(event)"
           style="display:block;text-align:center;font-size:13px;color:#0066cc;margin-top:12px;text-decoration:none;">
            Add another router
        </a>
    </div>
</div>

<script>
function copyScript() {
    const text = document.getElementById('script-content').innerText;
    navigator.clipboard.writeText(text).then(() => {
        const btn = document.querySelector('.copy-btn');
        btn.textContent = 'Copied!';
        setTimeout(() => btn.textContent = 'Copy', 2000);
    });
}
</script>
</body>
</html>
