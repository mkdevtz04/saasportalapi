<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vouchers — {{ $batchRef }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #f1f5f9;
            color: #1e293b;
            padding: 20px;
        }

        .controls {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
            background: #fff;
            padding: 14px 20px;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
        }

        .controls h1 { font-size: 16px; flex: 1; }
        .controls span { font-size: 13px; color: #64748b; }

        .btn {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 8px 16px; border-radius: 7px; font-size: 13px;
            font-weight: 600; cursor: pointer; border: none;
            text-decoration: none; transition: all 0.15s;
        }
        .btn-primary { background: #2563eb; color: #fff; }
        .btn-primary:hover { background: #1d4ed8; }
        .btn-secondary { background: #f1f5f9; color: #334155; border: 1px solid #e2e8f0; }

        /* Voucher grid */
        .voucher-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 12px;
        }

        .voucher-card {
            background: #fff;
            border: 1.5px solid #e2e8f0;
            border-radius: 10px;
            padding: 16px;
            text-align: center;
            position: relative;
        }

        .voucher-card.used {
            opacity: 0.45;
        }

        .vc-brand {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #64748b;
            margin-bottom: 4px;
        }

        .vc-package {
            font-size: 13px;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 2px;
        }

        .vc-price {
            font-size: 11px;
            color: #94a3b8;
            margin-bottom: 10px;
        }

        .vc-code {
            font-family: 'Courier New', monospace;
            font-size: 18px;
            font-weight: 800;
            letter-spacing: 2px;
            color: #0f172a;
            background: #f8fafc;
            border: 1px dashed #cbd5e1;
            border-radius: 6px;
            padding: 8px 6px;
            margin-bottom: 8px;
        }

        .vc-duration {
            font-size: 11px;
            color: #475569;
        }

        .vc-used-badge {
            position: absolute;
            top: 8px;
            right: 8px;
            background: #dcfce7;
            color: #15803d;
            font-size: 10px;
            font-weight: 700;
            padding: 2px 6px;
            border-radius: 99px;
        }

        /* Print styles */
        @media print {
            body { background: #fff; padding: 8px; }
            .controls { display: none; }
            .voucher-grid { grid-template-columns: repeat(4, 1fr); gap: 8px; }
            .voucher-card { border-color: #000; page-break-inside: avoid; }
            .voucher-card.used { display: none; }
        }
    </style>
</head>
<body>

<div class="controls">
    <h1>
        {{ $package?->name }} Vouchers
        <span style="font-weight:400;color:#64748b;font-size:13px;">
            · {{ $vouchers->count() }} total
            · {{ $vouchers->whereNull('used_at')->count() }} unused
        </span>
    </h1>
    <span>Batch: {{ $batchRef }}</span>
    <label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer;">
        <input type="checkbox" id="hideUsed" onchange="toggleUsed(this.checked)"> Hide used
    </label>
    <button onclick="window.print()" class="btn btn-primary"><i class="fa-solid fa-print"></i> Print</button>
    <a href="{{ route('dashboard.vouchers.index') }}" class="btn btn-secondary">← Back</a>
</div>

<div class="voucher-grid" id="grid">
    @foreach ($vouchers as $v)
        <div class="voucher-card {{ $v->isUsed() ? 'used' : '' }}" data-used="{{ $v->isUsed() ? '1' : '0' }}">
            @if ($v->isUsed())
                <span class="vc-used-badge"><i class="fa-solid fa-check"></i> Used</span>
            @endif
            <div class="vc-brand">{{ $tenant->name }}</div>
            <div class="vc-package">{{ $package?->name }}</div>
            <div class="vc-price">{{ number_format($package?->price ?? 0) }} TZS</div>
            <div class="vc-code">{{ $v->code }}</div>
            <div class="vc-duration">{{ $package?->durationLabel() }} · {{ $package?->speedLabel() }}</div>
        </div>
    @endforeach
</div>

<script>
function toggleUsed(hide) {
    document.querySelectorAll('[data-used="1"]').forEach(el => {
        el.style.display = hide ? 'none' : '';
    });
}
</script>
</body>
</html>
