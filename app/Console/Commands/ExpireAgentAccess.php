<?php

namespace App\Console\Commands;

use App\Services\AgentAccess;
use Illuminate\Console\Command;

class ExpireAgentAccess extends Command
{
    protected $signature   = 'access:expire';
    protected $description = 'Tell routers to remove customers whose time has run out';

    public function handle(AgentAccess $access): int
    {
        $queued = $access->expireDueAccess();

        $this->info("Queued {$queued} removals.");

        return self::SUCCESS;
    }
}
