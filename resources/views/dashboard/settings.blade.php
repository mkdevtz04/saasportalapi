@extends('dashboard.layout')

@section('title', 'Settings')
@section('breadcrumb', 'Settings')

@section('content')

<div class="page-header">
    <div>
        <div class="page-title">Portal Settings</div>
        <div class="page-sub">Customize your customer portal appearance and payout details</div>
    </div>
         <a href="//{{ $tenant->subdomain }}.trinetpay.online" target="_blank" class="btn btn-secondary">
         <i class="fa-solid fa-globe"></i> Preview Portal <i class="fa-solid fa-arrow-up-right-from-square"></i>
     </a>
</div>

<form method="POST" action="{{ route('dashboard.settings.update') }}" enctype="multipart/form-data">
    @csrf

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">

        {{-- Branding --}}
        <div class="card">
            <div class="card-title"><i class="fa-solid fa-palette"></i> Branding</div>

            <div class="field" style="margin-bottom:16px;">
                <label>Brand Color</label>
                <div style="display:flex;align-items:center;gap:10px;">
                    <input type="color" name="brand_color"
                           value="{{ old('brand_color', $settings?->brand_color ?? '#0066cc') }}"
                           style="width:48px;height:38px;padding:2px;border-radius:6px;border:1px solid #d1d5db;cursor:pointer;">
                    <input type="text" id="colorHex"
                           value="{{ old('brand_color', $settings?->brand_color ?? '#0066cc') }}"
                           maxlength="7" style="width:100px;font-family:monospace;"
                           oninput="syncColor(this.value)">
                </div>
                <span class="hint">Used for buttons and accents on the customer portal</span>
                @error('brand_color') <span class="error">{{ $message }}</span> @enderror
            </div>

            <div class="field" style="margin-bottom:16px;">
                <label>Portal Tagline</label>
                <input type="text" name="tagline"
                       value="{{ old('tagline', $settings?->tagline) }}"
                       placeholder="Fast. Reliable. Affordable.">
                <span class="hint">Shown below your business name on the portal</span>
                @error('tagline') <span class="error">{{ $message }}</span> @enderror
            </div>

            <div class="field" style="margin-bottom:16px;">
                <label>Contact Phone</label>
                <input type="tel" name="contact_phone"
                       value="{{ old('contact_phone', $settings?->contact_phone) }}"
                       placeholder="0712 345 678">
                <span class="hint">Displayed on the portal for customer support</span>
                @error('contact_phone') <span class="error">{{ $message }}</span> @enderror
            </div>

            <div class="field">
                <label>Logo</label>
                @if ($settings?->custom_logo_path)
                    <img src="{{ asset('storage/' . $settings->custom_logo_path) }}"
                         alt="Current logo" style="width:80px;height:80px;object-fit:contain;border:1px solid #e2e8f0;border-radius:8px;padding:4px;margin-bottom:8px;">
                @endif
                <input type="file" name="logo" accept="image/png,image/jpeg,image/svg+xml">
                <span class="hint">PNG, JPG or SVG, max 2MB. Recommended: square format.</span>
                @error('logo') <span class="error">{{ $message }}</span> @enderror
            </div>
        </div>

        {{-- Payout --}}
        <div>
            <div class="card">
                <div class="card-title"><i class="fa-solid fa-coins"></i> Payout Details</div>

                <div class="field">
                    <label>Withdrawal Mobile Number</label>
                    <input type="tel" name="withdrawal_number"
                           value="{{ old('withdrawal_number', $settings?->withdrawal_number) }}"
                           placeholder="0712 345 678">
                    <span class="hint">Mobile money number for receiving payouts (M-Pesa, Airtel, Tigo, Halotel)</span>
                    @error('withdrawal_number') <span class="error">{{ $message }}</span> @enderror
                </div>
            </div>

            {{-- Portal info --}}
            <div class="card" style="margin-top:0;">
                <div class="card-title"><i class="fa-solid fa-globe"></i> Your Portal</div>
                <div style="font-size:13px;color:#475569;line-height:1.8;">
                    <div><strong>Portal URL:</strong>
                        <a href="//{{ $tenant->subdomain }}.trinetpay.online" target="_blank"
                           style="color:#2563eb;">{{ $tenant->subdomain }}.trinetpay.online</a>
                    </div>
                    <div><strong>Status:</strong>
                        <span class="badge badge-{{ $tenant->status === 'active' ? 'success' : 'warning' }}">
                            {{ ucfirst($tenant->status) }}
                        </span>
                    </div>
                    <div><strong>Subdomain:</strong> {{ $tenant->subdomain }}</div>
                </div>
            </div>

            {{-- MikroTik redirect --}}
            <div class="card" style="margin-top:0;background:#f8fafc;">
                <div class="card-title"><i class="fa-solid fa-wifi"></i> MikroTik Redirect URL</div>
                <p style="font-size:13px;color:#64748b;margin-bottom:10px;">
                    Set this as the redirect URL in your hotspot server profile:
                </p>
                <code style="display:block;background:#1e293b;color:#e2e8f0;padding:10px 14px;border-radius:6px;font-size:12px;word-break:break-all;">
                    http://{{ $tenant->subdomain }}.trinetpay.online/portal
                </code>
            </div>
        </div>
    </div>

    <div style="margin-top:4px;">
        <button type="submit" class="btn btn-primary">Save Settings</button>
    </div>
</form>

@endsection

@push('scripts')
<script>
const colorInput = document.querySelector('[name=brand_color]');
const hexInput   = document.getElementById('colorHex');

colorInput.addEventListener('input', () => hexInput.value = colorInput.value);

function syncColor(val) {
    if (/^#[0-9A-Fa-f]{6}$/.test(val)) colorInput.value = val;
}
</script>
@endpush
