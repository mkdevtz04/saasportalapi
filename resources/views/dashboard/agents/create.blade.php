@extends('dashboard.layout')

@section('title', 'Add Agent')
@section('breadcrumb', 'Add Agent')

@section('content')

<div class="page-header">
    <div>
        <div class="page-title">Add Sales Agent</div>
        <div class="page-sub"><a href="{{ route('dashboard.agents.index') }}" style="color:#2563eb;text-decoration:none;">← Back to agents</a></div>
    </div>
</div>

<div class="card" style="max-width:540px;">
    <form method="POST" action="{{ route('dashboard.agents.store') }}">
        @csrf

        <div class="form-grid" style="margin-bottom:20px;">
            <div class="field form-full">
                <label>Full Name</label>
                <input type="text" name="name" value="{{ old('name') }}" placeholder="John Doe" required>
                @error('name') <span class="error">{{ $message }}</span> @enderror
            </div>

            <div class="field">
                <label>Email (login)</label>
                <input type="email" name="email" value="{{ old('email') }}" placeholder="agent@example.com" required>
                @error('email') <span class="error">{{ $message }}</span> @enderror
            </div>

            <div class="field">
                <label>Phone (optional)</label>
                <input type="tel" name="phone" value="{{ old('phone') }}" placeholder="0712 345 678">
                @error('phone') <span class="error">{{ $message }}</span> @enderror
            </div>

            <div class="field">
                <label>Password</label>
                <input type="password" name="password" autocomplete="new-password" required>
                @error('password') <span class="error">{{ $message }}</span> @enderror
            </div>

            <div class="field">
                <label>Confirm Password</label>
                <input type="password" name="password_confirmation" required>
            </div>
        </div>

        <div style="background:#fefce8;border:1px solid #fde68a;border-radius:8px;padding:12px 14px;font-size:13px;color:#92400e;margin-bottom:20px;line-height:1.6;">
            After creating the agent, you'll need to <strong>top up their wallet</strong> before they can sell vouchers.
            Agents log in at <strong>trinetpay.online/pos</strong>.
        </div>

        <div style="display:flex;gap:10px;">
            <button type="submit" class="btn btn-primary">Create Agent</button>
            <a href="{{ route('dashboard.agents.index') }}" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

@endsection
