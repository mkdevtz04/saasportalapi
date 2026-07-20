<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TenantWallet;
use App\Models\WithdrawalRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminWithdrawalController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->get('status', 'pending');

        $withdrawals = WithdrawalRequest::with('tenant')
            ->when($status !== 'all', fn($q) => $q->where('status', $status))
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        $pendingCount = WithdrawalRequest::where('status', 'pending')->count();
        $pendingTotal = WithdrawalRequest::where('status', 'pending')->sum('amount');

        return view('admin.withdrawals.index', compact('withdrawals', 'status', 'pendingCount', 'pendingTotal'));
    }

    public function approve(WithdrawalRequest $withdrawal): RedirectResponse
    {
        abort_unless($withdrawal->status === 'pending', 422);
        $withdrawal->update(['status' => 'approved']);

        return back()->with('success',
            'Approved. Send TZS ' . number_format($withdrawal->amount) .
            ' to ' . $withdrawal->mobile_number . ', then click "Mark Paid".'
        );
    }

    public function markPaid(WithdrawalRequest $withdrawal): RedirectResponse
    {
        abort_unless($withdrawal->status === 'approved', 422);
        $withdrawal->update(['status' => 'paid', 'processed_at' => now()]);
        return back()->with('success', 'Withdrawal marked as paid.');
    }

    public function reject(WithdrawalRequest $withdrawal, Request $request): RedirectResponse
    {
        abort_unless($withdrawal->status === 'pending', 422);
        $validated = $request->validate(['reason' => 'required|string|max:500']);

        $wallet = TenantWallet::where('tenant_id', $withdrawal->tenant_id)->first();
        $wallet?->refund($withdrawal->amount);

        $withdrawal->update([
            'status'      => 'rejected',
            'admin_notes' => $validated['reason'],
        ]);

        return back()->with('success',
            'Rejected. TZS ' . number_format($withdrawal->amount) . ' refunded to tenant wallet.'
        );
    }
}
