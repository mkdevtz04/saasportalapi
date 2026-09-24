<!DOCTYPE html>
<html lang="{{ $locale }}">
<head>
<meta charset="utf-8">
<meta http-equiv="pragma" content="no-cache">
<meta http-equiv="expires" content="-1">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $tenant?->name ?? 'WiFi' }} — {{ __('portal.title') }}</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
:root { --brand: {{ preg_match('/^#[0-9a-fA-F]{6}$/', (string) $settings?->brand_color) ? $settings->brand_color : '#2561e8' }}; }
*{box-sizing:border-box;margin:0;padding:0}
html,body{min-height:100%;background:#eef3f7;font-family:Arial,Helvetica,sans-serif;color:#040a17}
.page{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
.card{width:100%;max-width:480px;background:#fff;border:1px solid #d8dee8;box-shadow:0 16px 48px rgba(20,32,51,.14)}
.header{padding:24px 28px;border-bottom:2px solid var(--brand);display:flex;gap:14px;align-items:center}
.mark{width:46px;height:46px;background:var(--brand);color:#fff;display:grid;place-items:center;font-size:13px;font-weight:900;flex:0 0 auto;border-radius:4px;overflow:hidden}
.mark img{width:100%;height:100%;object-fit:cover}
.brand{font-size:21px;font-weight:900;line-height:1}
.sub{font-size:11px;font-weight:800;color:#526173;letter-spacing:.1em;text-transform:uppercase;margin-top:5px}
.lang{margin-left:auto;display:flex;gap:4px;font-size:11px;font-weight:900}
.lang a{padding:4px 7px;border:1px solid #d8dee8;color:#526173;text-decoration:none}
.lang a.on{background:var(--brand);border-color:var(--brand);color:#fff}
.body{padding:24px 28px}
.section-lbl{font-size:11px;font-weight:900;letter-spacing:.08em;text-transform:uppercase;color:#344054;margin-bottom:10px}
.packages{border:1px solid #d8dee8;margin-bottom:20px}
.package{display:flex;justify-content:space-between;align-items:center;padding:14px 16px;border-top:1px solid #d8dee8;cursor:pointer;transition:background .15s}
.package:first-child{border-top:0}
.package:hover{background:#e7eefc}
.package.selected{background:#e8f4f4;border-left:4px solid var(--brand);padding-left:12px}
.pkg-name{font-size:15px;font-weight:900}
.pkg-desc{font-size:12px;color:#667085;margin-top:2px}
.price{font-size:15px;font-weight:900;color:var(--brand);white-space:nowrap}
.field-lbl{display:block;font-size:11px;font-weight:900;letter-spacing:.08em;text-transform:uppercase;color:#344054;margin-bottom:7px}
.field-input{width:100%;height:50px;padding:0 14px;font-size:17px;border:1.5px solid #b8c2d1;outline:none;background:#fbfdff;color:#040a17}
.field-input:focus{border-color:var(--brand);box-shadow:0 0 0 3px color-mix(in srgb,var(--brand) 15%,transparent)}
.field-hint{font-size:11px;color:#8a96a3;margin-top:6px}
.error{margin-top:12px;padding:10px 13px;background:#fff1f1;border-left:4px solid #c62828;color:#a81717;font-size:13px;font-weight:700}
.btn{display:block;width:100%;height:50px;margin-top:18px;background:var(--brand);color:#fff;border:0;font-size:13px;font-weight:900;letter-spacing:.08em;text-transform:uppercase;cursor:pointer}
.btn:hover:not(:disabled){filter:brightness(1.12)}
.btn:disabled{opacity:.5;cursor:not-allowed}
.footer{padding:12px 28px;background:#f7f9fb;border-top:1px solid #e5e9f0;font-size:11px;color:#667085;text-align:center}
@media (max-width:420px){
  .page{padding:0}
  .card{border-left:0;border-right:0}
  .header{padding:18px 16px}
  .body{padding:20px 16px}
  .footer{padding:12px 16px}
  .brand{font-size:18px}
  .modal-box{padding:28px 20px}
}
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(20,32,51,.88);z-index:100;align-items:center;justify-content:center;padding:20px}
.modal-overlay.show{display:flex}
.modal-box{background:#fff;padding:36px 28px;max-width:360px;width:100%;text-align:center;animation:up .25s ease}
@keyframes up{from{transform:translateY(24px);opacity:0}to{transform:translateY(0);opacity:1}}
.m-icon{font-size:48px;display:block;margin-bottom:14px}
.m-icon.ok{color:#15803d}
.m-icon.bad{color:#c62828}
.m-title{font-size:19px;font-weight:900;margin-bottom:8px}
.m-msg{color:#667085;font-size:14px;line-height:1.6;margin-bottom:20px}
.m-ref{font-family:monospace;font-weight:700;color:#344054}
.spinner{width:44px;height:44px;border:3px solid #e8e8e8;border-top-color:var(--brand);border-radius:50%;animation:spin .8s linear infinite;margin:0 auto 20px}
@keyframes spin{to{transform:rotate(360deg)}}
.token-box{border:2px dashed var(--brand);padding:16px;margin-bottom:16px;text-align:left}
.token-lbl{font-size:11px;color:#667085;letter-spacing:1px;text-transform:uppercase;margin-bottom:4px}
.token-val{font-size:26px;font-weight:900;color:var(--brand);letter-spacing:4px;font-family:monospace}
.m-btn{display:block;width:100%;padding:13px;margin-top:8px;background:#fff;border:1.5px solid #d8dee8;color:#344054;font-family:Arial,sans-serif;font-size:13px;font-weight:700;cursor:pointer}
.m-btn:hover{border-color:var(--brand);color:#040a17}
.m-btn.primary{background:var(--brand);color:#fff;border-color:var(--brand)}
.no-packages{padding:24px;text-align:center;color:#667085;font-size:14px}
.tabs{display:flex;border-bottom:2px solid #e5e9f0;margin-bottom:20px}
.tab{flex:1;padding:11px 0;text-align:center;font-size:12px;font-weight:900;letter-spacing:.05em;text-transform:uppercase;color:#667085;cursor:pointer;transition:all .15s;border-bottom:3px solid transparent;margin-bottom:-2px}
.tab.active{color:var(--brand);border-bottom-color:var(--brand)}
.vc-input{width:100%;height:50px;padding:0 14px;font-size:22px;font-weight:900;letter-spacing:4px;text-transform:uppercase;border:1.5px solid #b8c2d1;outline:none;background:#fbfdff;color:#040a17;font-family:monospace;text-align:center}
.vc-input:focus{border-color:var(--brand);box-shadow:0 0 0 3px color-mix(in srgb,var(--brand) 15%,transparent)}
.welcome{background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;padding:18px 20px;margin-bottom:20px}
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
        <div class="brand">{{ strtoupper($tenant?->name ?? 'WiFi') }}</div>
        <div class="sub">{{ $settings?->tagline ?: __('portal.default_tagline') }}</div>
      </div>
      <div class="lang" aria-label="{{ __('portal.language') }}">
        <a href="{{ route('portal.tenant', $portalQuery + ['portal_key' => $portalKey, 'lang' => 'sw']) }}" class="{{ $locale === 'sw' ? 'on' : '' }}">SW</a>
        <a href="{{ route('portal.tenant', $portalQuery + ['portal_key' => $portalKey, 'lang' => 'en']) }}" class="{{ $locale === 'en' ? 'on' : '' }}">EN</a>
      </div>
    </div>

    <div class="body">
      {{-- The server only recognises a returning customer when the link carried their device's MAC
           address. When it did not, the page fills this in from what the device itself remembers. --}}
      <div id="welcome">
        @if($activeVoucher)
        <div class="welcome">
          <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px;">
            <i class="fa-solid fa-check" style="color:#15803d;font-size:20px;"></i>
            <div style="font-weight:800;color:#15803d;font-size:15px;">{{ __('portal.welcome_back') }}</div>
          </div>
          <div style="font-size:13px;color:#374151;line-height:1.6;margin-bottom:14px;">
            {{ __('portal.session_active', ['package' => $activeVoucher->package?->name ?? 'WiFi']) }}<br>
            {{ __('portal.expires') }}: <strong>{{ $activeVoucher->expires_at->timezone('Africa/Dar_es_Salaam')->format('d M Y, H:i') }}</strong>
          </div>
          <button class="btn" onclick="reconnectActive()" style="background:#15803d;margin-top:0;">
            <i class="fa-solid fa-wifi"></i> {{ __('portal.reconnect') }}
          </button>
          <p style="font-size:11px;color:#64748b;margin-top:8px;text-align:center;">
            {{ __('portal.token_label') }}: <span style="font-family:monospace;font-weight:700;">{{ $activeVoucher->voucher_code }}</span>
          </p>
        </div>
        @endif
      </div>

      {{-- Tab switcher --}}
      <div class="tabs">
         <div class="tab active" onclick="switchTab('pay',this)"><i class="fa-solid fa-credit-card"></i> {{ __('portal.tab_pay') }}</div>
         <div class="tab" onclick="switchTab('voucher',this)"><i class="fa-solid fa-ticket"></i> {{ __('portal.tab_voucher') }}</div>
      </div>

      {{-- Pay tab --}}
      <div id="payTab">
      <div class="section-lbl">{{ __('portal.select_package') }}</div>
      <div class="packages">
        @forelse($packages as $pkg)
        <div class="package"
             data-id="{{ $pkg->id }}"
             data-price="{{ $pkg->price }}"
             data-name="{{ $pkg->name }}"
             onclick="selectPackage(this)">
          <div>
            <div class="pkg-name">{{ $pkg->name }}</div>
            <div class="pkg-desc">{{ $pkg->durationLabel() }} &bull; {{ $pkg->speedLabel() }}@if($pkg->data_cap_mb) &bull; {{ $pkg->data_cap_mb >= 1024 ? rtrim(rtrim(number_format($pkg->data_cap_mb / 1024, 1), '0'), '.') . ' GB' : $pkg->data_cap_mb . ' MB' }}@endif</div>
          </div>
          <div class="price">{{ number_format($pkg->price) }} TZS</div>
        </div>
        @empty
        <div class="no-packages">{{ __('portal.no_packages') }}</div>
        @endforelse
      </div>

      <label class="field-lbl" for="phone">{{ __('portal.phone_label') }}</label>
      <input id="phone" type="tel" class="field-input" placeholder="{{ __('portal.phone_placeholder') }}" autocomplete="tel">
      <div class="field-hint">{{ __('portal.networks') }}</div>

      <div id="err" class="error" style="display:none"></div>

      <button id="payBtn" class="btn" onclick="initiatePayment()">{{ __('portal.pay_button') }}</button>
      </div>{{-- /payTab --}}

      {{-- Voucher tab --}}
      <div id="voucherTab" style="display:none">
        <div class="section-lbl">{{ __('portal.voucher_label') }}</div>
        <input id="vcCode" type="text" class="vc-input" placeholder="TN••••••••" maxlength="20"
               oninput="this.value=this.value.toUpperCase().replace(/[^A-Z0-9]/g,'')">
        <div class="field-hint" style="margin-top:6px;">{{ __('portal.voucher_hint') }}</div>
        <div id="vcErr" class="error" style="display:none"></div>
        <button class="btn" onclick="redeemVoucher()" style="margin-top:18px;">{{ __('portal.voucher_button') }}</button>
      </div>{{-- /voucherTab --}}
    </div>

    <div class="footer">{{ $tenant?->name ?? 'WiFi' }} &mdash; {{ $settings?->tagline ?: __('portal.default_tagline') }}</div>
  </div>
</main>

<div id="modal" class="modal-overlay">
  <div id="modalBox" class="modal-box"></div>
</div>

<script>
const hotspot = @json($hotspot ?? []);
const T       = @json(__('portal'));
const LANG    = @json($locale);
const TENANT  = @json($portalKey);   // the ISP this page sells for; sent back on every call
const PREFILL = @json($prefillCode); // a code typed on the router's login page, to redeem on arrival
const CONTACT = @json($contactPhone);
const ACTIVE  = @json($activeVoucher ? ['token' => $activeVoucher->voucher_code, 'package' => $activeVoucher->package?->name ?? 'WiFi'] : null);
const csrf    = document.querySelector('meta[name="csrf-token"]').content;

const POLL_MS       = 3000;
const POLL_ATTEMPTS = 60;   // three minutes per round

let selectedPackageId = null;
let selectedPrice     = 0;
let currentTxnId      = null;
let pollTimer         = null;

/**
 * What this device remembers about this ISP, kept in its own browser.
 *
 * The router's login address only ever reaches the portal in the link, and a customer coming back
 * often arrives without one: a captive-portal window that dropped the query string, a bookmark, or
 * history. Their code is then useless, because there is nowhere to send it. The device that used
 * the router is the one thing that always knows which router that was, so the address and the last
 * code are kept here and used whenever the link arrives bare.
 */
const MEM = 'trinetpay:' + TENANT;

function recall() {
  try { return JSON.parse(localStorage.getItem(MEM) || '{}') || {}; } catch (e) { return {}; }
}

function remember(patch) {
  try { localStorage.setItem(MEM, JSON.stringify(Object.assign(recall(), patch))); } catch (e) { /* private browsing */ }
}

/**
 * A login address is sent a customer's own WiFi code, so it has to be the router in front of them.
 * The server checks the one that comes in the link; this is the same check for one that came back
 * out of this browser's storage, which the customer could have edited themselves.
 */
function looksLocal(url) {
  const match = /^https?:\/\/([a-z0-9.\-]+)(:\d+)?(\/|$)/i.exec(String(url || ''));

  if (! match) return false;

  const host = match[1].toLowerCase();

  if (/^\d{1,3}(\.\d{1,3}){3}$/.test(host)) {
    return /^(10\.|127\.|192\.168\.|169\.254\.|172\.(1[6-9]|2\d|3[01])\.)/.test(host);
  }

  return host.indexOf('.') === -1 || /\.(lan|local|localdomain|hotspot|wifi)$/.test(host);
}

/** The code this device last used here, while it is recent enough to be worth offering. */
function savedSession() {
  const memory = recall();

  if (! memory.token || ! memory.at || (Date.now() - memory.at) > 7 * 24 * 3600 * 1000) {
    return null;
  }

  return {token: memory.token, package: memory.package || 'WiFi'};
}

// The link is the better answer when it has one, and worth keeping for the next visit that has none.
if (hotspot.link_login_only) {
  remember({login: hotspot.link_login_only});
} else if (looksLocal(recall().login)) {
  hotspot.link_login_only = recall().login;
}

/** Fill :name placeholders in a translated sentence. */
function t(key, params) {
  return String(T[key] ?? key).replace(/:(\w+)/g, (m, name) => (params && name in params) ? params[name] : m);
}

function safe(str) {
  return String(str ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
}

function api(path, options) {
  options = options || {};
  options.headers = Object.assign({
    'Accept': 'application/json',
    'X-CSRF-TOKEN': csrf,
    'X-Portal-Lang': LANG,
    'X-Portal-Tenant': TENANT
  }, options.headers || {});
  return fetch(path, options).then(res => res.json());
}

function selectPackage(el) {
  document.querySelectorAll('.package').forEach(p => p.classList.remove('selected'));
  el.classList.add('selected');
  selectedPackageId = parseInt(el.dataset.id, 10);
  selectedPrice     = parseInt(el.dataset.price, 10);
}

function showError(msg) {
  const el = document.getElementById('err');
  el.textContent = msg;
  el.style.display = 'block';
  setTimeout(() => el.style.display = 'none', 5000);
}

function closeModal() {
  document.getElementById('modal').classList.remove('show');
}

/** icon is a trusted Font Awesome class, title and msg are already translated or escaped. */
function showModal(iconClass, kind, title, msgHtml, opts) {
  opts = opts || {};
  document.getElementById('modalBox').innerHTML =
    `<span class="m-icon ${kind || ''}"><i class="${iconClass}"></i></span>
     <div class="m-title">${safe(title)}</div>
     <div class="m-msg">${msgHtml}</div>
     ${opts.spinner ? '<div class="spinner"></div>' : ''}
     ${opts.buttons || ''}`;
  document.getElementById('modal').classList.add('show');
}

function ref() {
  return currentTxnId ? String(currentTxnId).slice(-8).toUpperCase() : '';
}

function contactLine() {
  return CONTACT ? `<br><br><small>${safe(t('contact', {phone: CONTACT, ref: ref()}))}</small>` : '';
}

function showSuccess(token, pkgName, loginUrl, dst) {
  const target = dst || 'http://www.google.com';

  // Kept so this device can offer to reconnect on a later visit that arrives with a bare link.
  remember({token: token, package: pkgName, at: Date.now()});

  if (looksLocal(loginUrl)) {
    remember({login: loginUrl});
  }

  const form = loginUrl
    ? `<form id="routerLogin" method="post" action="${safe(loginUrl)}">
         <input type="hidden" name="username" value="${safe(token)}">
         <input type="hidden" name="password" value="${safe(token)}">
         <input type="hidden" name="dst" value="${safe(target)}">
       </form>`
    : '';

  document.getElementById('modalBox').innerHTML = `
    <span class="m-icon ok"><i class="fa-solid fa-circle-check"></i></span>
    <div class="m-title">${safe(t('paid_title'))}</div>
    <div class="m-msg">${safe(form ? t('connecting') : t('enter_token'))}</div>
    <div class="token-box">
      <div class="token-lbl">${safe(t('token_label'))}</div>
      <div class="token-val">${safe(token)}</div>
    </div>
    <p style="color:#667085;font-size:13px;margin-bottom:16px">${safe(t('package'))}: <strong>${safe(pkgName)}</strong></p>
    ${form}
    ${form ? `<button class="m-btn primary" onclick="document.getElementById('routerLogin').submit()">${safe(t('connect_btn'))}</button>` : ''}
    <button class="m-btn" onclick="closeModal()">${safe(t('close'))}</button>`;
  document.getElementById('modal').classList.add('show');

  if (form) {
    setTimeout(() => { const f = document.getElementById('routerLogin'); if (f) f.submit(); }, 1500);
  }
}

function reconnectActive() {
  const session = ACTIVE || savedSession();

  if (session) {
    showSuccess(session.token, session.package, hotspot.link_login_only, hotspot.link_orig);
  } else {
    showModal('fa-solid fa-circle-info', '', t('no_session_title'), safe(t('no_session_msg')),
      {buttons: `<button class="m-btn" onclick="closeModal()">${safe(t('close'))}</button>`});
  }
}

/**
 * The reconnect card for a device the server did not recognise, because the link carried no MAC
 * address. Only the device still knows the code, and only the router can say whether it still
 * works — so it is offered, and the router's own login page answers.
 */
function offerSavedSession() {
  const box = document.getElementById('welcome');

  if (! box || box.children.length || ! hotspot.link_login_only) return;

  const session = savedSession();

  if (! session) return;

  box.innerHTML = `
    <div class="welcome">
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px;">
        <i class="fa-solid fa-check" style="color:#15803d;font-size:20px;"></i>
        <div style="font-weight:800;color:#15803d;font-size:15px;">${safe(t('welcome_back'))}</div>
      </div>
      <div style="font-size:13px;color:#374151;line-height:1.6;margin-bottom:14px;">${safe(t('saved_code'))}</div>
      <button class="btn" onclick="reconnectActive()" style="background:#15803d;margin-top:0;">
        <i class="fa-solid fa-wifi"></i> ${safe(t('reconnect'))}
      </button>
      <p style="font-size:11px;color:#64748b;margin-top:8px;text-align:center;">
        ${safe(t('token_label'))}: <span style="font-family:monospace;font-weight:700;">${safe(session.token)}</span>
      </p>
    </div>`;
}

function switchTab(tab, el) {
  document.getElementById('payTab').style.display     = tab === 'pay'     ? '' : 'none';
  document.getElementById('voucherTab').style.display = tab === 'voucher' ? '' : 'none';
  document.querySelectorAll('.tab').forEach(x => x.classList.remove('active'));
  el.classList.add('active');
}

async function initiatePayment() {
  const phone = document.getElementById('phone').value.trim();
  if (!selectedPackageId) return showError(t('err_select_package'));
  if (!phone || phone.replace(/\D/g, '').length < 9) return showError(t('err_phone'));

  const btn = document.getElementById('payBtn');
  btn.disabled = true;

  showModal('fa-solid fa-hourglass-half', '', t('sending_title'), safe(t('sending_msg')), {spinner: true});

  try {
    const data = await api('/api/payment/initiate', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({
        phone,
        package_id:      selectedPackageId,
        mac:             hotspot.mac             || null,
        ip:              hotspot.ip              || null,
        link_login_only: hotspot.link_login_only || null,
        link_orig:       hotspot.link_orig       || null,
        nas:             hotspot.nas             || null
      })
    });

    if (data.status === 'success') {
      currentTxnId = data.transaction_id;
      showModal('fa-solid fa-mobile-screen', '', t('check_phone_title'),
        safe(t('check_phone_msg', {amount: selectedPrice.toLocaleString(), phone: phone})), {spinner: true});
      startPolling();
    } else {
      showModal('fa-solid fa-circle-xmark', 'bad', t('failed_title'), safe(data.message || t('err_generic')),
        {buttons: `<button class="m-btn primary" onclick="closeModal()">${safe(t('try_again'))}</button>`});
      btn.disabled = false;
    }
  } catch (e) {
    showModal('fa-solid fa-plug-circle-xmark', 'bad', t('failed_title'), safe(t('err_network')),
      {buttons: `<button class="m-btn primary" onclick="closeModal()">${safe(t('try_again'))}</button>`});
    btn.disabled = false;
  }
}

async function redeemVoucher() {
  const code  = document.getElementById('vcCode').value.trim();
  const vcErr = document.getElementById('vcErr');
  vcErr.style.display = 'none';

  if (!code || code.length < 6) {
    vcErr.textContent = t('err_voucher_short');
    vcErr.style.display = 'block';
    return;
  }

  showModal('fa-solid fa-spinner', '', t('voucher_checking_title'), safe(t('voucher_checking_msg')), {spinner: true});

  try {
    const data = await api('/api/voucher/redeem', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({code, phone: null, mac: hotspot.mac || null, ip: hotspot.ip || null, nas: hotspot.nas || null})
    });

    if (data.ok) {
      const go = () => showSuccess(data.code, data.package, hotspot.link_login_only, hotspot.link_orig);
      data.access_ready === false ? whenReady(data.access_ref || data.code, go) : go();
    } else if (hotspot.link_login_only) {
      // The platform does not know this code, but the ISP may have created the hotspot user on the
      // router itself. The router is the other authority on codes, so let it answer before we call
      // the code wrong. If it does not know it either, it says so on its own login page.
      tryRouterLogin(code);
    } else {
      closeModal();
      vcErr.textContent = data.message || t('err_generic');
      vcErr.style.display = 'block';
    }
  } catch (e) {
    closeModal();
    vcErr.textContent = t('err_network');
    vcErr.style.display = 'block';
  }
}

/**
 * Hand a code straight to the router's own login. Used for hotspot users an ISP created on the
 * router by hand, which the platform has no record of.
 */
function tryRouterLogin(code) {
  showModal('fa-solid fa-wifi', '', t('voucher_checking_title'), safe(t('connecting')), {spinner: true});

  const form = document.createElement('form');
  form.method = 'post';
  form.action = hotspot.link_login_only;

  [['username', code], ['password', code], ['dst', hotspot.link_orig || '']].forEach(function (pair) {
    const field = document.createElement('input');
    field.type  = 'hidden';
    field.name  = pair[0];
    field.value = pair[1];
    form.appendChild(field);
  });

  document.body.appendChild(form);
  form.submit();
}

function startPolling() {
  clearInterval(pollTimer);
  let attempts = 0;

  pollTimer = setInterval(async () => {
    attempts++;

    if (attempts > POLL_ATTEMPTS) {
      clearInterval(pollTimer);
      showModal('fa-solid fa-clock', '', t('timeout_title'), safe(t('timeout_msg', {ref: ref()})) + contactLine(), {
        buttons: `<button class="m-btn primary" onclick="checkAgain()">${safe(t('check_again'))}</button>
                  <button class="m-btn" onclick="closeModal();document.getElementById('payBtn').disabled=false">${safe(t('close'))}</button>`
      });
      return;
    }

    try {
      const data = await api('/api/payment/status?transaction_id=' + encodeURIComponent(currentTxnId));

      if (data.status === 'paid') {
        clearInterval(pollTimer);
        const go = () => showSuccess(data.wifi_token, data.package, data.login_url || hotspot.link_login_only, data.dst || hotspot.link_orig);
        data.access_ready === false ? whenReady(data.wifi_token, go) : go();
      } else if (data.status === 'failed') {
        clearInterval(pollTimer);
        showModal('fa-solid fa-circle-xmark', 'bad', t('failed_title'), safe(t('failed_msg')) + contactLine(), {
          buttons: `<button class="m-btn primary" onclick="closeModal();document.getElementById('payBtn').disabled=false">${safe(t('try_again'))}</button>`
        });
      }
    } catch (e) { /* the connection may drop for a moment, keep waiting */ }
  }, POLL_MS);
}

/**
 * On a router that creates the user itself, the login only works a few seconds after paying. Wait for the
 * router to say it is ready, then carry on. After about ninety seconds carry on anyway, the customer can
 * still press the connect button.
 */
function whenReady(ref, done) {
  showModal('fa-solid fa-gear', '', t('preparing_title'), safe(t('preparing_msg')), {spinner: true});
  let tries = 0;
  const timer = setInterval(async () => {
    tries++;
    try {
      const status = await api('/api/access/status?ref=' + encodeURIComponent(ref));
      if (status.ready) { clearInterval(timer); done(); return; }
    } catch (e) { /* keep waiting */ }
    if (tries > 45) { clearInterval(timer); done(); }
  }, 2000);
}

/** One more round of waiting, for a customer who confirmed late. */
function checkAgain() {
  showModal('fa-solid fa-mobile-screen', '', t('check_phone_title'), safe(t('sending_msg')), {spinner: true});
  startPolling();
}

offerSavedSession();

/**
 * The customer typed their code on the router's own login page. The router only knows codes it
 * has already been given, so the code comes here instead and is redeemed straight away.
 */
if (PREFILL) {
  document.getElementById('vcCode').value = PREFILL;
  switchTab('voucher', document.querySelectorAll('.tab')[1]);
  redeemVoucher();
}
</script>
</body>
</html>
