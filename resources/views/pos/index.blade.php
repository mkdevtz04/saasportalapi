@extends('pos.layout')

@section('title', 'POS')

@section('content')

{{-- Packages --}}
<div class="card">
    <div class="card-title">📦 Select Package to Sell</div>

    @if ($packages->isEmpty())
        <p style="font-size:13px;color:#94a3b8;text-align:center;padding:20px 0;">
            No active packages. Ask the owner to create packages first.
        </p>
    @else
        <div class="package-grid">
            @foreach ($packages as $pkg)
                @php $avail = $pkg->available ?? 0; @endphp
                <div class="pkg-card {{ $avail === 0 ? 'disabled' : '' }}"
                     onclick="{{ $avail > 0 ? "openSell({$pkg->id}, '{$pkg->name}', {$pkg->price})" : '' }}">
                    <div class="pkg-tzs">TZS</div>
                    <div class="pkg-price">{{ number_format($pkg->price) }}</div>
                    <div class="pkg-name">{{ $pkg->name }}</div>
                    <div class="pkg-dur">{{ $pkg->durationLabel() }}</div>
                    <div class="pkg-speed">{{ $pkg->speedLabel() }}</div>
                    <div class="pkg-avail {{ $avail > 0 ? 'ok' : 'none' }}">
                        {{ $avail > 0 ? "{$avail} available" : 'Out of stock' }}
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>

{{-- Recent sales --}}
@if ($recentSales->isNotEmpty())
    <div class="card">
        <div class="card-title">🕐 Recent Sales</div>
        <table>
            <tbody>
                @foreach ($recentSales as $v)
                    <tr>
                        <td>
                            <strong style="font-family:monospace;">{{ $v->code }}</strong><br>
                            <span style="color:#94a3b8;font-size:11px;">{{ $v->package?->name }}</span>
                        </td>
                        <td style="text-align:right;">
                            <span style="font-weight:600;">{{ number_format($v->package?->price ?? 0) }} TZS</span><br>
                            <span style="color:#94a3b8;font-size:11px;">{{ $v->sold_at->diffForHumans() }}</span>
                        </td>
                        <td style="text-align:right;">
                            @if ($v->isUsed())
                                <span class="badge" style="background:#dcfce7;color:#15803d;">Used</span>
                            @else
                                <span class="badge badge-muted">Pending</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

{{-- Sell modal --}}
<div class="modal-overlay" id="sellModal">
    <div class="modal">
        <div id="sellForm">
            <h2 id="modalTitle">Sell Voucher</h2>
            <p id="modalSub">Confirm sale details below.</p>

            <div class="modal-field">
                <label>Customer Phone (optional)</label>
                <input type="tel" id="customerPhone" placeholder="07xx xxx xxx" autocomplete="tel">
            </div>

            <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:12px;margin-bottom:14px;font-size:13px;">
                <div style="display:flex;justify-content:space-between;margin-bottom:4px;">
                    <span style="color:#64748b;">Package</span>
                    <strong id="modalPkgName">—</strong>
                </div>
                <div style="display:flex;justify-content:space-between;">
                    <span style="color:#64748b;">Amount</span>
                    <strong id="modalAmount" style="color:#15803d;">—</strong>
                </div>
            </div>

            <div class="modal-actions">
                <button class="btn btn-secondary" onclick="closeSell()">Cancel</button>
                <button class="btn btn-primary" id="confirmBtn" onclick="confirmSell()">Confirm Sale</button>
            </div>
        </div>

        {{-- Success state --}}
        <div class="success-screen" id="successScreen">
            <div style="font-size:40px;margin-bottom:8px;">✅</div>
            <h2 style="margin-bottom:4px;">Sale Complete!</h2>
            <p>Give this code to the customer:</p>
            <div class="success-code" id="successCode"></div>
            <p style="font-size:12px;color:#64748b;" id="successPkg"></p>
            <div style="margin-top:16px;">
                <button class="btn btn-primary" onclick="closeSell()">Done</button>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
let selectedPackageId = null;
let selectedPrice = 0;

function openSell(packageId, name, price) {
    selectedPackageId = packageId;
    selectedPrice = price;
    document.getElementById('modalTitle').textContent = 'Sell ' + name;
    document.getElementById('modalPkgName').textContent = name;
    document.getElementById('modalAmount').textContent = price.toLocaleString() + ' TZS';
    document.getElementById('customerPhone').value = '';
    document.getElementById('sellForm').style.display = '';
    document.getElementById('successScreen').style.display = 'none';
    document.getElementById('sellModal').classList.add('show');
}

function closeSell() {
    document.getElementById('sellModal').classList.remove('show');
    selectedPackageId = null;
}

function confirmSell() {
    const btn   = document.getElementById('confirmBtn');
    const phone = document.getElementById('customerPhone').value;

    btn.disabled = true;
    btn.textContent = 'Processing…';

    fetch('{{ route('pos.sell') }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json',
        },
        body: JSON.stringify({ package_id: selectedPackageId, customer_phone: phone })
    })
    .then(r => r.json())
    .then(data => {
        if (data.ok) {
            // Show success screen
            document.getElementById('sellForm').style.display = 'none';
            document.getElementById('successCode').textContent = data.code;
            document.getElementById('successPkg').textContent = data.package + ' · ' + data.duration;
            document.getElementById('successScreen').style.display = '';

            // Update wallet badge
            document.getElementById('walletBadge').textContent =
                data.wallet_balance.toLocaleString() + ' TZS';
        } else {
            alert('❌ ' + data.message);
        }
    })
    .catch(() => alert('Network error. Please try again.'))
    .finally(() => {
        btn.disabled = false;
        btn.textContent = 'Confirm Sale';
    });
}

// Close modal on overlay click
document.getElementById('sellModal').addEventListener('click', function (e) {
    if (e.target === this) closeSell();
});
</script>
@endpush
