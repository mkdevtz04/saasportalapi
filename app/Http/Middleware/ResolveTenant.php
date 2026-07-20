<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $subdomain = $this->extractSubdomain($request);

        if (! $subdomain) {
            // Main domain request — no tenant context needed
            return $next($request);
        }

        $tenant = Tenant::with('settings')->where('subdomain', $subdomain)->first();

        if (! $tenant) {
            abort(404, 'Portal not found.');
        }

        if ($tenant->isSuspended()) {
            return response()->view('suspended', ['tenant' => $tenant], 503);
        }

        // Bind tenant so it's accessible anywhere via app('tenant') or tenant()
        app()->instance('tenant', $tenant);

        return $next($request);
    }

    private function extractSubdomain(Request $request): ?string
    {
        $host = $request->getHost();
        $appHost = parse_url(config('app.url'), PHP_URL_HOST);

        // Local dev fallback: pass ?tenant=subdomain in the URL
        if (in_array($host, ['localhost', '127.0.0.1'])) {
            return $request->query('tenant') ?: null;
        }

        // Production: extract subdomain by stripping the base domain
        if ($appHost && str_ends_with($host, '.' . $appHost)) {
            $subdomain = substr($host, 0, strlen($host) - strlen('.' . $appHost));
            // Reject wildcard/empty subdomains
            return ($subdomain && $subdomain !== '*') ? $subdomain : null;
        }

        return null;
    }
}
