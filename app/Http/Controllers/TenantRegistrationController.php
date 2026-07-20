<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\TenantSettings;
use App\Models\TenantUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class TenantRegistrationController extends Controller
{
    public function show(): View
    {
        return view('register');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'business_name' => ['required', 'string', 'max:100'],
            'name'          => ['required', 'string', 'max:100'],
            'email'         => ['required', 'email', 'unique:tenant_users,email'],
            'phone'         => ['required', 'string', 'max:20'],
            'password'      => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $subdomain = $this->uniqueSubdomain($validated['business_name']);

        DB::transaction(function () use ($validated, $subdomain) {
            $tenant = Tenant::create([
                'name'          => $validated['business_name'],
                'subdomain'     => $subdomain,
                'status'        => 'trial',
                'trial_ends_at' => now()->addDays(14),
            ]);

            // Empty settings row so tenant always has a settings record to update
            TenantSettings::create([
                'tenant_id'    => $tenant->id,
                'brand_color'  => '#0066cc',
            ]);

            $user = TenantUser::create([
                'tenant_id' => $tenant->id,
                'name'      => $validated['name'],
                'email'     => $validated['email'],
                'phone'     => $validated['phone'],
                'password'  => $validated['password'],
                'role'      => 'owner',
            ]);

            Auth::guard('tenant')->login($user);
        });

        return redirect()->route('onboarding.router')
            ->with('success', 'Welcome! Let\'s set up your first router.');
    }

    private function uniqueSubdomain(string $businessName): string
    {
        $base = Str::slug($businessName);
        $subdomain = $base;
        $counter = 2;

        while (Tenant::where('subdomain', $subdomain)->exists()) {
            $subdomain = $base . '-' . $counter++;
        }

        return $subdomain;
    }
}
