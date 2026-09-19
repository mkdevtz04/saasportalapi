<?php

namespace App\Services;

use App\Models\RouterCommand;
use App\Models\TenantPackage;
use App\Models\TenantRouter;
use App\Models\Transaction;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Gives customers access on a router in agent mode.
 *
 * There is no RADIUS server and the platform never connects to the router. Paying, or redeeming a
 * voucher, queues a command. The router's own script picks it up within seconds and creates the
 * hotspot user locally. When the time runs out the platform queues the removal.
 */
class AgentAccess
{
    /**
     * Queue the creation of a customer's hotspot user. Also safe inside the database transaction
     * that settles a payment: it is only a row, so it cannot fail because the router is offline.
     */
    public function grant(TenantRouter $router, TenantPackage $package, string $username, CarbonInterface $expiresAt): RouterCommand
    {
        $seconds = max(60, (int) Carbon::now()->diffInSeconds($expiresAt, false));
        $bytes   = $package->data_cap_mb ? (int) $package->data_cap_mb * 1024 * 1024 : 0;

        return $router->queueCommand(RouterCommand::ADD_USER, [
            'username' => $username,
            'profile'  => self::profileName($package),
            // MikroTik reads "rx/tx" from the router, that is upload first, then download.
            'rate'     => $package->speed_up_mbps . 'M/' . $package->speed_down_mbps . 'M',
            'seconds'  => $seconds,
            'bytes'    => $bytes,
        ], null, $username);
    }

    /** Queue the removal of a customer's hotspot user and their current session. */
    public function revoke(TenantRouter $router, string $username, ?string $requestedBy = null): RouterCommand
    {
        return $router->queueCommand(RouterCommand::REMOVE_USER, ['username' => $username], $requestedBy, $username);
    }

    /**
     * Is the customer's access on the router? True once the router has picked up the command and had
     * a moment to act on it. Routers connected other ways have nothing to wait for.
     */
    public function isReady(?RouterCommand $grant): bool
    {
        if ($grant === null) {
            return true;
        }

        return $grant->status === 'delivered'
            && $grant->delivered_at !== null
            && $grant->delivered_at->lte(now()->subSeconds((int) config('router.ready_after_seconds', 3)));
    }

    /** The newest access command for a login, tenant scoping applied when a tenant is current. */
    public function latestGrant(string $username): ?RouterCommand
    {
        return RouterCommand::where('type', RouterCommand::ADD_USER)
            ->where('reference', $username)
            ->latest('id')
            ->first();
    }

    /**
     * Remove access whose time has run out. Runs every minute. Returns how many removals were queued.
     * A customer is removed once, and a router that is offline gets the command when it returns.
     */
    public function expireDueAccess(): int
    {
        $queued = 0;

        Transaction::withoutGlobalScopes()
            ->with('router')
            ->where('status', 'completed')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->whereNull('access_ended_at')
            ->whereNotNull('voucher_code')
            ->orderBy('id')
            ->chunkById(200, function ($transactions) use (&$queued) {
                foreach ($transactions as $transaction) {
                    $router = $transaction->router;

                    if ($router && $router->isAgent()) {
                        $this->revoke($router, (string) $transaction->voucher_code, 'expiry');
                        $queued++;
                    }

                    // Transactions on other kinds of router are expired by their own means, mark them
                    // so this query does not keep finding them.
                    $transaction->forceFill(['access_ended_at' => now()])->save();
                }
            });

        return $queued;
    }

    /**
     * Suspending an ISP takes every customer's access off its agent-mode routers.
     */
    public function suspendTenant(int $tenantId): int
    {
        $removed = 0;

        Transaction::withoutGlobalScopes()
            ->with('router')
            ->where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->where('expires_at', '>', now())
            ->whereNull('access_ended_at')
            ->whereNotNull('voucher_code')
            ->each(function (Transaction $transaction) use (&$removed) {
                if ($transaction->router && $transaction->router->isAgent()) {
                    $this->revoke($transaction->router, (string) $transaction->voucher_code, 'suspension');
                    $transaction->forceFill(['access_ended_at' => now()])->save();
                    $removed++;
                }
            });

        return $removed;
    }

    /**
     * Lifting a suspension gives customers back the time they had left.
     */
    public function resumeTenant(int $tenantId): int
    {
        $restored = 0;

        Transaction::withoutGlobalScopes()
            ->with(['router', 'package'])
            ->where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->where('expires_at', '>', now())
            ->whereNotNull('access_ended_at')
            ->whereNotNull('voucher_code')
            ->each(function (Transaction $transaction) use (&$restored) {
                if ($transaction->router && $transaction->router->isAgent() && $transaction->package) {
                    $this->grant($transaction->router, $transaction->package, (string) $transaction->voucher_code, $transaction->expires_at);
                    $transaction->forceFill(['access_ended_at' => null])->save();
                    $restored++;
                }
            });

        return $restored;
    }

    /** The name of the hotspot profile a package's customers are put on, made of plain characters only. */
    public static function profileName(TenantPackage $package): string
    {
        $name = preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($package->mikrotik_profile ?: $package->name)) ?? '';

        return $name !== '' ? substr($name, 0, 40) : 'tn-package';
    }
}
