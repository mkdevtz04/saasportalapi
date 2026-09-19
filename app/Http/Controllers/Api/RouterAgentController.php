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
     * Hand out each command once. They are marked delivered inside the same lock that
     * selects them, so two overlapping polls can never both receive the same reboot.
     *
     * @return Collection<int,RouterCommand>
     */
    private function takePendingCommands(TenantRouter $router): Collection
    {
        return DB::transaction(function () use ($router) {
            $commands = RouterCommand::withoutGlobalScopes()
                ->where('router_id', $router->id)
                ->where('status', 'pending')
                ->orderBy('id')
                ->limit(20)
                ->lockForUpdate()
                ->get();

            if ($commands->isNotEmpty()) {
                RouterCommand::withoutGlobalScopes()
                    ->whereIn('id', $commands->pluck('id'))
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
