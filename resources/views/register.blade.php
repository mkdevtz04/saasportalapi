<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up — TrinetPay</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #0066cc 0%, #004499 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px 16px;
        }

        .card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.25);
            width: 100%;
            max-width: 480px;
            padding: 40px 36px;
        }

        .logo {
            text-align: center;
            margin-bottom: 28px;
        }

        .logo h1 {
            font-size: 26px;
            font-weight: 800;
            color: #0066cc;
            letter-spacing: -0.5px;
        }

        .logo p {
            font-size: 14px;
            color: #666;
            margin-top: 4px;
        }

        h2 {
            font-size: 20px;
            font-weight: 700;
            color: #111;
            margin-bottom: 6px;
        }

        .subtitle {
            font-size: 14px;
            color: #666;
            margin-bottom: 28px;
        }

        .trial-badge {
            display: inline-block;
            background: #e8f4ff;
            color: #0066cc;
            font-size: 12px;
            font-weight: 600;
            padding: 4px 10px;
            border-radius: 20px;
            margin-bottom: 20px;
        }

        .section-label {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: #999;
            margin-bottom: 12px;
            margin-top: 20px;
        }

        .field {
            margin-bottom: 16px;
        }

        label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #333;
            margin-bottom: 6px;
        }

        input {
            width: 100%;
            padding: 11px 14px;
            border: 1.5px solid #ddd;
            border-radius: 8px;
            font-size: 15px;
            color: #111;
            transition: border-color 0.2s;
            outline: none;
            background: #fafafa;
        }

        input:focus {
            border-color: #0066cc;
            background: #fff;
        }

        input.error {
            border-color: #e53e3e;
        }

        .error-msg {
            font-size: 12px;
            color: #e53e3e;
            margin-top: 4px;
        }

        .subdomain-preview {
            font-size: 12px;
            color: #0066cc;
            margin-top: 5px;
            font-weight: 500;
            min-height: 16px;
        }

        .btn {
            width: 100%;
            padding: 13px;
            background: #0066cc;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            margin-top: 24px;
            transition: background 0.2s, transform 0.1s;
        }

        .btn:hover { background: #0055aa; }
        .btn:active { transform: scale(0.98); }
        .btn:disabled { background: #99c0e8; cursor: not-allowed; }

        .login-link {
            text-align: center;
            margin-top: 20px;
            font-size: 14px;
            color: #666;
        }

        .login-link a {
            color: #0066cc;
            font-weight: 600;
            text-decoration: none;
        }

        .alert-error {
            background: #fff5f5;
            border: 1px solid #fca5a5;
            border-radius: 8px;
            padding: 12px 14px;
            margin-bottom: 20px;
            font-size: 13px;
            color: #c53030;
        }

        .alert-error ul { padding-left: 16px; }
        .alert-error li { margin-top: 4px; }

        .divider {
            border: none;
            border-top: 1px solid #eee;
            margin: 24px 0 0;
        }
    </style>
</head>
<body>
<div class="card">
    <div class="logo">
        <h1>TrinetPay</h1>
        <p>WiFi Hotspot Billing Platform</p>
    </div>

    <h2>Create your ISP account</h2>
    <p class="subtitle">Set up your hotspot billing portal in minutes.</p>
    <span class="trial-badge">14-day free trial — no credit card needed</span>

    @if ($errors->any())
        <div class="alert-error">
            <strong>Please fix the following:</strong>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('register.store') }}" id="reg-form">
        @csrf

        <p class="section-label">Business Info</p>

        <div class="field">
            <label for="business_name">Business Name</label>
            <input
                type="text"
                id="business_name"
                name="business_name"
                value="{{ old('business_name') }}"
                placeholder="e.g. Juma WiFi Solutions"
                autocomplete="organization"
                class="{{ $errors->has('business_name') ? 'error' : '' }}"
            >
            <div class="subdomain-preview" id="subdomain-preview"></div>
            @error('business_name') <div class="error-msg">{{ $message }}</div> @enderror
        </div>

        <p class="section-label">Account Owner</p>

        <div class="field">
            <label for="name">Your Full Name</label>
            <input
                type="text"
                id="name"
                name="name"
                value="{{ old('name') }}"
                placeholder="Juma Omari"
                autocomplete="name"
                class="{{ $errors->has('name') ? 'error' : '' }}"
            >
            @error('name') <div class="error-msg">{{ $message }}</div> @enderror
        </div>

        <div class="field">
            <label for="email">Email Address</label>
            <input
                type="email"
                id="email"
                name="email"
                value="{{ old('email') }}"
                placeholder="juma@example.com"
                autocomplete="email"
                class="{{ $errors->has('email') ? 'error' : '' }}"
            >
            @error('email') <div class="error-msg">{{ $message }}</div> @enderror
        </div>

        <div class="field">
            <label for="phone">Phone Number</label>
            <input
                type="tel"
                id="phone"
                name="phone"
                value="{{ old('phone') }}"
                placeholder="0712 345 678"
                autocomplete="tel"
                class="{{ $errors->has('phone') ? 'error' : '' }}"
            >
            @error('phone') <div class="error-msg">{{ $message }}</div> @enderror
        </div>

        <div class="field">
            <label for="password">Password</label>
            <input
                type="password"
                id="password"
                name="password"
                placeholder="At least 8 characters"
                autocomplete="new-password"
                class="{{ $errors->has('password') ? 'error' : '' }}"
            >
            @error('password') <div class="error-msg">{{ $message }}</div> @enderror
        </div>

        <div class="field">
            <label for="password_confirmation">Confirm Password</label>
            <input
                type="password"
                id="password_confirmation"
                name="password_confirmation"
                placeholder="Repeat your password"
                autocomplete="new-password"
            >
        </div>

        <button type="submit" class="btn" id="submit-btn">Create My Account</button>
    </form>

    <hr class="divider">
    <p class="login-link">Already have an account? <a href="/login">Sign in</a></p>
</div>

<script>
    const businessInput = document.getElementById('business_name');
    const preview = document.getElementById('subdomain-preview');
    const form = document.getElementById('reg-form');
    const submitBtn = document.getElementById('submit-btn');

    function toSlug(str) {
        return str.toLowerCase()
            .replace(/[^a-z0-9\s-]/g, '')
            .trim()
            .replace(/\s+/g, '-')
            .replace(/-+/g, '-')
            .substring(0, 40);
    }

    businessInput.addEventListener('input', () => {
        const slug = toSlug(businessInput.value);
        preview.textContent = slug ? `Your portal: ${slug}.trinetpay.online` : '';
    });

    // Trigger on pre-filled value (validation error redirect)
    if (businessInput.value) {
        businessInput.dispatchEvent(new Event('input'));
    }

    form.addEventListener('submit', () => {
        submitBtn.disabled = true;
        submitBtn.textContent = 'Creating account...';
    });
</script>
</body>
</html>
