<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login — TrinetPay</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #0f0a1e;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .card {
            background: #1a1038;
            border: 1px solid #312e6a;
            border-radius: 14px;
            padding: 36px 32px;
            width: 360px;
        }
        .logo { text-align: center; margin-bottom: 24px; }
        .logo-name { font-size: 22px; font-weight: 800; color: #a5b4fc; letter-spacing: -0.5px; }
        .logo-sub  { font-size: 12px; color: #6b6b9a; margin-top: 2px; }
        h1 { font-size: 18px; font-weight: 700; color: #e2e8f0; text-align: center; margin-bottom: 20px; }
        .field { margin-bottom: 14px; }
        .field label { display: block; font-size: 12px; font-weight: 600; color: #94a3b8; margin-bottom: 5px; text-transform: uppercase; letter-spacing: 0.5px; }
        .field input {
            width: 100%;
            padding: 10px 12px;
            background: #0f0a1e;
            border: 1px solid #312e6a;
            border-radius: 8px;
            color: #e2e8f0;
            font-size: 14px;
        }
        .field input:focus { outline: none; border-color: #7c3aed; box-shadow: 0 0 0 3px #7c3aed33; }
        .btn {
            width: 100%;
            padding: 11px;
            background: #7c3aed;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            margin-top: 6px;
        }
        .btn:hover { background: #6d28d9; }
        .error { background: #450a0a; border: 1px solid #7f1d1d; border-radius: 8px; padding: 10px 12px; color: #fca5a5; font-size: 13px; margin-bottom: 14px; }
    </style>
</head>
<body>
<div class="card">
    <div class="logo">
        <div class="logo-name">TrinetPay</div>
        <div class="logo-sub">Platform Administration</div>
    </div>
    <h1>Admin Sign In</h1>

    @if ($errors->any())
        <div class="error">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('admin.login.store') }}">
        @csrf
        <div class="field">
            <label>Email</label>
            <input type="email" name="email" value="{{ old('email') }}" required autofocus>
        </div>
        <div class="field">
            <label>Password</label>
            <input type="password" name="password" required>
        </div>
        <button type="submit" class="btn">Sign in to Admin</button>
    </form>
</div>
</body>
</html>
