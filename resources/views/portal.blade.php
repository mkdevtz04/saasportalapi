<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta http-equiv="pragma" content="no-cache">
<meta http-equiv="expires" content="-1">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>TRINET SOLUTION - Buy WiFi</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
html,body{min-height:100%;background:#eef3f7;font-family:Arial,Helvetica,sans-serif;color:#142033}
.page{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
.card{width:100%;max-width:480px;background:#fff;border:1px solid #d8dee8;box-shadow:0 16px 48px rgba(20,32,51,.14)}
.header{padding:24px 28px;border-bottom:2px solid #142033;display:flex;gap:14px;align-items:center}
.mark{width:46px;height:46px;background:#142033;color:#fff;display:grid;place-items:center;font-size:13px;font-weight:900;flex:0 0 auto}
.brand{font-size:21px;font-weight:900;line-height:1}
.sub{font-size:11px;font-weight:800;color:#526173;letter-spacing:.1em;text-transform:uppercase;margin-top:5px}
.body{padding:24px 28px}
.section-lbl{font-size:11px;font-weight:900;letter-spacing:.08em;text-transform:uppercase;color:#344054;margin-bottom:10px}
.packages{border:1px solid #d8dee8;margin-bottom:20px}
.package{display:flex;justify-content:space-between;align-items:center;padding:14px 16px;border-top:1px solid #d8dee8;cursor:pointer;transition:background .15s}
.package:first-child{border-top:0}
.package:hover{background:#f5f8ff}
.package.selected{background:#e8f4f4;border-left:4px solid #0b7a75;padding-left:12px}
.pkg-name{font-size:15px;font-weight:900}
.pkg-desc{font-size:12px;color:#667085;margin-top:2px}
.price{font-size:15px;font-weight:900;color:#075954;white-space:nowrap}
.field-lbl{display:block;font-size:11px;font-weight:900;letter-spacing:.08em;text-transform:uppercase;color:#344054;margin-bottom:7px}
.field-input{width:100%;height:50px;padding:0 14px;font-size:17px;border:1.5px solid #b8c2d1;outline:none;background:#fbfdff;color:#142033}
.field-input:focus{border-color:#0b7a75;box-shadow:0 0 0 3px rgba(11,122,117,.12)}
.field-hint{font-size:11px;color:#8a96a3;margin-top:6px}
.error{margin-top:12px;padding:10px 13px;background:#fff1f1;border-left:4px solid #c62828;color:#a81717;font-size:13px;font-weight:700}
.btn{display:block;width:100%;height:50px;margin-top:18px;background:#142033;color:#fff;border:0;font-size:13px;font-weight:900;letter-spacing:.08em;text-transform:uppercase;cursor:pointer}
.btn:hover:not(:disabled){background:#0b7a75}
.btn:disabled{opacity:.5;cursor:not-allowed}
.footer{padding:12px 28px;background:#f7f9fb;border-top:1px solid #e5e9f0;font-size:11px;color:#667085;text-align:center}
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(20,32,51,.88);z-index:100;align-items:center;justify-content:center;padding:20px}
.modal-overlay.show{display:flex}
.modal-box{background:#fff;padding:36px 28px;max-width:360px;width:100%;text-align:center;animation:up .25s ease}
@keyframes up{from{transform:translateY(24px);opacity:0}to{transform:translateY(0);opacity:1}}
.m-icon{font-size:48px;display:block;margin-bottom:14px}
.m-title{font-size:19px;font-weight:900;margin-bottom:8px}
.m-msg{color:#667085;font-size:14px;line-height:1.6;margin-bottom:20px}
.spinner{width:44px;height:44px;border:3px solid #e8e8e8;border-top-color:#0b7a75;border-radius:50%;animation:spin .8s linear infinite;margin:0 auto 20px}
@keyframes spin{to{transform:rotate(360deg)}}
.token-box{border:2px dashed #0b7a75;padding:16px;margin-bottom:16px;text-align:left}
.token-lbl{font-size:11px;color:#667085;letter-spacing:1px;text-transform:uppercase;margin-bottom:4px}
.token-val{font-size:26px;font-weight:900;color:#0b7a75;letter-spacing:4px;font-family:monospace}
.m-btn{display:block;width:100%;padding:13px;margin-top:8px;background:#fff;border:1.5px solid #d8dee8;color:#344054;font-family:Arial,sans-serif;font-size:13px;font-weight:700;cursor:pointer}
.m-btn:hover{border-color:#0b7a75;color:#142033}
.m-btn.primary{background:#0b7a75;color:#fff;border-color:#0b7a75}
</style>
</head>
<body>
<main class="page">
  <div class="card">
    <div class="header">
      <div class="mark">TS</div>
      <div>
        <div class="brand">TRINET SOLUTION</div>
        <div class="sub">WiFi Hotspot &mdash; Tanzania</div>
      </div>
    </div>

    <div class="body">
      <div class="section-lbl">Select Package</div>
      <div class="packages">
        @foreach($packages as $key => $pkg)
        <div class="package"
             data-key="{{ $key }}"
             data-price="{{ $pkg['price'] }}"
             data-name="{{ $pkg['name'] }}"
             onclick="selectPackage(this)">
          <div>
            <div class="pkg-name">{{ $pkg['name'] }}</div>
            <div class="pkg-desc">{{ $pkg['duration'] }} &bull; {{ $pkg['speed'] }}</div>
          </div>
          <div class="price">{{ number_format($pkg['price']) }} TZS</div>
        </div>
        @endforeach
      </div>

      <label class="field-lbl" for="phone">Phone Number</label>
      <input id="phone" type="tel" class="field-input" placeholder="0712 345 678" autocomplete="tel">
      <div class="field-hint">Vodacom &bull; Airtel &bull; Tigo &bull; Halotel</div>

      <div id="err" class="error" style="display:none"></div>

      <button id="payBtn" class="btn" onclick="initiatePayment()">Pay &amp; Connect Now</button>
    </div>

    <div class="footer">TRINET SOLUTION &mdash; Fast &amp; Affordable WiFi in Tanzania</div>
  </div>
</main>

<div id="modal" class="modal-overlay">
  <div id="modalBox" class="modal-box"></div>
</div>

<script>
const hotspot = @json($hotspot ?? []);
let selectedPackage = null;
let selectedPrice   = 0;
let selectedName    = '';
let currentTxnId    = null;
let currentOrderId  = null;
let pollTimer       = null;

function selectPackage(el) {
  document.querySelectorAll('.package').forEach(p => p.classList.remove('selected'));
  el.classList.add('selected');
  selectedPackage = el.dataset.key;
  selectedPrice   = parseInt(el.dataset.price, 10);
  selectedName    = el.dataset.name;
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
  const canAutoLogin = Boolean(loginUrl);
  const target = dst || 'http://www.google.com';

  // Build the verify.html URL on the router's own HTTP origin.
  // The browser navigates there via window.location (navigation is allowed
  // HTTPS→HTTP), and verify.html auto-POSTs the token to /login in a
  // pure HTTP context, bypassing the mixed-content block.
  let verifyUrl = null;
  if (canAutoLogin) {
    try {
      const routerOrigin = new URL(loginUrl).origin; // e.g. http://192.168.88.1
      verifyUrl = routerOrigin + '/verify.html'
        + '?token='  + encodeURIComponent(token)
        + '&dst='    + encodeURIComponent(target);
    } catch (e) {
      // loginUrl was malformed — fall back to manual mode
    }
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
  if (!selectedPackage) return showError('Please select a package.');
  if (!phone || phone.replace(/\D/g,'').length < 9) return showError('Please enter a valid phone number.');

  const btn = document.getElementById('payBtn');
  btn.disabled = true;

  showModal('⏳', 'Sending Request...', 'Please wait while we send the payment prompt to your phone.', true);

  try {
    const res = await fetch('/api/payment/initiate', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
      },
      body: JSON.stringify({
        phone,
        package: selectedPackage,
        mac:              hotspot.mac              || null,
        ip:               hotspot.ip               || null,
        link_login_only:  hotspot.link_login_only  || null,
        link_orig:        hotspot.link_orig         || null
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

function startPolling() {
  let attempts = 0;
  pollTimer = setInterval(async () => {
    attempts++;
    if (attempts > 60) {
      clearInterval(pollTimer);
      showModal('⏱', 'Timed Out', 'Payment not confirmed in time. If you paid, contact support.');
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
