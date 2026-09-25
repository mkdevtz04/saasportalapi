<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set up your router — Wifikitaa</title>
    <meta name="description" content="Turn a brand new MikroTik router into a working hotspot by pasting one command. No router configuration needed.">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --indigo: #2561e8;
            --indigo-dark: #040a17;
            --indigo-light: #e7eefc;
            --dark: #040a17;
            --dark-2: #040a17;
            --mid: #334155;
            --muted: #64748b;
            --light: #94a3b8;
            --border: #e2e8f0;
            --bg: #f8fafc;
            --good: #15803d;
            --good-light: #f0fdf4;
            --good-border: #bbf7d0;
            --warn: #b45309;
        }

        html { scroll-behavior: smooth; }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Inter', sans-serif;
            color: var(--dark-2);
            background: #fff;
            line-height: 1.6;
        }

        a { text-decoration: none; color: inherit; }

        /* ── Navbar ─────────────────────────────────────────── */
        .nav {
            position: sticky; top: 0; z-index: 100;
            background: rgba(255,255,255,0.92);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--border);
            padding: 0 5%;
            height: 64px;
            display: flex; align-items: center; justify-content: space-between;
        }

        .nav-logo { display: flex; align-items: center; gap: 8px; }

        .logo-text {
            font-size: 18px; font-weight: 800; color: var(--dark);
            letter-spacing: -0.5px;
        }

        .logo-text span { color: var(--indigo); }

        .nav-links { display: flex; align-items: center; gap: 2px; }

        .nav-links a {
            padding: 6px 14px; border-radius: 6px;
            font-size: 14px; font-weight: 500; color: var(--mid);
            transition: all 0.15s;
        }

        .nav-links a:hover { background: var(--bg); color: var(--dark); }
        .nav-links a.on { background: var(--bg); color: var(--dark); font-weight: 600; }

        .nav-cta { display: flex; align-items: center; gap: 8px; }

        .btn-ghost {
            padding: 8px 16px; border-radius: 8px;
            font-size: 14px; font-weight: 600; color: var(--mid);
            transition: all 0.15s;
        }
        .btn-ghost:hover { background: var(--bg); color: var(--dark); }

        .btn-primary {
            padding: 8px 18px; border-radius: 8px;
            background: var(--indigo);
            color: #fff; font-size: 14px; font-weight: 600;
            transition: all 0.15s; border: none; cursor: pointer;
        }
        .btn-primary:hover { background: var(--indigo-dark); transform: translateY(-1px); box-shadow: 0 4px 12px rgba(79,70,229,0.3); }

        /* ── Page head ──────────────────────────────────────── */
        .page-head {
            background: #040a17;
            padding: 60px 5% 56px;
        }

        .page-head-inner { max-width: 820px; margin: 0 auto; }

        .crumb {
            display: inline-flex; align-items: center; gap: 6px;
            font-size: 13px; font-weight: 500; color: var(--light);
            margin-bottom: 16px; transition: color 0.15s;
        }
        .crumb:hover { color: #e7eefc; }

        .page-head h1 {
            font-size: clamp(28px, 4vw, 42px);
            font-weight: 800; color: #fff; line-height: 1.15;
            letter-spacing: -1.2px; margin-bottom: 14px;
        }

        .page-head p {
            font-size: 16px; color: var(--light); line-height: 1.7; max-width: 620px;
        }

        /* ── Section common ─────────────────────────────────── */
        section { padding: 64px 5%; }

        .section-inner { max-width: 820px; margin: 0 auto; }

        .section-tag {
            display: inline-block;
            background: var(--indigo-light); color: var(--indigo);
            border-radius: 99px; padding: 4px 14px;
            font-size: 12px; font-weight: 700; letter-spacing: 0.5px; text-transform: uppercase;
            margin-bottom: 12px;
        }

        .section-title {
            font-size: clamp(22px, 3vw, 30px);
            font-weight: 800; color: var(--dark); letter-spacing: -0.8px;
            margin-bottom: 12px;
        }

        .section-sub { font-size: 16px; color: var(--muted); line-height: 1.7; }

        .alt { background: var(--bg); }

        /* ── Prerequisites ──────────────────────────────────── */
        .pre-grid {
            display: grid; grid-template-columns: 1fr 1fr; gap: 16px;
            margin-top: 28px;
        }

        .pre-card {
            background: #fff; border: 1px solid var(--border);
            border-radius: 12px; padding: 22px;
        }

        .pre-icon {
            width: 38px; height: 38px; border-radius: 9px;
            background: var(--indigo-light); color: var(--indigo);
            display: flex; align-items: center; justify-content: center;
            font-size: 15px; margin-bottom: 14px;
        }

        .pre-card h3 { font-size: 15px; font-weight: 700; color: var(--dark); margin-bottom: 5px; }
        .pre-card p { font-size: 14px; color: var(--muted); line-height: 1.65; }

        /* ── Steps ──────────────────────────────────────────── */
        .steps { list-style: none; margin-top: 32px; display: flex; flex-direction: column; gap: 32px; }

        .step { display: grid; grid-template-columns: 40px 1fr; gap: 0 18px; align-items: start; }

        .step-num {
            grid-row: 1 / span 2;
            width: 40px; height: 40px; border-radius: 50%;
            background: var(--indigo); color: #fff;
            display: flex; align-items: center; justify-content: center;
            font-size: 15px; font-weight: 800;
        }

        .step h3 { font-size: 17px; font-weight: 700; color: var(--dark); padding-top: 8px; }

        .step-body { grid-column: 2; margin-top: 8px; }
        .step-body p { font-size: 15px; color: var(--muted); line-height: 1.7; margin-bottom: 12px; }
        .step-body p:last-child { margin-bottom: 0; }
        .step-body b { color: var(--mid); font-weight: 600; }

        kbd {
            display: inline-block;
            font-family: ui-monospace, 'SF Mono', Menlo, Consolas, monospace;
            font-size: 12.5px; font-weight: 600;
            background: #fff; border: 1px solid var(--border);
            border-bottom-width: 2px; border-radius: 5px;
            padding: 2px 7px; color: var(--mid); white-space: nowrap;
        }

        /* ── Command block ──────────────────────────────────── */
        .cmd {
            background: #040a17; border-radius: 12px;
            padding: 18px 20px; overflow-x: auto; margin-bottom: 12px;
        }

        .cmd code {
            font-family: ui-monospace, 'SF Mono', Menlo, Consolas, monospace;
            font-size: 12.5px; line-height: 1.8; color: #cfe0f7;
            white-space: pre; display: block;
        }

        .cmd .ph { color: #f0b45e; font-weight: 600; }

        .hint {
            display: flex; gap: 9px; align-items: flex-start;
            font-size: 13.5px; color: var(--muted); line-height: 1.6;
        }
        .hint i { color: var(--indigo); font-size: 13px; margin-top: 3px; flex: 0 0 auto; }

        /* ── Outcome list ───────────────────────────────────── */
        .outcome { list-style: none; margin-top: 28px; }

        .outcome li {
            display: grid; grid-template-columns: 24px 1fr; gap: 12px;
            align-items: start;
            padding: 15px 0; border-bottom: 1px solid var(--border);
            font-size: 15px; line-height: 1.65;
        }
        .outcome li:last-child { border-bottom: none; }

        .outcome i { color: var(--good); font-size: 14px; margin-top: 4px; }
        .outcome b { font-weight: 700; color: var(--dark); }
        .outcome span { color: var(--muted); }

        .callout {
            display: flex; gap: 12px; align-items: flex-start;
            background: var(--good-light); border: 1px solid var(--good-border);
            border-radius: 10px; padding: 16px 18px; margin-top: 24px;
        }
        .callout i { color: var(--good); font-size: 15px; margin-top: 3px; flex: 0 0 auto; }
        .callout p { font-size: 14px; color: var(--mid); line-height: 1.65; }
        .callout b { font-weight: 700; color: var(--dark); }

        /* ── Troubleshooting ────────────────────────────────── */
        .fix-lead {
            background: var(--indigo-light); border-radius: 12px;
            padding: 20px 22px; margin-top: 26px;
            display: flex; gap: 14px; align-items: flex-start;
        }
        .fix-lead i { color: var(--indigo); font-size: 18px; margin-top: 2px; flex: 0 0 auto; }
        .fix-lead p { font-size: 15px; color: var(--mid); line-height: 1.7; }
        .fix-lead b { color: var(--dark); font-weight: 700; }

        .fix-list { margin-top: 18px; display: flex; flex-direction: column; gap: 12px; }

        .fix {
            background: #fff; border: 1px solid var(--border);
            border-left: 3px solid var(--warn);
            border-radius: 10px; padding: 18px 20px;
        }

        .fix h3 { font-size: 15px; font-weight: 700; color: var(--dark); margin-bottom: 6px; }
        .fix p { font-size: 14px; color: var(--muted); line-height: 1.7; }

        .chip {
            display: inline-block;
            background: var(--indigo-light); color: var(--indigo);
            border-radius: 5px; padding: 1px 7px;
            font-size: 13px; font-weight: 700; white-space: nowrap;
        }

        /* ── Don't change ───────────────────────────────────── */
        .dont { list-style: none; margin-top: 26px; }

        .dont li {
            display: grid; grid-template-columns: 24px 1fr; gap: 12px;
            align-items: start;
            padding: 15px 0; border-bottom: 1px solid var(--border);
            font-size: 15px; line-height: 1.65;
        }
        .dont li:last-child { border-bottom: none; }

        .dont i { color: var(--warn); font-size: 14px; margin-top: 4px; }
        .dont b { font-weight: 700; color: var(--dark); }
        .dont span { color: var(--muted); }

        .dont code {
            font-family: ui-monospace, 'SF Mono', Menlo, Consolas, monospace;
            font-size: 13px; background: var(--bg);
            border: 1px solid var(--border); border-radius: 4px; padding: 1px 5px;
        }

        /* ── Help strip ─────────────────────────────────────── */
        .help {
            background: #040a17; padding: 52px 5%;
        }
        .help-inner {
            max-width: 820px; margin: 0 auto; text-align: center;
        }
        .help h2 {
            font-size: clamp(20px, 2.6vw, 26px); font-weight: 800; color: #fff;
            letter-spacing: -0.6px; margin-bottom: 10px;
        }
        .help p { font-size: 15px; color: var(--light); line-height: 1.7; max-width: 560px; margin: 0 auto 22px; }

        /* ── Footer ─────────────────────────────────────────── */
        .footer {
            background: var(--dark); border-top: 1px solid #1e293b;
            padding: 40px 5%;
        }

        .footer-inner {
            max-width: 1200px; margin: 0 auto;
            display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px;
        }

        .footer-logo .logo-text { font-size: 16px; }
        .footer-logo p { font-size: 12px; color: var(--muted); margin-top: 3px; }

        .footer-links { display: flex; gap: 24px; flex-wrap: wrap; }

        .footer-links a { font-size: 13px; color: var(--muted); transition: color 0.15s; }
        .footer-links a:hover { color: #e2e8f0; }

        .footer-copy { font-size: 12px; color: #334155; }

        /* ── Responsive ─────────────────────────────────────── */
        @media (max-width: 640px) {
            .nav-links { display: none; }
            .pre-grid { grid-template-columns: 1fr; }
        }

        @media (max-width: 480px) {
            .page-head { padding: 44px 5% 40px; }
            .page-head h1 { letter-spacing: -0.5px; }
            section { padding: 48px 5%; }
            .step { grid-template-columns: 34px 1fr; gap: 0 14px; }
            .step-num { width: 34px; height: 34px; font-size: 14px; }
            .step h3 { padding-top: 5px; font-size: 16px; }
            .cmd code { font-size: 11.5px; }
        }
    </style>
</head>
<body>

{{-- ── Navbar ──────────────────────────────────────── --}}
<nav class="nav">
    <div class="nav-logo">
        <a href="/"><div class="logo-text">Wifi<span>kitaa</span></div></a>
    </div>

    <div class="nav-links">
        <a href="/#features">Features</a>
        <a href="/#how-it-works">How it works</a>
        <a href="/#pricing">Pricing</a>
        <a href="{{ route('guide.router-setup') }}" class="on">Router setup</a>
    </div>

    <div class="nav-cta">
        <a href="{{ route('login') }}" class="btn-ghost">Sign in</a>
        <a href="{{ route('register') }}" class="btn-primary">Get Started Free</a>
    </div>
</nav>

{{-- ── Page head ────────────────────────────────────── --}}
<header class="page-head">
    <div class="page-head-inner">
        <a href="/" class="crumb"><i class="fa-solid fa-arrow-left"></i> Back to home</a>
        <h1>Set up your router</h1>
        <p>A brand new MikroTik becomes a working hotspot by pasting a single line. You never configure the router
            yourself — no WiFi settings, no hotspot settings, nothing.</p>
    </div>
</header>

{{-- ── Before you start ─────────────────────────────── --}}
<section>
    <div class="section-inner">
        <span class="section-tag">Before you start</span>
        <h2 class="section-title">Two things you need</h2>
        <p class="section-sub">Nothing else. The router does not need to be set up first — straight out of the box
            is exactly right.</p>

        <div class="pre-grid">
            <div class="pre-card">
                <div class="pre-icon"><i class="fa-solid fa-ethernet"></i></div>
                <h3>Internet in port 1</h3>
                <p>Plug your internet cable into <b>ether1</b>. The router downloads its own setup, so it cannot be
                    configured without a connection.</p>
            </div>
            <div class="pre-card">
                <div class="pre-icon"><i class="fa-solid fa-desktop"></i></div>
                <h3>WinBox, logged in</h3>
                <p>Open WinBox and connect to the router. Any MikroTik model works, including one fresh from the
                    shop.</p>
            </div>
        </div>
    </div>
</section>

{{-- ── The three steps ──────────────────────────────── --}}
<section class="alt">
    <div class="section-inner">
        <span class="section-tag">Do this</span>
        <h2 class="section-title">Three steps, about a minute</h2>

        <ol class="steps">
            <li class="step">
                <div class="step-num">1</div>
                <h3>Copy your command</h3>
                <div class="step-body">
                    <p>In your dashboard, open your router and press <b>Setup command</b>, then
                        <b>Copy Command</b>. It looks like this:</p>
                    <div class="cmd"><code>/tool fetch url="https://<span class="ph">&lt;your dashboard&gt;</span>/provision/<span class="ph">&lt;your router code&gt;</span>"
  dst-path=trinetpay-bootstrap.rsc mode=https check-certificate=no;
  :delay 2s; /import file-name=trinetpay-bootstrap.rsc;
  /file remove trinetpay-bootstrap.rsc</code></div>
                    <div class="hint">
                        <i class="fa-solid fa-circle-info"></i>
                        <span>Every router has its own command with its own code inside it. Always copy yours from
                            the dashboard — the example above will not work.</span>
                    </div>
                </div>
            </li>

            <li class="step">
                <div class="step-num">2</div>
                <h3>Paste it into the router</h3>
                <div class="step-body">
                    <p>In WinBox: <kbd>New Terminal</kbd> &rarr; right-click &rarr; <kbd>Paste</kbd> &rarr;
                        <kbd>Enter</kbd>.</p>
                    <p>A few downloads scroll past, then it finishes with
                        <b>Script file loaded and executed successfully</b>.</p>
                </div>
            </li>

            <li class="step">
                <div class="step-num">3</div>
                <h3>Watch the dashboard turn green</h3>
                <div class="step-body">
                    <p>Go back to the setup page. Its five steps turn green one after another, ending at
                        <b>Waiting for the router to report in</b>.</p>
                    <p>Once that last one is green, the router is live and can take payments.</p>
                </div>
            </li>
        </ol>
    </div>
</section>

{{-- ── What you should see ──────────────────────────── --}}
<section>
    <div class="section-inner">
        <span class="section-tag">Then</span>
        <h2 class="section-title">What you should see</h2>

        <ul class="outcome">
            <li>
                <i class="fa-solid fa-check"></i>
                <span><b>A WiFi network named after your business.</b> The router's old name is gone.</span>
            </li>
            <li>
                <i class="fa-solid fa-check"></i>
                <span><b>No WiFi password.</b> This is deliberate. Customers have to be able to join before they can
                    pay — your payment page is what controls access, not a password.</span>
            </li>
            <li>
                <i class="fa-solid fa-check"></i>
                <span><b>Your payment page opens by itself</b> when a phone joins the WiFi.</span>
            </li>
            <li>
                <i class="fa-solid fa-check"></i>
                <span><b>Customers pay by mobile money or enter a voucher code</b>, and go online straight
                    away.</span>
            </li>
            <li>
                <i class="fa-solid fa-check"></i>
                <span><b>Returning customers reconnect on their own.</b> A phone that already paid does not type its
                    code again until the package runs out.</span>
            </li>
            <li>
                <i class="fa-solid fa-check"></i>
                <span><b>The router shows Online</b> in your dashboard.</span>
            </li>
        </ul>

        <div class="callout">
            <i class="fa-solid fa-mobile-screen"></i>
            <p><b>Testing on your own phone?</b> Tell it to forget the network first. Both the name and the password
                changed, so a phone that remembers the old WiFi may refuse to join the new one.</p>
        </div>
    </div>
</section>

{{-- ── If something looks wrong ─────────────────────── --}}
<section class="alt">
    <div class="section-inner">
        <span class="section-tag">If it looks wrong</span>
        <h2 class="section-title">Fixing it yourself</h2>

        <div class="fix-lead">
            <i class="fa-solid fa-rotate-right"></i>
            <p><b>Pasting the command again is safe, and it is the answer to almost everything.</b> It repairs
                whatever is wrong and leaves the rest alone, so you can do it as many times as you like.</p>
        </div>

        <div class="fix-list">
            <div class="fix">
                <h3>Customers get internet without paying</h3>
                <p>The most expensive fault, and the easiest to fix. <span class="chip">Paste it again</span></p>
            </div>

            <div class="fix">
                <h3>The setup page sits on one step and never finishes</h3>
                <p><span class="chip">Paste it again</span> &nbsp;If it stops on the very first step, the router
                    cannot reach the internet — check the cable in port 1.</p>
            </div>

            <div class="fix">
                <h3>The WiFi still has its old name, or still asks for a password</h3>
                <p><span class="chip">Paste it again</span> &nbsp;If the name still does not change, this router
                    model needs its WiFi named by hand. Send us the model and we will do it with you — everything
                    else already works in the meantime.</p>
            </div>

            <div class="fix">
                <h3>A red "Setup finished with problems" message</h3>
                <p><span class="chip">Paste it again</span> &nbsp;If the same message comes back, send us the exact
                    wording. It names the part that failed and tells us where to look.</p>
            </div>

            <div class="fix">
                <h3>The WiFi works, but nobody can buy</h3>
                <p>Check the router still shows <b>Online</b> in your dashboard. A router that has lost its internet
                    keeps customers who already paid online, but nobody new can buy until it is back.</p>
            </div>
        </div>
    </div>
</section>

{{-- ── Don't change ─────────────────────────────────── --}}
<section>
    <div class="section-inner">
        <span class="section-tag">Careful</span>
        <h2 class="section-title">Four things not to change</h2>

        <ul class="dont">
            <li>
                <i class="fa-solid fa-xmark"></i>
                <span><b>Do not put a password back on the WiFi.</b>
                    <span>Customers could not join, so they would never reach the page that takes their
                        money.</span></span>
            </li>
            <li>
                <i class="fa-solid fa-xmark"></i>
                <span><b>Do not turn the hotspot off.</b>
                    <span>Everyone gets free internet the moment you do.</span></span>
            </li>
            <li>
                <i class="fa-solid fa-xmark"></i>
                <span><b>Do not delete anything named <code>trinetpay</code>.</b>
                    <span>That includes the scheduled task — it is what keeps the router talking to your
                        dashboard.</span></span>
            </li>
            <li>
                <i class="fa-solid fa-xmark"></i>
                <span><b>Do not reset the router</b> unless you will paste the command again afterwards.
                    <span>A reset wipes the whole setup.</span></span>
            </li>
        </ul>

        <div class="callout">
            <i class="fa-solid fa-lightbulb"></i>
            <p><b>Changed something by mistake?</b> You already know the fix — paste the command again.</p>
        </div>
    </div>
</section>

{{-- ── Help ─────────────────────────────────────────── --}}
<section class="help">
    <div class="help-inner">
        <h2>Still stuck after pasting it twice?</h2>
        <p>Send us your router's name and anything the dashboard or the terminal says, word for word. That tells us
            which part failed, and saves us both a guessing game.</p>
        <a href="{{ route('login') }}" class="btn-primary">Go to my dashboard</a>
    </div>
</section>

{{-- ── Footer ───────────────────────────────────────── --}}
<footer class="footer">
    <div class="footer-inner">
        <div class="footer-logo">
            <div class="nav-logo">
                <div class="logo-text" style="color:white;font-size:16px;">Wifikitaa</div>
            </div>
            <p>Hotspot billing for Tanzanian ISPs.</p>
        </div>

        <div class="footer-links">
            <a href="{{ route('login') }}">Dashboard Login</a>
            <a href="{{ route('register') }}">Sign Up</a>
            <a href="{{ route('guide.router-setup') }}">Router setup</a>
            <a href="/#pricing">Pricing</a>
        </div>

        <div class="footer-copy">
            &copy; {{ date('Y') }} Wifikitaa. All rights reserved.
        </div>
    </div>
</footer>

</body>
</html>
