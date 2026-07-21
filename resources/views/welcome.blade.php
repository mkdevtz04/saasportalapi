<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TrinetPay — Hotspot Billing Made Simple</title>
    <meta name="description" content="The easiest way for small ISPs in Tanzania to manage hotspot billing, voucher sales, and agent networks.">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --indigo: #4f46e5;
            --indigo-dark: #4338ca;
            --purple: #7c3aed;
            --indigo-light: #e0e7ff;
            --dark: #0f172a;
            --dark-2: #1e293b;
            --mid: #334155;
            --muted: #64748b;
            --light: #94a3b8;
            --border: #e2e8f0;
            --bg: #f8fafc;
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

        .nav-logo {
            display: flex; align-items: center; gap: 8px;
        }

        .logo-mark {
            width: 34px; height: 34px;
            background: linear-gradient(135deg, var(--indigo), var(--purple));
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            color: #fff; font-weight: 800; font-size: 16px; letter-spacing: -1px;
        }

        .logo-text {
            font-size: 18px; font-weight: 800; color: var(--dark);
            letter-spacing: -0.5px;
        }

        .logo-text span { color: var(--indigo); }

        .nav-links {
            display: flex; align-items: center; gap: 2px;
        }

        .nav-links a {
            padding: 6px 14px; border-radius: 6px;
            font-size: 14px; font-weight: 500; color: var(--mid);
            transition: all 0.15s;
        }

        .nav-links a:hover { background: var(--bg); color: var(--dark); }

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

        /* ── Hero ───────────────────────────────────────────── */
        .hero {
            background: linear-gradient(160deg, #0f0a1e 0%, #1a1250 40%, #1e1b6e 70%, #2d1b6e 100%);
            min-height: 620px;
            display: flex; align-items: center;
            padding: 80px 5% 60px;
            position: relative;
            overflow: hidden;
        }

        .hero::before {
            content: '';
            position: absolute; inset: 0;
            background-image:
                radial-gradient(ellipse 80% 50% at 50% -20%, rgba(99,102,241,0.3) 0%, transparent 60%),
                radial-gradient(ellipse 60% 40% at 80% 80%, rgba(124,58,237,0.2) 0%, transparent 60%);
        }

        .hero-grid {
            position: absolute; inset: 0; opacity: 0.04;
            background-image:
                linear-gradient(rgba(255,255,255,1) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,1) 1px, transparent 1px);
            background-size: 40px 40px;
        }

        .hero-inner {
            max-width: 1200px; margin: 0 auto; width: 100%;
            display: grid; grid-template-columns: 1fr 1fr; gap: 60px; align-items: center;
            position: relative; z-index: 1;
        }

        .hero-badge {
            display: inline-flex; align-items: center; gap: 6px;
            background: rgba(99,102,241,0.2); border: 1px solid rgba(99,102,241,0.4);
            border-radius: 99px; padding: 4px 12px;
            font-size: 12px; font-weight: 600; color: #a5b4fc;
            margin-bottom: 20px; letter-spacing: 0.3px;
        }

        .hero-badge .dot {
            width: 6px; height: 6px; border-radius: 50%;
            background: #a5b4fc; animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.4; }
        }

        .hero h1 {
            font-size: clamp(32px, 4vw, 52px);
            font-weight: 800; color: #fff; line-height: 1.1;
            letter-spacing: -1.5px; margin-bottom: 20px;
        }

        .hero h1 .highlight {
            background: linear-gradient(90deg, #a5b4fc, #c4b5fd);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .hero-sub {
            font-size: 17px; color: #94a3b8; margin-bottom: 32px;
            max-width: 480px; line-height: 1.7;
        }

        .hero-actions {
            display: flex; gap: 12px; flex-wrap: wrap;
        }

        .btn-hero-primary {
            padding: 13px 28px; border-radius: 10px;
            background: var(--indigo); color: #fff;
            font-size: 15px; font-weight: 700;
            transition: all 0.2s; border: none; cursor: pointer;
            box-shadow: 0 0 0 0 rgba(99,102,241,0.5);
        }
        .btn-hero-primary:hover {
            background: var(--indigo-dark); transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(79,70,229,0.4);
        }

        .btn-hero-ghost {
            padding: 13px 24px; border-radius: 10px;
            background: rgba(255,255,255,0.08); color: #e2e8f0;
            border: 1px solid rgba(255,255,255,0.15);
            font-size: 15px; font-weight: 600;
            transition: all 0.2s;
        }
        .btn-hero-ghost:hover {
            background: rgba(255,255,255,0.14);
            transform: translateY(-1px);
        }

        .hero-note {
            margin-top: 16px; font-size: 12px; color: #475569;
        }

        /* ── Dashboard mockup ───────────────────────────────── */
        .mockup-wrap {
            position: relative;
        }

        .mockup {
            background: #1a1a2e;
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 14px;
            padding: 16px;
            box-shadow: 0 40px 80px rgba(0,0,0,0.5), 0 0 0 1px rgba(255,255,255,0.05);
            transform: perspective(1000px) rotateY(-6deg) rotateX(2deg);
            transition: transform 0.3s;
        }

        .mockup:hover { transform: perspective(1000px) rotateY(-2deg) rotateX(1deg); }

        .mockup-topbar {
            display: flex; align-items: center; gap: 6px; margin-bottom: 12px;
        }
        .mockup-dot { width: 10px; height: 10px; border-radius: 50%; }
        .mockup-dot:nth-child(1) { background: #ff5f57; }
        .mockup-dot:nth-child(2) { background: #febc2e; }
        .mockup-dot:nth-child(3) { background: #28c840; }

        .mockup-body {
            display: grid; grid-template-columns: 140px 1fr; gap: 10px; min-height: 260px;
        }

        .mockup-sidebar {
            background: #111827; border-radius: 8px; padding: 10px;
        }

        .mockup-logo-row {
            padding: 4px 6px 10px; margin-bottom: 8px;
            border-bottom: 1px solid rgba(255,255,255,0.06);
        }
        .mockup-logo-row span { font-size: 11px; font-weight: 700; color: #818cf8; }

        .mock-nav-item {
            height: 24px; border-radius: 4px; margin-bottom: 3px;
            background: rgba(255,255,255,0.04);
        }
        .mock-nav-item.active { background: rgba(99,102,241,0.25); }

        .mockup-content { display: flex; flex-direction: column; gap: 8px; }

        .mock-stat-row {
            display: grid; grid-template-columns: repeat(3, 1fr); gap: 6px;
        }

        .mock-stat {
            background: #111827; border-radius: 6px; padding: 8px 10px;
        }
        .mock-stat-label { height: 6px; width: 60%; background: rgba(255,255,255,0.15); border-radius: 3px; margin-bottom: 6px; }
        .mock-stat-value { height: 14px; width: 70%; background: rgba(255,255,255,0.3); border-radius: 3px; }

        .mock-chart {
            background: #111827; border-radius: 6px; padding: 10px; flex: 1;
            display: flex; align-items: flex-end; gap: 5px; min-height: 80px;
        }

        .mock-bar {
            flex: 1; border-radius: 3px 3px 0 0;
            background: rgba(99,102,241,0.4);
        }
        .mock-bar:nth-child(2) { background: rgba(99,102,241,0.5); }
        .mock-bar:nth-child(4) { background: rgba(99,102,241,0.6); }
        .mock-bar:nth-child(5) { background: rgba(99,102,241,0.7); }
        .mock-bar:nth-child(7) { background: rgba(124,58,237,0.8); }

        .mock-table { background: #111827; border-radius: 6px; overflow: hidden; }
        .mock-table-header { height: 22px; background: rgba(255,255,255,0.04); display: flex; gap: 4px; padding: 0 8px; align-items: center; }
        .mock-th { height: 6px; border-radius: 3px; background: rgba(255,255,255,0.2); }
        .mock-row { display: flex; gap: 4px; padding: 5px 8px; border-top: 1px solid rgba(255,255,255,0.04); align-items: center; }
        .mock-td { height: 6px; border-radius: 3px; background: rgba(255,255,255,0.1); }
        .mock-badge { width: 40px; height: 12px; border-radius: 99px; background: rgba(34,197,94,0.3); }

        /* ── Stats bar ──────────────────────────────────────── */
        .stats-bar {
            background: var(--bg); border-top: 1px solid var(--border); border-bottom: 1px solid var(--border);
            padding: 18px 5%;
        }

        .stats-bar-inner {
            max-width: 1200px; margin: 0 auto;
            display: flex; align-items: center; justify-content: center; gap: 0; flex-wrap: wrap;
        }

        .stat-item {
            padding: 4px 32px; text-align: center;
            border-right: 1px solid var(--border);
        }
        .stat-item:last-child { border-right: none; }

        .stat-num { font-size: 22px; font-weight: 800; color: var(--dark); letter-spacing: -0.5px; }
        .stat-lbl { font-size: 12px; color: var(--muted); font-weight: 500; }

        /* ── Section common ─────────────────────────────────── */
        section { padding: 80px 5%; }

        .section-inner { max-width: 1200px; margin: 0 auto; }

        .section-tag {
            display: inline-block;
            background: var(--indigo-light); color: var(--indigo);
            border-radius: 99px; padding: 4px 14px;
            font-size: 12px; font-weight: 700; letter-spacing: 0.5px; text-transform: uppercase;
            margin-bottom: 12px;
        }

        .section-title {
            font-size: clamp(26px, 3.5vw, 38px);
            font-weight: 800; color: var(--dark); letter-spacing: -1px;
            margin-bottom: 12px;
        }

        .section-sub {
            font-size: 16px; color: var(--muted); max-width: 540px; line-height: 1.7;
        }

        /* ── Features ───────────────────────────────────────── */
        .features { background: #fff; }

        .features-header { text-align: center; margin-bottom: 52px; }
        .features-header .section-sub { margin: 0 auto; }

        .features-grid {
            display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px;
        }

        .feature-card {
            background: var(--bg); border: 1px solid var(--border);
            border-radius: 14px; padding: 28px;
            transition: all 0.2s;
        }

        .feature-card:hover {
            border-color: #c7d2fe; background: #fff;
            transform: translateY(-3px);
            box-shadow: 0 12px 30px rgba(99,102,241,0.08);
        }

        .feature-icon {
            width: 48px; height: 48px; border-radius: 12px;
            background: var(--indigo-light);
            display: flex; align-items: center; justify-content: center;
            font-size: 22px; margin-bottom: 16px;
        }

        .feature-card h3 {
            font-size: 16px; font-weight: 700; color: var(--dark); margin-bottom: 8px;
        }

        .feature-card p { font-size: 14px; color: var(--muted); line-height: 1.7; }

        /* ── How it works ───────────────────────────────────── */
        .how { background: var(--bg); }

        .how-grid {
            display: grid; grid-template-columns: repeat(3, 1fr); gap: 32px;
            margin-top: 52px; position: relative;
        }

        .how-grid::before {
            content: '';
            position: absolute; top: 28px; left: calc(33.33% + 28px); right: calc(33.33% + 28px);
            height: 1px; background: linear-gradient(90deg, var(--indigo), var(--purple));
            opacity: 0.3;
        }

        .step-card { text-align: center; padding: 0 8px; }

        .step-num {
            width: 56px; height: 56px; border-radius: 50%;
            background: linear-gradient(135deg, var(--indigo), var(--purple));
            color: #fff; font-size: 20px; font-weight: 800;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 20px; box-shadow: 0 8px 20px rgba(79,70,229,0.3);
        }

        .step-card h3 { font-size: 17px; font-weight: 700; color: var(--dark); margin-bottom: 10px; }
        .step-card p  { font-size: 14px; color: var(--muted); line-height: 1.7; }

        /* ── Integrations strip ─────────────────────────────── */
        .integrations {
            background: #fff; padding: 40px 5%;
            border-top: 1px solid var(--border); border-bottom: 1px solid var(--border);
        }

        .int-inner {
            max-width: 1000px; margin: 0 auto;
            display: flex; align-items: center; justify-content: center; gap: 12px; flex-wrap: wrap;
        }

        .int-label { font-size: 13px; color: var(--light); font-weight: 500; margin-right: 8px; }

        .int-chip {
            display: flex; align-items: center; gap: 7px;
            padding: 7px 16px; border-radius: 99px;
            background: var(--bg); border: 1px solid var(--border);
            font-size: 13px; font-weight: 600; color: var(--mid);
        }

        .int-dot { width: 8px; height: 8px; border-radius: 50%; }

        /* ── Pricing ────────────────────────────────────────── */
        .pricing { background: var(--dark); }

        .pricing-header { text-align: center; margin-bottom: 48px; }
        .pricing-header .section-tag { background: rgba(99,102,241,0.2); color: #a5b4fc; }
        .pricing-header .section-title { color: #fff; }
        .pricing-header .section-sub { color: #64748b; margin: 0 auto; }

        .pricing-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; max-width: 760px; margin: 0 auto; }

        .price-card {
            background: #1e293b; border: 1px solid #334155;
            border-radius: 16px; padding: 32px;
        }

        .price-card.featured {
            background: linear-gradient(135deg, #312e81, #4c1d95);
            border-color: #6d28d9;
            position: relative;
        }

        .price-badge {
            position: absolute; top: -12px; left: 50%; transform: translateX(-50%);
            background: linear-gradient(90deg, var(--indigo), var(--purple));
            color: #fff; font-size: 11px; font-weight: 700;
            padding: 4px 14px; border-radius: 99px; white-space: nowrap;
        }

        .price-name { font-size: 13px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 10px; }
        .price-card.featured .price-name { color: #a5b4fc; }

        .price-amount { font-size: 36px; font-weight: 800; color: #fff; letter-spacing: -1px; }
        .price-amount span { font-size: 16px; color: #64748b; font-weight: 500; }

        .price-desc { font-size: 13px; color: #64748b; margin: 8px 0 24px; }
        .price-card.featured .price-desc { color: #94a3b8; }

        .price-list { list-style: none; display: flex; flex-direction: column; gap: 10px; margin-bottom: 28px; }
        .price-list li { display: flex; align-items: center; gap: 8px; font-size: 13px; color: #94a3b8; }
        .price-list li::before { content: '✓'; color: #22c55e; font-weight: 700; font-size: 14px; }
        .price-card.featured .price-list li { color: #c4b5fd; }
        .price-card.featured .price-list li::before { color: #86efac; }

        .btn-price-ghost {
            display: block; text-align: center; padding: 11px;
            border-radius: 9px; border: 1px solid #334155; color: #94a3b8;
            font-size: 14px; font-weight: 600; transition: all 0.15s;
        }
        .btn-price-ghost:hover { background: #334155; color: #fff; }

        .btn-price-primary {
            display: block; text-align: center; padding: 11px;
            border-radius: 9px; background: linear-gradient(135deg, var(--indigo), var(--purple));
            color: #fff; font-size: 14px; font-weight: 700; transition: all 0.2s;
            box-shadow: 0 4px 15px rgba(99,102,241,0.4);
        }
        .btn-price-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(99,102,241,0.5); }

        /* ── CTA section ────────────────────────────────────── */
        .cta-section {
            background: linear-gradient(135deg, var(--indigo), var(--purple));
            padding: 80px 5%; text-align: center;
        }

        .cta-section h2 {
            font-size: clamp(26px, 3.5vw, 40px);
            font-weight: 800; color: #fff; letter-spacing: -1px; margin-bottom: 14px;
        }

        .cta-section p { font-size: 16px; color: rgba(255,255,255,0.75); margin-bottom: 32px; }

        .cta-section .btn-hero-primary {
            background: #fff; color: var(--indigo); font-size: 15px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.2);
        }
        .cta-section .btn-hero-primary:hover {
            background: #f0f4ff; box-shadow: 0 12px 35px rgba(0,0,0,0.25); transform: translateY(-2px);
        }

        .cta-note { margin-top: 16px; font-size: 13px; color: rgba(255,255,255,0.5); }

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

        .footer-links {
            display: flex; gap: 24px; flex-wrap: wrap;
        }

        .footer-links a {
            font-size: 13px; color: var(--muted); transition: color 0.15s;
        }
        .footer-links a:hover { color: #e2e8f0; }

        .footer-copy { font-size: 12px; color: #334155; }

        /* ── Responsive ─────────────────────────────────────── */
        @media (max-width: 960px) {
            .hero-inner { grid-template-columns: 1fr; }
            .mockup-wrap { display: none; }
            .features-grid { grid-template-columns: 1fr 1fr; }
            .how-grid { grid-template-columns: 1fr; }
            .how-grid::before { display: none; }
            .pricing-grid { grid-template-columns: 1fr; }
        }

        @media (max-width: 640px) {
            .nav-links { display: none; }
            .features-grid { grid-template-columns: 1fr; }
            .stat-item { padding: 4px 16px; }
        }
    </style>
</head>
<body>

{{-- ── Navbar ──────────────────────────────────────── --}}
<nav class="nav">
    <div class="nav-logo">
        <div class="logo-mark">T</div>
        <div class="logo-text">Trinet<span>Pay</span></div>
    </div>

    <div class="nav-links">
        <a href="#features">Features</a>
        <a href="#how-it-works">How it works</a>
        <a href="#pricing">Pricing</a>
    </div>

    <div class="nav-cta">
        <a href="{{ route('login') }}" class="btn-ghost">Sign in</a>
        <a href="{{ route('register') }}" class="btn-primary">Get Started Free</a>
    </div>
</nav>

{{-- ── Hero ─────────────────────────────────────────── --}}
<section class="hero">
    <div class="hero-grid"></div>
    <div class="hero-inner">
        <div>
            <div class="hero-badge">
                <span class="dot"></span>
                Now live — Built for Tanzanian ISPs
            </div>

            <h1>Hotspot Billing<br><span class="highlight">Made Simple</span></h1>

            <p class="hero-sub">
                TrinetPay helps small ISPs manage hotspot payments, generate vouchers,
                and run an agent sales network — all through one dashboard.
                Works with MikroTik, accepts mobile money.
            </p>

            <div class="hero-actions">
                <a href="{{ route('register') }}" class="btn-hero-primary">
                    Start Free Trial — 14 Days
                </a>
                <a href="{{ route('login') }}" class="btn-hero-ghost">
                    Sign into Dashboard →
                </a>
            </div>

            <p class="hero-note">No credit card required · Cancel anytime</p>
        </div>

        <div class="mockup-wrap">
            <div class="mockup">
                <div class="mockup-topbar">
                    <div class="mockup-dot"></div>
                    <div class="mockup-dot"></div>
                    <div class="mockup-dot"></div>
                </div>
                <div class="mockup-body">
                    <div class="mockup-sidebar">
                        <div class="mockup-logo-row"><span>TrinetPay</span></div>
                        <div class="mock-nav-item active"></div>
                        <div class="mock-nav-item"></div>
                        <div class="mock-nav-item"></div>
                        <div class="mock-nav-item"></div>
                        <div class="mock-nav-item"></div>
                        <div class="mock-nav-item"></div>
                        <div class="mock-nav-item"></div>
                    </div>
                    <div class="mockup-content">
                        <div class="mock-stat-row">
                            <div class="mock-stat">
                                <div class="mock-stat-label"></div>
                                <div class="mock-stat-value"></div>
                            </div>
                            <div class="mock-stat">
                                <div class="mock-stat-label" style="width:50%"></div>
                                <div class="mock-stat-value" style="width:55%"></div>
                            </div>
                            <div class="mock-stat">
                                <div class="mock-stat-label" style="width:70%"></div>
                                <div class="mock-stat-value" style="width:80%"></div>
                            </div>
                        </div>
                        <div class="mock-chart">
                            <div class="mock-bar" style="height:40%"></div>
                            <div class="mock-bar" style="height:60%"></div>
                            <div class="mock-bar" style="height:45%"></div>
                            <div class="mock-bar" style="height:75%"></div>
                            <div class="mock-bar" style="height:55%"></div>
                            <div class="mock-bar" style="height:85%"></div>
                            <div class="mock-bar" style="height:100%"></div>
                        </div>
                        <div class="mock-table">
                            <div class="mock-table-header">
                                <div class="mock-th" style="width:30%"></div>
                                <div class="mock-th" style="width:20%"></div>
                                <div class="mock-th" style="width:20%"></div>
                            </div>
                            <div class="mock-row">
                                <div class="mock-td" style="flex:1"></div>
                                <div class="mock-td" style="width:50px"></div>
                                <div class="mock-badge"></div>
                            </div>
                            <div class="mock-row">
                                <div class="mock-td" style="flex:1;width:70%"></div>
                                <div class="mock-td" style="width:40px"></div>
                                <div class="mock-badge"></div>
                            </div>
                            <div class="mock-row">
                                <div class="mock-td" style="flex:1;width:80%"></div>
                                <div class="mock-td" style="width:55px"></div>
                                <div class="mock-badge"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ── Stats bar ───────────────────────────────────── --}}
<div class="stats-bar">
    <div class="stats-bar-inner">
        <div class="stat-item">
            <div class="stat-num">14 Days</div>
            <div class="stat-lbl">Free Trial</div>
        </div>
        <div class="stat-item">
            <div class="stat-num">MikroTik</div>
            <div class="stat-lbl">RouterOS Direct</div>
        </div>
        <div class="stat-item">
            <div class="stat-num">5 Networks</div>
            <div class="stat-lbl">M-Pesa, Airtel, Tigo, Halotel, PalmPesa</div>
        </div>
        <div class="stat-item">
            <div class="stat-num">Real-time</div>
            <div class="stat-lbl">Payment Tracking</div>
        </div>
    </div>
</div>

{{-- ── Features ────────────────────────────────────── --}}
<section class="features" id="features">
    <div class="section-inner">
        <div class="features-header">
            <div class="section-tag">Features</div>
            <h2 class="section-title">Everything you need to run a hotspot business</h2>
            <p class="section-sub">From customer payments to agent commissions — TrinetPay handles the full billing lifecycle.</p>
        </div>

        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon"><i class="fa-solid fa-wifi"></i></div>
                <h3>MikroTik Integration</h3>
                <p>Connect your RouterOS device directly over the local network. Hotspot users are provisioned automatically the moment a customer pays — no manual setup.</p>
            </div>

            <div class="feature-card">
                <div class="feature-icon"><i class="fa-solid fa-credit-card"></i></div>
                <h3>Mobile Money Payments</h3>
                <p>Accept payments via PalmPesa, M-Pesa, Airtel Money, Tigo Pesa, and Halotel. Customers pay from the captive portal — no apps, no cards needed.</p>
            </div>

            <div class="feature-card">
                <div class="feature-icon"><i class="fa-solid fa-ticket"></i></div>
                <h3>Voucher System</h3>
                <p>Generate bulk voucher batches and print them on card stock. Customers scratch-and-connect. Track used, sold, and available vouchers per batch.</p>
            </div>

            <div class="feature-card">
                <div class="feature-icon"><i class="fa-solid fa-users"></i></div>
                <h3>Agent POS Network</h3>
                <p>Deploy field agents with their own mobile-optimized POS. Fund agent wallets, let them sell vouchers, and collect commissions — all tracked automatically.</p>
            </div>

            <div class="feature-card">
                <div class="feature-icon"><i class="fa-solid fa-chart-column"></i></div>
                <h3>Real-time Dashboard</h3>
                <p>Monitor daily revenue, 7-day chart, router status, transaction history, and wallet balance from your browser. No app install required.</p>
            </div>

            <div class="feature-card">
                <div class="feature-icon"><i class="fa-solid fa-coins"></i></div>
                <h3>Instant Wallet Payouts</h3>
                <p>Your earnings accumulate in your TrinetPay wallet. Request a withdrawal to your mobile money number at any time — no waiting for month-end settlements.</p>
            </div>
        </div>
    </div>
</section>

{{-- ── Integrations strip ──────────────────────────── --}}
<div class="integrations">
    <div class="int-inner">
        <span class="int-label">Works with</span>
        <div class="int-chip"><div class="int-dot" style="background:#f97316"></div> PalmPesa</div>
        <div class="int-chip"><div class="int-dot" style="background:#15803d"></div> M-Pesa</div>
        <div class="int-chip"><div class="int-dot" style="background:#dc2626"></div> Airtel Money</div>
        <div class="int-chip"><div class="int-dot" style="background:#2563eb"></div> Tigo Pesa</div>
        <div class="int-chip"><div class="int-dot" style="background:#7c3aed"></div> Halotel</div>
        <div class="int-chip"><div class="int-dot" style="background:#0ea5e9"></div> MikroTik RouterOS</div>
    </div>
</div>

{{-- ── How it works ─────────────────────────────────── --}}
<section class="how" id="how-it-works">
    <div class="section-inner">
        <div style="text-align:center;">
            <div class="section-tag">How it works</div>
            <h2 class="section-title">Up and running in under 10 minutes</h2>
            <p class="section-sub" style="margin:0 auto;">No technical expertise required. If you can log in to MikroTik, you can set up TrinetPay.</p>
        </div>

        <div class="how-grid">
            <div class="step-card">
                <div class="step-num">1</div>
                <h3>Create Your Account</h3>
                <p>Register your ISP profile, choose your subdomain, and complete the onboarding wizard. Your branded captive portal is live immediately.</p>
            </div>

            <div class="step-card">
                <div class="step-num">2</div>
                <h3>Connect Your Router</h3>
                <p>Enter your MikroTik's LAN IP address and admin credentials. We test the connection and configure your hotspot redirect URL automatically.</p>
            </div>

            <div class="step-card">
                <div class="step-num">3</div>
                <h3>Start Selling Packages</h3>
                <p>Create data packages (1 hour, 1 day, weekly), set prices in TZS, and share your portal URL with customers. Payments arrive instantly.</p>
            </div>
        </div>
    </div>
</section>

{{-- ── Pricing ──────────────────────────────────────── --}}
<section class="pricing" id="pricing">
    <div class="section-inner">
        <div class="pricing-header">
            <div class="section-tag">Pricing</div>
            <h2 class="section-title">Simple, transparent pricing</h2>
            <p class="section-sub">No hidden fees. A small platform fee is automatically deducted from each transaction — you keep the rest.</p>
        </div>

        <div class="pricing-grid">
            <div class="price-card">
                <div class="price-name">Free Trial</div>
                <div class="price-amount">0 <span>TZS / 14 days</span></div>
                <div class="price-desc">Try everything, no commitment required.</div>
                <ul class="price-list">
                    <li>Full dashboard access</li>
                    <li>MikroTik integration</li>
                    <li>Voucher generation & printing</li>
                    <li>Agent POS system</li>
                    <li>Mobile money payments</li>
                    <li>Up to 3 routers</li>
                </ul>
                <a href="{{ route('register') }}" class="btn-price-ghost">Start Free Trial</a>
            </div>

            <div class="price-card featured">
                <div class="price-badge">Most Popular</div>
                <div class="price-name">Growth Plan</div>
                <div class="price-amount">15,000 <span>TZS / month</span></div>
                <div class="price-desc">Plus a 5% platform fee per successful transaction.</div>
                <ul class="price-list">
                    <li>Everything in Trial</li>
                    <li>Unlimited routers</li>
                    <li>Unlimited packages & vouchers</li>
                    <li>Unlimited agents</li>
                    <li>Priority support</li>
                    <li>Wallet payouts anytime</li>
                </ul>
                <a href="{{ route('register') }}" class="btn-price-primary">Get Started →</a>
            </div>
        </div>

        <p style="text-align:center;margin-top:24px;font-size:13px;color:#475569;">
            The 5% platform fee covers payment processing and platform maintenance. No additional gateway fees.
        </p>
    </div>
</section>

{{-- ── CTA ──────────────────────────────────────────── --}}
<section class="cta-section">
    <div class="section-inner">
        <h2>Ready to grow your hotspot business?</h2>
        <p>Join ISPs across Tanzania using TrinetPay to automate billing and maximize revenue.</p>
        <a href="{{ route('register') }}" class="btn-hero-primary">
            Create Your Free Account
        </a>
        <p class="cta-note">14-day free trial · No credit card · Cancel anytime</p>
    </div>
</section>

{{-- ── Footer ───────────────────────────────────────── --}}
<footer class="footer">
    <div class="footer-inner">
        <div class="footer-logo">
            <div class="nav-logo">
                <div class="logo-mark" style="width:28px;height:28px;font-size:13px;">T</div>
                <div class="logo-text" style="font-size:16px;">Trinet<span>Pay</span></div>
            </div>
            <p>Hotspot billing for Tanzanian ISPs.</p>
        </div>

        <div class="footer-links">
            <a href="{{ route('login') }}">Dashboard Login</a>
            <a href="{{ route('register') }}">Sign Up</a>
            <a href="#features">Features</a>
            <a href="#pricing">Pricing</a>
        </div>

        <div class="footer-copy">
            &copy; {{ date('Y') }} TrinetPay. All rights reserved.
        </div>
    </div>
</footer>

</body>
</html>
