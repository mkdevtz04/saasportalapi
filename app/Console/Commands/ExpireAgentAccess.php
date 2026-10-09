<?php

namespace App\Console\Commands;

use App\Services\AccessGranter;
use Illuminate\Console\Command;

class ExpireAgentAccess extends Command
{
    protected $signature   = 'access:expire';
    protected $description = 'Cut off customers whose time has run out, on every kind of router';

    public function handle(AccessGranter $access): int
    {
        $ended = $access->endExpiredAccess();

        $this->info("Ended {$ended} expired sessions.");

        return self::SUCCESS;
    }
}
