<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlatformBillingLog;
use App\Models\RouterCommand;
use App\Models\Tenant;
use App\Models\Transaction;
use App\Support\Audit;
use App\Services\AgentAccess;
use App\Services\Radius\RadiusAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminTenantController extends Controller
{
    public function index(Request $request): View
    {
        $query = Tenant::with('wallet');

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }
        if ($search = $request->get('q')) {
            $query->where(fn($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('subdomain', 'like', "%{$search}%"));
        }

        $tenants = $query->orderByDesc('created_at')->paginate(20)->withQueryString();

        return view('admin.tenants.index', compact('tenants'));
    }

    public function show(Tenant $tenant): View
    {
        $tenant->load([
            'wallet',
            'withdrawalRequests' => fn($q) => $q->orderByDesc('created_at')->limit(10),
        ]);

        $platformEarnings  = PlatformBillingLog::where('tenant_id', $tenant->id)->sum('amount');
        $monthRevenue      = Transaction::where('tenant_id', $tenant->id)
            ->where('status', 'completed')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('amount');
        $totalTransactions = Transaction::where('tenant_id', $tenant->id)
            ->where('status', 'completed')->count();
        $recentTransactions = Transaction::where('tenant_id', $tenant->id)
            ->orderByDesc('created_at')->limit(10)->get();

        return view('admin.tenants.show', compact(
            'tenant', 'platformEarnings', 'monthRevenue', 'totalTransactions', 'recentTransactions'
        ));
    }

    public function suspend(Tenant $tenant, RadiusAccess $radius, AgentAccess $agent): RedirectResponse
    {
        $tenant->update(['status' => 'suspended']);

        // A suspended ISP loses access at once: every current login is blocked and every
        // router is told to disconnect its customers on its next check-in.
        $radius->suspendTenant($tenant->id);
        $agent->suspendTenant($tenant->id);
        Audit::record('tenant.suspended', $tenant->id, [], $tenant);
        $tenant->routers()->get()->each(function ($router) {
            if ($router->runsAgent()) {
                $router->queueCommand(RouterCommand::KICK_ALL, [], 'platform admin');
            }
        });

        return redirect()->route('admin.tenants.show', $tenant)
            ->with('success', $tenant->name . ' has been suspended.');
    }

    public function activate(Tenant $tenant, RadiusAccess $radius, AgentAccess $agent): RedirectResponse
    {
        $tenant->update(['status' => 'active']);
        $radius->resumeTenant($tenant->id);
        $agent->resumeTenant($tenant->id);
        Audit::record('tenant.activated', $tenant->id, [], $tenant);
        return redirect()->route('admin.tenants.show', $tenant)
            ->with('success', $tenant->name . ' is now active.');
    }
}
