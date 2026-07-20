<?php

namespace App\Http\Controllers;

use App\Models\AgentWallet;
use App\Models\TenantUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardAgentController extends Controller
{
    private function tenant()
    {
        return Auth::guard('tenant')->user()->tenant;
    }

    public function index(): View
    {
        $tenant = $this->tenant();
        $agents = TenantUser::with('wallet')
            ->where('tenant_id', $tenant->id)
            ->where('role', 'agent')
            ->get();

        return view('dashboard.agents.index', compact('tenant', 'agents'));
    }

    public function create(): View
    {
        return view('dashboard.agents.create', ['tenant' => $this->tenant()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenant = $this->tenant();

        $validated = $request->validate([
            'name'     => 'required|string|max:100',
            'email'    => 'required|email|unique:tenant_users,email',
            'phone'    => 'nullable|string|max:20',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $agent = TenantUser::create([
            'tenant_id' => $tenant->id,
            'name'      => $validated['name'],
            'email'     => $validated['email'],
            'phone'     => $validated['phone'] ?? null,
            'password'  => $validated['password'],
            'role'      => 'agent',
        ]);

        // Create empty wallet
        AgentWallet::create(['tenant_user_id' => $agent->id, 'balance' => 0]);

        return redirect()->route('dashboard.agents.index')
            ->with('success', 'Agent "' . $agent->name . '" created. Share their login credentials.');
    }

    public function topup(TenantUser $agent, Request $request): RedirectResponse
    {
        $tenant = $this->tenant();
        abort_unless($agent->tenant_id === $tenant->id && $agent->isAgent(), 403);

        $validated = $request->validate([
            'amount' => 'required|integer|min:500',
        ]);

        $wallet = $agent->wallet ?? AgentWallet::create([
            'tenant_user_id' => $agent->id,
            'balance'        => 0,
        ]);

        $wallet->credit(
            $validated['amount'],
            'TOPUP-' . now()->format('YmdHis'),
            'Top-up by ' . Auth::guard('tenant')->user()->name
        );

        return back()->with('success', number_format($validated['amount']) . ' TZS added to ' . $agent->name . "'s wallet.");
    }

    public function destroy(TenantUser $agent): RedirectResponse
    {
        $tenant = $this->tenant();
        abort_unless($agent->tenant_id === $tenant->id && $agent->isAgent(), 403);

        $agent->wallet?->delete();
        $agent->delete();

        return redirect()->route('dashboard.agents.index')->with('success', 'Agent removed.');
    }
}
