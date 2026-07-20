<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class TenantLoginController extends Controller
{
    public function show(): View|RedirectResponse
    {
        if (Auth::guard('tenant')->check()) {
            return redirect()->route($this->defaultRoute());
        }
        return view('login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        if (Auth::guard('tenant')->attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();
            return redirect()->intended(route($this->defaultRoute()));
        }

        return back()
            ->withErrors(['email' => 'These credentials do not match our records.'])
            ->onlyInput('email');
    }

    private function defaultRoute(): string
    {
        $tenant = Auth::guard('tenant')->user()?->tenant;
        return $tenant?->isActive() ? 'dashboard.home' : 'onboarding.router';
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('tenant')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/');
    }
}
