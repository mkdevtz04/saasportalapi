<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureOwner
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::guard('tenant')->user()?->isOwner()) {
            abort(403, 'Owner access required.');
        }

        return $next($request);
    }
}
