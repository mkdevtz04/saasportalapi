<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlatformBillingLog;
use App\Models\Tenant;
use App\Models\Transaction;
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

    public function suspend(Tenant $tenant): RedirectResponse
    {
        $tenant->update(['status' => 'suspended']);
        return redirect()->route('admin.tenants.show', $tenant)
            ->with('success', $tenant->name . ' has been suspended.');
    }

    public function activate(Tenant $tenant): RedirectResponse
    {
        $tenant->update(['status' => 'active']);
        return redirect()->route('admin.tenants.show', $tenant)
            ->with('success', $tenant->name . ' is now active.');
    }

    public function setFee(Tenant $tenant, Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'monthly_fee_tzs' => 'nullable|integer|min:0|max:1000000',
        ]);
        $tenant->update(['monthly_fee_tzs' => $validated['monthly_fee_tzs'] ?: null]);
        return back()->with('success', 'Monthly fee updated.');
    }
}
