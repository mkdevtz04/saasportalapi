<?php

namespace App\Support;

/**
 * Safe building blocks for RouterOS scripts.
 *
 * Anything a tenant can type, such as a router name, must go through here before it is
 * placed in a script. Without it a value containing a quote or a line break could end
 * the string early and run its own commands on the router.
 */
class RouterOs
{
    /**
     * A double-quoted RouterOS string literal with every special character escaped.
     * Backslash, quote, dollar and question mark are escaped, line breaks become \n and
     * other control characters are dropped.
     */
    public static function quote(string $value): string
    {
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value) ?? '';

        $escaped = strtr($value, [
            '\\'   => '\\\\',
            '"'    => '\\"',
            '$'    => '\\$',
            '?'    => '\\?',
            "\r\n" => '\\n',
            "\n"   => '\\n',
            "\r"   => '\\n',
            "\t"   => '\\t',
        ]);

        return '"' . $escaped . '"';
    }

    /**
     * Text for a "# comment" line: one line, no control characters.
     */
    public static function comment(string $value): string
    {
        return trim(preg_replace('/[\x00-\x1F\x7F]+/', ' ', $value) ?? '');
    }

    /**
     * A name that is safe to use bare (unquoted) in RouterOS: letters, digits, dash, underscore.
     */
    public static function bareName(string $value, string $fallback = 'trinetpay'): string
    {
        $clean = preg_replace('/[^A-Za-z0-9_-]+/', '', $value) ?? '';

        return $clean !== '' ? $clean : $fallback;
    }
}
