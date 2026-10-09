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

    /**
     * End a customer's access now, whichever way their router is connected.
     *
     * Returns true once the cut-off is arranged, false when it could not be and should be tried
     * again. A router in agent mode counts as arranged the moment the command is queued: the row
     * waits for the router however long it is away.
     */
    public function endAccess(Transaction $transaction): bool
    {
        $router = $transaction->router;
        $login  = (string) $transaction->voucher_code;

        if (! $router || $login === '') {
            return true;    // nothing a retry can fix
        }

        if ($router->isAgent()) {
            $this->agent->revoke($router, $login, 'expiry');

            return true;
        }

        if ($router->isRadius()) {
            // Removing the rows refuses the next login. The kick ends the session already open,
            // because Session-Timeout is the router's own clock and a router that was rebooted
            // mid-session has forgotten it.
            //
            // The device login is only taken away when this customer has nothing else running on
            // that device. A phone has one device login, rewritten by each purchase, so it belongs
            // to the newest one — and removing it because an older session ran out would knock a
            // customer who has just paid again off their own device.
            $device = $this->deviceStillPaidFor($transaction) ? null : $transaction->customer_mac;

            foreach ($this->radius->revokeAccess($login, $device) as $revoked) {
                $router->queueCommand(RouterCommand::KICK_USER, ['username' => $revoked], 'expiry', $revoked);
            }

            return true;
        }

        return $this->revokeViaApi($router, $login);
    }

    /**
     * Has this customer's device got time left on a different purchase?
     *
     * A customer who tops up before their hour is out holds two transactions on one device. The
     * older one expiring must not take the device with it.
     */
    private function deviceStillPaidFor(Transaction $transaction): bool
    {
        if ((string) $transaction->customer_mac === '') {
            return false;
        }

        return Transaction::withoutGlobalScopes()
            ->where('tenant_id', $transaction->tenant_id)
            ->where('customer_mac', $transaction->customer_mac)
            ->where('id', '!=', $transaction->id)
            ->where('status', 'completed')
            ->whereNull('access_ended_at')
            ->where('expires_at', '>', now())
            ->exists();
    }

    /**
     * Remove a login over the router API. Unlike the other two ways this needs the router to be
     * reachable right now, so a failure here means "try again later", not "the customer is off".
     */
    public function revokeViaApi(TenantRouter $router, string $login): bool
    {
        try {
            $mikrotik = MikrotikService::forRouter($router);

            if (! $mikrotik->connect()) {
                Log::warning('Could not reach the router to end a customer session', [
                    'router_id' => $router->id,
                    'login'     => $login,
                ]);

                return false;
            }

            $removed = $mikrotik->removeHotspotUser($login);
            $mikrotik->disconnect();

            return $removed;
        } catch (Throwable $e) {
            Log::error('Error ending a customer session on the router', [
                'router_id' => $router->id,
                'error'     => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * End access whose time has run out. Runs every minute.
     *
     * A customer's deadline is wall-clock: six hours after buying means six hours, spent online
     * or not. The router enforces that itself — a one-shot timer in agent mode, Session-Timeout
     * over RADIUS — and this is the backstop for the cases a router cannot cover on its own: a
     * reboot that cleared the timer, a package whose router was swapped, an old router still on
     * the API. It is also what marks the transaction finished.
     *
     * Returns how many customers were cut off. One that could not be reached is left alone and
     * tried again next minute, rather than being marked finished while still online.
     */
    public function endExpiredAccess(): int
    {
        $ended = 0;

        // A router on the old API mode has to be reached for its customer to be cut off, and one
        // that has been switched off for good can never be. Two things keep that from turning
        // into a connection attempt per expired customer per minute, for ever: a router that
        // fails once is not tried again for the rest of this run, and a deadline old enough that
        // the customer cannot still be online is closed off without reaching the router at all.
        $unreachable = [];
        $giveUpAfter = now()->subHours((int) config('router.expiry_retry_hours', 24));

        Transaction::withoutGlobalScopes()
            ->with('router')
            ->where('status', 'completed')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->whereNull('access_ended_at')
            ->whereNotNull('voucher_code')
            ->orderBy('id')
            ->chunkById(200, function ($transactions) use (&$ended, &$unreachable, $giveUpAfter) {
                foreach ($transactions as $transaction) {
                    $routerId = (int) $transaction->router_id;

                    // Don't dial a router this run has already found unreachable.
                    if (isset($unreachable[$routerId])) {
                        $arranged = false;
                    } else {
                        $arranged = $this->endAccess($transaction);

                        if (! $arranged) {
                            $unreachable[$routerId] = true;
                        }
                    }

                    if (! $arranged) {
                        if ($transaction->expires_at->gte($giveUpAfter)) {
                            continue;   // recent enough to be worth another try next minute
                        }

                        Log::warning('Closing off an expired session without reaching the router', [
                            'transaction_id' => $transaction->id,
                            'router_id'      => $routerId,
                            'expired_at'     => $transaction->expires_at->toDateTimeString(),
                        ]);
                    }

                    $transaction->forceFill(['access_ended_at' => now()])->save();
                    $ended++;
                }
            });

        return $ended;
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
