<?php

namespace App\Support;

use App\Models\Tenant;

/**
 * Public addresses of the platform and of a tenant's portal, always built from the
 * configured APP_URL and never from the request's Host header, which a caller controls.
 */
class TenantUrls
{
    /** https://trinetpay.online, no trailing slash. */
    public static function base(): string
    {
        return rtrim((string) config('app.url'), '/');
    }

    /** trinetpay.online, without a leading "www.". */
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

    /** acme.trinetpay.online, or the plain host during local development. */
    public static function portalHost(Tenant $tenant): string
    {
        return self::isLocal() ? self::baseHost() : $tenant->subdomain . '.' . self::baseHost();
    }

    /** The captive portal address for a tenant. */
    public static function portal(Tenant $tenant): string
    {
        $scheme = (string) (parse_url(self::base(), PHP_URL_SCHEME) ?: 'https');
        $port   = parse_url(self::base(), PHP_URL_PORT);
        $url    = $scheme . '://' . self::portalHost($tenant) . ($port ? ':' . $port : '') . '/portal';

        return self::isLocal() ? $url . '?tenant=' . urlencode($tenant->subdomain) : $url;
    }
}
