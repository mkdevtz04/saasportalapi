<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Withdrawal Setup — TrinetPay</title>
    @include('onboarding._styles')
</head>
<body>
<div class="wizard-wrap">
    @include('onboarding._steps', ['current' => 3])

    <div class="card">
        <div class="card-header">
            <div class="step-icon">💳</div>
            <div>
                <h2>Withdrawal Number</h2>
                <p class="sub">Enter the mobile money number where you want to receive your earnings.</p>
            </div>
        </div>

        @if ($errors->any())
            <div class="alert-error">
                @foreach ($errors->all() as $e) <div>{{ $e }}</div> @endforeach
            </div>
        @endif

        <div class="info-box" style="margin-bottom:24px;">
            <strong>How payments work on TrinetPay:</strong><br>
            All your customers' payments are collected through the TrinetPay platform account.
            Your earnings are tracked in your dashboard wallet and you can request a withdrawal
            to your mobile money number at any time. Withdrawals are processed within 24 hours.
        </div>

        <form method="POST" action="{{ route('onboarding.payment.store') }}">
            @csrf

            <div class="field">
                <label>Your Mobile Money Number <span class="hint-inline">(M-Pesa, Airtel, Tigo, Halotel)</span></label>
                <input
                    type="tel"
                    name="withdrawal_number"
                    value="{{ old('withdrawal_number', $settings?->withdrawal_number) }}"
                    placeholder="0712 345 678"
                    required
                    autocomplete="tel"
                    style="font-size:18px;letter-spacing:1px;"
                >
                <span class="hint">This is where TrinetPay will send your earnings when you request a withdrawal.</span>
            </div>

            <div class="field" style="margin-top:16px;">
                <label style="display:flex;align-items:flex-start;gap:10px;cursor:pointer;font-weight:400;">
                    <input type="checkbox" name="agree_terms" required style="margin-top:3px;flex-shrink:0;">
                    <span style="font-size:14px;color:#555;line-height:1.5;">
                        I understand that customer payments are collected by TrinetPay and credited to my dashboard wallet.
                        I can request withdrawals at any time from my dashboard.
                    </span>
                </label>
            </div>

            <button type="submit" class="btn-primary" style="margin-top:24px;">
                Finish Setup — Launch My Portal 🚀
            </button>
        </form>
    </div>
</div>
</body>
</html>
