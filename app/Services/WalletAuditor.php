<?php

namespace App\Services;

use App\Models\PlatformBillingLog;
use App\Models\TenantWallet;
use App\Models\Transaction;
use App\Models\WalletEntry;
use App\Models\WithdrawalRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Checks that the money in tenant wallets is real, and explains where the platform money is.
 *
 * Two facts must always hold:
 *   1. A tenant never holds more than gateway-confirmed portal payments minus what they
 *      have withdrawn or requested. Anything above that is unbacked.
 *   2. A wallet balance equals the sum of its ledger. Any gap means money moved without
 *      being recorded, or a balance was edited by hand.
 *
 * Used by the wallet:audit command and by the admin reconciliation page.
 */
class WalletAuditor
{
    /**
     * One row per tenant wallet.
     *
     * @return Collection<int,array<string,mixed>>
     */
    public function tenants(): Collection
    {
        return TenantWallet::withoutGlobalScopes()->with('tenant')->orderBy('tenant_id')->get()->map(function (TenantWallet $wallet) {
            $collected = (int) Transaction::withoutGlobalScopes()
                ->where('tenant_id', $wallet->tenant_id)
                ->where('channel', Transaction::CHANNEL_PORTAL)
                ->where('status', 'completed')
                ->sum('amount');

            // Rejected requests were refunded, everything else has left the wallet.
            $withdrawn = (int) WithdrawalRequest::withoutGlobalScopes()
                ->where('tenant_id', $wallet->tenant_id)
                ->whereIn('status', ['pending', 'approved', 'paid'])
                ->sum('amount');

            $ledger  = (int) WalletEntry::withoutGlobalScopes()->where('tenant_id', $wallet->tenant_id)->sum('amount');
            $ceiling = $collected - $withdrawn;
            $excess  = max(0, $wallet->balance - $ceiling);
            $drift   = $wallet->balance - $ledger;

            return [
                'tenant_id' => $wallet->tenant_id,
                'name'      => $wallet->tenant?->name ?? '?',
                'collected' => $collected,
                'withdrawn' => $withdrawn,
                'balance'   => $wallet->balance,
                'ceiling'   => $ceiling,
                'excess'    => $excess,
                'drift'     => $drift,
                'result'    => match (true) {
                    $excess > 0  => 'UNBACKED',
                    $drift !== 0 => 'LEDGER MISMATCH',
                    default      => 'ok',
                },
            ];
        });
    }

    /**
     * Where the money is, across the whole platform. Compare "expected balance" with the
     * real balance on the PalmPesa account.
     *
     * @return array<string,int>
     */
    public function platform(?\DateTimeInterface $from = null, ?\DateTimeInterface $to = null): array
    {
        $collected = (int) Transaction::withoutGlobalScopes()
            ->where('channel', Transaction::CHANNEL_PORTAL)
            ->where('status', 'completed')
            ->sum('amount');

        $paidNet  = (int) WithdrawalRequest::withoutGlobalScopes()->where('status', 'paid')->sum('net_amount');
        $paidFees = (int) WithdrawalRequest::withoutGlobalScopes()->where('status', 'paid')->sum('fee_amount');
        $wallets  = (int) TenantWallet::withoutGlobalScopes()->sum('balance');

        // Requested but not yet paid out: the money has left the wallet and is still on the account.
        $openGross = (int) WithdrawalRequest::withoutGlobalScopes()->whereIn('status', ['pending', 'approved'])->sum('amount');
        $openFees  = (int) WithdrawalRequest::withoutGlobalScopes()->whereIn('status', ['pending', 'approved'])->sum('fee_amount');

        $expectedCash = $collected - $paidNet;
        $owed         = $wallets + $openGross;

        return [
            'collected'         => $collected,
            'paid_out'          => $paidNet,
            'expected_cash'     => $expectedCash,
            'owed_wallets'      => $wallets,
            'owed_open'         => $openGross,
            'owed_total'        => $owed,
            'platform_money'    => $expectedCash - $owed,
            'fees_earned'       => $paidFees,
            'fees_pending'      => $openFees,
            'fees_logged'       => (int) PlatformBillingLog::sum('amount'),
        ];
    }

    /** True when any tenant fails one of the two checks. */
    public function hasProblems(): bool
    {
        return $this->tenants()->contains(fn (array $row) => $row['result'] !== 'ok');
    }

    /** How many completed portal payments sit in the ledger, for a quick sanity number. */
    public function ledgerEntryCount(): int
    {
        return (int) DB::table('wallet_entries')->count();
    }
}
