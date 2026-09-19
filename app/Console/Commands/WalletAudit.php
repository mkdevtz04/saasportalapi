<?php

namespace App\Console\Commands;

use App\Services\WalletAuditor;
use Illuminate\Console\Command;

/**
 * Read-only check for tenant wallets holding money that real payments cannot explain.
 * The rules live in WalletAuditor. Balances below the payment ceiling are fine, older
 * versions also took a fee at payment time.
 */
class WalletAudit extends Command
{
    protected $signature   = 'wallet:audit';
    protected $description = 'List tenant wallets whose balance payments or the ledger cannot explain (read-only)';

    public function handle(WalletAuditor $auditor): int
    {
        $rows = $auditor->tenants();

        $this->table(
            ['Tenant', 'Name', 'Portal paid', 'Withdrawn', 'Balance', 'Ceiling', 'Excess', 'Ledger gap', 'Result'],
            $rows->map(fn (array $r) => [
                $r['tenant_id'],
                $r['name'],
                number_format($r['collected']),
                number_format($r['withdrawn']),
                number_format($r['balance']),
                number_format($r['ceiling']),
                $r['excess'] > 0 ? number_format($r['excess']) : '-',
                $r['drift'] !== 0 ? number_format($r['drift']) : '-',
                $r['result'],
            ])->all()
        );

        $bad = $rows->filter(fn (array $r) => $r['result'] !== 'ok')->count();

        if ($bad > 0) {
            $this->error("{$bad} wallet(s) have money that portal payments or the ledger cannot explain. Do not pay their withdrawals until reviewed.");

            return self::FAILURE;
        }

        $this->info('All wallets are explained by portal payments.');

        return self::SUCCESS;
    }
}
