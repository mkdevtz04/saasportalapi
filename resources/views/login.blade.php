<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In — TrinetPay</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: linear-gradient(135deg, #0066cc 0%, #004499 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px 16px; }
        .card { background: #fff; border-radius: 16px; box-shadow: 0 20px 60px rgba(0,0,0,0.25); width: 100%; max-width: 420px; padding: 40px 36px; }
        .logo { text-align: center; margin-bottom: 28px; }
        .logo h1 { font-size: 26px; font-weight: 800; color: #0066cc; }
        .logo p { font-size: 14px; color: #666; margin-top: 4px; }
        h2 { font-size: 20px; font-weight: 700; color: #111; margin-bottom: 20px; }
        .field { margin-bottom: 16px; }
        label { display: block; font-size: 13px; font-weight: 600; color: #333; margin-bottom: 6px; }
        input[type=email], input[type=password] { width: 100%; padding: 11px 14px; border: 1.5px solid #ddd; border-radius: 8px; font-size: 15px; color: #111; outline: none; background: #fafafa; }
        input:focus { border-color: #0066cc; background: #fff; }
        .error-msg { font-size: 12px; color: #e53e3e; margin-top: 4px; }
        .remember { display: flex; align-items: center; gap: 8px; font-size: 14px; color: #555; margin-bottom: 4px; }
        .btn { width: 100%; padding: 13px; background: #0066cc; color: #fff; border: none; border-radius: 8px; font-size: 16px; font-weight: 700; cursor: pointer; margin-top: 20px; }
        .btn:hover { background: #0055aa; }
        .link-row { text-align: center; margin-top: 18px; font-size: 14px; color: #666; }
        .link-row a { color: #0066cc; font-weight: 600; text-decoration: none; }
        .alert { background: #fff5f5; border: 1px solid #fca5a5; border-radius: 8px; padding: 12px 14px; margin-bottom: 16px; font-size: 13px; color: #c53030; }
    </style>
</head>
<body>
<div class="card">
    <div class="logo">
        <h1>TrinetPay</h1>
        <p>ISP Management Portal</p>
    </div>

    <h2>Sign in to your account</h2>

    @if ($errors->any())
        <div class="alert">{{ $errors->first() }}</div>
    @endif

    @if (session('success'))
        <div style="background:#f0fff4;border:1px solid #9ae6b4;border-radius:8px;padding:12px 14px;margin-bottom:16px;font-size:13px;color:#276749;">
            {{ session('success') }}
        </div>
    @endif

    <form method="POST" action="{{ route('tenant.login.store') }}">
        @csrf
        <div class="field">
            <label for="email">Email Address</label>
            <input type="email" id="email" name="email" value="{{ old('email') }}" placeholder="you@example.com" autocomplete="email">
        </div>
        <div class="field">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" placeholder="Your password" autocomplete="current-password">
        </div>
        <label class="remember">
            <input type="checkbox" name="remember"> Remember me
        </label>
        <button type="submit" class="btn">Sign In</button>
    </form>

    <div class="link-row">Don't have an account? <a href="{{ route('register') }}">Sign up free</a></div>
</div>
</body>
</html>
