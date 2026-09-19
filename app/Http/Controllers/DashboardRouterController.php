<?php

namespace App\Http\Controllers;

use App\Models\RouterCommand;
use App\Models\TenantRouter;
use App\Services\MikrotikService;
use App\Services\ProvisioningScript;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

class DashboardRouterController extends Controller
{
    public function __construct(private ProvisioningScript $scripts)
    {
    }

    private function tenant()
    {
        return Auth::guard('tenant')->user()->tenant;
    }

    public function index(): View
    {
        $tenant  = $this->tenant();
        $routers = $tenant->routers()->orderBy('name')->get();

        $commands = $routers
            ->filter(fn (TenantRouter $router) => $router->isRadius())
            ->mapWithKeys(fn (TenantRouter $router) => [$router->id => $this->scripts->oneLiner($router)]);

        return view('dashboard.routers.index', compact('tenant', 'routers', 'commands'));
    }

    public function create(Request $request): View
    {
        $tenant = $this->tenant();
        $mode   = $request->query('mode') === 'api' ? TenantRouter::MODE_API : TenantRouter::MODE_RADIUS;

        return view('dashboard.routers.form', ['tenant' => $tenant, 'router' => null, 'mode' => $mode, 'command' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenant = $this->tenant();

        // The older way of working, where the platform logs in to a router it can reach.
        if ($request->input('mode') === TenantRouter::MODE_API) {
            $data = $this->validatedApi($request);

            // Left blank, the platform creates the router login itself: a name based on the
            // tenant name and a random password. The setup command then creates it on the router.
            $data['username'] = $data['username'] ?? TenantRouter::apiUsernameFor($tenant);
            $data['password'] = $data['password'] ?? Str::random(24);

            $data['tenant_id']        = $tenant->id;
            $data['auth_mode']        = TenantRouter::MODE_API;
            $data['nas_identifier']   = TenantRouter::generateNasIdentifier($tenant->id);
            $data['provision_token']  = TenantRouter::generateProvisionToken();
            $data['provision_status'] = 'pending';
            $data['status']           = 'unknown';

            TenantRouter::create($data);

            return redirect()->route('dashboard.routers.index')->with('success', 'Router added successfully.');
        }

        $validated = $request->validate(['name' => 'required|string|max:100']);

        $router = TenantRouter::create([
            'tenant_id'        => $tenant->id,
            'name'             => $validated['name'],
            'auth_mode'        => TenantRouter::MODE_RADIUS,
            'nas_identifier'   => TenantRouter::generateNasIdentifier($tenant->id),
            'provision_token'  => TenantRouter::generateProvisionToken(),
            'agent_token'      => TenantRouter::generateAgentToken(),
            'provision_status' => 'pending',
            'status'           => 'unknown',
        ]);

        return redirect()->route('dashboard.routers.edit', $router)
            ->with('success', 'Router added. Paste the setup command into the router to connect it.');
    }

    public function edit(TenantRouter $router): View
    {
        $tenant = $this->tenant();
        abort_unless($router->tenant_id === $tenant->id, 403);

        $router->getOrGenerateProvisionToken();

        return view('dashboard.routers.form', [
            'tenant'  => $tenant,
            'router'  => $router,
            'mode'    => $router->auth_mode,
            'command' => $router->isRadius() ? $this->scripts->oneLiner($router) : null,
        ]);
    }

    public function update(Request $request, TenantRouter $router): RedirectResponse
    {
        $tenant = $this->tenant();
        abort_unless($router->tenant_id === $tenant->id, 403);

        if ($router->isRadius()) {
            $router->update($request->validate(['name' => 'required|string|max:100']));

            return redirect()->route('dashboard.routers.index')->with('success', 'Router updated.');
        }

        $data = $this->validatedApi($request, $router);

        if (empty($data['password'])) {
            unset($data['password']);
        }

        $router->update($data);

        return redirect()->route('dashboard.routers.index')->with('success', 'Router updated.');
    }

    public function destroy(TenantRouter $router): RedirectResponse
    {
        $tenant = $this->tenant();
        abort_unless($router->tenant_id === $tenant->id, 403);
        $router->delete();

        return redirect()->route('dashboard.routers.index')->with('success', 'Router removed.');
    }

    /**
     * Ask a router to do something. It runs the next time the router checks in, within a minute.
     */
    public function command(Request $request, TenantRouter $router): RedirectResponse
    {
        $tenant = $this->tenant();
        abort_unless($router->tenant_id === $tenant->id, 403);
        abort_unless($router->isRadius(), 422, 'This router is connected the older way and cannot take commands.');

        $validated = $request->validate(['type' => 'required|in:' . RouterCommand::REBOOT . ',' . RouterCommand::KICK_ALL]);

        $router->queueCommand($validated['type'], [], Auth::guard('tenant')->user()->email);
        Audit::record('router.' . $validated['type'], $tenant->id, ['router' => $router->name], $router);

        return back()->with('success', $validated['type'] === RouterCommand::REBOOT
            ? 'Reboot requested. The router restarts within a minute.'
            : 'All customers will be disconnected within a minute.');
    }

    /**
     * Move a router that the platform logs in to over to the one-command setup.
     */
    public function switchToRadius(TenantRouter $router): RedirectResponse
    {
        $tenant = $this->tenant();
        abort_unless($router->tenant_id === $tenant->id, 403);

        $router->update([
            'auth_mode'        => TenantRouter::MODE_RADIUS,
            'agent_token'      => TenantRouter::generateAgentToken(),
            'provision_token'  => TenantRouter::generateProvisionToken(),
            'provision_status' => 'pending',
            'provision_note'   => null,
            'status'           => 'unknown',
        ]);

        return redirect()->route('dashboard.routers.edit', $router)
            ->with('success', 'Switched. Paste the new setup command into the router to finish.');
    }

    /**
     * Replace the router secrets. Use it when a setup command or agent address was shared by accident.
     * The router must run the setup command again, until it does it shows as offline.
     */
    public function rotateSecrets(TenantRouter $router): RedirectResponse
    {
        $tenant = $this->tenant();
        abort_unless($router->tenant_id === $tenant->id, 403);

        $router->update([
            'provision_token'  => TenantRouter::generateProvisionToken(),
            'agent_token'      => TenantRouter::generateAgentToken(),
            'provision_status' => 'pending',
        ]);
        Audit::record('router.secrets_rotated', $tenant->id, ['router' => $router->name], $router);

        return redirect()->route('dashboard.routers.edit', $router)
            ->with('success', 'New secrets created. Run the new setup command on the router.');
    }

    public function testConnection(Request $request): JsonResponse
    {
        $this->tenant(); // ensure authenticated

        $request->validate([
            'ip'       => ['required', 'ip', $this->privateIpRule('Must be a private IP address.')],
            'username' => 'required|string',
            'password' => 'required|string',
            'port'     => 'nullable|integer|min:1|max:65535',
        ]);

        try {
            $svc = new MikrotikService(
                $request->ip,
                $request->username,
                $request->password,
                (int) ($request->port ?? 8728),
                useRelay: (bool) config('services.mikrotik.relay_enabled', false),
                relayUrl: (string) config('services.mikrotik.relay_url', ''),
                relaySecret: (string) config('services.mikrotik.relay_secret', ''),
            );
            $connected = $svc->connect();
            $svc->disconnect();

            return $connected
                ? response()->json(['ok' => true, 'message' => 'Connection successful!'])
                : response()->json(['ok' => false, 'message' => 'Could not connect to router.'], 422);
        } catch (\Exception $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    private function validatedApi(Request $request, ?TenantRouter $existing = null): array
    {
        return $request->validate([
            'name'      => 'required|string|max:100',
            'router_ip' => ['required', 'ip', $this->privateIpRule('Router IP must be a private network address (e.g. 192.168.x.x).')],
            'username'  => 'nullable|string|max:50',
            'password'  => 'nullable|string|max:100',
            'port'      => 'nullable|integer|min:1|max:65535',
        ]);
    }

    /** Only RFC 1918 and loopback addresses, so the platform cannot be pointed at public hosts. */
    private function privateIpRule(string $message): \Closure
    {
        return function ($_attribute, $value, $fail) use ($message) {
            $long = ip2long($value);

            foreach ([
                ['10.0.0.0', '10.255.255.255'],
                ['172.16.0.0', '172.31.255.255'],
                ['192.168.0.0', '192.168.255.255'],
                ['127.0.0.0', '127.255.255.255'],
            ] as [$start, $end]) {
                if ($long !== false && $long >= ip2long($start) && $long <= ip2long($end)) {
                    return;
                }
            }

            $fail($message);
        };
    }
}
