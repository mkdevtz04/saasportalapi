<?php

namespace App\Console\Commands;

use App\Models\TenantRouter;
use App\Services\MikrotikService;
use App\Services\RouterAlerts;
use Illuminate\Console\Command;

class RouterHeartbeat extends Command
{
    protected $signature   = 'router:heartbeat';
    protected $description = 'Refresh the online or offline status of every router';

    public function handle(RouterAlerts $alerts): int
    {
        $online  = 0;
        $offline = 0;
        $total   = 0;

        TenantRouter::withoutGlobalScopes()->orderBy('id')->each(function (TenantRouter $router) use (&$online, &$offline, &$total, $alerts) {
            $total++;

            $isOnline = $router->isRadius()
                ? $this->radiusRouterIsOnline($router)
                : $this->apiRouterIsOnline($router);

            // Tell the owner once when a router goes quiet, and once when it returns.
            if ($router->isRadius()) {
                $alerts->check($router->fresh());
            }

            $isOnline ? $online++ : $offline++;
        });

        $this->info("Heartbeat complete: {$online} online, {$offline} offline out of {$total} routers.");

        return self::SUCCESS;
    }

    /**
     * A router that connects out cannot be pinged. It is online while its agent keeps
     * checking in, and offline once it has been quiet for a few minutes.
     */
    private function radiusRouterIsOnline(TenantRouter $router): bool
    {
        $isOnline = $router->isOnline();
        $status   = $isOnline ? 'online' : ($router->last_seen_at ? 'offline' : 'unknown');

        if ($router->status !== $status) {
            $router->update(['status' => $status]);
        }

        return $isOnline;
    }

    /** Routers the platform logs in to are checked by connecting to them. */
    private function apiRouterIsOnline(TenantRouter $router): bool
    {
        $mikrotik  = MikrotikService::forRouter($router);
        $connected = $mikrotik->connect();

        if ($connected) {
            $router->update(['status' => 'online', 'last_seen_at' => now()]);
            $mikrotik->disconnect();

            return true;
        }

        $router->update(['status' => 'offline']);

        return false;
    }
}
