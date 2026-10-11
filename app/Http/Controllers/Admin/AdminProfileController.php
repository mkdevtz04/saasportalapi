<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlatformAdmin;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * The signed-in platform admin's own account: their name, sign-in email, phone and password.
 *
 * The mirror of the tenant-side ProfileController, for the one account that runs the platform
 * itself. Changing the email or the password needs the current one first, for the same reason:
 * a session someone walked away from should not be enough to take over the account that can
 * suspend any ISP or approve any withdrawal.
 */
class AdminProfileController extends Controller
{
    public function edit(): View
    {
        return view('admin.profile', ['admin' => $this->admin()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $admin = $this->admin();

        $validated = $request->validate([
            'name'  => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique('platform_admins', 'email')->ignore($admin->id)],
            'phone' => ['nullable', 'string', 'max:20'],
        ]);

        $wasEmail     = (string) $admin->email;
        $emailChanged = Str::lower($validated['email']) !== Str::lower($wasEmail);

        if ($emailChanged) {
            $this->confirmCurrentPassword($request, $admin);
        }

        $admin->update([
            'name'  => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
        ]);

        if ($emailChanged) {
            Audit::record('profile.admin_email_changed', null, [
                'from' => $wasEmail,
                'to'   => $admin->email,
            ], $admin);

            return back()->with('success', 'Saved. You now sign in with ' . $admin->email . '.');
        }

        return back()->with('success', 'Profile saved.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $admin = $this->admin();

        $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $this->confirmCurrentPassword($request, $admin);

        $admin->update(['password' => $request->input('password')]);

        $admin->setRememberToken(Str::random(60));
        $admin->save();
        $request->session()->regenerate();

        Audit::record('profile.admin_password_changed', null, [], $admin);

        return back()->with('success', 'Password changed. Any other device that was signed in has been signed out.');
    }

    private function confirmCurrentPassword(Request $request, PlatformAdmin $admin): void
    {
        $current = (string) $request->input('current_password');

        if ($current === '' || ! Hash::check($current, (string) $admin->password)) {
            Audit::record('profile.admin_wrong_password', null, [], $admin);

            throw ValidationException::withMessages([
                'current_password' => 'That is not your current password.',
            ]);
        }
    }

    /** Always the signed-in admin. Nothing in the request may choose whose profile this is. */
    private function admin(): PlatformAdmin
    {
        return Auth::guard('admin')->user();
    }
}
