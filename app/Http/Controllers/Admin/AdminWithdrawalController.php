<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlatformBillingLog;
use App\Models\TenantWallet;
use App\Models\WithdrawalRequest;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdminWithdrawalController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->get('status', 'pending');

        $withdrawals = WithdrawalRequest::with('tenant.settings')
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        $pendingCount = WithdrawalRequest::where('status', 'pending')->count();
        $pendingTotal = WithdrawalRequest::where('status', 'pending')->sum('net_amount');

        return view('admin.withdrawals.index', compact('withdrawals', 'status', 'pendingCount', 'pendingTotal'));
    }

    public function approve(WithdrawalRequest $withdrawal): RedirectResponse
    {
        $locked = $this->moveFrom($withdrawal, 'pending', fn (WithdrawalRequest $w) => $w->update(['status' => 'approved']));

        Audit::record('withdrawal.approved', $locked->tenant_id, ['net' => $locked->net_amount], $locked);

        return back()->with('success',
            'Approved. Send TZS ' . number_format($locked->net_amount ?? $locked->amount) .
            ' to ' . $locked->mobile_number . ', then click "Mark Paid".'
        );
    }

    public function markPaid(WithdrawalRequest $withdrawal): RedirectResponse
    {
        $paid = $this->moveFrom($withdrawal, 'approved', function (WithdrawalRequest $w) {
            $w->update(['status' => 'paid', 'processed_at' => now()]);

            // The platform fee is earned at the moment the payout is made.
            if ($w->fee_amount > 0) {
                PlatformBillingLog::create([
                    'tenant_id' => $w->tenant_id,
                    'type'      => 'revenue_share',
                    'amount'    => $w->fee_amount,
                    'reference' => 'WDR-' . $w->id,
                    'notes'     => 'Fee on withdrawal of TZS ' . number_format($w->amount),
                ]);
            }
        });

        Audit::record('withdrawal.paid', $paid->tenant_id, ['net' => $paid->net_amount, 'fee' => $paid->fee_amount], $paid);

        return back()->with('success', 'Withdrawal marked as paid.');
    }

    public function reject(WithdrawalRequest $withdrawal, Request $request): RedirectResponse
    {
        $validated = $request->validate(['reason' => 'required|string|max:500']);

        $locked = $this->moveFrom($withdrawal, 'pending', function (WithdrawalRequest $w) use ($validated) {
            // The whole requested amount goes back, no fee is charged on a rejected withdrawal.
            TenantWallet::where('tenant_id', $w->tenant_id)->first()?->refund($w->amount, 'WDR-' . $w->id, ['reason' => $validated['reason']]);

            $w->update([
                'status'      => 'rejected',
                'admin_notes' => $validated['reason'],
            ]);
        });

        Audit::record('withdrawal.rejected', $locked->tenant_id, ['amount' => $locked->amount, 'reason' => $validated['reason']], $locked);

        return back()->with('success',
            'Rejected. TZS ' . number_format($locked->amount) . ' refunded to tenant wallet.'
        );
    }

    /**
     * Run a status change under a row lock. The status is re-read inside the lock,
     * so clicking twice or two admins acting together cannot approve, pay or refund
     * the same request twice.
     */
    private function moveFrom(WithdrawalRequest $withdrawal, string $expected, callable $apply): WithdrawalRequest
    {
        return DB::transaction(function () use ($withdrawal, $expected, $apply) {
            $locked = WithdrawalRequest::whereKey($withdrawal->id)->lockForUpdate()->firstOrFail();

            abort_unless($locked->status === $expected, 422, 'This withdrawal was already processed.');

            $apply($locked);

            return $locked;
        });
    }
}
