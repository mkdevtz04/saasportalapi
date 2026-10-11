<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RouterCommand;
use App\Models\TenantRouter;
use App\Models\Transaction;
use App\Services\ProvisioningScript;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The heartbeat of a router. A script on the router calls this every minute, so the
 * platform never has to reach the router: the router reports in and takes any waiting
 * commands back with it.
 */
class RouterAgentController extends Controller
{
    public function __construct(private ProvisioningScript $scripts)
    {
    }

    public function poll(Request $request, string $token): Response
    {
        $router = TenantRouter::where('agent_token', $token)->first();

        if (! $router) {
            return $this->plain("# unknown router\n", 404);
        }

        $update = [
            'status'           => 'online',
            'last_seen_at'     => now(),
            'public_ip'        => $request->ip(),
            'routeros_version' => $this->clean($request->query('v'), 40),
            'router_uptime'    => $this->clean($request->query('u'), 40),
            'active_users'     => max(0, min(100000, (int) $request->query('n', 0))),
        ];

        // Older agent scripts do not send this, and then it stays unknown.
        if ($request->query->has('m')) {
            $update['identity_ok'] = $request->query('m') === '1';
        }

        // A router that is polling has clearly run the setup script.
        if ($router->provision_status !== 'completed') {
            $update += ['provision_status' => 'completed', 'provisioned_at' => now(), 'provision_note' => null];
        }

        $router->update($update);

        $commands = $this->takePendingCommands($router);
        $this->markAccessGiven($router, $commands);

        return $this->plain($this->scripts->agentCommands($commands));
    }

    /**
     * The commands this router should act on now.
     *
     * Marking one delivered is a guess: the platform knows it wrote the reply, not that the reply
     * arrived. The router fetches it over the internet, and a fetch that times out part way through
     * leaves the platform certain it was delivered and the router with nothing. A command handed
     * out once and then dropped is gone for good — which for a customer who has just paid means a
     * code that will never work, on a router that has never heard of them.
     *
     * So the two commands that decide whether a customer is online keep being handed out for a
     * while after the first time. Both are safe to repeat: creating a hotspot user removes any
     * existing one first, and removing one that is already gone does nothing. The rest are handed
     * out exactly once, because repeating them would be its own kind of damage — a reboot every
     * ten seconds, or a customer kicked off again and again.
     *
     * Marking happens inside the lock that selected the rows, so two overlapping polls cannot both
     * take the same one, and only the first delivery sets the clock the window is measured from.
     *
     * @return Collection<int,RouterCommand>
     */
    private function takePendingCommands(TenantRouter $router): Collection
    {
        return DB::transaction(function () use ($router) {
            $repeatable = [RouterCommand::ADD_USER, RouterCommand::REMOVE_USER];
            $until      = now()->subSeconds((int) config('router.redeliver_seconds', 120));

            $commands = RouterCommand::withoutGlobalScopes()
                ->where('router_id', $router->id)
                ->where(function ($query) use ($repeatable, $until) {
                    $query->where('status', 'pending')
                        ->orWhere(fn ($unconfirmed) => $unconfirmed
                            ->where('status', 'delivered')
                            ->whereIn('type', $repeatable)
                            ->where('delivered_at', '>', $until));
                })
                ->orderBy('id')
                ->limit(20)
                ->lockForUpdate()
                ->get();

            $first = $commands->where('status', 'pending')->pluck('id');

            if ($first->isNotEmpty()) {
                RouterCommand::withoutGlobalScopes()
                    ->whereIn('id', $first)
                    ->update(['status' => 'delivered', 'delivered_at' => now()]);
            }

            return $commands;
        });
    }

    /**
     * A customer's access is on the router once it has taken the command that creates their user.
     * Their transaction stops saying "connecting".
     *
     * @param Collection<int,RouterCommand> $commands
     */
    private function markAccessGiven(TenantRouter $router, Collection $commands): void
    {
        $logins = $commands->where('type', RouterCommand::ADD_USER)->pluck('reference')->filter()->values();

        if ($logins->isNotEmpty()) {
            Transaction::withoutGlobalScopes()
                ->where('tenant_id', $router->tenant_id)
                ->whereIn('voucher_code', $logins)
                ->where('provision_status', '!=', 'done')
                ->update(['provision_status' => 'done', 'provision_error' => null]);
        }
    }

    /** Values reported by a router end up in dashboards, so keep them to plain text. */
    private function clean(mixed $value, int $max): ?string
    {
        $clean = substr((string) preg_replace('/[^A-Za-z0-9.\-+:]/', '', (string) $value), 0, $max);

        return $clean !== '' ? $clean : null;
    }

    private function plain(string $body, int $status = 200): Response
    {
        return response($body, $status, [
            'Content-Type'  => 'text/plain; charset=utf-8',
            'Cache-Control' => 'no-store',
        ]);
    }
}
