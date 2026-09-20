<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Models\TenantRouter;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Works out which ISP a customer is buying from, so their money and their WiFi access
 * go to that ISP and to no one else.
 *
 * In order of how much the platform trusts it:
 *  1. the host, acme.wifikitaa.site, for anyone still on the older subdomain form
 *  2. the portal key in the address, /portal/acme — what a router's login page links to
 *     now, and what the page repeats on every call it makes while the customer pays
 *  3. the NAS identifier the router sends, which names one registered router
 *
 * When none of them names an ISP the request stays on the platform, with no tenant. The
 * portal then refuses to sell, because a payment with no ISP behind it cannot be paid out.
 */
class ResolveTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->resolve($request);

        if (! $tenant) {
            return $next($request);
        }

        if ($tenant->isSuspended()) {
            return response()->view('suspended', ['tenant' => $tenant], 503);
        }

        // Bound here so it is reachable anywhere via app('tenant') or tenant().
        app()->instance('tenant', $tenant);

        return $next($request);
    }

    private function resolve(Request $request): ?Tenant
    {
        if ($subdomain = $this->subdomain($request)) {
            $tenant = Tenant::with('settings')->where('subdomain', $subdomain)->first();

            // A subdomain that belongs to nobody is a dead address, not the platform's home page.
            abort_unless($tenant, 404, 'Portal not found.');

            return $tenant;
        }

        // Only a customer buying WiFi may name an ISP in the request itself. Letting a signed-in
        // user do it anywhere would hand them another ISP's dashboard by adding one form field.
        if (! $this->isCustomerPortalRequest($request)) {
            return null;
        }

        if ($key = $this->portalKey($request)) {
            if ($tenant = Tenant::with('settings')->where('subdomain', $key)->first()) {
                return $tenant;
            }
        }

        return $this->byNasIdentifier($request);
    }

    /** The captive portal page and the calls it makes while a customer pays or redeems a voucher. */
    private function isCustomerPortalRequest(Request $request): bool
    {
        return $request->is('portal', 'portal/*', 'api/payment/*', 'api/voucher/*', 'api/access/*');
    }

    /**
     * The ISP named in the address the customer opened, or repeated by the portal page on the
     * calls it makes afterwards. Those calls are POSTs with no query string, so the page sends
     * the key back in a header and in the body as well.
     */
    private function portalKey(Request $request): ?string
    {
        foreach ([
            $request->route('portal_key'),
            $request->input('tenant'),
            $request->header('X-Portal-Tenant'),
        ] as $candidate) {
            if (is_string($candidate) && preg_match('/^[a-z0-9-]{1,63}$/i', $candidate)) {
                return strtolower($candidate);
            }
        }

        return null;
    }

    /**
     * The name the router gives itself. Only an exact match on a registered router counts:
     * an empty or unknown identifier must never fall through to somebody else's router.
     */
    private function byNasIdentifier(Request $request): ?Tenant
    {
        $nas = $request->input('nas');

        if (! is_string($nas) || trim($nas) === '') {
            return null;
        }

        return TenantRouter::where('nas_identifier', trim($nas))->first()?->tenant?->load('settings');
    }

    private function subdomain(Request $request): ?string
    {
        $host    = $request->getHost();
        $appHost = parse_url(config('app.url'), PHP_URL_HOST);

        // Subdomains do not exist on a development machine, where ?tenant= is used instead.
        if (in_array($host, ['localhost', '127.0.0.1'], true)) {
            return null;
        }

        if ($appHost && str_ends_with($host, '.' . $appHost)) {
            $subdomain = substr($host, 0, strlen($host) - strlen('.' . $appHost));

            return ($subdomain !== '' && $subdomain !== '*' && $subdomain !== 'www') ? $subdomain : null;
        }

        return null;
    }
}
