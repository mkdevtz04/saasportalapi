<?php

namespace App\Http\Controllers;

use App\Models\TenantRouter;
use App\Services\MikrotikService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardRouterController extends Controller
{
    private function tenant()
    {
        return Auth::guard('tenant')->user()->tenant;
    }

    public function index(): View
    {
        $tenant  = $this->tenant();
        $routers = $tenant->routers()->orderBy('name')->get();
        return view('dashboard.routers.index', compact('tenant', 'routers'));
    }

    public function create(): View
    {
        $tenant = $this->tenant();
        return view('dashboard.routers.form', ['tenant' => $tenant, 'router' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenant = $this->tenant();
        $data   = $this->validated($request);

        $data['tenant_id']        = $tenant->id;
        $data['nas_identifier']   = TenantRouter::generateNasIdentifier($tenant->id);
        $data['provision_token']  = TenantRouter::generateProvisionToken();
        $data['provision_status'] = 'pending';
        $data['status']           = 'unknown';

        TenantRouter::create($data);

        return redirect()->route('dashboard.routers.index')->with('success', 'Router added successfully.');
    }

    public function edit(TenantRouter $router): View
    {
        $tenant = $this->tenant();
        abort_unless($router->tenant_id === $tenant->id, 403);
        $router->getOrGenerateProvisionToken();
        return view('dashboard.routers.form', compact('tenant', 'router'));
    }

    public function update(Request $request, TenantRouter $router): RedirectResponse
    {
        $tenant = $this->tenant();
        abort_unless($router->tenant_id === $tenant->id, 403);

        $data = $this->validated($request, $router);

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

    public function testConnection(Request $request): JsonResponse
    {
        $this->tenant(); // ensure authenticated

        $request->validate([
            'ip'       => ['required', 'ip', function ($_attr, $value, $fail) {
                $long = ip2long($value);
                foreach ([
                    ['10.0.0.0', '10.255.255.255'],
                    ['172.16.0.0', '172.31.255.255'],
                    ['192.168.0.0', '192.168.255.255'],
                    ['127.0.0.0', '127.255.255.255'],
                ] as [$s, $e]) {
                    if ($long >= ip2long($s) && $long <= ip2long($e)) return;
                }
                $fail('Must be a private IP address.');
            }],
            'username' => 'required|string',
            'password' => 'required|string',
            'port'     => 'nullable|integer|min:1|max:65535',
        ]);

        try {
            $svc       = new MikrotikService($request->ip, $request->username, $request->password, (int) ($request->port ?? 8728));
            $connected = $svc->connect();
            $svc->disconnect();

            return $connected
                ? response()->json(['ok' => true,  'message' => 'Connection successful!'])
                : response()->json(['ok' => false, 'message' => 'Could not connect to router.'], 422);
        } catch (\Exception $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    private function validated(Request $request, ?TenantRouter $existing = null): array
    {
        return $request->validate([
            'name'      => 'required|string|max:100',
            'router_ip' => ['required', 'ip', function ($_attr, $value, $fail) {
                $long = ip2long($value);
                foreach ([
                    ['10.0.0.0', '10.255.255.255'],
                    ['172.16.0.0', '172.31.255.255'],
                    ['192.168.0.0', '192.168.255.255'],
                    ['127.0.0.0', '127.255.255.255'],
                ] as [$s, $e]) {
                    if ($long >= ip2long($s) && $long <= ip2long($e)) return;
                }
                $fail('Router IP must be a private network address (e.g. 192.168.x.x).');
            }],
            'username'  => 'required|string|max:50',
            'password'  => $existing ? 'nullable|string|max:100' : 'required|string|max:100',
            'port'      => 'nullable|integer|min:1|max:65535',
        ]);
    }
}
