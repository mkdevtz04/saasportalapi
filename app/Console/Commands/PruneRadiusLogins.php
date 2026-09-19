<?php

namespace App\Console\Commands;

use App\Services\Radius\RadiusAccess;
use Illuminate\Console\Command;

class PruneRadiusLogins extends Command
{
    protected $signature   = 'radius:prune';
    protected $description = 'Remove customer logins that expired more than a day ago';

    public function handle(RadiusAccess $radius): int
    {
        $removed = $radius->pruneExpired();

        $this->info("Removed {$removed} expired logins.");

        return self::SUCCESS;
    }
}
