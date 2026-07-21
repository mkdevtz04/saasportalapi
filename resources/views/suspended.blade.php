<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Service Suspended</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #f5f5f5;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }

        .card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.10);
            max-width: 420px;
            width: 100%;
            padding: 48px 36px;
            text-align: center;
        }

        .icon {
            font-size: 56px;
            margin-bottom: 20px;
            display: block;
        }

        h1 {
            font-size: 22px;
            font-weight: 700;
            color: #111;
            margin-bottom: 12px;
        }

        p {
            font-size: 15px;
            color: #555;
            line-height: 1.6;
        }

        .business-name {
            font-weight: 700;
            color: #111;
        }

        .contact {
            margin-top: 28px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            font-size: 13px;
            color: #888;
        }

        .contact a {
            color: #0066cc;
            text-decoration: none;
            font-weight: 600;
        }
    </style>
</head>
<body>
<div class="card">
    <span class="icon"><i class="fa-solid fa-triangle-exclamation"></i></span>
    <h1>Service Temporarily Suspended</h1>
    <p>
        The WiFi service for
        <span class="business-name">{{ $tenant->name }}</span>
        is currently unavailable. Please contact your network provider.
    </p>

    @if ($tenant->settings?->contact_phone)
        <div class="contact">
            Contact support:
            <a href="tel:{{ $tenant->settings->contact_phone }}">
                {{ $tenant->settings->contact_phone }}
            </a>
        </div>
    @endif
</div>
</body>
</html>
