<?php

namespace App\Support;

use App\Models\Tenant;

/**
 * Public addresses of the platform and of a tenant's portal, always built from the
 * configured APP_URL and never from the request's Host header, which a caller controls.
 *
 * Every ISP gets their own portal address. It is a path on the platform host,
 * https://wifikitaa.site/portal/acme, so a new ISP needs no DNS record and no extra
 * certificate, and the routers only ever have to reach one host. The older
 * acme.wifikitaa.site form still opens the same portal for anyone already using it.
 */
class TenantUrls
{
    /** The platform address from APP_URL, for example https://wifikitaa.site, no trailing slash. */
    public static function base(): string
    {
        return rtrim((string) config('app.url'), '/');
    }

    /** The platform host, for example wifikitaa.site, without a leading "www.". */
    public static function baseHost(): string
    {
        $host = (string) parse_url(self::base(), PHP_URL_HOST);

        return preg_replace('/^www\./', '', $host) ?? $host;
    }

    /** True for local development addresses where subdomains do not exist. */
    public static function isLocal(): bool
    {
        return in_array(self::baseHost(), ['localhost', '127.0.0.1'], true);
    }

    /** The piece of the portal address that says which ISP this is. */
    public static function portalKey(Tenant $tenant): string
    {
        return (string) $tenant->subdomain;
    }

    /**
     * The host a customer's phone must be able to reach before paying, which the router
     * setup puts in the walled garden. Portals live on the platform host, so it is the same
     * one for every ISP.
     */
    public static function portalHost(Tenant $tenant): string
    {
        return self::baseHost();
    }

    /** The captive portal address for one ISP. This is the link that goes on their routers. */
    public static function portal(Tenant $tenant): string
    {
        return self::base() . '/portal/' . rawurlencode(self::portalKey($tenant));
    }

    /** The same address without the scheme, for printing on a page or a voucher. */
    public static function portalLabel(Tenant $tenant): string
    {
        return (string) preg_replace('#^https?://#', '', self::portal($tenant));
    }
}
