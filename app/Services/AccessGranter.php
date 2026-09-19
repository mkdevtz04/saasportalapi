<?php

namespace App\Services;

use App\Models\RouterCommand;
use App\Models\TenantPackage;
use App\Models\TenantRouter;
use App\Models\Transaction;
use App\Services\Radius\RadiusAccess;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Gives a paying or voucher-redeeming customer internet access on their tenant's router.
 *
 * "Did the customer pay" and "did the customer get online" are separate questions.
 * Payment is settled first and never rolled back by a router problem. This class
 * handles the second question and records the answer on the transaction.
 */
class AccessGranter
{
    public function __construct(private RadiusAccess $radius, private AgentAccess $agent)
    {
    }

    /**
     * Give access through RADIUS. Just database writes, so it is safe to call inside the
     * database transaction that settles the payment and it cannot fail because a router
     * is offline.
     */
    public function grantViaRadius(TenantRouter $router, TenantPackage $package, string $username, string $password, \DateTimeInterface $expiresAt, string $source, ?string $mac = null): void
    {
        $this->radius->grant($router->tenant_id, $package, $username, $password, \Illuminate\Support\Carbon::instance($expiresAt), $source, $mac);
    }

    /**
     * Queue the customer's access for a router in agent mode. The router creates the hotspot user
     * itself within seconds. Also safe inside the transaction that settles the payment.
     */
    public function grantViaAgent(TenantRouter $router, TenantPackage $package, string $username, \DateTimeInterface $expiresAt): RouterCommand
    {
        return $this->agent->grant($router, $package, $username, \Illuminate\Support\Carbon::instance($expiresAt));
    }

    /**
     * Create the hotspot user through the router API. Used for routers that are still
     * reached directly, or through the relay agent.
     */
    public function grantViaApi(TenantRouter $router, TenantPackage $package, string $username, string $password): bool
    {
        try {
            $mikrotik = MikrotikService::forRouter($router);

            if (! $mikrotik->connect()) {
                Log::error('MikroTik connection failed', ['router_id' => $router->id, 'router_ip' => $router->router_ip]);

                return false;
            }

            $created = $mikrotik->createHotspotUser($username, $password, $package->mikrotik_profile);
            $mikrotik->disconnect();

            return $created;
        } catch (Throwable $e) {
            Log::error('MikroTik provisioning error', ['router_id' => $router->id, 'error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Grant access for a settled portal payment and record the outcome on it.
     * Returns true when the customer is online, false when it should be retried.
     */
    public function grantTransaction(Transaction $transaction): bool
    {
        $router  = $transaction->router;
        $package = $transaction->package;

        if (! $router || ! $package) {
            $this->markFailed($transaction, 'The transaction has no router or package.');

            return true;    // nothing a retry can fix
        }

        $token = $transaction->voucher_code;

        if ($router->isAgent()) {
            // Access is queued. The transaction shows "connecting" until the router has picked it up.
            $this->grantViaAgent($router, $package, $token, $transaction->expires_at ?? now()->addHours($package->duration_hours));

            return true;
        }

        if ($router->isRadius()) {
            $this->grantViaRadius($router, $package, $token, $token, $transaction->expires_at ?? now()->addHours($package->duration_hours), 'txn:' . $transaction->id, $transaction->customer_mac);
        } elseif (! $this->grantViaApi($router, $package, $token, $token)) {
            return false;
        }

        $transaction->update(['provision_status' => 'done', 'provision_error' => null]);

        return true;
    }

    public function markFailed(Transaction $transaction, string $reason): void
    {
        $transaction->update([
            'provision_status' => 'failed',
            'provision_error'  => mb_substr($reason, 0, 250),
        ]);

        Log::warning('Customer paid but has no access yet, manual help may be needed', [
            'transaction_id' => $transaction->id,
            'token'          => $transaction->voucher_code,
            'reason'         => $reason,
        ]);
    }
}
