<?php

namespace App\Console\Commands;

use App\Models\TenantRouter;
use App\Services\MikrotikService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RouterHeartbeat extends Command
{
    protected $signature   = 'router:heartbeat';
    protected $description = 'Ping all MikroTik routers and update their online status';

    public function handle(): int
    {
        $routers = TenantRouter::all();

        $online  = 0;
        $offline = 0;

        foreach ($routers as $router) {
            $mikrotik = MikrotikService::forRouter($router);
            $connected = $mikrotik->connect();

            if ($connected) {
                $router->update(['status' => 'online', 'last_seen_at' => now()]);
                $mikrotik->disconnect();
                $online++;
            } else {
                $router->update(['status' => 'offline']);
                $offline++;
            }
        }

        $this->info("Heartbeat complete: {$online} online, {$offline} offline out of {$routers->count()} routers.");

        return self::SUCCESS;
    }
}
