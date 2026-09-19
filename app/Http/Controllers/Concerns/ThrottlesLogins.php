<?php

namespace App\Http\Controllers\Concerns;

use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Brute-force protection for a login form.
 *
 * Two limits apply at once. One counts attempts from a single address, so someone cannot
 * hammer the form. The other counts attempts against a single account from anywhere, so a
 * guessing attack spread over many addresses is stopped as well. A correct login clears both.
 */
trait ThrottlesLogins
{
    /** Attempts allowed per minute from one address for one account. */
    private const PER_ADDRESS = 5;

    /** Attempts allowed per 15 minutes against one account from any address. */
    private const PER_ACCOUNT = 15;

    /** Stops the request with an error when either limit has been reached. */
    protected function ensureNotLockedOut(Request $request, string $guard): void
    {
        [$address, $account] = $this->loginKeys($request, $guard);

        foreach ([[$address, self::PER_ADDRESS], [$account, self::PER_ACCOUNT]] as [$key, $max]) {
            if (RateLimiter::tooManyAttempts($key, $max)) {
                $seconds = RateLimiter::availableIn($key);

                Audit::record('auth.locked_out', null, ['guard' => $guard, 'email' => Str::lower((string) $request->input('email'))]);

                throw ValidationException::withMessages([
                    'email' => 'Too many sign-in attempts. Try again in ' . ceil($seconds / 60) . ' minute(s).',
                ])->status(429);
            }
        }
    }

    protected function recordFailedLogin(Request $request, string $guard): void
    {
        [$address, $account] = $this->loginKeys($request, $guard);

        RateLimiter::hit($address, 60);
        RateLimiter::hit($account, 900);
    }

    protected function clearFailedLogins(Request $request, string $guard): void
    {
        foreach ($this->loginKeys($request, $guard) as $key) {
            RateLimiter::clear($key);
        }
    }

    /** @return array{0:string,1:string} */
    private function loginKeys(Request $request, string $guard): array
    {
        $email = Str::lower(trim((string) $request->input('email')));

        return [
            "login:{$guard}:{$email}|" . $request->ip(),
            "login-account:{$guard}:{$email}",
        ];
    }
}
