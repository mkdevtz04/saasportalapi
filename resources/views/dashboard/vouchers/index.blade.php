@extends('dashboard.layout')

@section('title', 'Vouchers')
@section('breadcrumb', 'Vouchers')

@section('content')

<div class="page-header">
    <div>
        <div class="page-title">Voucher Batches</div>
        <div class="page-sub">Pre-generated codes for agent & cash sales</div>
    </div>
    <a href="{{ route('dashboard.vouchers.generate') }}" class="btn btn-primary">+ Generate Batch</a>
</div>

@if ($batches->isEmpty())
    <div class="card">
        <div class="empty-state">
            <div class="icon">🎫</div>
            <p>No voucher batches yet. Generate a batch to start selling via agents or cash.</p>
            <a href="{{ route('dashboard.vouchers.generate') }}" class="btn btn-primary" style="margin-top:16px;display:inline-flex;">+ Generate First Batch</a>
        </div>
    </div>
@else
    <div class="card">
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Batch</th>
                        <th>Package</th>
                        <th>Generated</th>
                        <th style="text-align:center;">Total</th>
                        <th style="text-align:center;">Sold</th>
                        <th style="text-align:center;">Used</th>
                        <th style="text-align:center;">Available</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($batches as $batch)
                        @php
                            $available = $batch->total - $batch->sold_count - $batch->used_count;
                        @endphp
                        <tr>
                            <td style="font-family:monospace;font-size:12px;color:#475569;">{{ $batch->batch_ref }}</td>
                            <td style="font-weight:600;">
                                {{ $batch->package?->name ?? '—' }}
                                <span style="color:#94a3b8;font-size:12px;font-weight:400;">
                                    · {{ number_format($batch->package?->price ?? 0) }} TZS
                                </span>
                            </td>
                            <td style="color:#64748b;font-size:13px;">
                                {{ \Carbon\Carbon::parse($batch->created_at)->format('d M Y') }}
                            </td>
                            <td style="text-align:center;font-weight:600;">{{ $batch->total }}</td>
                            <td style="text-align:center;">
                                <span class="badge badge-warning">{{ $batch->sold_count }}</span>
                            </td>
                            <td style="text-align:center;">
                                <span class="badge badge-success">{{ $batch->used_count }}</span>
                            </td>
                            <td style="text-align:center;">
                                @if ($available > 0)
                                    <span class="badge badge-muted">{{ $available }}</span>
                                @else
                                    <span style="color:#94a3b8;font-size:12px;">—</span>
                                @endif
                            </td>
                            <td>
                                <div style="display:flex;gap:6px;">
                                    <a href="{{ route('dashboard.vouchers.print', $batch->batch_ref) }}"
                                       class="btn btn-secondary btn-sm" target="_blank">🖨️ Print</a>
                                    @if ($available > 0)
                                        <form method="POST" action="{{ route('dashboard.vouchers.destroy', $batch->batch_ref) }}"
                                              onsubmit="return confirm('Delete {{ $available }} unused vouchers in this batch?')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-danger btn-sm">Delete unused</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div style="margin-top:16px;">
            {{ $batches->links('dashboard.pagination') }}
        </div>
    </div>
@endif

@endsection
