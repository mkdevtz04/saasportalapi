<?php

namespace App\Services\Radius;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Reads what the routers report to FreeRADIUS (the radacct table): who is online right
 * now and how much data was used. A session is tied to a tenant through its login,
 * because every login row in radcheck carries the tenant id.
 *
 * Times in radacct are in UTC, the RADIUS server clock.
 */
class RadiusReports
{
    /** A router reports every few minutes. Silence for longer than this means the session ended. */
    public const STALE_AFTER_MINUTES = 15;

    /**
     * Sessions that are open and still reporting.
     *
     * @return Collection<int,object>
     */
    public function activeSessions(int $tenantId, int $limit = 500): Collection
    {
        $fresh = now()->utc()->subMinutes(self::STALE_AFTER_MINUTES)->format('Y-m-d H:i:s');

        return DB::table('radacct')
            ->whereNull('acctstoptime')
            ->where(function ($query) use ($fresh) {
                $query->where('acctupdatetime', '>=', $fresh)
                    ->orWhere(fn ($q) => $q->whereNull('acctupdatetime')->where('acctstarttime', '>=', $fresh));
            })
            ->whereIn('username', $this->loginsOf($tenantId))
            ->orderByDesc('acctstarttime')
            ->limit($limit)
            ->get();
    }

    /**
     * Data moved by sessions that started at or after the given moment.
     *
     * @return array{download:int,upload:int,sessions:int}
     */
    public function usageSince(int $tenantId, \DateTimeInterface $since): array
    {
        $row = DB::table('radacct')
            ->whereIn('username', $this->loginsOf($tenantId))
            ->where('acctstarttime', '>=', \Illuminate\Support\Carbon::instance($since)->utc()->format('Y-m-d H:i:s'))
            ->selectRaw('COALESCE(SUM(acctoutputoctets), 0) as down, COALESCE(SUM(acctinputoctets), 0) as up, COUNT(*) as sessions')
            ->first();

        return [
            'download' => (int) $row->down,
            'upload'   => (int) $row->up,
            'sessions' => (int) $row->sessions,
        ];
    }

    /**
     * Data used per day for the last few days, oldest first, days in East Africa Time.
     *
     * @return array<string,int> date (Y-m-d) => bytes
     */
    public function usageByDay(int $tenantId, int $days = 7): array
    {
        $zone  = 'Africa/Dar_es_Salaam';
        $start = now($zone)->startOfDay()->subDays($days - 1);
        $out   = [];

        for ($i = 0; $i < $days; $i++) {
            $out[$start->copy()->addDays($i)->format('Y-m-d')] = 0;
        }

        DB::table('radacct')
            ->whereIn('username', $this->loginsOf($tenantId))
            ->where('acctstarttime', '>=', $start->copy()->utc()->format('Y-m-d H:i:s'))
            ->select(['acctstarttime', 'acctinputoctets', 'acctoutputoctets'])
            ->orderBy('radacctid')
            ->each(function ($row) use (&$out, $zone) {
                $day = \Illuminate\Support\Carbon::parse($row->acctstarttime, 'UTC')->timezone($zone)->format('Y-m-d');

                if (array_key_exists($day, $out)) {
                    $out[$day] += (int) $row->acctinputoctets + (int) $row->acctoutputoctets;
                }
            });

        return $out;
    }

    /** Every login name (codes and device MAC addresses) that belongs to the tenant. */
    private function loginsOf(int $tenantId): \Illuminate\Database\Query\Builder
    {
        return DB::table('radcheck')->where('tenant_id', $tenantId)->select('username');
    }
}
