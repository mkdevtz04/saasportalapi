<?php

namespace App\Services;

use App\Jobs\GrantAccessJob;
use App\Jobs\SendReceiptJob;
use App\Models\TenantWallet;
use App\Models\Transaction;
use App\Models\WalletEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * The single place where a portal payment becomes "paid".
 *
 * Rules:
 *  - The gateway is the only source of truth. Callback payloads and anything
 *    sent by the browser are never trusted, we always ask PalmPesa ourselves,
 *    using the order id stored on our own transaction row.
 *  - Settlement happens once. The transaction row is locked, its status flips
 *    and the tenant wallet is credited inside one database transaction, so a
 *    callback and a status poll racing each other cannot double credit.
 *  - If anything inside that transaction fails, everything rolls back and the
 *    transaction stays pending, so the next poll or reconcile run retries it.
 */
class PaymentSettlement
{
    public const GATEWAY_COMPLETED = 'COMPLETED';
    public const GATEWAY_FAILED    = 'FAILED';
    public const GATEWAY_PENDING   = 'PENDING';

    /** Gateway statuses that mean the customer will not pay this order. */
    private const FAILED_STATUSES = ['FAILED', 'CANCELLED', 'CANCELED', 'REJECTED', 'EXPIRED'];

    /**
     * Ask the gateway what happened to this transaction's order.
     * Returns one of the GATEWAY_* constants. Unknown values count as pending
     * so a new gateway status can never fail a real payment by accident.
     */
    public function gatewayStatus(Transaction $transaction): string
    {
        if (! $transaction->palmpesa_order_id) {
            return self::GATEWAY_PENDING;
        }

        $result = PalmPesaService::platform()->checkStatus($transaction->palmpesa_order_id);
        $raw    = strtoupper((string) ($result['data'][0]['payment_status'] ?? 'PENDING'));

        if ($raw === 'COMPLETED') {
            return self::GATEWAY_COMPLETED;
        }

        return in_array($raw, self::FAILED_STATUSES, true)
            ? self::GATEWAY_FAILED
            : self::GATEWAY_PENDING;
    }

    /**
     * Confirm the payment with the gateway and act on the answer.
     * Safe to call from a callback, a status poll or a scheduled job, any number of times.
     */
    public function verifyAndSettle(Transaction $transaction): Transaction
    {
        if ($transaction->isCompleted()) {
            return $transaction;
        }

        try {
            $status = $this->gatewayStatus($transaction);
        } catch (Throwable $e) {
            Log::error('PalmPesa status check failed', [
                'transaction_id' => $transaction->id,
                'error'          => $e->getMessage(),
            ]);

            return $transaction;
        }

        if ($status === self::GATEWAY_COMPLETED) {
            $this->settle($transaction);
        } elseif ($status === self::GATEWAY_FAILED) {
            Transaction::whereKey($transaction->id)
                ->where('status', 'pending')
                ->update(['status' => 'failed']);
        }

        return $transaction->refresh();
    }

    /**
     * Mark a gateway-confirmed payment as completed exactly once and credit the tenant.
     * Returns true only for the call that performed the settlement.
     *
     * A payment the customer completed after we marked it failed (late mobile money
     * confirmation) is still settled, because the money really arrived.
     */
    public function settle(Transaction $transaction): bool
    {
        $outcome = DB::transaction(function () use ($transaction) {
            $locked = Transaction::with(['package', 'router'])
                ->whereKey($transaction->id)
                ->lockForUpdate()
                ->first();

            if (! $locked || ! in_array($locked->status, ['pending', 'failed'], true)) {
                return null;
            }

            $package = $locked->package;
            $router  = $locked->router;

            $locked->update([
                'status'       => 'completed',
                'voucher_code' => $this->newToken(),
                'expires_at'   => $package ? now()->addHours($package->duration_hours) : null,
            ]);

            // The customer paid the platform, so the whole amount belongs to the tenant.
            // The platform fee is taken later, when the tenant withdraws.
            TenantWallet::withoutGlobalScopes()->firstOrCreate(
                ['tenant_id' => $locked->tenant_id],
                ['balance' => 0, 'total_earned' => 0]
            )->credit($locked->amount, WalletEntry::PAYMENT, 'TXN-' . $locked->id, [
                'order_id' => $locked->palmpesa_order_id,
            ]);

            // A router that connects out is given access right here, in the same database
            // transaction as the payment: either both happen or neither does.
            if ($router && $package && $router->isRadius()) {
                app(AccessGranter::class)->grantViaRadius(
                    $router,
                    $package,
                    $locked->voucher_code,
                    $locked->voucher_code,
                    $locked->expires_at,
                    'txn:' . $locked->id,
                    $locked->customer_mac,
                );
                $locked->update(['provision_status' => 'done', 'provision_error' => null]);

                return 'granted';
            }

            // A router in agent mode is given a command in the same database transaction as the
            // payment. It shows as "connecting" until the router has picked the command up.
            if ($router && $package && $router->isAgent()) {
                app(AccessGranter::class)->grantViaAgent($router, $package, $locked->voucher_code, $locked->expires_at);

                return 'granted';
            }

            return 'queue';
        });

        if ($outcome === 'queue') {
            $this->queueAccess($transaction);
        }

        if ($outcome !== null) {
            $this->queueReceipt($transaction);
        }

        return $outcome !== null;
    }

    /**
     * Hand the "get the customer online" step to the queue. The payment is already
     * settled, so a router problem here can never undo it or block the callback.
     */
    private function queueAccess(Transaction $transaction): void
    {
        try {
            GrantAccessJob::dispatch($transaction->id);
        } catch (Throwable $e) {
            Log::error('Could not queue access for a settled payment', [
                'transaction_id' => $transaction->id,
                'error'          => $e->getMessage(),
            ]);
        }
    }

    /** The SMS receipt is a courtesy, so a failure to queue it is only logged. */
    private function queueReceipt(Transaction $transaction): void
    {
        try {
            SendReceiptJob::dispatch($transaction->id);
        } catch (Throwable $e) {
            Log::warning('Could not queue the SMS receipt', ['transaction_id' => $transaction->id, 'error' => $e->getMessage()]);
        }
    }

    /** Random hotspot username and password. Not derived from the clock, so it cannot be guessed. */
    private function newToken(): string
    {
        return 'TN' . strtoupper(Str::random(10));
    }
}
