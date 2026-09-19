<?php

namespace App\Support;

/**
 * The tenant whose data the current request is allowed to see.
 *
 * It is set per request by the SetTenantContext middleware and is empty everywhere
 * else: the admin panel, console commands and queue jobs all see every tenant.
 * Models that use BelongsToTenant are filtered by this value automatically.
 */
class CurrentTenant
{
    private static ?int $id = null;

    public static function id(): ?int
    {
        return self::$id;
    }

    public static function set(?int $tenantId): void
    {
        self::$id = $tenantId;
    }

    public static function clear(): void
    {
        self::$id = null;
    }

    /**
     * Run a callback as if it were a request for this tenant, then restore the previous state.
     *
     * @template T
     * @param  callable():T  $callback
     * @return T
     */
    public static function runAs(?int $tenantId, callable $callback): mixed
    {
        $previous = self::$id;
        self::$id = $tenantId;

        try {
            return $callback();
        } finally {
            self::$id = $previous;
        }
    }
}
