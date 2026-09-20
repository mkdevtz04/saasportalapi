<?php

namespace App\Http\Controllers;

use App\Models\AgentWallet;
use App\Models\TenantUser;
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
 * The signed-in person's own account: their name, the email they sign in with, and their password.
 *
 * Separate from dashboard settings, which belong to the business. Owners and agents both land
 * here and both may only ever change their own row, because the account always comes from the
 * session and never from anything the form sends.
 *
 * Changing the email or the password needs the current password. Without that, a session someone
 * walked away from is enough to take the account over and lock the real owner out of the money.
 */
class ProfileController extends Controller
{
    public function edit(): View
    {
        $user = $this->user();

        return view('profile.edit', [
            'user'   => $user,
            'tenant' => $user->tenant,
            // The POS layout, which agents see, expects these two by name.
            'agent'  => $user,
            'wallet' => $user->wallet ?? new AgentWallet(['balance' => 0]),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $this->user();

        $validated = $request->validate([
            'name'  => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique('tenant_users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:20'],
        ]);

        $wasEmail     = (string) $user->email;
        $emailChanged = Str::lower($validated['email']) !== Str::lower($wasEmail);

        // The email is what they sign in with, so changing it is a credential change, not a detail.
        if ($emailChanged) {
            $this->confirmCurrentPassword($request, $user);
        }

        $user->update([
            'name'  => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
        ]);

        if ($emailChanged) {
            Audit::record('profile.email_changed', $user->tenant_id, [
                'from' => $wasEmail,
                'to'   => $user->email,
            ], $user);

            return back()->with('success', 'Saved. You now sign in with ' . $user->email . '.');
        }

        return back()->with('success', 'Profile saved.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $user = $this->user();

        $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $this->confirmCurrentPassword($request, $user);

        $user->update(['password' => $request->input('password')]);

        // A new password should end the sessions the old one opened. Regenerating the id keeps
        // this browser signed in, and a fresh remember token drops "remember me" everywhere else.
        $user->setRememberToken(Str::random(60));
        $user->save();
        $request->session()->regenerate();

        Audit::record('profile.password_changed', $user->tenant_id, [], $user);

        return back()->with('success', 'Password changed. Any other device that was signed in has been signed out.');
    }

    /**
     * Never says whether the field was empty or merely wrong, and never reveals anything about
     * the stored password.
     */
    private function confirmCurrentPassword(Request $request, TenantUser $user): void
    {
        $current = (string) $request->input('current_password');

        if ($current === '' || ! Hash::check($current, (string) $user->password)) {
            Audit::record('profile.wrong_password', $user->tenant_id, [], $user);

            throw ValidationException::withMessages([
                'current_password' => 'That is not your current password.',
            ]);
        }
    }

    /** Always the signed-in account. Nothing in the request may choose whose profile this is. */
    private function user(): TenantUser
    {
        return Auth::guard('tenant')->user();
    }
}
