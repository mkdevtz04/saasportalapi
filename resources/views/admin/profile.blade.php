@extends('admin.layout')

@section('title', 'My account')
@section('breadcrumb', 'My account')

@section('content')

<div class="page-header">
    <div>
        <div class="page-title">My account</div>
        <div class="page-sub">Your own name, sign-in email, phone and password.</div>
    </div>
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:20px;align-items:start;">

    {{-- Details --}}
    <div class="card">
        <div class="card-title"><i class="fa-solid fa-id-card"></i> Your details</div>

        <form method="POST" action="{{ route('admin.profile.update') }}">
            @csrf
            @method('PUT')

            <div class="field" style="margin-bottom:14px;">
                <label for="name">Your name</label>
                <input id="name" type="text" name="name" value="{{ old('name', $admin->name) }}" required maxlength="100">
            </div>

            <div class="field" style="margin-bottom:14px;">
                <label for="email">Sign-in email</label>
                <input id="email" type="email" name="email" value="{{ old('email', $admin->email) }}" required maxlength="255">
                <div style="font-size:12px;color:#64748b;margin-top:6px;">
                    This is what you sign in with. Changing it asks for your password below.
                </div>
            </div>

            <div class="field" style="margin-bottom:14px;">
                <label for="phone">Phone</label>
                <input id="phone" type="tel" name="phone" value="{{ old('phone', $admin->phone) }}" maxlength="20"
                       placeholder="0712 345 678">
            </div>

            <div class="field" style="margin-bottom:16px;">
                <label for="current_password_email">Current password</label>
                <x-password-input id="current_password_email" name="current_password" autocomplete="current-password" />
                <div style="font-size:12px;color:#64748b;margin-top:6px;">
                    Only needed if you are changing the email.
                </div>
            </div>

            <button type="submit" class="btn btn-primary">
                <i class="fa-solid fa-floppy-disk"></i> Save details
            </button>
        </form>
    </div>

    {{-- Password --}}
    <div class="card">
        <div class="card-title"><i class="fa-solid fa-key"></i> Change password</div>

        <form method="POST" action="{{ route('admin.profile.password') }}">
            @csrf
            @method('PUT')

            <div class="field" style="margin-bottom:14px;">
                <label for="current_password">Current password</label>
                <x-password-input id="current_password" name="current_password" required autocomplete="current-password" />
            </div>

            <div class="field" style="margin-bottom:14px;">
                <label for="password">New password</label>
                <x-password-input id="password" name="password" required minlength="8" autocomplete="new-password" />
                <div style="font-size:12px;color:#64748b;margin-top:6px;">At least 8 characters.</div>
            </div>

            <div class="field" style="margin-bottom:16px;">
                <label for="password_confirmation">Repeat new password</label>
                <x-password-input id="password_confirmation" name="password_confirmation" required autocomplete="new-password" />
            </div>

            <button type="submit" class="btn btn-primary">
                <i class="fa-solid fa-key"></i> Change password
            </button>

            <p style="font-size:12px;color:#64748b;margin-top:12px;line-height:1.6;">
                Every other device that was signed in will be signed out. This one stays.
            </p>
        </form>
    </div>

</div>

@endsection
