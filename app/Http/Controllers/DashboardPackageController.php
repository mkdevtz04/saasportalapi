<?php

namespace App\Http\Controllers;

use App\Models\TenantPackage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardPackageController extends Controller
{
    private function tenant()
    {
        return Auth::guard('tenant')->user()->tenant;
    }

    public function index(): View
    {
        $tenant   = $this->tenant();
        $packages = $tenant->packages()->orderBy('sort_order')->orderBy('price')->get();
        return view('dashboard.packages.index', compact('tenant', 'packages'));
    }

    public function create(): View
    {
        $tenant = $this->tenant();
        return view('dashboard.packages.form', ['tenant' => $tenant, 'package' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenant              = $this->tenant();
        $data                = $this->validated($request);
        $data['tenant_id']   = $tenant->id;
        $data['sort_order']  = ($tenant->packages()->max('sort_order') ?? 0) + 1;
        $data['is_active']   = true;

        TenantPackage::create($data);

        return redirect()->route('dashboard.packages.index')->with('success', 'Package created.');
    }

    public function edit(TenantPackage $package): View
    {
        $tenant = $this->tenant();
        abort_unless($package->tenant_id === $tenant->id, 403);
        return view('dashboard.packages.form', compact('tenant', 'package'));
    }

    public function update(Request $request, TenantPackage $package): RedirectResponse
    {
        $tenant = $this->tenant();
        abort_unless($package->tenant_id === $tenant->id, 403);

        $package->update($this->validated($request));

        return redirect()->route('dashboard.packages.index')->with('success', 'Package updated.');
    }

    public function destroy(TenantPackage $package): RedirectResponse
    {
        $tenant = $this->tenant();
        abort_unless($package->tenant_id === $tenant->id, 403);
        $package->delete();
        return redirect()->route('dashboard.packages.index')->with('success', 'Package deleted.');
    }

    public function toggle(TenantPackage $package): JsonResponse
    {
        $tenant = $this->tenant();
        abort_unless($package->tenant_id === $tenant->id, 403);

        $package->update(['is_active' => ! $package->is_active]);

        return response()->json(['is_active' => $package->is_active]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name'             => 'required|string|max:100',
            'price'            => 'required|integer|min:100',
            'duration_hours'   => 'required|integer|min:1',
            'speed_down_mbps'  => 'required|integer|min:1|max:1000',
            'speed_up_mbps'    => 'required|integer|min:1|max:1000',
            'data_cap_mb'      => 'nullable|integer|min:1',
            'mikrotik_profile' => 'required|string|max:100',
            'validity_type'    => 'required|in:strict,first_login',
        ]);
    }
}
