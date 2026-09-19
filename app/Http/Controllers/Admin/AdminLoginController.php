<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ThrottlesLogins;
use App\Http\Controllers\Controller;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AdminLoginController extends Controller
{
    use ThrottlesLogins;

    public function show(): View|RedirectResponse
    {
        if (Auth::guard('admin')->check()) {
            return redirect()->route('admin.dashboard');
        }
        return view('admin.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        $this->ensureNotLockedOut($request, 'admin');

        if (!Auth::guard('admin')->attempt($credentials, $request->boolean('remember'))) {
            $this->recordFailedLogin($request, 'admin');
            Audit::record('auth.admin_login_failed', null, ['email' => strtolower((string) $request->input('email'))]);

            return back()->withErrors(['email' => 'Invalid credentials.'])->onlyInput('email');
        }

        $this->clearFailedLogins($request, 'admin');
        $request->session()->regenerate();
        Audit::record('auth.admin_login', null, ['email' => strtolower((string) $request->input('email'))]);
        return redirect()->route('admin.dashboard');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('admin')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('admin.login');
    }
}
