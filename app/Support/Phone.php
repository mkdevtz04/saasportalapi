<?php

namespace App\Support;

/**
 * Tanzanian phone numbers in the form SMS and mobile money gateways expect.
 */
class Phone
{
    /**
     * 0712 345 678, 712345678 and +255 712 345 678 all become 255712345678.
     * Anything that does not look like a Tanzanian number is returned as plain digits.
     */
    public static function international(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone) ?? '';

        if (strlen($digits) === 10 && $digits[0] === '0') {
            return '255' . substr($digits, 1);
        }

        if (strlen($digits) === 9 && in_array($digits[0], ['6', '7'], true)) {
            return '255' . $digits;
        }

        return $digits;
    }

    /** True for a number that can plausibly receive an SMS: country code plus 9 digits. */
    public static function isMobile(string $phone): bool
    {
        return (bool) preg_match('/^255[67]\d{8}$/', self::international($phone));
    }
}
