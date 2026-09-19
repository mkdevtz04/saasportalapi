<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Lets platform support open an ISP's dashboard to help them. Every start and end is recorded,
 * a banner is shown the whole time, and money actions are blocked while it lasts.
 */
class AdminImpersonationController extends Controller
{
    public function start(Tenant $tenant): RedirectResponse
    {
        $owner = TenantUser::where('tenant_id', $tenant->id)->where('role', 'owner')->orderBy('id')->first();

        if (! $owner) {
            return back()->withErrors(['impersonate' => 'This ISP has no owner account to open.']);
        }

        $admin = Auth::guard('admin')->user();

        Auth::guard('tenant')->login($owner);
        session(['impersonated_by' => $admin->email]);

        Audit::record('impersonation.started', $tenant->id, ['as' => $owner->email], $tenant);

        return redirect()->route('dashboard.home');
    }

    public function stop(): RedirectResponse
    {
        $tenantId = Auth::guard('tenant')->user()?->tenant_id;

        if (session()->has('impersonated_by')) {
            Audit::record('impersonation.ended', $tenantId);
        }

        Auth::guard('tenant')->logout();
        session()->forget('impersonated_by');

        return Auth::guard('admin')->check()
            ? redirect()->route('admin.dashboard')
            : redirect()->route('login');
    }
}
