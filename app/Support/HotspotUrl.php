<?php

namespace App\Support;

/**
 * Cleans the addresses a hotspot router puts in the portal link.
 *
 * After a customer pays, the portal sends their WiFi login to the router's login address,
 * which arrives as a query parameter. Anyone can craft a portal link with a different
 * address, so a value pointing at an outside website would hand the customer's WiFi
 * credentials to a stranger. Only addresses that belong to a local network are accepted.
 *
 * Browsers understand many spellings of the same IPv4 address, for example 134744072 and
 * 0x08080808 both mean 8.8.8.8. So the check is an allow-list of exact shapes, and anything
 * that merely looks numeric is refused.
 */
class HotspotUrl
{
    /** Host name endings that only ever exist on a local network. */
    private const LOCAL_SUFFIXES = ['lan', 'local', 'localdomain', 'hotspot', 'wifi'];

    /**
     * The router login address, or null when it does not look like a local router.
     * Real ones look like http://192.168.88.1/login or http://hotspot.lan/login.
     */
    public static function loginUrl(?string $url): ?string
    {
        $parts = self::parse($url);

        if ($parts === null || ! self::isLocalHost($parts['host'])) {
            return null;
        }

        return $url;
    }

    /**
     * The page the customer originally wanted, which the router sends them on to after
     * login. Any web address is fine, but never a script or another scheme.
     */
    public static function destination(?string $url): ?string
    {
        return self::parse($url) === null ? null : $url;
    }

    /** @return array{host:string}|null */
    private static function parse(?string $url): ?array
    {
        if ($url === null || $url === '' || strlen($url) > 500 || preg_match('/[\x00-\x20\x7F"\'<>\\\\]/', $url)) {
            return null;
        }

        $parts = parse_url($url);

        if (! is_array($parts)
            || ! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
            || empty($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])) {
            return null;
        }

        return ['host' => strtolower($parts['host'])];
    }

    private static function isLocalHost(string $host): bool
    {
        // A hotspot is addressed over IPv4. An IPv6 host is refused outright, which also closes
        // the IPv4-inside-IPv6 spelling (::ffff:8.8.8.8).
        if (str_contains($host, ':') || str_contains($host, '[')) {
            return false;
        }

        // A plain dotted IPv4 address: accepted only when it is a private or reserved one.
        if (preg_match('/^\d{1,3}(\.\d{1,3}){3}$/', $host)) {
            if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
                return false;   // out of range or with leading zeros, which some systems read as octal
            }

            // filter_var returns false when the flags reject the address, that is, when it is private or reserved.
            return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
        }

        $labels = explode('.', $host);

        foreach ($labels as $label) {
            if (! preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?$/', $label)) {
                return false;   // empty labels (trailing dot), odd characters
            }
        }

        $last = end($labels);

        // The last label must start with a letter. A purely numeric or 0x-prefixed last label is an
        // IPv4 address in disguise for a browser, such as 134744072 or 0x08080808.
        if (! preg_match('/^[a-z]/', $last)) {
            return false;
        }

        // A single name such as "hotspot" only resolves on a local network.
        if (count($labels) === 1) {
            return true;
        }

        return in_array($last, self::LOCAL_SUFFIXES, true);
    }
}
