<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\TenantPackage;
use App\Models\TenantRouter;
use App\Services\MikrotikService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class OnboardingController extends Controller
{
    private function tenant(): Tenant
    {
        return Auth::guard('tenant')->user()->tenant;
    }

    // ── Step 1a: Router form ───────────────────────────────────────────────────

    public function router(): View
    {
        $tenant = $this->tenant();

        // If they already saved a router in this session, jump to the script view
        if ($routerId = session('onboarding_router_id')) {
            $router = TenantRouter::where('id', $routerId)->first();
            if ($router && $router->tenant_id === $tenant->id) {
                return view('onboarding.router-script', compact('tenant', 'router'));
            }
        }

        return view('onboarding.router', compact('tenant'));
    }

    public function storeRouter(Request $request): RedirectResponse
    {
        $tenant = $this->tenant();

        $request->validate([
            'name'      => 'required|string|max:100',
            'router_ip' => ['required', 'ip', function ($_attr, $value, $fail) {
                // SSRF protection: only allow RFC 1918 private ranges
                $long = ip2long($value);
                foreach ([['10.0.0.0','10.255.255.255'],['172.16.0.0','172.31.255.255'],['192.168.0.0','192.168.255.255'],['127.0.0.0','127.255.255.255']] as [$s, $e]) {
                    if ($long >= ip2long($s) && $long <= ip2long($e)) return;
                }
                $fail('Router IP must be a private network address (e.g. 192.168.x.x).');
            }],
            'username'  => 'required|string|max:50',
            'password'  => 'required|string|max:100',
            'port'      => 'required|integer|min:1|max:65535',
        ]);

        $router = TenantRouter::create([
            'tenant_id'      => $tenant->id,
            'name'           => $request->name,
            'router_ip'      => $request->router_ip,
            'username'       => $request->username,
            'password'       => $request->password, // model accessor encrypts on write
            'port'           => $request->port,
            'nas_identifier' => TenantRouter::generateNasIdentifier($tenant->id),
        ]);

        session(['onboarding_router_id' => $router->id]);

        return redirect()->route('onboarding.router');
    }

    // AJAX: test router connection without saving
    public function testRouter(Request $request): JsonResponse
    {
        $request->validate([
            'router_ip' => ['required', 'ip'],
            'username'  => 'required|string|max:50',
            'password'  => 'required|string|max:100',
            'port'      => 'required|integer|min:1|max:65535',
        ]);

        // SSRF protection
        $long = ip2long($request->router_ip);
        $allowed = false;
        foreach ([['10.0.0.0','10.255.255.255'],['172.16.0.0','172.31.255.255'],['192.168.0.0','192.168.255.255'],['127.0.0.0','127.255.255.255']] as [$s, $e]) {
            if ($long >= ip2long($s) && $long <= ip2long($e)) { $allowed = true; break; }
        }
        if (! $allowed) {
            return response()->json(['success' => false, 'message' => 'Invalid IP address.'], 422);
        }

        $mikrotik = new MikrotikService(
            $request->router_ip,
            $request->username,
            $request->password,
            (int) $request->port,
        );

        $connected = $mikrotik->connect();
        if ($connected) $mikrotik->disconnect();

        return response()->json([
            'success' => $connected,
            'message' => $connected
                ? 'Connection successful! Router is reachable.'
                : 'Could not connect. Check IP, credentials, and that API is enabled on port ' . $request->port . '.',
        ]);
    }

    // ── Step 2: Packages ───────────────────────────────────────────────────────

    public function packages(): View
    {
        $tenant = $this->tenant();

        $defaults = [
            ['name' => 'Bronze', 'price' => 500,  'duration_hours' => 6,   'speed_down_mbps' => 1, 'speed_up_mbps' => 1, 'mikrotik_profile' => 'bronze'],
            ['name' => 'Silver', 'price' => 1000, 'duration_hours' => 24,  'speed_down_mbps' => 2, 'speed_up_mbps' => 2, 'mikrotik_profile' => 'silver'],
            ['name' => 'Gold',   'price' => 8000, 'duration_hours' => 168, 'speed_down_mbps' => 5, 'speed_up_mbps' => 5, 'mikrotik_profile' => 'gold'],
        ];

        return view('onboarding.packages', compact('tenant', 'defaults'));
    }

    public function storePackages(Request $request): RedirectResponse
    {
        $tenant = $this->tenant();

        $request->validate([
            'packages'                       => 'required|array|min:1|max:10',
            'packages.*.name'                => 'required|string|max:60',
            'packages.*.price'               => 'required|integer|min:1',
            'packages.*.duration_hours'      => 'required|integer|min:1',
            'packages.*.speed_down_mbps'     => 'required|integer|min:1',
            'packages.*.speed_up_mbps'       => 'required|integer|min:1',
            'packages.*.mikrotik_profile'    => 'required|string|max:50|regex:/^[a-z0-9_-]+$/i',
        ]);

        // Delete any previously created onboarding packages to avoid duplicates on re-submit
        $tenant->packages()->delete();

        foreach ($request->packages as $index => $pkg) {
            TenantPackage::create([
                'tenant_id'        => $tenant->id,
                'name'             => $pkg['name'],
                'price'            => $pkg['price'],
                'duration_hours'   => $pkg['duration_hours'],
                'speed_down_mbps'  => $pkg['speed_down_mbps'],
                'speed_up_mbps'    => $pkg['speed_up_mbps'],
                'mikrotik_profile' => strtolower($pkg['mikrotik_profile']),
                'is_active'        => true,
                'sort_order'       => $index,
            ]);
        }

        return redirect()->route('onboarding.payment');
    }

    // ── Step 3: Payment settings ───────────────────────────────────────────────

    public function payment(): View
    {
        $tenant   = $this->tenant();
        $settings = $tenant->settings;
        return view('onboarding.payment', compact('tenant', 'settings'));
    }

    public function storePayment(Request $request): RedirectResponse
    {
        $tenant = $this->tenant();

        $request->validate([
            'withdrawal_number' => 'required|string|max:30',
        ]);

        $tenant->settings()->updateOrCreate(
            ['tenant_id' => $tenant->id],
            ['withdrawal_number' => $request->withdrawal_number]
        );

        // Mark tenant active now that setup is complete
        $tenant->update(['status' => 'active']);

        // Clear onboarding session data
        session()->forget('onboarding_router_id');

        return redirect('/dashboard')
            ->with('success', 'Setup complete! Your portal is live at ' . $tenant->subdomain . '.trinetpay.online');
    }
}
