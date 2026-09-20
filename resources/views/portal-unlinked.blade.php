{{--
  Shown when a portal link names no ISP: no subdomain, no /portal/{key}, and no NAS identifier
  the platform recognises. Nothing can be sold here, because a payment with no ISP behind it
  has no packages, no wallet to credit and no router to open. Both languages are on the page
  so it needs no ISP settings to pick one.
--}}
<!DOCTYPE html>
<html lang="{{ $locale }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>WiFi</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
html,body{min-height:100%;background:#eef3f7;font-family:Arial,Helvetica,sans-serif;color:#040a17}
.page{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
.card{width:100%;max-width:440px;background:#fff;border:1px solid #d8dee8;box-shadow:0 16px 48px rgba(20,32,51,.14)}
.header{padding:26px 28px 20px;border-bottom:3px solid #b45309}
.brand{font-size:20px;font-weight:900;line-height:1.2}
.sub{font-size:11px;font-weight:800;color:#526173;letter-spacing:.1em;text-transform:uppercase;margin-top:6px}
.body{padding:24px 28px}
.lead{font-size:14px;line-height:1.65;margin-bottom:18px}
.lead + .lead{padding-top:16px;border-top:1px solid #e5e9f0}
.lead strong{display:block;font-size:12px;font-weight:900;letter-spacing:.06em;text-transform:uppercase;color:#b45309;margin-bottom:6px}
.footer{padding:13px 28px;background:#f7f9fb;border-top:1px solid #e5e9f0;font-size:11px;color:#667085;text-align:center}
@media (max-width:420px){
  .page{padding:0}
  .card{border-left:0;border-right:0}
  .header{padding:20px 16px}
  .body{padding:20px 16px}
  .footer{padding:12px 16px}
  .brand{font-size:17px}
}
</style>
</head>
<body>
<main class="page">
  <div class="card">
    <div class="header">
      <div class="brand">This link is not connected to a provider</div>
      <div class="sub">Kiungo hiki hakijaunganishwa na mtoa huduma</div>
    </div>
    <div class="body">
      <p class="lead">
        <strong>English</strong>
        This page cannot tell which WiFi provider you are buying from, so it cannot sell you a
        package. Please connect to the WiFi first and use the &ldquo;Buy WiFi&rdquo; link on the
        login page, or ask the provider for their own portal address.
      </p>
      <p class="lead">
        <strong>Kiswahili</strong>
        Ukurasa huu haujui unanunua kutoka kwa mtoa huduma yupi, kwa hiyo hauwezi kukuuzia kifurushi.
        Tafadhali unganisha kwenye WiFi kwanza kisha tumia kiungo cha &ldquo;Nunua WiFi&rdquo; kwenye
        ukurasa wa kuingia, au muulize mtoa huduma anwani yake ya portal.
      </p>
    </div>
    <div class="footer">{{ \App\Support\TenantUrls::baseHost() }}</div>
  </div>
</main>
</body>
</html>
