<?php

namespace App\Services\Radius;

use App\Models\TenantPackage;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Writes and removes customer logins in the FreeRADIUS SQL tables.
 *
 * Granting access is a plain database write, so it can sit inside the same database
 * transaction as the payment that earned it. FreeRADIUS reads these rows when the router
 * sends an Access-Request, then answers Accept or Reject.
 *
 * A login is made of two kinds of rows:
 *   radcheck  conditions that must hold: password, expiry date, and which routers may use it
 *   radreply  what the router is told to do: speed limit, data cap, report interval
 */
class RadiusAccess
{
    /** Stored in radcheck.source for every row that blocks a suspended tenant. */
    public const SUSPENDED = 'suspended';

    /**
     * Give a customer access.
     *
     * The login works on every router of the tenant, and only there: the NAS-Identifier
     * check ties it to the tenant, so a login can never be used on another ISP's router.
     * When a device MAC address is given, a second login is created for that MAC so the
     * same phone is let back in automatically without typing the code again.
     */
    public function grant(
        int $tenantId,
        TenantPackage $package,
        string $username,
        string $password,
        CarbonInterface $expiresAt,
        string $source,
        ?string $mac = null,
    ): void {
        DB::transaction(function () use ($tenantId, $package, $username, $password, $expiresAt, $source, $mac) {
            $this->writeLogin($tenantId, $package, $username, $expiresAt, $source, ['Cleartext-Password' => [':=', $password]]);

            $macLogin = $this->normaliseMac($mac);

            if ($macLogin !== null) {
                // The router sends the MAC as the username. Any password is accepted for it,
                // safe because the row is tied to this tenant's routers and to an expiry date.
                $this->writeLogin($tenantId, $package, $macLogin, $expiresAt, $source . ':mac', ['Auth-Type' => [':=', 'Accept']]);
            }
        });
    }

    /**
     * Remove a login immediately. The customer stays connected until the router next
     * checks in, so pair this with a kick_user router command when a cut-off must be instant.
     */
    public function revoke(string $username): void
    {
        DB::transaction(function () use ($username) {
            DB::table('radcheck')->where('username', $username)->delete();
            DB::table('radreply')->where('username', $username)->delete();
        });
    }

    /**
     * Block every current login of a tenant, used when the platform suspends an ISP.
     * New purchases are already blocked because a suspended portal shows a notice.
     */
    public function suspendTenant(int $tenantId): void
    {
        DB::transaction(function () use ($tenantId) {
            $this->resumeTenant($tenantId);

            $usernames = DB::table('radcheck')
                ->where('tenant_id', $tenantId)
                ->distinct()
                ->pluck('username');

            foreach ($usernames->chunk(500) as $chunk) {
                DB::table('radcheck')->insert($chunk->map(fn ($username) => [
                    'username'  => $username,
                    'attribute' => 'Auth-Type',
                    'op'        => ':=',
                    'value'     => 'Reject',
                    'tenant_id' => $tenantId,
                    'source'    => self::SUSPENDED,
                ])->all());
            }
        });
    }

    /** Lift the block put in place by suspendTenant(). Customers keep their remaining time. */
    public function resumeTenant(int $tenantId): void
    {
        DB::table('radcheck')
            ->where('tenant_id', $tenantId)
            ->where('source', self::SUSPENDED)
            ->delete();
    }

    /**
     * Remove logins that expired more than a day ago. FreeRADIUS already rejects them,
     * this only keeps the tables small.
     */
    public function pruneExpired(): int
    {
        $cutoff = now()->utc()->subDay();
        $removed = 0;

        DB::table('radcheck')
            ->where('attribute', 'Expiration')
            ->orderBy('id')
            ->each(function ($row) use ($cutoff, &$removed) {
                $expires = \DateTimeImmutable::createFromFormat('M d Y H:i:s', $row->value, new \DateTimeZone('UTC'));

                if ($expires && $expires < $cutoff) {
                    $this->revoke($row->username);
                    $removed++;
                }
            });

        return $removed;
    }

    // ── internals ────────────────────────────────────────────────────────────

    /**
     * @param array<string,array{0:string,1:string}> $credentials  attribute => [operator, value]
     */
    private function writeLogin(
        int $tenantId,
        TenantPackage $package,
        string $username,
        CarbonInterface $expiresAt,
        string $source,
        array $credentials,
    ): void {
        $this->revoke($username);

        $check = $credentials + [
            // FreeRADIUS reads this date in its own time zone, and the server runs in UTC.
            'Expiration'     => [':=', $expiresAt->copy()->utc()->format('M d Y H:i:s')],
            // Only routers whose identity starts with "nas-<tenant id>-" may use this login.
            'NAS-Identifier' => ['=~', '^nas-' . $tenantId . '-'],
        ];

        foreach ($check as $attribute => [$op, $value]) {
            DB::table('radcheck')->insert([
                'username'  => $username,
                'attribute' => $attribute,
                'op'        => $op,
                'value'     => $value,
                'tenant_id' => $tenantId,
                'source'    => $source,
            ]);
        }

        foreach ($this->replyFor($package) as $attribute => $value) {
            DB::table('radreply')->insert([
                'username'  => $username,
                'attribute' => $attribute,
                'op'        => ':=',
                'value'     => (string) $value,
                'tenant_id' => $tenantId,
                'source'    => $source,
            ]);
        }
    }

    /**
     * What the router is told about this customer's session.
     *
     * @return array<string,string|int>
     */
    private function replyFor(TenantPackage $package): array
    {
        $reply = [
            // MikroTik rate limit is "rx/tx" seen from the router, that is upload/download.
            'Mikrotik-Rate-Limit' => $package->speed_up_mbps . 'M/' . $package->speed_down_mbps . 'M',
            // Ask the router for usage reports so live sessions and data use stay current.
            'Acct-Interim-Interval' => 300,
        ];

        if ($package->data_cap_mb) {
            $bytes = $package->data_cap_mb * 1024 * 1024;
            $giga  = intdiv($bytes, 4294967296);

            $reply['Mikrotik-Total-Limit'] = $bytes % 4294967296;

            if ($giga > 0) {
                $reply['Mikrotik-Total-Limit-Gigawords'] = $giga;
            }
        }

        return $reply;
    }

    /** MikroTik sends MAC addresses as upper case AA:BB:CC:DD:EE:FF. */
    private function normaliseMac(?string $mac): ?string
    {
        if ($mac === null) {
            return null;
        }

        $hex = strtoupper(preg_replace('/[^0-9A-Fa-f]/', '', $mac) ?? '');

        if (strlen($hex) !== 12) {
            return null;
        }

        return implode(':', str_split($hex, 2));
    }
}
