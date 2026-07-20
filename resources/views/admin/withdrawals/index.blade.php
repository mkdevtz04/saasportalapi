@extends('admin.layout')

@section('title', 'Withdrawals')
@section('breadcrumb', 'Withdrawal Requests')

@section('content')

<div class="page-header">
    <div>
        <div class="page-title">Withdrawals</div>
        <div class="page-sub">ISP payout requests</div>
    </div>
    @if ($pendingCount > 0)
        <div style="background:#fef9c3;border:1px solid #fde047;border-radius:8px;padding:8px 14px;font-size:13px;font-weight:600;color:#a16207;">
            {{ $pendingCount }} pending · TZS {{ number_format($pendingTotal) }}
        </div>
    @endif
</div>

{{-- Status filter --}}
<div class="status-tabs">
    @foreach (['pending' => 'Pending', 'approved' => 'Approved', 'paid' => 'Paid', 'rejected' => 'Rejected', 'all' => 'All'] as $val => $label)
        <a href="{{ route('admin.withdrawals.index', ['status' => $val]) }}"
           class="status-tab {{ $status === $val ? 'active' : '' }}">
            {{ $label }}
            @if ($val === 'pending' && $pendingCount > 0)
                <span style="background:#ef4444;color:#fff;border-radius:99px;padding:1px 6px;font-size:10px;margin-left:4px;">{{ $pendingCount }}</span>
            @endif
        </a>
    @endforeach
</div>

<div class="card" style="padding:0;">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>ISP</th>
                    <th>Amount</th>
                    <th>Mobile</th>
                    <th>Status</th>
                    <th>Requested</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            @forelse ($withdrawals as $wr)
                <tr>
                    <td>
                        <a href="{{ route('admin.tenants.show', $wr->tenant) }}" style="font-weight:600;color:#0f172a;text-decoration:none;">
                            {{ $wr->tenant?->name ?? '—' }}
                        </a>
                    </td>
                    <td><strong>{{ number_format($wr->amount) }}</strong> <span style="color:#94a3b8;font-size:11px;">TZS</span></td>
                    <td style="font-family:monospace;font-size:13px;">{{ $wr->mobile_number }}</td>
                    <td><span class="badge badge-{{ $wr->status }}">{{ ucfirst($wr->status) }}</span></td>
                    <td style="color:#64748b;font-size:12px;">{{ $wr->created_at->format('d M Y, H:i') }}</td>
                    <td>
                        <div style="display:flex;flex-direction:column;gap:4px;align-items:flex-start;">
                            @if ($wr->status === 'pending')
                                {{-- Approve --}}
                                <form method="POST" action="{{ route('admin.withdrawals.approve', $wr) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-success btn-sm">Approve</button>
                                </form>
                                {{-- Reject toggle --}}
                                <button type="button" class="btn btn-danger btn-sm" onclick="toggleReject({{ $wr->id }})">Reject</button>
                                <div class="reject-form" id="reject-{{ $wr->id }}">
                                    <form method="POST" action="{{ route('admin.withdrawals.reject', $wr) }}">
                                        @csrf
                                        <textarea name="reason" rows="2" placeholder="Reason for rejection…" required
                                                  style="width:100%;padding:6px 8px;border:1px solid #fca5a5;border-radius:6px;font-size:12px;margin-bottom:6px;resize:vertical;"></textarea>
                                        <button type="submit" class="btn btn-danger btn-sm">Confirm Reject</button>
                                        <button type="button" class="btn btn-secondary btn-sm" onclick="toggleReject({{ $wr->id }})">Cancel</button>
                                    </form>
                                </div>

                            @elseif ($wr->status === 'approved')
                                <div class="alert alert-info" style="padding:6px 10px;font-size:12px;margin-bottom:4px;">
                                    Send <strong>TZS {{ number_format($wr->amount) }}</strong> to {{ $wr->mobile_number }}
                                </div>
                                <form method="POST" action="{{ route('admin.withdrawals.paid', $wr) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-primary btn-sm">Mark Paid</button>
                                </form>

                            @else
                                <span style="color:#94a3b8;font-size:12px;">
                                    {{ $wr->processed_at ? $wr->processed_at->format('d M Y') : '—' }}
                                </span>
                                @if ($wr->admin_notes)
                                    <span style="color:#94a3b8;font-size:11px;max-width:200px;">{{ $wr->admin_notes }}</span>
                                @endif
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" style="text-align:center;color:#94a3b8;padding:32px 0;">No {{ $status !== 'all' ? $status : '' }} withdrawal requests.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

{{ $withdrawals->links('dashboard.pagination') }}

@endsection

@push('scripts')
<script>
function toggleReject(id) {
    const el = document.getElementById('reject-' + id);
    el.style.display = el.style.display === 'block' ? 'none' : 'block';
}
</script>
@endpush
