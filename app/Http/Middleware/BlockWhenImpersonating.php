<?php

namespace App\Http\Middleware;

use App\Support\Audit;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stops money and payout actions while platform support is looking at an ISP account.
 * Support can see and fix, but can never move the tenant's money or change where it goes.
 */
class BlockWhenImpersonating
{
    public function handle(Request $request, Closure $next): Response
    {
        if (session()->has('impersonated_by')) {
            Audit::record('impersonation.blocked', $request->user('tenant')?->tenant_id, ['path' => $request->path()]);

            abort(403, 'This action is not available while you are viewing the account as platform support.');
        }

        return $next($request);
    }
}
