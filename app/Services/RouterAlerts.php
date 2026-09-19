<?php

namespace App\Services;

use App\Contracts\SmsGateway;
use App\Models\TenantRouter;
use App\Models\TenantUser;
use App\Notifications\RouterStatusNotification;
use App\Support\Phone;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Tells owners when a router that connects out goes quiet, and when it returns.
 *
 * One alert per outage: offline_alerted_at remembers that the owner was told, so the
 * heartbeat that runs every few minutes does not repeat it. A short blip is ignored,
 * an alert goes out only after the router has been silent for alert_after_minutes.
 */
class RouterAlerts
{
    public function __construct(private SmsGateway $sms)
    {
    }

    /** Returns "offline", "online" or null when nothing needed saying. */
    public function check(TenantRouter $router): ?string
    {
        if (! $router->runsAgent() || $router->provision_status !== 'completed' || ! $router->last_seen_at) {
            return null;
        }

        $silentFor = (int) config('radius.alert_after_minutes', 10);

        if (! $router->isOnline()) {
            if ($router->offline_alerted_at === null && $router->last_seen_at->lt(now()->subMinutes($silentFor))) {
                $router->update(['offline_alerted_at' => now()]);
                $this->tell($router, false);

                return 'offline';
            }

            return null;
        }

        if ($router->offline_alerted_at !== null) {
            $router->update(['offline_alerted_at' => null]);
            $this->tell($router, true);

            return 'online';
        }

        return null;
    }

    private function tell(TenantRouter $router, bool $online): void
    {
        $owners = TenantUser::where('tenant_id', $router->tenant_id)->where('role', 'owner')->get();

        foreach ($owners as $owner) {
            try {
                $owner->notify(new RouterStatusNotification($router, $online));
            } catch (Throwable $e) {
                Log::warning('Router alert email failed', ['router_id' => $router->id, 'error' => $e->getMessage()]);
            }

            if ($owner->phone && Phone::isMobile($owner->phone)) {
                $this->sms->send(
                    Phone::international($owner->phone),
                    $online
                        ? "TrinetPay: router {$router->name} is back online."
                        : "TrinetPay: router {$router->name} is offline. Customers cannot connect. Check its power and internet."
                );
            }
        }
    }
}
