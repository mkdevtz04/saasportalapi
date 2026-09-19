<?php

namespace App\Http\Middleware;

use App\Support\CurrentTenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Decides which tenant this request may see and tells the models about it.
 *
 * Runs after ResolveTenant and after the session has started:
 *  - a portal request on a tenant subdomain sees only that tenant
 *  - a logged-in tenant user sees only their own tenant
 *  - the platform admin panel, webhooks and everything else see all tenants
 */
class SetTenantContext
{
    public function handle(Request $request, Closure $next): Response
    {
        CurrentTenant::set($this->tenantIdFor($request));

        try {
            return $next($request);
        } finally {
            CurrentTenant::clear();
        }
    }

    private function tenantIdFor(Request $request): ?int
    {
        if ($request->is('admin', 'admin/*')) {
            return null;
        }

        if (app()->bound('tenant')) {
            return app('tenant')->id;
        }

        return Auth::guard('tenant')->user()?->tenant_id;
    }
}
