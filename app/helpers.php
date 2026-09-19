<?php

use App\Models\Tenant;

if (! function_exists('tenant')) {
    /**
     * Return the currently resolved tenant for this request, or null on the main domain.
     */
    function tenant(): ?Tenant
    {
        return app()->bound('tenant') ? app('tenant') : null;
    }
}

if (! function_exists('withdrawal_fee_label')) {
    /**
     * The platform withdrawal fee as text, e.g. "5%" or "2.5%".
     */
    function withdrawal_fee_label(): string
    {
        $pct = (float) config('platform.withdrawal_fee_pct', 0);

        return rtrim(rtrim(number_format($pct, 2), '0'), '.') . '%';
    }
}
