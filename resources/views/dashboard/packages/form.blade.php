@extends('dashboard.layout')

@section('title', $package ? 'Edit Package' : 'New Package')
@section('breadcrumb', $package ? 'Edit Package' : 'New Package')

@section('content')

<div class="page-header">
    <div>
        <div class="page-title">{{ $package ? 'Edit Package' : 'Create Package' }}</div>
        <div class="page-sub">
            <a href="{{ route('dashboard.packages.index') }}" style="color:#2563eb;text-decoration:none;">← Back to packages</a>
        </div>
    </div>
</div>

<div class="card" style="max-width:720px;">
    <form method="POST" action="{{ $package ? route('dashboard.packages.update', $package) : route('dashboard.packages.store') }}">
        @csrf
        @if ($package) @method('PUT') @endif

        <div class="form-grid" style="margin-bottom:20px;">

            <div class="field form-full">
                <label>Package Name</label>
                <input type="text" name="name" value="{{ old('name', $package?->name) }}"
                       placeholder="1 Hour Browsing" required>
                @error('name') <span class="error">{{ $message }}</span> @enderror
            </div>

            <div class="field">
                <label>Price (TZS)</label>
                <input type="number" name="price" value="{{ old('price', $package?->price) }}"
                       min="100" step="100" placeholder="500" required>
                @error('price') <span class="error">{{ $message }}</span> @enderror
            </div>

            <div class="field">
                <label>Duration (hours)</label>
                <input type="number" name="duration_hours" value="{{ old('duration_hours', $package?->duration_hours) }}"
                       min="1" placeholder="24" required>
                <span class="hint">Examples: 1, 6, 24 (1 day), 168 (7 days)</span>
                @error('duration_hours') <span class="error">{{ $message }}</span> @enderror
            </div>

            <div class="field">
                <label>Download Speed (Mbps)</label>
                <input type="number" name="speed_down_mbps" value="{{ old('speed_down_mbps', $package?->speed_down_mbps) }}"
                       min="1" max="1000" placeholder="5" required>
                @error('speed_down_mbps') <span class="error">{{ $message }}</span> @enderror
            </div>

            <div class="field">
                <label>Upload Speed (Mbps)</label>
                <input type="number" name="speed_up_mbps" value="{{ old('speed_up_mbps', $package?->speed_up_mbps) }}"
                       min="1" max="1000" placeholder="2" required>
                @error('speed_up_mbps') <span class="error">{{ $message }}</span> @enderror
            </div>

            <div class="field">
                <label>MikroTik Profile Name</label>
                <input type="text" name="mikrotik_profile" value="{{ old('mikrotik_profile', $package?->mikrotik_profile) }}"
                       placeholder="hotspot-5mbps" required>
                <span class="hint">Must match a profile in your router's /ip hotspot user profile</span>
                @error('mikrotik_profile') <span class="error">{{ $message }}</span> @enderror
            </div>

            <div class="field">
                <label>Data Cap (MB) — optional</label>
                <input type="number" name="data_cap_mb" value="{{ old('data_cap_mb', $package?->data_cap_mb) }}"
                       min="1" placeholder="Leave blank for unlimited">
                <span class="hint">Leave blank for unlimited data</span>
                @error('data_cap_mb') <span class="error">{{ $message }}</span> @enderror
            </div>

            <div class="field">
                <label>Validity Type</label>
                <select name="validity_type">
                    <option value="strict"      {{ old('validity_type', $package?->validity_type) === 'strict'      ? 'selected' : '' }}>
                        Strict — starts immediately after payment
                    </option>
                    <option value="first_login" {{ old('validity_type', $package?->validity_type) === 'first_login' ? 'selected' : '' }}>
                        First Login — starts when device first connects
                    </option>
                </select>
                @error('validity_type') <span class="error">{{ $message }}</span> @enderror
            </div>

        </div>

        <div style="display:flex;gap:10px;">
            <button type="submit" class="btn btn-primary">
                {{ $package ? 'Save Changes' : 'Create Package' }}
            </button>
            <a href="{{ route('dashboard.packages.index') }}" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

@endsection
