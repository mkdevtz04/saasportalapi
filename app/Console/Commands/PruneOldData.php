<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Keeps the busiest tables from growing forever. Money records (transactions, the wallet ledger,
 * withdrawals) and the audit trail are never touched.
 */
class PruneOldData extends Command
{
    protected $signature   = 'data:prune';
    protected $description = 'Delete old login attempts, delivered router commands, old gateway callbacks and old usage records';

    public function handle(): int
    {
        $removed = [
            // Every login attempt from every router, useful for a few days of debugging only.
            'radpostauth'      => DB::table('radpostauth')->where('authdate', '<', now()->subDays(14))->delete(),

            // Commands a router already took.
            'router_commands'  => DB::table('router_commands')->where('status', 'delivered')->where('delivered_at', '<', now()->subDays(30))->delete(),

            // Raw gateway callbacks are kept long enough to settle any payment dispute.
            'payment_webhooks' => DB::table('payment_webhooks')->where('created_at', '<', now()->subDays(180))->delete(),

            // Usage reports, kept for over a year of history.
            'radacct'          => DB::table('radacct')->whereNotNull('acctstoptime')->where('acctstoptime', '<', now()->subDays(400))->delete(),
        ];

        foreach ($removed as $table => $count) {
            $this->line(str_pad($table, 18) . $count . ' rows removed');
        }

        return self::SUCCESS;
    }
}
