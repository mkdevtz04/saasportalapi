<?php

namespace App\Http\Controllers;

use App\Models\RouterCommand;
use App\Services\Radius\RadiusAccess;
use App\Services\Radius\RadiusReports;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Who is online right now, how much data is being used, and a way to cut someone off.
 * The numbers come from the usage reports that routers send to RADIUS.
 */
class DashboardSessionController extends Controller
{
    private function tenant()
    {
        return Auth::guard('tenant')->user()->tenant;
    }

    public function index(RadiusReports $reports): View
    {
        $tenant   = $this->tenant();
        $sessions = $reports->activeSessions($tenant->id);

        $zone   = now('Africa/Dar_es_Salaam')->startOfDay();
        $today  = $reports->usageSince($tenant->id, $zone);
        $byDay  = $reports->usageByDay($tenant->id, 7);

        $hasRadiusRouter = $tenant->routers()->where('auth_mode', 'radius')->exists();

        return view('dashboard.sessions', compact('tenant', 'sessions', 'today', 'byDay', 'hasRadiusRouter'));
    }

    /**
     * Disconnect a customer and remove their access, so a device that logs in by address
     * cannot simply reconnect. The login must belong to this tenant.
     */
    public function disconnect(Request $request, RadiusAccess $radius): RedirectResponse
    {
        $tenant    = $this->tenant();
        $validated = $request->validate(['username' => 'required|string|max:64']);
        $username  = $validated['username'];

        $source = DB::table('radcheck')
            ->where('tenant_id', $tenant->id)
            ->where('username', $username)
            ->value('source');

        abort_if($source === null, 404);

        // A purchase creates a code login and a device login. Removing one removes both.
        $base = preg_replace('/:mac$/', '', (string) $source);
        $logins = DB::table('radcheck')
            ->where('tenant_id', $tenant->id)
            ->whereIn('source', [$base, $base . ':mac'])
            ->distinct()
            ->pluck('username');

        foreach ($logins as $login) {
            $radius->revoke($login);
        }

        foreach ($tenant->routers()->where('auth_mode', 'radius')->get() as $router) {
            foreach ($logins as $login) {
                $router->queueCommand(RouterCommand::KICK_USER, ['username' => $login], Auth::guard('tenant')->user()->email);
            }
        }

        Audit::record('session.revoked', $tenant->id, ['logins' => $logins->count(), 'source' => $base]);

        return back()->with('success', 'The customer will be disconnected within a minute and cannot reconnect.');
    }
}
