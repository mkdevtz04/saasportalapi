<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta http-equiv="pragma" content="no-cache">
<meta http-equiv="expires" content="-1">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $tenant?->name ?? 'WiFi Portal' }} — Buy WiFi</title>
<style>
:root { --brand: {{ $settings?->brand_color ?? '#0b7a75' }}; }
*{box-sizing:border-box;margin:0;padding:0}
html,body{min-height:100%;background:#eef3f7;font-family:Arial,Helvetica,sans-serif;color:#142033}
.page{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
.card{width:100%;max-width:480px;background:#fff;border:1px solid #d8dee8;box-shadow:0 16px 48px rgba(20,32,51,.14)}
.header{padding:24px 28px;border-bottom:2px solid var(--brand);display:flex;gap:14px;align-items:center}
.mark{width:46px;height:46px;background:var(--brand);color:#fff;display:grid;place-items:center;font-size:13px;font-weight:900;flex:0 0 auto;border-radius:4px;overflow:hidden}
.mark img{width:100%;height:100%;object-fit:cover}
.brand{font-size:21px;font-weight:900;line-height:1}
.sub{font-size:11px;font-weight:800;color:#526173;letter-spacing:.1em;text-transform:uppercase;margin-top:5px}
.body{padding:24px 28px}
.section-lbl{font-size:11px;font-weight:900;letter-spacing:.08em;text-transform:uppercase;color:#344054;margin-bottom:10px}
.packages{border:1px solid #d8dee8;margin-bottom:20px}
.package{display:flex;justify-content:space-between;align-items:center;padding:14px 16px;border-top:1px solid #d8dee8;cursor:pointer;transition:background .15s}
.package:first-child{border-top:0}
.package:hover{background:#f5f8ff}
.package.selected{background:#e8f4f4;border-left:4px solid var(--brand);padding-left:12px}
.pkg-name{font-size:15px;font-weight:900}
.pkg-desc{font-size:12px;color:#667085;margin-top:2px}
.price{font-size:15px;font-weight:900;color:var(--brand);white-space:nowrap}
.field-lbl{display:block;font-size:11px;font-weight:900;letter-spacing:.08em;text-transform:uppercase;color:#344054;margin-bottom:7px}
.field-input{width:100%;height:50px;padding:0 14px;font-size:17px;border:1.5px solid #b8c2d1;outline:none;background:#fbfdff;color:#142033}
.field-input:focus{border-color:var(--brand);box-shadow:0 0 0 3px color-mix(in srgb,var(--brand) 15%,transparent)}
.field-hint{font-size:11px;color:#8a96a3;margin-top:6px}
.error{margin-top:12px;padding:10px 13px;background:#fff1f1;border-left:4px solid #c62828;color:#a81717;font-size:13px;font-weight:700}
.btn{display:block;width:100%;height:50px;margin-top:18px;background:var(--brand);color:#fff;border:0;font-size:13px;font-weight:900;letter-spacing:.08em;text-transform:uppercase;cursor:pointer}
.btn:hover:not(:disabled){filter:brightness(1.12)}
.btn:disabled{opacity:.5;cursor:not-allowed}
.footer{padding:12px 28px;background:#f7f9fb;border-top:1px solid #e5e9f0;font-size:11px;color:#667085;text-align:center}
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(20,32,51,.88);z-index:100;align-items:center;justify-content:center;padding:20px}
.modal-overlay.show{display:flex}
.modal-box{background:#fff;padding:36px 28px;max-width:360px;width:100%;text-align:center;animation:up .25s ease}
@keyframes up{from{transform:translateY(24px);opacity:0}to{transform:translateY(0);opacity:1}}
.m-icon{font-size:48px;display:block;margin-bottom:14px}
.m-title{font-size:19px;font-weight:900;margin-bottom:8px}
.m-msg{color:#667085;font-size:14px;line-height:1.6;margin-bottom:20px}
.spinner{width:44px;height:44px;border:3px solid #e8e8e8;border-top-color:var(--brand);border-radius:50%;animation:spin .8s linear infinite;margin:0 auto 20px}
@keyframes spin{to{transform:rotate(360deg)}}
.token-box{border:2px dashed var(--brand);padding:16px;margin-bottom:16px;text-align:left}
.token-lbl{font-size:11px;color:#667085;letter-spacing:1px;text-transform:uppercase;margin-bottom:4px}
.token-val{font-size:26px;font-weight:900;color:var(--brand);letter-spacing:4px;font-family:monospace}
.m-btn{display:block;width:100%;padding:13px;margin-top:8px;background:#fff;border:1.5px solid #d8dee8;color:#344054;font-family:Arial,sans-serif;font-size:13px;font-weight:700;cursor:pointer}
.m-btn:hover{border-color:var(--brand);color:#142033}
.m-btn.primary{background:var(--brand);color:#fff;border-color:var(--brand)}
.no-packages{padding:24px;text-align:center;color:#667085;font-size:14px}
.tabs{display:flex;border-bottom:2px solid #e5e9f0;margin-bottom:20px}
.tab{flex:1;padding:11px 0;text-align:center;font-size:12px;font-weight:900;letter-spacing:.05em;text-transform:uppercase;color:#667085;cursor:pointer;transition:all .15s;border-bottom:3px solid transparent;margin-bottom:-2px}
.tab.active{color:var(--brand);border-bottom-color:var(--brand)}
.vc-input{width:100%;height:50px;padding:0 14px;font-size:22px;font-weight:900;letter-spacing:4px;text-transform:uppercase;border:1.5px solid #b8c2d1;outline:none;background:#fbfdff;color:#142033;font-family:monospace;text-align:center}
.vc-input:focus{border-color:var(--brand);box-shadow:0 0 0 3px color-mix(in srgb,var(--brand) 15%,transparent)}
</style>
</head>
<body>
<main class="page">
  <div class="card">
    <div class="header">
      <div class="mark">
        @if($settings?->custom_logo_path)
          <img src="{{ asset('storage/' . $settings->custom_logo_path) }}" alt="{{ $tenant?->name }}">
        @else
          {{ strtoupper(substr($tenant?->name ?? 'W', 0, 2)) }}
        @endif
      </div>
      <div>
        <div class="brand">{{ strtoupper($tenant?->name ?? 'WiFi Portal') }}</div>
        <div class="sub">{{ $settings?->tagline ?? 'Fast &amp; Affordable WiFi' }}</div>
      </div>
    </div>

    <div class="body">
      {{-- Tab switcher --}}
      <div class="tabs">
        <div class="tab active" onclick="switchTab('pay',this)">💳 Pay Online</div>
        <div class="tab" onclick="switchTab('voucher',this)">🎫 Enter Voucher</div>
      </div>

      {{-- Pay tab --}}
      <div id="payTab">
      <div class="section-lbl">Select Package</div>
      <div class="packages">
        @forelse($packages as $pkg)
        <div class="package"
             data-id="{{ $pkg->id }}"
             data-price="{{ $pkg->price }}"
             data-name="{{ $pkg->name }}"
             onclick="selectPackage(this)">
          <div>
            <div class="pkg-name">{{ $pkg->name }}</div>
            <div class="pkg-desc">{{ $pkg->durationLabel() }} &bull; {{ $pkg->speedLabel() }}</div>
          </div>
          <div class="price">{{ number_format($pkg->price) }} TZS</div>
        </div>
        @empty
        <div class="no-packages">No packages available. Please contact the provider.</div>
        @endforelse
      </div>

      <label class="field-lbl" for="phone">Phone Number</label>
      <input id="phone" type="tel" class="field-input" placeholder="0712 345 678" autocomplete="tel">
      <div class="field-hint">Vodacom &bull; Airtel &bull; Tigo &bull; Halotel</div>

      <div id="err" class="error" style="display:none"></div>

      <button id="payBtn" class="btn" onclick="initiatePayment()">Pay &amp; Connect Now</button>
      </div>{{-- /payTab --}}

      {{-- Voucher tab --}}
      <div id="voucherTab" style="display:none">
        <div class="section-lbl">Voucher Code</div>
        <input id="vcCode" type="text" class="vc-input" placeholder="TN••••••••" maxlength="20"
               oninput="this.value=this.value.toUpperCase().replace(/[^A-Z0-9]/g,'')">
        <div class="field-hint" style="margin-top:6px;">Enter the code printed on your voucher card</div>
        <div id="vcErr" class="error" style="display:none"></div>
        <button class="btn" onclick="redeemVoucher()" style="margin-top:18px;">Redeem Voucher</button>
      </div>{{-- /voucherTab --}}
    </div>

    <div class="footer">{{ $tenant?->name ?? 'WiFi Portal' }} &mdash; {{ $settings?->tagline ?? 'Fast &amp; Affordable WiFi in Tanzania' }}</div>
  </div>
</main>

<div id="modal" class="modal-overlay">
  <div id="modalBox" class="modal-box"></div>
</div>

<script>
const hotspot = @json($hotspot ?? []);
let selectedPackageId = null;
let selectedPrice     = 0;
let selectedName      = '';
let currentTxnId      = null;
let currentOrderId    = null;
let pollTimer         = null;

function selectPackage(el) {
  document.querySelectorAll('.package').forEach(p => p.classList.remove('selected'));
  el.classList.add('selected');
  selectedPackageId = parseInt(el.dataset.id, 10);
  selectedPrice     = parseInt(el.dataset.price, 10);
  selectedName      = el.dataset.name;
}

function showError(msg) {
  const el = document.getElementById('err');
  el.textContent = msg;
  el.style.display = 'block';
  setTimeout(() => el.style.display = 'none', 5000);
}

function safe(str) {
  return String(str ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
}

function showModal(icon, title, msg, spinner) {
  document.getElementById('modalBox').innerHTML =
    `<span class="m-icon">${icon}</span>
     <div class="m-title">${title}</div>
     <div class="m-msg">${msg}</div>
     ${spinner ? '<div class="spinner"></div>' : ''}`;
  document.getElementById('modal').classList.add('show');
}

function showSuccess(token, pkgName, loginUrl, dst) {
  const target = dst || 'http://www.google.com';
  let verifyUrl = null;
  if (loginUrl) {
    try {
      const routerOrigin = new URL(loginUrl).origin;
      verifyUrl = routerOrigin + '/verify.html'
        + '?token=' + encodeURIComponent(token)
        + '&dst='   + encodeURIComponent(target);
    } catch (e) { /* malformed loginUrl — fall back to manual mode */ }
  }

  document.getElementById('modalBox').innerHTML = `
    <span class="m-icon">✅</span>
    <div class="m-title">Payment Successful!</div>
    <div class="m-msg">${verifyUrl ? 'Connecting your device...' : 'Enter this token on the WiFi login page.'}</div>
    <div class="token-box">
      <div class="token-lbl">WiFi Token</div>
      <div class="token-val">${safe(token)}</div>
    </div>
    <p style="color:#667085;font-size:13px;margin-bottom:16px">Package: <strong>${safe(pkgName)}</strong></p>
    ${verifyUrl ? `<a class="m-btn primary" href="${safe(verifyUrl)}">Tap to Connect</a>` : ''}
    <button class="m-btn" onclick="document.getElementById('modal').classList.remove('show')">Close</button>`;

  if (verifyUrl) {
    setTimeout(() => { window.location.href = verifyUrl; }, 1500);
  }
}

async function initiatePayment() {
  const phone = document.getElementById('phone').value.trim();
  if (!selectedPackageId) return showError('Please select a package.');
  if (!phone || phone.replace(/\D/g,'').length < 9) return showError('Please enter a valid phone number.');

  const btn = document.getElementById('payBtn');
  btn.disabled = true;

  showModal('⏳', 'Sending Request...', 'Please wait while we send the payment prompt to your phone.', true);

  try {
    const res = await fetch('/api/payment/initiate', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept':       'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
      },
      body: JSON.stringify({
        phone,
        package_id:      selectedPackageId,
        mac:             hotspot.mac              || null,
        ip:              hotspot.ip               || null,
        link_login_only: hotspot.link_login_only  || null,
        link_orig:       hotspot.link_orig         || null,
        nas:             hotspot.nas              || null,
      })
    });

    const data = await res.json();

    if (data.status === 'success') {
      currentTxnId   = data.transaction_id;
      currentOrderId = data.order_id;
      showModal('📱', 'Check Your Phone!',
        `A payment prompt of <strong>${selectedPrice.toLocaleString()} TZS</strong> has been sent to <strong>${phone}</strong>.<br><br>Confirm it on your phone to connect.`,
        true);
      startPolling();
    } else {
      showModal('❌', 'Failed', data.message || 'Something went wrong. Please try again.');
      btn.disabled = false;
    }
  } catch (e) {
    showModal('❌', 'Error', 'Could not reach the server. Check your connection.');
    btn.disabled = false;
  }
}

function switchTab(tab, el) {
  document.getElementById('payTab').style.display     = tab === 'pay'     ? '' : 'none';
  document.getElementById('voucherTab').style.display = tab === 'voucher' ? '' : 'none';
  document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
  el.classList.add('active');
}

async function redeemVoucher() {
  const code = document.getElementById('vcCode').value.trim();
  const vcErr = document.getElementById('vcErr');
  vcErr.style.display = 'none';

  if (!code || code.length < 6) {
    vcErr.textContent = 'Please enter a valid voucher code.';
    vcErr.style.display = 'block';
    return;
  }

  showModal('⏳', 'Verifying Voucher…', 'Please wait…', true);

  try {
    const res = await fetch('/api/voucher/redeem', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
      },
      body: JSON.stringify({
        code,
        phone: null,
        mac: hotspot.mac  || null,
        ip:  hotspot.ip   || null,
        nas: hotspot.nas  || null,
      })
    });

    const data = await res.json();

    if (data.ok) {
      // Show success — voucher behaves same as paid token
      showSuccess(data.code, data.package, hotspot.link_login_only, hotspot.link_orig);
    } else {
      document.getElementById('modal').classList.remove('show');
      vcErr.textContent = data.message || 'Invalid voucher code.';
      vcErr.style.display = 'block';
    }
  } catch (e) {
    document.getElementById('modal').classList.remove('show');
    vcErr.textContent = 'Network error. Please try again.';
    vcErr.style.display = 'block';
  }
}

function startPolling() {
  let attempts = 0;
  pollTimer = setInterval(async () => {
    attempts++;
    if (attempts > 60) {
      clearInterval(pollTimer);
      showModal('⏱', 'Timed Out', 'Payment not confirmed in time. If you paid, please contact support.');
      document.getElementById('payBtn').disabled = false;
      return;
    }
    try {
      const res  = await fetch(`/api/payment/status?transaction_id=${currentTxnId}&order_id=${currentOrderId}`);
      const data = await res.json();
      if (data.status === 'paid') {
        clearInterval(pollTimer);
        showSuccess(data.wifi_token, data.package, data.login_url, data.dst);
      } else if (data.status === 'failed') {
        clearInterval(pollTimer);
        showModal('❌', 'Payment Failed', 'Payment was declined. Please try again.');
        document.getElementById('payBtn').disabled = false;
      }
    } catch (e) { /* keep polling */ }
  }, 3000);
}
</script>
</body>
</html>
