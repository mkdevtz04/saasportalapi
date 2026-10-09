<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\Voucher;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Everyone who has ever bought WiFi from one ISP, gathered into one customer per person.
 *
 * The platform has no customer table: it has sales. A customer is therefore worked out from
 * their sales, and two people are the same customer when they share an identity — their phone
 * number where we know it, otherwise the MAC address of the device that bought. Phone first,
 * because one person uses several devices, and a phone number is the thing an ISP has to go on
 * when a customer rings up about a session they paid for.
 *
 * A sale with neither — a voucher card sold over the counter and redeemed without a phone
 * number — identifies nobody, so it is counted separately rather than lumped in as a customer
 * who does not exist.
 */
class CustomerDirectory
{
    public const PER_PAGE = 50;

    /**
     * How a sale is attributed to a person. Written once because the listing groups by it and
     * the profile looks a single customer up by it, and the two must agree exactly.
     */
    private const IDENTITY = "COALESCE(NULLIF(transactions.phone, ''), transactions.customer_mac)";

    /**
     * One row per customer, newest first.
     *
     * @param array{search?:string|null,status?:string|null,router?:int|null} $filters
     * @param int|null $page explicit page, for walking the whole directory out to a file
     */
    public function list(int $tenantId, array $filters = [], ?int $page = null): LengthAwarePaginator
    {
        $customers = DB::query()
            ->fromSub($this->aggregate($tenantId, $filters), 'c')
            ->when(($filters['status'] ?? null) === 'active', fn ($q) => $q->where('active', 1))
            ->when(($filters['status'] ?? null) === 'expired', fn ($q) => $q->where('active', 0))
            ->orderByDesc('last_seen')
            ->paginate(self::PER_PAGE, ['*'], 'page', $page)
            ->withQueryString();

        $customers->setCollection($customers->getCollection()->map(fn ($row) => $this->present($row)));

        return $customers;
    }

    /** Totals for the cards above the list, over the whole directory rather than this page. */
    public function summary(int $tenantId): array
    {
        $now = now();

        $row = DB::query()
            ->fromSub($this->aggregate($tenantId), 'c')
            ->selectRaw('COUNT(*) as customers')
            ->selectRaw('COALESCE(SUM(active), 0) as active')
            ->selectRaw('COALESCE(SUM(spent), 0) as spent')
            ->first();

        return [
            'customers'    => (int) ($row->customers ?? 0),
            'active'       => (int) ($row->active ?? 0),
            'spent'        => (int) ($row->spent ?? 0),
            // Sales that name nobody. Shown so the numbers here and on the reports page can be
            // reconciled instead of looking like one of them has lost sales.
            'unidentified' => Transaction::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('status', 'completed')
                ->where(fn ($q) => $q->whereNull('phone')->orWhere('phone', ''))
                ->where(fn ($q) => $q->whereNull('customer_mac')->orWhere('customer_mac', ''))
                ->count(),
            'as_of'        => $now,
        ];
    }

    /**
     * Everything one customer has: their devices, every purchase including the ones that failed,
     * and any voucher card they redeemed. Null when the identity buys nothing from this ISP,
     * which is also what stops one ISP reading another's customer by guessing a phone number.
     */
    public function profile(int $tenantId, string $identity): ?array
    {
        $sales = Transaction::withoutGlobalScopes()
            ->with(['package:id,name,duration_hours', 'router:id,name'])
            ->where('tenant_id', $tenantId)
            ->whereRaw(self::IDENTITY . ' = ?', [$identity])
            ->orderByDesc('id')
            ->limit(500)
            ->get();

        if ($sales->isEmpty()) {
            return null;
        }

        $paid = $sales->where('status', 'completed');

        return [
            'identity'  => $identity,
            'phone'     => $sales->firstWhere(fn ($s) => (string) $s->phone !== '')?->phone,
            'devices'   => $this->devices($sales),
            'sales'     => $sales,
            'purchases' => $paid->count(),
            'spent'     => (int) $paid->sum('amount'),
            'first_seen' => $sales->min('created_at'),
            'last_seen'  => $sales->max('created_at'),
            'expires_at' => $paid->max('expires_at'),
            'active'     => $paid->contains(fn ($s) => $s->expires_at?->isFuture() && $s->access_ended_at === null),
            'vouchers'   => $this->vouchersFor($tenantId, $paid->pluck('voucher_code')->filter()->unique()),
        ];
    }

    // ── internals ────────────────────────────────────────────────────────────

    /**
     * The grouped query every listing and total is built on.
     *
     * @param array{search?:string|null,router?:int|null} $filters
     */
    private function aggregate(int $tenantId, array $filters = []): Builder
    {
        $search = trim((string) ($filters['search'] ?? ''));

        return DB::table('transactions')
            ->where('tenant_id', $tenantId)
            // A customer is someone who paid. Pending and failed attempts belong on the
            // transactions page, and on this customer's own profile, not in a head count.
            ->where('status', 'completed')
            ->whereRaw(self::IDENTITY . " IS NOT NULL AND " . self::IDENTITY . " <> ''")
            ->when(($filters['router'] ?? null), fn ($q, $router) => $q->where('router_id', $router))
            ->when($search !== '', function ($q) use ($search) {
                $like = '%' . $search . '%';

                $q->where(function ($match) use ($like, $search) {
                    $match->where('phone', 'like', $like)
                        ->orWhere('customer_mac', 'like', $like)
                        ->orWhere('customer_ip', 'like', $like)
                        ->orWhere('voucher_code', strtoupper($search));
                });
            })
            ->groupByRaw(self::IDENTITY)
            ->selectRaw(self::IDENTITY . ' as identity')
            ->selectRaw('COUNT(*) as purchases')
            ->selectRaw('COALESCE(SUM(amount), 0) as spent')
            ->selectRaw('MIN(created_at) as first_seen')
            ->selectRaw('MAX(created_at) as last_seen')
            ->selectRaw('MAX(expires_at) as expires_at')
            ->selectRaw('COUNT(DISTINCT customer_mac) as devices')
            ->selectRaw("MAX(CASE WHEN expires_at > ? AND access_ended_at IS NULL THEN 1 ELSE 0 END) as active", [now()])
            ->selectRaw("MAX(CASE WHEN phone IS NULL THEN '' ELSE phone END) as phone")
            ->selectRaw('MAX(customer_mac) as mac')
            ->selectRaw('MAX(customer_ip) as ip');
    }

    /** Turn the raw aggregate row into the shapes a view can print without thinking. */
    private function present(object $row): object
    {
        $row->active     = (bool) $row->active;
        $row->purchases  = (int) $row->purchases;
        $row->spent      = (int) $row->spent;
        $row->devices    = (int) $row->devices;
        $row->phone      = (string) $row->phone !== '' ? $row->phone : null;
        $row->first_seen = $row->first_seen ? \Illuminate\Support\Carbon::parse($row->first_seen) : null;
        $row->last_seen  = $row->last_seen ? \Illuminate\Support\Carbon::parse($row->last_seen) : null;
        $row->expires_at = $row->expires_at ? \Illuminate\Support\Carbon::parse($row->expires_at) : null;

        return $row;
    }

    /**
     * The devices this customer has bought on, most recent first.
     *
     * @param Collection<int,Transaction> $sales
     * @return Collection<int,array{mac:string,ip:string|null,last_seen:\Illuminate\Support\Carbon}>
     */
    private function devices(Collection $sales): Collection
    {
        return $sales
            ->filter(fn ($sale) => (string) $sale->customer_mac !== '')
            ->groupBy('customer_mac')
            ->map(fn ($forMac, $mac) => [
                'mac'       => (string) $mac,
                'ip'        => $forMac->first()->customer_ip,
                'purchases' => $forMac->count(),
                'last_seen' => $forMac->max('created_at'),
            ])
            ->sortByDesc('last_seen')
            ->values();
    }

    /**
     * The voucher cards behind a customer's redemptions, so an ISP can see which batch they came
     * from and which agent sold them.
     *
     * @param Collection<int,string> $codes
     */
    private function vouchersFor(int $tenantId, Collection $codes): Collection
    {
        if ($codes->isEmpty()) {
            return collect();
        }

        return Voucher::withoutGlobalScopes()
            ->with('agent:id,name')
            ->where('tenant_id', $tenantId)
            ->whereIn('code', $codes->all())
            ->get()
            ->keyBy('code');
    }
}
