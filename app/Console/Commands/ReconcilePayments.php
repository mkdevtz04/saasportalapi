<?php

namespace App\Console\Commands;

use App\Models\Transaction;
use App\Services\PaymentSettlement;
use Illuminate\Console\Command;

class ReconcilePayments extends Command
{
    protected $signature   = 'payments:reconcile {--minutes=1 : Only look at payments older than this} {--hours=24 : Ignore payments older than this}';
    protected $description = 'Ask the gateway about portal payments still pending and settle the ones that were paid';

    public function handle(PaymentSettlement $settlement): int
    {
        $checked = 0;
        $settled = 0;

        Transaction::query()
            ->where('channel', Transaction::CHANNEL_PORTAL)
            ->where('status', 'pending')
            ->whereNotNull('palmpesa_order_id')
            ->where('created_at', '<=', now()->subMinutes((int) $this->option('minutes')))
            ->where('created_at', '>=', now()->subHours((int) $this->option('hours')))
            ->orderBy('id')
            ->chunkById(100, function ($transactions) use ($settlement, &$checked, &$settled) {
                foreach ($transactions as $transaction) {
                    $checked++;

                    if ($settlement->verifyAndSettle($transaction)->isCompleted()) {
                        $settled++;
                    }
                }
            });

        $this->info("Reconciled {$checked} pending payments, settled {$settled}.");

        return self::SUCCESS;
    }
}
