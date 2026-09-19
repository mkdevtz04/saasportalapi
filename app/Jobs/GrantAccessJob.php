<?php

namespace App\Jobs;

use App\Models\Transaction;
use App\Services\AccessGranter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;
use Throwable;

/**
 * Creates the customer's hotspot user after a payment. Runs on the queue so a slow or
 * offline router never holds up the payment callback, and is retried with a growing
 * delay until the router accepts it.
 */
class GrantAccessJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 6;

    public function __construct(public int $transactionId)
    {
    }

    /** @return int[] seconds to wait before each retry */
    public function backoff(): array
    {
        return [10, 30, 60, 180, 600];
    }

    public function handle(AccessGranter $granter): void
    {
        $transaction = Transaction::withoutGlobalScopes()
            ->with(['router', 'package'])
            ->find($this->transactionId);

        if (! $transaction || $transaction->provision_status === 'done') {
            return;
        }

        if (! $granter->grantTransaction($transaction)) {
            throw new RuntimeException('The router did not accept the hotspot user yet.');
        }
    }

    /** Called once every retry has been used up. */
    public function failed(Throwable $exception): void
    {
        $transaction = Transaction::withoutGlobalScopes()->find($this->transactionId);

        if ($transaction && $transaction->provision_status !== 'done') {
            app(AccessGranter::class)->markFailed($transaction, $exception->getMessage());
        }
    }
}
