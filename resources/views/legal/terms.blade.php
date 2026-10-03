<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Terms &amp; Conditions — Wifikitaa</title>
    <meta name="description" content="The terms that apply to internet providers who sell hotspot access through Wifikitaa: how money moves, what the fees are, and what each side is responsible for.">
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
            --warn-light: #fffbeb;
            --warn-border: #fde68a;
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
            padding: 8px 16px; border-radius: 7px;
            font-size: 14px; font-weight: 600; color: var(--mid);
        }

        .btn-ghost:hover { background: var(--bg); color: var(--dark); }

        .btn-primary {
            padding: 8px 18px; border-radius: 7px;
            background: var(--indigo); color: #fff;
            font-size: 14px; font-weight: 600;
            transition: all 0.15s;
            display: inline-block;
        }

        .btn-primary:hover { background: var(--indigo-dark); transform: translateY(-1px); box-shadow: 0 4px 12px rgba(79,70,229,0.3); }

        /* ── Page head ──────────────────────────────────────── */
        .page-head {
            padding: 56px 5% 44px;
            border-bottom: 1px solid var(--border);
            background: var(--bg);
        }

        .page-head-inner { max-width: 820px; margin: 0 auto; }

        .eyebrow {
            display: inline-block;
            font-size: 12px; font-weight: 700; letter-spacing: 0.6px; text-transform: uppercase;
            color: var(--indigo); background: var(--indigo-light);
            padding: 5px 11px; border-radius: 5px;
            margin-bottom: 16px;
        }

        .page-head h1 {
            font-size: 36px; font-weight: 800; letter-spacing: -1px;
            color: var(--dark); line-height: 1.2;
        }

        .page-head .lede {
            font-size: 16px; color: var(--muted); line-height: 1.7;
            margin-top: 14px;
        }

        .dates {
            display: flex; gap: 22px; flex-wrap: wrap;
            margin-top: 22px; padding-top: 20px;
            border-top: 1px solid var(--border);
            font-size: 13px; color: var(--muted);
        }

        .dates strong { color: var(--mid); font-weight: 600; }

        /* ── Body ───────────────────────────────────────────── */
        .doc { padding: 44px 5% 64px; }
        .doc-inner { max-width: 820px; margin: 0 auto; }

        /* Contents */
        .toc {
            background: var(--bg); border: 1px solid var(--border);
            border-radius: 10px; padding: 22px 26px;
            margin-bottom: 44px;
        }

        .toc h2 {
            font-size: 13px; font-weight: 700; letter-spacing: 0.5px; text-transform: uppercase;
            color: var(--muted); margin-bottom: 14px;
        }

        .toc ol {
            list-style: none; counter-reset: toc;
            columns: 2; column-gap: 32px;
        }

        .toc li {
            counter-increment: toc;
            font-size: 14px; line-height: 1.5;
            margin-bottom: 7px;
            break-inside: avoid;
        }

        .toc li::before {
            content: counter(toc) ". ";
            color: var(--light); font-variant-numeric: tabular-nums;
        }

        .toc a { color: var(--mid); }
        .toc a:hover { color: var(--indigo); text-decoration: underline; }

        /* Sections */
        .clause {
            counter-increment: clause;
            padding-top: 14px;
            margin-bottom: 38px;
            scroll-margin-top: 80px;
        }

        .doc-inner { counter-reset: clause; }

        .clause h2 {
            font-size: 21px; font-weight: 700; letter-spacing: -0.3px;
            color: var(--dark); line-height: 1.3;
            margin-bottom: 14px;
        }

        .clause h2::before {
            content: counter(clause) ".";
            color: var(--indigo);
            margin-right: 10px;
            font-variant-numeric: tabular-nums;
        }

        .clause h3 {
            font-size: 16px; font-weight: 700; color: var(--dark);
            margin: 22px 0 8px;
        }

        .clause p {
            font-size: 15px; color: var(--mid); line-height: 1.75;
            margin-bottom: 14px;
        }

        .clause ul { margin: 0 0 14px 0; padding-left: 0; list-style: none; }

        .clause ul li {
            font-size: 15px; color: var(--mid); line-height: 1.7;
            padding-left: 22px; margin-bottom: 9px;
            position: relative;
        }

        .clause ul li::before {
            content: ''; position: absolute;
            left: 6px; top: 11px;
            width: 5px; height: 5px; border-radius: 50%;
            background: var(--light);
        }

        .clause strong { color: var(--dark); font-weight: 600; }

        /* Callouts */
        .note {
            background: var(--bg); border: 1px solid var(--border);
            border-left: 3px solid var(--indigo);
            border-radius: 8px; padding: 16px 20px;
            margin: 0 0 16px;
        }

        .note p { margin-bottom: 0; font-size: 14px; }
        .note p + p { margin-top: 10px; }

        .draft {
            background: var(--warn-light); border: 1px solid var(--warn-border);
            border-radius: 10px; padding: 18px 22px;
            margin-bottom: 40px;
            display: flex; gap: 14px; align-items: flex-start;
        }

        .draft i { color: var(--warn); font-size: 17px; margin-top: 2px; }
        .draft p { font-size: 14px; color: #78350f; line-height: 1.65; margin: 0; }
        .draft strong { color: #451a03; }

        /* A value a human still has to supply before this page goes live. */
        .fill {
            background: #fef08a; color: #713f12;
            padding: 1px 6px; border-radius: 4px;
            font-size: 0.93em; font-weight: 600;
            white-space: nowrap;
        }

        /* Fee table */
        .fees {
            width: 100%; border-collapse: collapse;
            margin: 0 0 16px;
            font-size: 14px;
        }

        .fees th, .fees td {
            text-align: left; padding: 11px 14px;
            border-bottom: 1px solid var(--border);
        }

        .fees th {
            font-size: 12px; font-weight: 700; letter-spacing: 0.4px; text-transform: uppercase;
            color: var(--muted); background: var(--bg);
        }

        .fees td { color: var(--mid); }
        .fees td:last-child { font-weight: 600; color: var(--dark); white-space: nowrap; }
        .fees tr:last-child td { border-bottom: none; }

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
            .toc ol { columns: 1; }
        }

        @media (max-width: 480px) {
            .page-head { padding: 44px 5% 36px; }
            .page-head h1 { font-size: 28px; letter-spacing: -0.5px; }
            .nav-cta .btn-ghost { display: none; }
            .clause h2 { font-size: 19px; }
            .fees th, .fees td { padding: 9px 10px; }
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
        <a href="{{ route('guide.router-setup') }}">Router setup</a>
    </div>

    <div class="nav-cta">
        <a href="{{ route('login') }}" class="btn-ghost">Sign in</a>
        <a href="{{ route('register') }}" class="btn-primary">Get Started Free</a>
    </div>
</nav>

{{-- ── Page head ───────────────────────────────────── --}}
<section class="page-head">
    <div class="page-head-inner">
        <span class="eyebrow">Legal</span>
        <h1>Terms &amp; Conditions</h1>
        <p class="lede">
            These terms apply to internet providers who use Wifikitaa to sell hotspot access.
            They explain how your customers' payments reach you, what we charge, what we do when
            something goes wrong, and what each of us is responsible for.
        </p>
        <div class="dates">
            <span><strong>Last updated:</strong> 3 October 2026</span>
            <span><strong>Applies to:</strong> everyone with a Wifikitaa account</span>
        </div>
    </div>
</section>

{{-- ── Document ────────────────────────────────────── --}}
<section class="doc">
    <div class="doc-inner">

        <div class="draft">
            <i class="fa-solid fa-triangle-exclamation"></i>
            <p>
                <strong>Draft — not yet reviewed by a lawyer.</strong>
                The highlighted values still need to be filled in, and a Tanzanian advocate should
                check this document before it governs real accounts. It describes how the platform
                actually works today, which is the right starting point, but it is not legal advice.
            </p>
        </div>

        {{-- Contents --}}
        <nav class="toc">
            <h2>Contents</h2>
            <ol>
                <li><a href="#who-we-are">Who we are</a></li>
                <li><a href="#what-wifikitaa-is">What Wifikitaa is, and is not</a></li>
                <li><a href="#your-account">Your account</a></li>
                <li><a href="#your-network">Your network and your licences</a></li>
                <li><a href="#how-money-moves">How money moves</a></li>
                <li><a href="#fees">What we charge</a></li>
                <li><a href="#withdrawals">Withdrawals</a></li>
                <li><a href="#your-customers">Your customers are your customers</a></li>
                <li><a href="#refunds">Refunds and disputes</a></li>
                <li><a href="#agents">Agents and staff accounts</a></li>
                <li><a href="#vouchers">Vouchers</a></li>
                <li><a href="#availability">Service availability</a></li>
                <li><a href="#acceptable-use">Acceptable use</a></li>
                <li><a href="#suspension">Suspension and closing your account</a></li>
                <li><a href="#customer-data">Customer data</a></li>
                <li><a href="#support-access">Our access to your account</a></li>
                <li><a href="#liability">Limits on our liability</a></li>
                <li><a href="#changes">Changes to these terms</a></li>
                <li><a href="#law">Governing law</a></li>
                <li><a href="#contact">How to reach us</a></li>
            </ol>
        </nav>

        {{-- 1 --}}
        <div class="clause" id="who-we-are">
            <h2>Who we are</h2>
            <p>
                Wifikitaa is operated by <span class="fill">legal company name</span>, a company
                registered in the United Republic of Tanzania, registration number
                <span class="fill">company reg. no.</span>, TIN <span class="fill">TIN</span>,
                with its registered office at <span class="fill">physical address</span>.
            </p>
            <p>
                In this document <strong>"we"</strong>, <strong>"us"</strong> and
                <strong>"Wifikitaa"</strong> mean that company. <strong>"You"</strong> means the
                business or person who holds a Wifikitaa account. <strong>"Your customers"</strong>
                means the people who buy WiFi access on your hotspot.
            </p>
        </div>

        {{-- 2 --}}
        <div class="clause" id="what-wifikitaa-is">
            <h2>What Wifikitaa is, and is not</h2>
            <p>
                Wifikitaa is billing software. It gives your hotspot a payment page, creates WiFi
                logins on your router once a customer has paid, prints vouchers, and keeps your
                sales in one dashboard.
            </p>
            <p>We are not your internet provider and we are not part of your network. In particular:</p>
            <ul>
                <li>We do not supply bandwidth, routers, access points or any other equipment.</li>
                <li>We do not operate, monitor or secure your network.</li>
                <li>We do not decide your prices, your packages or your coverage.</li>
                <li>We do not hold a telecommunications licence on your behalf.</li>
            </ul>
            <p>
                Everything about the internet service your customers receive — its speed, its
                uptime, its lawfulness — remains yours.
            </p>
        </div>

        {{-- 3 --}}
        <div class="clause" id="your-account">
            <h2>Your account</h2>
            <p>
                You may open an account if you are at least 18 years old and are allowed to run a
                business in Tanzania. The details you give us — business name, your name, phone
                number, mobile money number — must be true, and you must keep them current. We
                send payment notices and withdrawal confirmations to the number on your account,
                so a stale number means missed money.
            </p>
            <p>
                You are responsible for your password and for everything done with your login.
                Tell us immediately if you think someone else has access to it.
            </p>
            <p>
                One account is for one business. You may run as many routers and hotspot locations
                under it as you like.
            </p>
        </div>

        {{-- 4 --}}
        <div class="clause" id="your-network">
            <h2>Your network and your licences</h2>
            <p>
                Selling internet access to the public in Tanzania is a regulated activity. Holding
                whatever authorisation the Tanzania Communications Regulatory Authority (TCRA) and
                any other authority require for your service is your responsibility, not ours, and
                you confirm you have it.
            </p>
            <p>
                You also confirm that you have the right to resell the bandwidth you are selling.
                If your upstream contract forbids reselling, using Wifikitaa does not change that.
            </p>
            <p>
                If a regulator or your upstream provider tells us your service is unlawful, we may
                suspend your account while it is sorted out.
            </p>
        </div>

        {{-- 5 --}}
        <div class="clause" id="how-money-moves">
            <h2>How money moves</h2>
            <p>
                When a customer pays on your portal, the money is collected through the Wifikitaa
                platform mobile money account, not into your own account directly. Once the payment
                gateway confirms it, the full amount is credited to your <strong>wallet</strong> in
                your dashboard. You then request a withdrawal whenever you want, and we send the
                money to the mobile money number on your account.
            </p>
            <div class="note">
                <p>
                    <strong>Why it works this way.</strong> One platform account means a single
                    integration with the payment gateway, which is what lets an ISP start selling
                    the same day instead of waiting weeks for their own merchant account.
                </p>
                <p>
                    <strong>What it means for you.</strong> Between the customer paying and you
                    withdrawing, your earnings sit as a balance we owe you. We record every shilling
                    of it against your account, and your dashboard shows the running total and every
                    transaction behind it.
                </p>
            </div>
            <p>
                Your wallet balance is a debt we owe you, payable on your withdrawal request under
                clause 7. It does not earn interest.
            </p>
            <p>
                A payment only credits your wallet once the gateway confirms it to us. A payment a
                customer says they made, that the gateway has not confirmed, is not yet yours — and
                a prompt a customer cancelled or let expire never becomes a payment at all.
            </p>
        </div>

        {{-- 6 --}}
        <div class="clause" id="fees">
            <h2>What we charge</h2>
            <p>There is no monthly fee and no setup fee. We are paid only when you withdraw.</p>
            <table class="fees">
                <thead>
                    <tr><th>Event</th><th>Our fee</th></tr>
                </thead>
                <tbody>
                    <tr><td>Opening and running your account</td><td>Free</td></tr>
                    <tr><td>A customer pays on your portal</td><td>No fee</td></tr>
                    <tr><td>A voucher is sold</td><td>No fee</td></tr>
                    <tr><td>Adding a router, package or staff account</td><td>Free</td></tr>
                    <tr><td><strong>Withdrawing from your wallet</strong></td><td>{{ withdrawal_fee_label() }} of the amount</td></tr>
                </tbody>
            </table>
            <p>
                The withdrawal fee is shown to you on the withdrawal screen before you confirm, and
                it is deducted from the amount withdrawn.
            </p>
            <p>
                Your mobile money provider may charge you separately for receiving money. That
                charge is theirs, not ours, and we do not control it.
            </p>
            <p>
                If we change our fees we will tell you first — see clause 18.
            </p>
        </div>

        {{-- 7 --}}
        <div class="clause" id="withdrawals">
            <h2>Withdrawals</h2>
            <p>
                You request a withdrawal from your dashboard. Money goes only to the mobile money
                number registered on your account, and only to a number in your own name or your
                business's name.
            </p>
            <p>
                We aim to process withdrawal requests <strong>within 3 hours</strong>. That is our
                target, not a guarantee: a request may take longer over a weekend or public holiday,
                during a mobile money outage, or while we check something that looks unusual.
            </p>
            <p>We may hold or refuse a withdrawal when:</p>
            <ul>
                <li>The receiving number does not belong to you.</li>
                <li>We reasonably suspect fraud or money laundering, or we are required to check.</li>
                <li>There is an unresolved dispute or chargeback against payments in that balance.</li>
                <li>Your account details are incomplete or cannot be verified.</li>
            </ul>
            <p>
                If we hold a withdrawal we will tell you why and what we need, unless the law
                prevents us from saying.
            </p>
            <p>
                If we send money to a wrong number because the number on your account was wrong, we
                will help you chase it, but we cannot promise to recover it.
            </p>
        </div>

        {{-- 8 --}}
        <div class="clause" id="your-customers">
            <h2>Your customers are your customers</h2>
            <p>
                The person who buys WiFi on your hotspot is buying internet access from you. Our
                software takes the payment and opens the session, but the service sold is yours.
            </p>
            <p>So you are the one who:</p>
            <ul>
                <li>Decides the packages, the prices, the speeds and the time limits.</li>
                <li>Answers your customers when they cannot connect or their session ends early.</li>
                <li>Handles their refund requests and complaints.</li>
                <li>Is accountable to them, and to any regulator, for the service they received.</li>
            </ul>
            <p>
                Your packages and your portal must describe honestly what the customer is getting.
                Selling a speed your line cannot carry, or a time limit your router will not honour,
                is a breach of these terms.
            </p>
        </div>

        {{-- 9 --}}
        <div class="clause" id="refunds">
            <h2>Refunds and disputes</h2>
            <p>
                Refunds to your customers are your decision and come out of your wallet balance.
                Since the money was collected through our platform account, ask us to make the
                refund and we will send it from the balance we hold for you.
            </p>
            <p>
                We will refund a customer directly, without asking you first, in two situations: when
                the payment gateway or a regulator requires it, and when a customer was charged
                twice for the same session by a fault in our software. In both cases we tell you, and
                the amount comes off your wallet balance.
            </p>
            <p>
                If a customer paid and did not get online, your dashboard flags the transaction so
                you can see it and sort the customer out. The payment stays yours; the failure to
                connect is a thing to fix, not a reason to keep a customer unconnected.
            </p>
        </div>

        {{-- 10 --}}
        <div class="clause" id="agents">
            <h2>Agents and staff accounts</h2>
            <p>
                You can create staff accounts — agents — who sell vouchers from a point-of-sale page
                against a float you top up for them from your wallet.
            </p>
            <p>
                Everything an agent does is treated as done by you. You choose who gets an account,
                you decide their float, and you are responsible for their conduct and for any loss
                they cause. Remove an agent's access from your dashboard as soon as they stop
                working for you.
            </p>
        </div>

        {{-- 11 --}}
        <div class="clause" id="vouchers">
            <h2>Vouchers</h2>
            <p>
                Vouchers you generate are prepaid access to your own service. A voucher is valid
                for the package it was printed for, and its clock starts when a customer redeems it,
                not when it is printed.
            </p>
            <p>
                Printed voucher cards are cash once they leave your hands. Keep them secure: we
                cannot tell a stolen voucher from a sold one, and we do not reimburse unredeemed
                vouchers that go missing.
            </p>
            <p>
                If you delete a voucher batch, codes in it stop working, including codes already
                sold. Settling that with the customer is yours to do.
            </p>
        </div>

        {{-- 12 --}}
        <div class="clause" id="availability">
            <h2>Service availability</h2>
            <p>
                We work to keep Wifikitaa running at all times, but we do not promise a particular
                uptime figure. The platform depends on things outside our control, including the
                mobile money gateway, the SMS networks, our hosting provider, and the internet
                connection between your router and us.
            </p>
            <p>
                We may take the platform down for maintenance. For planned work we will give notice
                when we reasonably can and choose a quiet hour.
            </p>
            <p>
                Your router keeps existing customer sessions running on its own while the platform
                is unreachable. What stops during an outage is new sales. Vouchers you have already
                printed are the safeguard worth having for that.
            </p>
            <p>
                We are not responsible for sales you could not make because your own router, power
                or upstream link was down.
            </p>
        </div>

        {{-- 13 --}}
        <div class="clause" id="acceptable-use">
            <h2>Acceptable use</h2>
            <p>You must not use Wifikitaa to:</p>
            <ul>
                <li>Break any Tanzanian law, or any licence condition that applies to you.</li>
                <li>Take payments for a service you cannot or do not intend to supply.</li>
                <li>Launder money, or move money that is not from genuine WiFi sales.</li>
                <li>Send payment prompts to numbers that did not ask for them.</li>
                <li>Attack, overload, reverse engineer or probe the platform, or try to reach another ISP's data.</li>
                <li>Resell or sublicense the platform itself as if it were your own product.</li>
            </ul>
            <p>
                You must also not present Wifikitaa as the seller of the internet service, or imply
                we guarantee it.
            </p>
        </div>

        {{-- 14 --}}
        <div class="clause" id="suspension">
            <h2>Suspension and closing your account</h2>
            <h3>When we suspend</h3>
            <p>
                We may suspend your account if you breach these terms, if we reasonably suspect
                fraud or unlawful use, or if the law or our payment gateway requires it. While
                suspended, your portal stops selling and your customers' active sessions are cut off.
            </p>
            <p>
                We will tell you that we have suspended you and why, and we will lift the suspension
                as soon as the reason is resolved. When a suspension is lifted, customers who still
                had paid time left get the remainder of it back.
            </p>
            <h3>When you leave</h3>
            <p>
                You can stop using Wifikitaa whenever you want. Withdraw your balance before you go.
                Ask us to close your account and we will, once any pending payments have settled and
                your balance has been paid out.
            </p>
            <h3>Money on a closed account</h3>
            <p>
                Closing or suspending an account does not cancel what we owe you. Any balance,
                less our fee and anything you owe us, is paid to your registered number. If we
                cannot reach you, we hold it for <span class="fill">holding period</span> and then
                deal with it as unclaimed money under Tanzanian law.
            </p>
            <h3>Dormant accounts</h3>
            <p>
                An account with no sales and no login for <span class="fill">dormancy period</span>
                may be closed. We will try to reach you on your registered number first, and any
                balance is handled as above.
            </p>
        </div>

        {{-- 15 --}}
        <div class="clause" id="customer-data">
            <h2>Customer data</h2>
            <p>
                To sell a WiFi session the platform records the customer's phone number, the amount
                and time of payment, the device's network address, and which package they bought.
                We keep it to run the service, show you your sales, send receipts, and answer the
                questions a payment dispute raises.
            </p>
            <p>
                That data belongs to your business. We process it to provide the platform to you,
                and we do not sell it or use it to market anything to your customers.
            </p>
            <p>
                You are the one with the direct relationship with those customers, so complying with
                Tanzania's personal data protection law in how you use their details — including
                anything you send them yourself — is your responsibility. Ask us and we will give
                you your data, or delete it, as far as the law and our own record-keeping duties allow.
            </p>
            <p>
                Financial records are kept for the period Tanzanian tax and anti-money-laundering
                law requires, even after an account closes.
            </p>
            <p>
                Our full privacy notice is at <span class="fill">privacy policy URL</span>.
            </p>
        </div>

        {{-- 16 --}}
        <div class="clause" id="support-access">
            <h2>Our access to your account</h2>
            <p>
                Our support staff can open a read-only view of your dashboard to investigate a
                problem you have reported or a payment dispute. Every such session is logged.
            </p>
            <p>
                Support cannot change your password, your withdrawal number or your settings, and
                cannot request a withdrawal. Those actions are yours alone.
            </p>
        </div>

        {{-- 17 --}}
        <div class="clause" id="liability">
            <h2>Limits on our liability</h2>
            <p>
                We are responsible for the money we hold for you, and for running the platform with
                reasonable skill and care. If our mistake costs you money that was genuinely yours,
                we put it right.
            </p>
            <p>Beyond that, and as far as the law allows:</p>
            <ul>
                <li>
                    We are not liable for your lost profit or lost sales, for your customers' claims
                    against you, or for anything caused by your own network, power, equipment or
                    upstream provider.
                </li>
                <li>
                    We are not liable for failures of the mobile money gateway, the SMS networks or
                    the internet itself.
                </li>
                <li>
                    Our total liability to you for anything other than your wallet balance is capped
                    at the fees we charged you in the twelve months before the claim.
                </li>
            </ul>
            <p>
                Nothing here limits liability that cannot lawfully be limited, including for fraud.
            </p>
        </div>

        {{-- 18 --}}
        <div class="clause" id="changes">
            <h2>Changes to these terms</h2>
            <p>
                We may update these terms as the platform changes. The date at the top shows when
                we last did.
            </p>
            <p>
                For a change that affects your money or your obligations — a new fee, a higher fee,
                a change to how withdrawals work — we will tell you at least
                <span class="fill">notice period</span> beforehand, by SMS to your registered
                number and a notice in your dashboard. If you do not accept it, withdraw your
                balance and close your account before it takes effect. Carrying on selling after
                that date means you accept the new terms.
            </p>
            <p>
                Smaller corrections take effect when published.
            </p>
        </div>

        {{-- 19 --}}
        <div class="clause" id="law">
            <h2>Governing law</h2>
            <p>
                These terms are governed by the laws of the United Republic of Tanzania, and the
                courts of Tanzania have jurisdiction over any dispute.
            </p>
            <p>
                If something goes wrong, talk to us first. Most disputes are a misunderstanding
                about a payment, and we can usually settle one in a day by looking at the record
                together.
            </p>
        </div>

        {{-- 20 --}}
        <div class="clause" id="contact">
            <h2>How to reach us</h2>
            <p>
                Email <span class="fill">support email</span><br>
                Phone or WhatsApp <span class="fill">support phone</span><br>
                Post <span class="fill">postal address</span>
            </p>
            <p>
                For anything about money — a withdrawal that has not arrived, a payment you cannot
                find — include your business name and the transaction reference from your dashboard.
                It saves a round of questions.
            </p>
        </div>

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
            <a href="{{ route('terms') }}">Terms</a>
        </div>

        <div class="footer-copy">
            &copy; {{ date('Y') }} Wifikitaa. All rights reserved.
        </div>
    </div>
</footer>

</body>
</html>
