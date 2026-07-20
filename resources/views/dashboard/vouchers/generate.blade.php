@extends('dashboard.layout')

@section('title', 'Generate Vouchers')
@section('breadcrumb', 'Generate Vouchers')

@section('content')

<div class="page-header">
    <div>
        <div class="page-title">Generate Voucher Batch</div>
        <div class="page-sub"><a href="{{ route('dashboard.vouchers.index') }}" style="color:#2563eb;text-decoration:none;">← Back to vouchers</a></div>
    </div>
</div>

<div class="card" style="max-width:560px;">
    @if ($packages->isEmpty())
        <div class="alert alert-error">
            No active packages found. <a href="{{ route('dashboard.packages.create') }}">Create a package first.</a>
        </div>
    @else
        <form method="POST" action="{{ route('dashboard.vouchers.store') }}">
            @csrf

            <div class="field" style="margin-bottom:20px;">
                <label>Package</label>
                <select name="package_id" required>
                    <option value="">Select a package…</option>
                    @foreach ($packages as $pkg)
                        <option value="{{ $pkg->id }}" {{ old('package_id') == $pkg->id ? 'selected' : '' }}>
                            {{ $pkg->name }} — {{ number_format($pkg->price) }} TZS · {{ $pkg->durationLabel() }}
                        </option>
                    @endforeach
                </select>
                @error('package_id') <span class="error">{{ $message }}</span> @enderror
            </div>

            <div class="field" style="margin-bottom:20px;">
                <label>Quantity</label>
                <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:8px;margin-bottom:10px;">
                    @foreach ([10, 25, 50, 100, 200] as $qty)
                        <button type="button" class="btn btn-secondary qty-btn"
                                onclick="setQty({{ $qty }})">{{ $qty }}</button>
                    @endforeach
                </div>
                <input type="number" id="qtyInput" name="quantity"
                       value="{{ old('quantity', 50) }}" min="1" max="500" required>
                <span class="hint">Max 500 per batch. Each code is unique across all your vouchers.</span>
                @error('quantity') <span class="error">{{ $message }}</span> @enderror
            </div>

            <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:14px;margin-bottom:20px;font-size:13px;color:#166534;line-height:1.6;">
                Voucher codes are 10-character unique codes (e.g. <strong>TNAB12XY34</strong>).
                After generating, you can print them as cards for agents to sell.
                Agents sell vouchers directly from their POS wallet.
            </div>

            <button type="submit" class="btn btn-primary">Generate Vouchers</button>
        </form>
    @endif
</div>

@endsection

@push('scripts')
<script>
function setQty(n) {
    document.getElementById('qtyInput').value = n;
    document.querySelectorAll('.qty-btn').forEach(b => b.classList.remove('btn-primary'));
    event.target.classList.add('btn-primary');
    event.target.classList.remove('btn-secondary');
}
</script>
@endpush
